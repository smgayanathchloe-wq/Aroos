<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the first super admin so a fresh deployment can be signed into.
 *
 * Credentials come from the environment, so no password is ever
 * committed to source control:
 *
 *   ADMIN_BOOTSTRAP_EMAIL=you@example.com
 *   ADMIN_BOOTSTRAP_PASSWORD=<a strong password>
 *
 * Without ADMIN_BOOTSTRAP_PASSWORD this seeder does nothing, which is
 * the safe default: better to have no admin than a guessable one.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_BOOTSTRAP_EMAIL', 'admin@wed.letsventure.co.tz');
        $password = env('ADMIN_BOOTSTRAP_PASSWORD');

        if (! $password) {
            $this->command?->warn(
                'AdminUserSeeder skipped: set ADMIN_BOOTSTRAP_PASSWORD to create the first admin.'
            );

            return;
        }

        $existing = User::where('email', $email)->first();

        if ($existing) {
            // Never silently reset a password that is already in use.
            $this->command?->info("AdminUserSeeder skipped: {$email} already exists.");

            return;
        }

        User::create([
            'name' => env('ADMIN_BOOTSTRAP_NAME', 'Administrator'),
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Created super admin {$email}. Change the password after first sign-in.");
    }
}
