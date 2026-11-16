<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Brings a fresh database to a usable state.
 *
 * Production-safe by default. It seeds only the reference data the app
 * needs to work (packages, marketplace regions and categories) plus a bootstrap admin taken from the
 * environment.
 *
 * The demo wedding and its sample users are NOT seeded here: fictional data
 * should never appear on a live site. Seed them explicitly in a test
 * environment (prints the accounts; set DEMO_PASSWORD to choose the password):
 *
 *   php artisan db:seed --class=RoleUsersSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PackageSeeder::class,
            MarketplaceSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
