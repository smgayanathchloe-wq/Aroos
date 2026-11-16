<?php

namespace Tests\Feature\Marketplace;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_filters_by_category_region_price_and_pricing_type(): void
    {
        $this->seedMarketplace();
        $user = $this->user();
        $this->provider(['business_name' => 'Dar Lens'], ['base_price' => 500000, 'pricing_type' => 'fixed']);
        $this->provider(['business_name' => 'Arusha Lens', 'region_id' => $this->region('Arusha')->id], ['base_price' => 1500000, 'pricing_type' => 'fixed']);
        $this->provider(['business_name' => 'Kilimanjaro Feast', 'primary_category_id' => $this->category('catering')->id], ['category_id' => $this->category('catering')->id, 'pricing_type' => 'quote_only', 'base_price' => null]);
        \App\Models\Marketplace\ProviderProfile::where('business_name', 'Kilimanjaro Feast')->first()->categories()->sync([$this->category('catering')->id]);

        $get = fn (array $q) => $this->actingAs($user)->get(route('marketplace.index', $q))->assertOk();

        $get(['category' => $this->category('catering')->id])->assertSee('Kilimanjaro Feast')->assertDontSee('Dar Lens');
        $get(['region' => $this->region('Arusha')->id])->assertSee('Arusha Lens')->assertDontSee('Dar Lens');
        $get(['max_price' => 1000000])->assertSee('Dar Lens')->assertDontSee('Arusha Lens')->assertDontSee('Kilimanjaro Feast');
        $get(['pricing' => 'quote_only'])->assertSee('Kilimanjaro Feast')->assertDontSee('Dar Lens');
        $get(['q' => 'photography'])->assertSee('Dar Lens')->assertSee('Arusha Lens');
        $get(['sort' => 'price_desc', 'q' => 'Lens'])->assertSeeInOrder(['Arusha Lens', 'Dar Lens']);
        $get([])->assertSee('Browse by category');
    }
}
