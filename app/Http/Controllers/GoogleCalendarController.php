<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleCalendarException;
use App\Services\GoogleCalendarService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GoogleCalendarController extends Controller
{
    public function __construct(private GoogleCalendarService $googleCalendarService) {}

    public function connect(Request $request): RedirectResponse
    {
        if (! $this->googleCalendarService->isConfigured()) {
            return redirect()->route('calendar')->with('error', 'Google Calendar is not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to the application environment.');
        }

        $state = Str::random(40);
        $request->session()->put('google_calendar_oauth_state', $state);

        return redirect()->away($this->googleCalendarService->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_calendar_oauth_state');
        $receivedState = $request->query('state');

        if (! is_string($expectedState) || ! is_string($receivedState) || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->route('calendar')->with('error', 'Google Calendar connection could not be verified. Please try connecting again.');
        }

        if ($request->query('error') !== null) {
            return redirect()->route('calendar')->with('error', 'Google Calendar connection was cancelled or denied.');
        }

        if (! $this->googleCalendarService->isConfigured()) {
            return redirect()->route('calendar')->with('error', 'Google Calendar is not configured. Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to the application environment.');
        }

        $code = $request->string('code')->toString();

        if ($code === '') {
            return redirect()->route('calendar')->with('error', 'Google did not return an authorization code. Please try connecting again.');
        }

        try {
            $this->googleCalendarService->exchangeAuthorizationCode($request->user(), $code);
        } catch (ConnectionException) {
            return redirect()->route('calendar')->with('error', 'Google Calendar could not be reached. Please try again.');
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('calendar')->with('error', $exception->getMessage());
        }

        return redirect()->route('calendar')->with('success', 'Google Calendar connected. Use Sync events to export and update your EventFlow schedule.');
    }

    public function sync(Request $request): RedirectResponse
    {
        try {
            $results = $this->googleCalendarService->sync($request->user());
        } catch (ConnectionException) {
            return redirect()->route('calendar')->with('error', 'Google Calendar could not be reached. Please try again.');
        } catch (GoogleCalendarException $exception) {
            return redirect()->route('calendar')->with('error', $exception->getMessage());
        }

        return redirect()->route('calendar')->with(
            'success',
            "Google Calendar synchronized: {$results['created']} created, {$results['updated']} updated, and {$results['deleted']} removed."
        );
    }

    public function disconnect(Request $request): RedirectResponse
    {
        $this->googleCalendarService->disconnect($request->user());

        return redirect()->route('calendar')->with('success', 'Google Calendar disconnected.');
    }
}
