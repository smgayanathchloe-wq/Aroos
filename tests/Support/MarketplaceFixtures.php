<?php

namespace Tests\Support;

use App\Marketplace\ProviderStats;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ProviderService;
use App\Models\Marketplace\ServiceCategory;
use App\Models\Marketplace\TzRegion;
use App\Models\User;
use App\Models\Wedding;
use Database\Seeders\MarketplaceSeeder;

trait MarketplaceFixtures
{
    protected function seedMarketplace(): void
    {
        $this->seed(MarketplaceSeeder::class);
    }

    protected function user(string $role = 'planner', array $attrs = []): User
    {
        return User::create($attrs + ['name' => ucfirst($role).' '.uniqid(), 'email' => uniqid().'@example.com', 'password' => bcrypt('p'), 'role' => $role, 'phone' => '+255700111222']);
    }

    protected function region(string $name = 'Dar es Salaam'): TzRegion
    {
        return TzRegion::where('name', $name)->firstOrFail();
    }

    protected function category(string $slug = 'photography-videography'): ServiceCategory
    {
        return ServiceCategory::where('slug', $slug)->firstOrFail();
    }

    protected function wedding(?User $owner = null): Wedding
    {
        $owner ??= $this->user();

        return Wedding::create(['user_id' => $owner->id, 'partner_a' => 'Asha', 'partner_b' => 'Juma', 'currency' => 'TZS', 'wedding_date' => now()->addMonths(3)->toDateString()]);
    }

    /** A listed (published + verified) provider with one published service. */
    protected function provider(array $attrs = [], array $service = [], ?User $owner = null): ProviderProfile
    {
        $owner ??= $this->user();
        $provider = ProviderProfile::create($attrs + [
            'owner_user_id' => $owner->id,
            'business_name' => 'Lens Studio '.uniqid(),
            'description' => 'Wedding photography in Dar es Salaam.',
            'primary_category_id' => $this->category()->id,
            'phone' => '+255711000000',
            'email' => 'studio@example.com',
            'region_id' => $this->region()->id,
            'status' => 'active',
            'verification_status' => 'verified',
            'verified_at' => now(),
        ]);
        $provider->categories()->attach($provider->primary_category_id);
        $provider->members()->create(['user_id' => $owner->id, 'role' => 'owner']);
        $provider->services()->create($service + [
            'title' => 'Wedding photography', 'category_id' => $provider->primary_category_id,
            'pricing_type' => 'negotiable', 'base_price' => 900000, 'status' => 'published',
            'scheduling' => 'per_day', 'capacity_per_day' => 1, 'capacity_scope' => 'service',
        ]);
        ProviderStats::refresh($provider);

        return $provider->fresh();
    }

    protected function serviceOf(ProviderProfile $provider): ProviderService
    {
        return $provider->services()->firstOrFail();
    }

    protected function enquiryData(Wedding $wedding, ProviderProfile $provider, array $over = []): array
    {
        return $over + [
            'wedding_id' => $wedding->id,
            'service_id' => $this->serviceOf($provider)->id,
            'event_date' => now()->addMonths(2)->toDateString(),
            'guests' => 300,
            'budget' => 1000000,
            'requirements' => 'Photo and video, full day.',
            'preferred_contact' => 'phone',
            'contact_phone' => '+255700111222',
            'client_token' => bin2hex(random_bytes(16)),
        ];
    }
}
