<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('deleted_at')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('audit_logs')
                    ->whereColumn('audit_logs.auditable_id', 'users.id')
                    ->where('audit_logs.auditable_type', 'App\\Models\\User')
                    ->where('audit_logs.action', 'user.deleted');
            })
            ->select(['id', 'email'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    DB::table('password_reset_tokens')
                        ->whereRaw('LOWER(email) = ?', [Str::lower($user->email)])
                        ->delete();

                    if (config('session.driver') === 'database') {
                        DB::table(config('session.table', 'sessions'))
                            ->where('user_id', $user->id)
                            ->delete();
                    }

                    DB::table('google_calendar_event_mappings')
                        ->where('user_id', $user->id)
                        ->delete();

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'name' => 'Deleted user',
                            'email' => 'deleted-user-'.$user->id.'-'.Str::lower(Str::random(12)).'@deleted.invalid',
                            'email_verified_at' => null,
                            'password' => Hash::make(Str::random(64)),
                            'remember_token' => null,
                            'system_role' => 'user',
                            'account_status' => 'suspended',
                            'suspended_at' => null,
                            'suspension_reason' => null,
                            'suspended_by' => null,
                            'is_super_admin' => false,
                            'admin_permissions' => null,
                            'notification_preferences' => null,
                            'last_login_at' => null,
                            'last_activity_at' => null,
                            'google_access_token' => null,
                            'google_refresh_token' => null,
                            'google_token_expires_at' => null,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Anonymized account details cannot be restored.
    }
};
