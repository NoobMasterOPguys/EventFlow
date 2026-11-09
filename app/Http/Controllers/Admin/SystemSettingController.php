<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => [
                'application_name' => SystemSetting::get('application_name', config('app.name')),
                'timezone' => SystemSetting::get('timezone', config('app.timezone')),
                'registration_enabled' => SystemSetting::get('registration_enabled', true),
                'deadline_reminder_hours' => SystemSetting::get('deadline_reminder_hours', 24),
            ],
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'application_name' => ['required', 'string', 'max:80'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'registration_enabled' => ['required', 'boolean'],
            'deadline_reminder_hours' => ['required', 'integer', 'between:1,168'],
        ]);

        DB::transaction(function () use ($request, $validated): void {
            foreach ($validated as $key => $value) {
                $before = SystemSetting::get($key, match ($key) {
                    'application_name' => config('app.name'),
                    'timezone' => config('app.timezone'),
                    'registration_enabled' => true,
                    'deadline_reminder_hours' => 24,
                });
                $normalized = match ($key) {
                    'registration_enabled' => $request->boolean($key),
                    'deadline_reminder_hours' => (int) $value,
                    default => $value,
                };
                SystemSetting::put($key, $normalized, $request->user());

                if ($before !== $normalized) {
                    $setting = SystemSetting::query()->where('key', $key)->firstOrFail();
                    AuditLog::record($request->user(), 'setting.updated', $setting, [
                        'setting' => $key,
                        'before' => $before,
                        'after' => $normalized,
                    ], $request->ip());
                }
            }
        });

        return back()->with('success', 'System settings updated.');
    }
}
