<?php

namespace App\Services;

use App\Exceptions\GoogleCalendarException;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class GoogleCalendarService
{
    private const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const EVENTS_ENDPOINT = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    public function isConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    public function authorizationUrl(string $state): string
    {
        return self::AUTHORIZATION_ENDPOINT.'?'.http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @throws ConnectionException
     */
    public function exchangeAuthorizationCode(User $user, string $code): void
    {
        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(15)
            ->post(self::TOKEN_ENDPOINT, [
                'code' => $code,
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw new GoogleCalendarException('Google authorization failed. Please try connecting again.');
        }

        $tokens = $response->json();
        $refreshToken = is_array($tokens) ? ($tokens['refresh_token'] ?? $user->google_refresh_token) : null;

        if (
            ! is_array($tokens)
            || ! is_string($tokens['access_token'] ?? null)
            || ! is_numeric($tokens['expires_in'] ?? null)
            || ! is_string($refreshToken)
            || $refreshToken === ''
        ) {
            throw new GoogleCalendarException('Google did not provide the credentials needed for a reliable connection. Please revoke access and reconnect.');
        }

        $user->forceFill([
            'google_access_token' => $tokens['access_token'],
            'google_refresh_token' => $refreshToken,
            'google_token_expires_at' => now()->addSeconds((int) $tokens['expires_in']),
        ])->save();
    }

    public function disconnect(User $user): void
    {
        $user->forceFill([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ])->save();
    }

    /**
     * @return array{created: int, updated: int, deleted: int}
     *
     * @throws ConnectionException
     */
    public function sync(User $user): array
    {
        if (! $this->isConfigured()) {
            throw new GoogleCalendarException('Google Calendar is not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to the application environment.');
        }

        if (! $user->hasGoogleCalendarConnection()) {
            throw new GoogleCalendarException('Connect Google Calendar before synchronizing events.');
        }

        $events = $user->events()->orderBy('events.id')->get();
        $activeEventIds = [];
        $created = 0;
        $updated = 0;

        foreach ($events as $event) {
            $activeEventIds[$event->getKey()] = true;
            $mapping = DB::table('google_calendar_event_mappings')
                ->where('user_id', $user->getKey())
                ->where('event_id', $event->getKey())
                ->first();
            $payload = $this->eventPayload($event);

            if ($mapping !== null) {
                $response = $this->request(
                    $user,
                    'PATCH',
                    self::EVENTS_ENDPOINT.'/'.rawurlencode($mapping->google_event_id),
                    $payload,
                    allowNotFound: true,
                );

                if (! $response->notFound()) {
                    $updated++;

                    continue;
                }
            }

            $googleEventId = $this->googleEventId($user, $event);
            $response = $this->request(
                $user,
                'POST',
                self::EVENTS_ENDPOINT,
                ['id' => $googleEventId] + $payload,
                allowConflict: true,
            );

            if ($response->conflict()) {
                $this->request(
                    $user,
                    'PATCH',
                    self::EVENTS_ENDPOINT.'/'.rawurlencode($googleEventId),
                    $payload,
                );
            } else {
                $googleEventId = $response->json('id');
            }

            if (! is_string($googleEventId) || $googleEventId === '') {
                throw new GoogleCalendarException('Google Calendar did not return an event identifier.');
            }

            DB::table('google_calendar_event_mappings')->updateOrInsert(
                ['user_id' => $user->getKey(), 'event_id' => $event->getKey()],
                ['google_event_id' => $googleEventId, 'created_at' => now(), 'updated_at' => now()],
            );
            $created++;
        }

        $deleted = 0;
        $mappings = DB::table('google_calendar_event_mappings')
            ->where('user_id', $user->getKey())
            ->get();

        foreach ($mappings as $mapping) {
            if (isset($activeEventIds[$mapping->event_id])) {
                continue;
            }

            $this->request(
                $user,
                'DELETE',
                self::EVENTS_ENDPOINT.'/'.rawurlencode($mapping->google_event_id),
                allowNotFound: true,
            );
            DB::table('google_calendar_event_mappings')->where('id', $mapping->id)->delete();
            $deleted++;
        }

        return compact('created', 'updated', 'deleted');
    }

    private function googleEventId(User $user, Event $event): string
    {
        return 'ef'.hash('sha256', $user->getKey().':'.$event->getKey());
    }

    /**
     * @throws ConnectionException
     */
    private function request(
        User $user,
        string $method,
        string $url,
        array $payload = [],
        bool $allowNotFound = false,
        bool $allowConflict = false,
    ): Response {
        $response = Http::acceptJson()
            ->withToken($this->validAccessToken($user))
            ->connectTimeout(3)
            ->timeout(15)
            ->send($method, $url, $payload === [] ? [] : ['json' => $payload]);

        if ($response->unauthorized()) {
            $accessToken = $this->refreshAccessToken($user);

            if ($accessToken === null) {
                throw new GoogleCalendarException('Google Calendar authorization expired. Reconnect your account and try again.');
            }

            $response = Http::acceptJson()
                ->withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(15)
                ->send($method, $url, $payload === [] ? [] : ['json' => $payload]);
        }

        if (
            ! $response->successful()
            && ! ($allowNotFound && $response->notFound())
            && ! ($allowConflict && $response->conflict())
        ) {
            throw new GoogleCalendarException('Google Calendar could not complete the synchronization (HTTP '.$response->status().').');
        }

        return $response;
    }

    private function validAccessToken(User $user): string
    {
        if (
            filled($user->google_access_token)
            && $user->google_token_expires_at?->isAfter(now()->addMinute())
        ) {
            return $user->google_access_token;
        }

        $accessToken = $this->refreshAccessToken($user);

        if ($accessToken === null) {
            throw new GoogleCalendarException('Google Calendar authorization expired. Reconnect your account and try again.');
        }

        return $accessToken;
    }

    /**
     * @throws ConnectionException
     */
    private function refreshAccessToken(User $user): ?string
    {
        if (! filled($user->google_refresh_token)) {
            return null;
        }

        $response = Http::asForm()
            ->connectTimeout(3)
            ->timeout(15)
            ->post(self::TOKEN_ENDPOINT, [
                'client_id' => config('services.google.client_id'),
                'client_secret' => config('services.google.client_secret'),
                'refresh_token' => $user->google_refresh_token,
                'grant_type' => 'refresh_token',
            ]);
        $tokens = $response->json();

        if (! $response->successful()) {
            if (is_array($tokens) && ($tokens['error'] ?? null) === 'invalid_grant') {
                $this->disconnect($user);

                return null;
            }

            throw new GoogleCalendarException('Google could not refresh the calendar connection. Please try again.');
        }

        if (! is_array($tokens) || ! is_string($tokens['access_token'] ?? null) || ! is_numeric($tokens['expires_in'] ?? null)) {
            throw new GoogleCalendarException('Google returned an invalid token response. Please reconnect your account.');
        }

        $user->forceFill([
            'google_access_token' => $tokens['access_token'],
            'google_token_expires_at' => now()->addSeconds((int) $tokens['expires_in']),
        ])->save();

        return $user->google_access_token;
    }

    /**
     * @return array<string, mixed>
     */
    private function eventPayload(Event $event): array
    {
        return [
            'summary' => $event->title,
            'description' => $event->description,
            'location' => $event->location,
            'start' => [
                'dateTime' => $event->start_at->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
            'end' => [
                'dateTime' => $event->end_at->toIso8601String(),
                'timeZone' => config('app.timezone'),
            ],
        ];
    }

    private function redirectUri(): string
    {
        return config('services.google.redirect_uri') ?: route('google-calendar.callback');
    }
}
