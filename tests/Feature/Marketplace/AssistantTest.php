<?php

namespace Tests\Feature\Marketplace;

use App\Marketplace\BudgetEstimator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\OutreachFixtures;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use MarketplaceFixtures, OutreachFixtures, RefreshDatabase {
        MarketplaceFixtures::user insteadof OutreachFixtures;
        MarketplaceFixtures::wedding insteadof OutreachFixtures;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarketplace();
    }

    public function test_matching_works_without_ai_and_explains_reasons(): void
    {
        config(['marketplace.ai.enabled' => false]);
        $near = $this->provider(['business_name' => 'Dar Lens'], ['base_price' => 800000, 'pricing_type' => 'fixed']);
        $far = $this->provider(['business_name' => 'Mwanza Lens', 'region_id' => $this->region('Mwanza')->id], ['base_price' => 2000000, 'pricing_type' => 'fixed']);

        $this->actingAs($this->user())->get(route('marketplace.assistant', [
            'category_ids' => [$near->primary_category_id], 'region_id' => $this->region()->id, 'budget' => 1000000,
        ]))->assertOk()
            ->assertSeeInOrder(['Dar Lens', 'Mwanza Lens'])
            ->assertSee('Based in Dar es Salaam')
            ->assertSee('is within your budget')
            ->assertSee('is above your budget')
            ->assertDontSee('Draft with AI');
    }

    public function test_booked_providers_are_left_out_when_a_date_is_given(): void
    {
        $provider = $this->provider(['business_name' => 'Busy Lens']);
        $date = now()->addMonth()->toDateString();
        $provider->blackouts()->create(['starts_on' => $date, 'ends_on' => $date]);

        $this->actingAs($this->user())->get(route('marketplace.assistant', ['category_ids' => [$provider->primary_category_id], 'event_date' => $date]))
            ->assertOk()->assertDontSee('Busy Lens');
    }

    public function test_ai_reads_the_description_but_only_real_ids_are_used(): void
    {
        config(['marketplace.ai.enabled' => true]);
        $this->fakeLlm();
        $provider = $this->provider(['business_name' => 'Dar Lens']);
        $this->llmReplies[] = json_encode([
            'category_ids' => [$provider->primary_category_id, 99999], 'region_id' => $this->region()->id, 'budget' => 1000000,
            'event_date' => '2000-01-01', 'guests' => 300, 'request_text' => 'Habari, tunahitaji picha na video kwa wageni 300.', 'summary' => 'Photography in Dar',
        ]);

        $this->actingAs($this->user())->get(route('marketplace.assistant', ['description' => 'Harusi Dar, wageni 300, bajeti ya picha 1,000,000']))
            ->assertOk()->assertSee('Dar Lens')->assertSee('Photography in Dar')->assertSee('tunahitaji picha na video');
        $this->assertStringContainsString('CATEGORIES', $this->llmCalls[0]['system']);
    }

    public function test_ai_failure_falls_back_gracefully(): void
    {
        config(['marketplace.ai.enabled' => true]);
        $this->fakeLlm(); // no replies queued: the fake throws
        $this->actingAs($this->user())->get(route('marketplace.assistant', ['description' => 'Need an MC']))
            ->assertOk()->assertSee('The assistant is unavailable right now');
    }

    public function test_provider_draft_endpoint(): void
    {
        $provider = $this->provider();
        config(['marketplace.ai.enabled' => false]);
        $this->actingAs($provider->owner)->postJson(route('provider.assist'), ['kind' => 'business_description'])->assertStatus(503);

        config(['marketplace.ai.enabled' => true]);
        $this->fakeLlm();
        $this->llmReplies[] = 'We capture weddings across Dar es Salaam.';
        $this->actingAs($provider->owner)->postJson(route('provider.assist'), ['kind' => 'business_description'])
            ->assertOk()->assertJson(['text' => 'We capture weddings across Dar es Salaam.']);
        $this->assertStringContainsString('Do not invent prices', $this->llmCalls[0]['system']);
        $this->assertNull($provider->fresh()->description === 'We capture weddings across Dar es Salaam.' ? 'saved' : null, 'Drafts are never saved automatically.');

        $this->actingAs($this->user())->postJson(route('provider.assist'), ['kind' => 'caption'])->assertNotFound();
    }

    public function test_budget_ranges_need_enough_real_prices(): void
    {
        $category = $this->category();
        foreach ([400000, 600000] as $price) {
            $p = $this->provider();
            $this->serviceOf($p)->packages()->create(['name' => 'Basic', 'price' => $price]);
        }
        $this->assertNull(app(BudgetEstimator::class)->ranges([$category->id])->first()['median']);

        $p = $this->provider();
        $this->serviceOf($p)->packages()->create(['name' => 'Premium', 'price' => 1500000]);
        $range = app(BudgetEstimator::class)->ranges([$category->id])->first();
        $this->assertSame([400000.0, 600000.0, 1500000.0], [$range['low'], $range['median'], $range['high']]);
    }
}
