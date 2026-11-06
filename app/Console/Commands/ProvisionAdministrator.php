<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

#[Signature('admin:provision {email : Email address for the initial administrator}')]
#[Description('Securely provision the first EventFlow administrator')]
class ProvisionAdministrator extends Command
{
    public function handle(): int
    {
        if (User::query()->where('system_role', 'admin')->exists()) {
            $this->components->error('An administrator already exists. Use the administrator panel to grant additional administrator access.');

            return self::FAILURE;
        }

        $name = $this->ask('Administrator name', 'Platform Administrator');
        $password = $this->secret('Administrator password (minimum 12 characters with upper/lowercase, number, and symbol)');
        $passwordConfirmation = $this->secret('Confirm administrator password');

        $validator = Validator::make([
            'name' => $name,
            'email' => Str::lower(trim((string) $this->argument('email'))),
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        if (! $this->confirm('Create the initial administrator account?', false)) {
            $this->components->warn('Administrator provisioning cancelled.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($validator): ?User {
            if (User::query()->where('system_role', 'admin')->lockForUpdate()->exists()) {
                return null;
            }

            $user = User::query()->forceCreate([
                'name' => $validator->validated()['name'],
                'email' => $validator->validated()['email'],
                'password' => Hash::make($validator->validated()['password']),
                'system_role' => 'admin',
                'account_status' => 'active',
                'is_super_admin' => true,
            ]);

            AuditLog::record($user, 'administrator.provisioned', $user);

            return $user;
        });

        if ($user === null) {
            $this->components->error('An administrator was created while provisioning. No additional administrator was added.');

            return self::FAILURE;
        }

        $this->components->info('Initial administrator created.');

        return self::SUCCESS;
    }
}
