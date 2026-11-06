<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            $suspensionReason = $user->suspension_reason;
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with(
                'status',
                filled($suspensionReason)
                    ? 'This account has been suspended: '.$suspensionReason
                    : 'This account has been suspended.',
            );
        }

        if ($user !== null && ($user->last_activity_at === null || $user->last_activity_at->lt(now()->subMinute()))) {
            DB::table('users')
                ->where('id', $user->getKey())
                ->update(['last_activity_at' => now()]);
        }

        return $next($request);
    }
}
