<?php

namespace Tests\Feature\Marketplace;

use App\Models\Collaborator;
use App\Models\Marketplace\ServiceBooking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    public function test_mobile_flow_from_search_to_booking(): void
    {
        $this->seedMarketplace();
        $provider = $this->provider(['business_name' => 'Api Lens', 'phone_visibility' => 'after_enquiry']);
        $owner = $this->user();
        $wedding = $this->wedding($owner);
        $couple = $this->user('couple');
        Collaborator::create(['wedding_id' => $wedding->id, 'user_id' => $couple->id, 'status' => 'active', 'email' => $couple->email, 'role' => 'couple']);

        Sanctum::actingAs($couple);
        $headers = ['X-Wedding-Id' => $wedding->id];
        $this->getJson('/api/v1/marketplace/providers?q=Api')->assertOk()->assertJsonPath('data.0.slug', $provider->slug)->assertJsonPath('data.0.verified', true);
        $this->getJson("/api/v1/marketplace/providers/{$provider->slug}")->assertOk()->assertJsonPath('data.phone', null);

        $requestId = $this->postJson("/api/v1/marketplace/providers/{$provider->slug}/requests", [
            'service_id' => $this->serviceOf($provider)->id, 'event_date' => now()->addMonths(2)->toDateString(), 'guests' => 200,
        ], $headers)->assertOk()->json('data.id');
        $this->getJson("/api/v1/marketplace/providers/{$provider->slug}")->assertJsonPath('data.phone', '+255711000000');

        Sanctum::actingAs($provider->owner);
        $this->getJson("/api/v1/marketplace/business/requests/{$requestId}")->assertOk()->assertJsonPath('data.contact.phone', '+255700111222');
        $versionId = $this->postJson("/api/v1/marketplace/business/requests/{$requestId}/quote", ['amount' => 750000])->assertOk()->json('data.latest_offer.id');

        Sanctum::actingAs($couple);
        $this->getJson("/api/v1/marketplace/requests/{$requestId}", $headers)->assertOk()->assertJsonPath('data.can_accept', true)->assertJsonMissingPath('data.contact');
        $this->postJson("/api/v1/marketplace/requests/{$requestId}/accept", ['version_id' => $versionId], $headers)->assertUnprocessable();
        $this->postJson("/api/v1/marketplace/requests/{$requestId}/accept", ['version_id' => $versionId, 'confirm' => true], $headers)
            ->assertOk()->assertJsonPath('data.status', 'confirmed')->assertJsonPath('data.agreed_amount', 750000);
        $this->assertSame(1, ServiceBooking::count());

        // Another wedding cannot read it; another provider cannot either.
        $stranger = $this->user();
        $otherWedding = $this->wedding($stranger);
        Sanctum::actingAs($stranger);
        $this->getJson("/api/v1/marketplace/requests/{$requestId}", ['X-Wedding-Id' => $otherWedding->id])->assertNotFound();
        $other = $this->provider();
        Sanctum::actingAs($other->owner);
        $this->getJson("/api/v1/marketplace/business/requests/{$requestId}")->assertNotFound();
    }
}
