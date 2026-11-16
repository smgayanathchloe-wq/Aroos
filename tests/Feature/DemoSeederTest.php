<?php

namespace Tests\Feature;

use App\Models\Marketplace\ServiceBooking;
use App\Models\User;
use Database\Seeders\RoleUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_whole_system_and_never_resets_existing_passwords(): void
    {
        $existing = User::create(['name' => 'Real Admin', 'email' => 'admin@wed.letsventure.co.tz', 'password' => Hash::make('my-own-secret'), 'role' => 'super_admin']);

        $this->seed(RoleUsersSeeder::class);
        $this->seed(RoleUsersSeeder::class); // second run changes nothing

        $this->assertTrue(Hash::check('my-own-secret', $existing->fresh()->password), 'An existing password must never be reset.');
        $this->assertSame(7, User::count());
        $this->assertSame(1, \App\Models\Wedding::count());
        $this->assertSame(1, ServiceBooking::count());
        $this->assertSame(2, \App\Models\Marketplace\ProviderProfile::listed()->count());
        $this->assertSame('sw', \App\Models\Wedding::first()->outreach()->language());
    }

    public function test_no_passwords_are_stored_in_the_seeder(): void
    {
        $source = file_get_contents(database_path('seeders/RoleUsersSeeder.php'));
        $this->assertDoesNotMatchRegularExpression("/Hash::make\\('[^']+'\\)/", $source);
    }
}
