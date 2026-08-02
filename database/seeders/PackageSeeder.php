<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Harusi',
                'description' => 'Perfect for intimate weddings and elopements',
                'price' => 0,
                'billing_cycle' => 'onetime',
                'max_guests' => 100,
                'max_weddings' => 1,
                'sort_order' => 1,
                'active' => true,
                'features' => [
                    'sms_reminders' => false,
                    'email_campaigns' => false,
                    'pledge_tracking' => true,
                    'website_designs' => 1,
                    'album_categories' => false,
                    'team_collaboration' => false,
                    'priority_support' => false,
                ],
            ],
            [
                'name' => 'Shangwe',
                'description' => 'Complete wedding planning and execution suite',
                'price' => 120000,
                'billing_cycle' => 'onetime',
                'max_guests' => null, // unlimited
                'max_weddings' => 1,
                'sort_order' => 2,
                'active' => true,
                'features' => [
                    'sms_reminders' => true,
                    'email_campaigns' => true,
                    'pledge_tracking' => true,
                    'website_designs' => 4,
                    'album_categories' => true,
                    'team_collaboration' => false,
                    'priority_support' => false,
                ],
            ],
            [
                'name' => 'Studio',
                'description' => 'For professional wedding planners and coordinators',
                'price' => 450000,
                'billing_cycle' => 'yearly',
                'max_guests' => null,
                'max_weddings' => null, // unlimited
                'sort_order' => 3,
                'active' => true,
                'features' => [
                    'sms_reminders' => true,
                    'email_campaigns' => true,
                    'pledge_tracking' => true,
                    'website_designs' => 4,
                    'album_categories' => true,
                    'team_collaboration' => true,
                    'priority_support' => true,
                ],
            ],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(
                ['name' => $package['name']],
                $package
            );
        }
    }
}
