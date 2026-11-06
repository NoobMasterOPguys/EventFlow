<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_unless(SystemSetting::get('registration_enabled', true), 404);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(SystemSetting::get('registration_enabled', true), 404);
        if (is_string($request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($request->input('email')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);
        AuditLog::record($user, 'user.registered', $user, [], $request->ip());

        return redirect()->route('login')->with('status', 'Your account is ready. Sign in with your new credentials to continue.');
    }
}
