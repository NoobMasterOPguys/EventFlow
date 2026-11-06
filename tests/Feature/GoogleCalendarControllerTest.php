<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCalendarControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_explains_when_google_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('calendar'))
            ->assertOk()
            ->assertSee('Connection is not configured yet')
            ->assertSee('GOOGLE_CLIENT_ID')
            ->assertDontSee('Connect Google Calendar');

        $this->get(route('google-calendar.connect'))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('error');
    }

    public function test_sync_stops_before_contacting_google_when_credentials_are_missing(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
        ]);
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $user->forceFill([
            'google_access_token' => 'access-token',
            'google_refresh_token' => 'refresh-token',
            'google_token_expires_at' => now()->addHour(),
        ])->save();

        $this->actingAs($user)
            ->post(route('google-calendar.sync'))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('error', 'Google Calendar is not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to the application environment.');

        Http::assertNothingSent();
    }

    public function test_oauth_redirect_uses_a_session_state_and_only_the_calendar_events_scope(): void
    {
        $this->configureGoogle();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('google-calendar.connect'));
        $query = [];
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);

        $response->assertRedirect();
        $this->assertSame('https://www.googleapis.com/auth/calendar.events', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame(session('google_calendar_oauth_state'), $query['state']);
        $this->assertSame(route('google-calendar.callback'), $query['redirect_uri']);
    }

    public function test_google_oauth_checks_state_and_stores_tokens_encrypted(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'access-token-secret',
                'refresh_token' => 'refresh-token-secret',
                'expires_in' => 3600,
            ]),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['google_calendar_oauth_state' => 'expected-state'])
            ->get(route('google-calendar.callback', ['state' => 'unexpected-state', 'code' => 'auth-code']))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('error');

        Http::assertNothingSent();

        $this->withSession(['google_calendar_oauth_state' => 'expected-state'])
            ->get(route('google-calendar.callback', ['state' => 'expected-state', 'code' => 'auth-code']))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('access-token-secret', $user->google_access_token);
        $this->assertSame('refresh-token-secret', $user->google_refresh_token);
        $this->assertNotSame('access-token-secret', DB::table('users')->where('id', $user->getKey())->value('google_access_token'));
        $this->assertArrayNotHasKey('google_access_token', $user->toArray());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['code'] === 'auth-code'
            && $request['grant_type'] === 'authorization_code');
    }

    public function test_oauth_callback_reports_a_connection_failure_without_exposing_transport_details(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::failedConnection(),
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['google_calendar_oauth_state' => 'expected-state'])
            ->get(route('google-calendar.callback', ['state' => 'expected-state', 'code' => 'auth-code']))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('error', 'Google Calendar could not be reached. Please try again.');

        $this->assertFalse($user->fresh()->hasGoogleCalendarConnection());
    }

    public function test_calendar_sync_creates_updates_and_removes_event_exports(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $user->forceFill([
            'google_access_token' => 'access-token',
            'google_refresh_token' => 'refresh-token',
            'google_token_expires_at' => now()->addHour(),
        ])->save();
        $event = Event::factory()->for($user, 'creator')->create([
            'title' => 'Community Science Night',
            'description' => 'Student demonstrations',
            'location' => 'Main Hall',
        ]);
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $googleEventId = 'ef'.hash('sha256', $user->getKey().':'.$event->getKey());
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::sequence()
                ->push(['id' => $googleEventId], 200),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/'.$googleEventId => Http::sequence()
                ->push(['id' => $googleEventId], 200)
                ->push(null, 204),
        ]);

        $this->actingAs($user)
            ->post(route('google-calendar.sync'))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('success', 'Google Calendar synchronized: 1 created, 0 updated, and 0 removed.');

        $this->assertDatabaseHas('google_calendar_event_mappings', [
            'user_id' => $user->getKey(),
            'event_id' => $event->getKey(),
            'google_event_id' => $googleEventId,
        ]);

        $event->update(['title' => 'Updated Science Night']);

        $this->post(route('google-calendar.sync'))
            ->assertSessionHas('success', 'Google Calendar synchronized: 0 created, 1 updated, and 0 removed.');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/'.$googleEventId)
            && $request['summary'] === 'Updated Science Night');

        $event->delete();

        $this->post(route('google-calendar.sync'))
            ->assertSessionHas('success', 'Google Calendar synchronized: 0 created, 0 updated, and 1 removed.');

        $this->assertDatabaseMissing('google_calendar_event_mappings', [
            'user_id' => $user->getKey(),
            'event_id' => $event->getKey(),
        ]);
    }

    public function test_sync_recovers_from_a_calendar_event_created_before_mapping_persistence(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $user->forceFill([
            'google_access_token' => 'access-token',
            'google_refresh_token' => 'refresh-token',
            'google_token_expires_at' => now()->addHour(),
        ])->save();
        $event = Event::factory()->for($user, 'creator')->create([
            'title' => 'Community Science Night',
        ]);
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);
        $googleEventId = 'ef'.hash('sha256', $user->getKey().':'.$event->getKey());
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::sequence()
                ->push(['error' => 'duplicate event ID'], 409),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/'.$googleEventId => Http::response([
                'id' => $googleEventId,
            ]),
        ]);

        $this->actingAs($user)
            ->post(route('google-calendar.sync'))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('success', 'Google Calendar synchronized: 1 created, 0 updated, and 0 removed.');

        $this->assertDatabaseHas('google_calendar_event_mappings', [
            'user_id' => $user->getKey(),
            'event_id' => $event->getKey(),
            'google_event_id' => $googleEventId,
        ]);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request['id'] === $googleEventId);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/'.$googleEventId)
            && $request['summary'] === 'Community Science Night');
    }

    public function test_sync_refreshes_an_expired_access_token(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
            ]),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response(['id' => 'google-event-2']),
        ]);
        $user = User::factory()->create();
        $user->forceFill([
            'google_access_token' => 'expired-access-token',
            'google_refresh_token' => 'refresh-token',
            'google_token_expires_at' => now()->subMinute(),
        ])->save();
        $event = Event::factory()->for($user, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($user)
            ->post(route('google-calendar.sync'))
            ->assertSessionHas('success');

        $this->assertSame('refreshed-access-token', $user->fresh()->google_access_token);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'refresh_token');
    }

    public function test_sync_reports_a_calendar_connection_failure(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::failedConnection(),
        ]);
        $user = User::factory()->create();
        $user->forceFill([
            'google_access_token' => 'access-token',
            'google_refresh_token' => 'refresh-token',
            'google_token_expires_at' => now()->addHour(),
        ])->save();
        $event = Event::factory()->for($user, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($user)
            ->post(route('google-calendar.sync'))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('error', 'Google Calendar could not be reached. Please try again.');

        $this->assertDatabaseMissing('google_calendar_event_mappings', [
            'user_id' => $user->getKey(),
            'event_id' => $event->getKey(),
        ]);
    }

    public function test_revoked_google_authorization_requires_a_new_connection(): void
    {
        $this->configureGoogle();
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response([], 401),
        ]);
        $user = User::factory()->create();
        $user->forceFill([
            'google_access_token' => 'expired-access-token',
            'google_refresh_token' => 'revoked-refresh-token',
            'google_token_expires_at' => now()->addHour(),
        ])->save();
        $event = Event::factory()->for($user, 'creator')->create();
        $event->members()->attach($user->getKey(), ['role' => 'leader', 'joined_at' => now()]);

        $this->actingAs($user)
            ->post(route('google-calendar.sync'))
            ->assertRedirect(route('calendar'))
            ->assertSessionHas('error', 'Google Calendar authorization expired. Reconnect your account and try again.');

        $this->assertFalse($user->fresh()->hasGoogleCalendarConnection());
    }

    /**
     * Configure non-secret test credentials and the exact callback URL.
     */
    private function configureGoogle(): void
    {
        config([
            'services.google.client_id' => 'client-id',
            'services.google.client_secret' => 'client-secret',
            'services.google.redirect_uri' => route('google-calendar.callback'),
        ]);
    }
}
