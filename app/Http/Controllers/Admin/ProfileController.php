<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.profile.index', [
            'user' => $request->user(),
            'recentActivity' => AuditLog::query()
                ->where('actor_id', $request->user()->getKey())
                ->latest()
                ->limit(20)
                ->get(),
            'activeSessions' => config('session.driver') === 'database'
                ? DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $request->user()->getKey())
                    ->orderByDesc('last_activity')
                    ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
                    ->map(fn (object $session): array => [
                        'current' => hash_equals($request->session()->getId(), $session->id),
                        'ip_address' => $session->ip_address,
                        'user_agent' => $session->user_agent,
                        'last_activity' => $session->last_activity,
                    ])
                : collect(),
        ]);
    }
}
