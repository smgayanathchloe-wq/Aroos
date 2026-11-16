<?php

namespace Tests\Feature\Marketplace;

use App\Models\Marketplace\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class ProviderOnboardingTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarketplace();
    }

    public function test_existing_account_registers_a_business_without_a_new_user(): void
    {
        $user = $this->user();
        $users = User::count();

        $this->actingAs($user)->post(route('provider.register.store'), [
            'business_name' => 'Mwangaza Decor', 'business_type' => 'company',
            'primary_category_id' => $this->category('decoration-event-design')->id,
            'phone' => '0712 000 000', 'region_id' => $this->region('Arusha')->id,
        ])->assertRedirect(route('provider.profile'));

        $this->assertSame($users, User::count());
        $provider = $user->providerProfile();
        $this->assertSame('Mwangaza Decor', $provider->business_name);
        $this->assertSame('draft', $provider->status);
        $this->assertSame('none', $provider->verification_status);
        $this->assertSame('owner', $provider->members()->sole()->role);
        $this->actingAs($user)->get(route('provider.dashboard'))->assertOk()->assertSee('Mwangaza Decor');
    }

    public function test_social_links_must_be_https_on_the_right_platform(): void
    {
        $provider = $this->provider();
        $owner = $provider->owner;
        $base = [
            'business_name' => $provider->business_name, 'business_type' => 'individual', 'primary_category_id' => $provider->primary_category_id,
            'phone' => '+255711000000', 'preferred_contact' => 'phone', 'phone_visibility' => 'public', 'email_visibility' => 'after_enquiry',
            'region_id' => $provider->region_id,
        ];

        $this->actingAs($owner)->put(route('provider.profile.update'), $base + ['social_links' => ['instagram' => 'http://evil.example/x']])
            ->assertSessionHasErrors('social_links.instagram');

        $this->actingAs($owner)->put(route('provider.profile.update'), $base + ['social_links' => ['instagram' => 'https://www.instagram.com/lens', 'tiktok' => 'https://www.tiktok.com/@lens']])
            ->assertSessionHasNoErrors();
        $this->assertSame('https://www.tiktok.com/@lens', $provider->fresh()->social_links['tiktok']);
    }

    public function test_only_published_and_verified_providers_are_visible(): void
    {
        $browser = $this->user();
        $unverified = $this->provider(['verification_status' => 'submitted', 'verified_at' => null, 'business_name' => 'Pending Photos']);
        $hidden = $this->provider(['status' => 'hidden', 'business_name' => 'Hidden Photos']);
        $listed = $this->provider(['business_name' => 'Listed Photos']);

        $this->actingAs($browser)->get(route('marketplace.index', ['q' => 'Photos']))
            ->assertSee('Listed Photos')->assertDontSee('Pending Photos')->assertDontSee('Hidden Photos');
        $this->actingAs($browser)->get(route('marketplace.provider', $unverified))->assertForbidden();
        $this->actingAs($unverified->owner)->get(route('marketplace.provider', $unverified))->assertOk()->assertSee('Preview');
        $this->actingAs($browser)->get(route('marketplace.provider', $listed))->assertOk();
    }

    public function test_publishing_needs_a_published_service_and_listing_needs_admin_verification(): void
    {
        $provider = $this->provider(['status' => 'draft', 'verification_status' => 'none', 'verified_at' => null]);
        $this->serviceOf($provider)->update(['status' => 'draft']);

        $this->actingAs($provider->owner)->post(route('provider.visibility'), ['status' => 'active'])->assertSessionHasErrors('status');

        $this->serviceOf($provider)->update(['status' => 'published']);
        $this->actingAs($provider->owner)->post(route('provider.visibility'), ['status' => 'active'])->assertSessionHasNoErrors();
        $this->assertFalse($provider->fresh()->isListed(), 'Published but not verified is not listed.');

        // A provider cannot verify itself.
        $this->actingAs($provider->owner)->post(route('admin.marketplace.review', $provider), ['decision' => 'verified'])->assertForbidden();

        $admin = $this->user('super_admin');
        $this->actingAs($admin)->post(route('admin.marketplace.review', $provider), ['decision' => 'verified'])->assertSessionHasNoErrors();
        $this->assertTrue($provider->fresh()->isListed());
        $this->assertSame(1, $provider->owner->notifications()->count(), 'Owner hears about verification.');
    }

    public function test_banned_provider_disappears_and_cannot_work(): void
    {
        $provider = $this->provider(['business_name' => 'Banned Beats']);
        $admin = $this->user('super_admin');

        $this->actingAs($admin)->post(route('admin.marketplace.ban', $provider), ['reason' => 'Fraud reports'])->assertSessionHasNoErrors();

        $provider->refresh();
        $this->assertSame('banned', $provider->status);
        $this->actingAs($this->user())->get(route('marketplace.index', ['q' => 'Banned']))->assertDontSee('Banned Beats');
        $this->actingAs($provider->owner)->post(route('provider.visibility'), ['status' => 'active'])->assertForbidden();
        $this->actingAs($provider->owner)->get(route('provider.dashboard'))->assertOk()->assertSee('Suspended by Aroos');
    }

    public function test_verification_documents_are_private(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $provider = $this->provider(['verification_status' => 'none', 'verified_at' => null]);

        $this->actingAs($provider->owner)->post(route('provider.verification.submit'), [
            'representative_name' => 'Baraka',
            'documents' => [UploadedFile::fake()->create('licence.pdf', 200, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $doc = $provider->verifications()->sole()->documents()->sole();
        Storage::disk('local')->assertExists($doc->path);
        $this->assertSame([], Storage::disk('public')->allFiles(), 'Documents never land on the public disk.');
        $this->assertSame('submitted', $provider->fresh()->verification_status);

        $other = $this->provider();
        $this->actingAs($other->owner)->get(route('provider.verification.document', $doc))->assertForbidden();
        $this->actingAs($this->user())->get(route('provider.verification.document', $doc))->assertForbidden();
        $this->actingAs($provider->owner)->get(route('provider.verification.document', $doc))->assertOk();
        $this->actingAs($this->user('super_admin'))->get(route('provider.verification.document', $doc))->assertOk();
    }

    public function test_portfolio_images_are_reencoded_and_links_restricted(): void
    {
        Storage::fake('public');
        $provider = $this->provider();

        $this->actingAs($provider->owner)->post(route('provider.media.store'), [
            'kind' => 'image', 'file' => UploadedFile::fake()->image('w.jpg', 3000, 2000), 'album' => 'Church weddings',
        ])->assertSessionHasNoErrors();
        $media = $provider->media()->sole();
        Storage::disk('public')->assertExists($media->path);
        [$w] = getimagesizefromstring(Storage::disk('public')->get($media->path));
        $this->assertSame(2000, $w, 'Large images are scaled down.');
        $this->assertTrue($provider->fresh()->has_portfolio);

        $this->actingAs($provider->owner)->post(route('provider.media.store'), ['kind' => 'image', 'file' => UploadedFile::fake()->create('x.jpg', 10, 'image/jpeg')])
            ->assertSessionHasErrors();
        $this->actingAs($provider->owner)->post(route('provider.media.store'), ['kind' => 'video_link', 'url' => 'https://evil.example/v'])
            ->assertSessionHasErrors('url');
        $this->actingAs($provider->owner)->post(route('provider.media.store'), ['kind' => 'video_link', 'url' => 'https://www.tiktok.com/@lens/video/123'])
            ->assertSessionHasNoErrors();
        $this->actingAs($provider->owner)->post(route('provider.media.store'), [
            'kind' => 'video', 'file' => UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4'),
        ])->assertSessionHasNoErrors();
        $this->assertSame(3, $provider->media()->count());
    }

    public function test_provider_pages_render(): void
    {
        $provider = $this->provider();
        foreach (['provider.dashboard', 'provider.profile', 'provider.services', 'provider.portfolio', 'provider.verification', 'provider.enquiries', 'provider.bookings', 'provider.calendar'] as $route) {
            $this->actingAs($provider->owner)->get(route($route))->assertOk();
        }
        $this->actingAs($provider->owner)->get(route('provider.services.edit', $this->serviceOf($provider)))->assertOk();
        $this->get(route('marketplace.landing'))->assertOk()->assertSee('Everything you need for your perfect wedding')->assertDontSee($provider->business_name);
    }
}
