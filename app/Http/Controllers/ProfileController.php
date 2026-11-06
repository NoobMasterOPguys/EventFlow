<?php

namespace App\Http\Controllers;

use App\Services\AccountOffboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request, AccountOffboarding $offboarding): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'offboardingEvents' => $offboarding->eventsRequiringTransfer($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getKey())],
        ]);
        $emailChanged = $user->email !== $validated['email'];
        $user->fill($validated);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('success', $emailChanged
            ? 'Profile updated. The new email address has not been verified.'
            : 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(10)->mixedCase()->numbers()],
        ]);

        $request->user()->password = Hash::make($validated['password']);
        $request->user()->save();

        return back()->with('success', 'Password changed successfully.');
    }

    public function updateNotificationPreferences(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notification_preferences' => ['required', 'array:task_assignments,task_status_updates,deadline_reminders,event_updates'],
            'notification_preferences.task_assignments' => ['required', 'boolean'],
            'notification_preferences.task_status_updates' => ['required', 'boolean'],
            'notification_preferences.deadline_reminders' => ['required', 'boolean'],
            'notification_preferences.event_updates' => ['required', 'boolean'],
        ]);

        $preferences = [];
        foreach (array_keys($validated['notification_preferences']) as $preference) {
            $preferences[$preference] = $request->boolean('notification_preferences.'.$preference);
        }

        $request->user()->forceFill(['notification_preferences' => $preferences])->save();

        return back()->with('success', 'Notification preferences updated.');
    }

    public function destroy(Request $request, AccountOffboarding $offboarding): RedirectResponse
    {
        $user = $request->user();
        $offboardingEvents = $offboarding->eventsRequiringTransfer($user);
        $rules = [
            'account_password' => ['required', 'string', 'current_password'],
            'event_successors' => ['sometimes', 'array'],
        ];
        $allowedEventIds = [];

        foreach ($offboardingEvents as $event) {
            if ($event->members->isNotEmpty()) {
                $eventId = (string) $event->getKey();
                $allowedEventIds[] = $eventId;
                $rules['event_successors.'.$eventId] = [
                    'required',
                    'integer',
                    Rule::in($event->members->modelKeys()),
                ];
            }
        }

        $validated = $request->validate($rules);
        $successors = $validated['event_successors'] ?? [];

        if (array_diff(array_keys($successors), $allowedEventIds) !== []) {
            throw ValidationException::withMessages([
                'event_successors' => 'Choose successors only from the events listed on this page.',
            ]);
        }

        $offboarding->offboard($user, $successors, $request->ip());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your account has been deleted. Shared events and task history were preserved.');
    }
}
