<?php

namespace Tests\Feature\Marketplace;

use App\Marketplace\BookingService;
use App\Models\Marketplace\MarketplaceReport;
use App\Models\Marketplace\ProviderReview;
use App\Models\Marketplace\ServiceBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class TrustAndTeamTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarketplace();
    }

    private function booking(?\App\Models\Marketplace\ProviderProfile $provider = null, string $status = 'completed'): ServiceBooking
    {
        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider ??= $this->provider();
        $this->actingAs($planner)->post(route('marketplace.enquire', $provider), $this->enquiryData($wedding, $provider));
        $enquiry = $provider->enquiries()->latest('id')->first();
        $this->actingAs($provider->owner)->post(route('provider.enquiries.quote', $enquiry), ['amount' => 500000]);
        $booking = app(BookingService::class)->accept($enquiry, $enquiry->latestVersion()->id, $planner);
        $booking->update(['status' => $status]);

        return $booking->fresh();
    }

    public function test_only_the_booking_wedding_reviews_once_after_completion(): void
    {
        $booking = $this->booking(status: 'confirmed');
        $planner = $booking->wedding->user;
        $route = route('services.bookings.review', [$booking->wedding, $booking]);

        $this->actingAs($planner)->post($route, ['overall' => 5])->assertSessionHasErrors('marketplace');
        $booking->update(['status' => 'completed']);

        $this->actingAs($this->user())->post($route, ['overall' => 1])->assertSessionHasErrors('marketplace');
        $this->actingAs($booking->provider->owner)->post($route, ['overall' => 5])->assertSessionHasErrors('marketplace');

        $this->actingAs($planner)->post($route, ['overall' => 4, 'quality' => 5, 'body' => 'Beautiful photos'])->assertSessionHasNoErrors();
        $this->actingAs($planner)->post($route, ['overall' => 1])->assertSessionHasErrors('marketplace');

        $provider = $booking->provider->fresh();
        $this->assertSame(1, ProviderReview::count());
        $this->assertSame(1, $provider->rating_count);
        $this->assertEquals(4.0, (float) $provider->rating_avg);

        // Provider responds; profile shows both.
        $review = ProviderReview::sole();
        $this->actingAs($provider->owner)->post(route('provider.reviews.respond', $review), ['response' => 'Asante sana!'])->assertSessionHasNoErrors();
        $this->actingAs($this->user())->get(route('marketplace.provider', $provider))->assertSee('Beautiful photos')->assertSee('Asante sana!');
    }

    public function test_reports_and_moderation_recompute_ratings(): void
    {
        $booking = $this->booking();
        $this->actingAs($booking->wedding->user)->post(route('services.bookings.review', [$booking->wedding, $booking]), ['overall' => 1, 'body' => 'spam']);
        $review = ProviderReview::sole();

        $reporter = $this->user();
        $this->actingAs($reporter)->post(route('marketplace.report'), ['type' => 'review', 'id' => $review->id, 'reason' => 'Fake'])->assertSessionHasNoErrors();
        $this->actingAs($reporter)->post(route('marketplace.report'), ['type' => 'review', 'id' => $review->id, 'reason' => 'Fake again']);
        $this->assertSame(1, MarketplaceReport::count(), 'One report per user per item.');

        $admin = $this->user('super_admin');
        $this->actingAs($admin)->get(route('admin.marketplace.reports'))->assertOk()->assertSee('Fake');
        $this->actingAs($admin)->post(route('admin.marketplace.reports.resolve', MarketplaceReport::sole()), ['status' => 'resolved', 'action' => 'hide_review'])->assertSessionHasNoErrors();

        $this->assertSame('hidden', $review->fresh()->status);
        $this->assertSame(0, $booking->provider->fresh()->rating_count);
        $this->actingAs($reporter)->post(route('admin.marketplace.reports.resolve', MarketplaceReport::sole()), ['status' => 'dismissed'])->assertForbidden();
    }

    public function test_team_members_get_only_the_access_granted(): void
    {
        $provider = $this->provider();
        $staff = $this->user();

        $this->actingAs($provider->owner)->post(route('provider.team.invite'), ['email' => strtoupper($staff->email), 'permissions' => ['enquiries']])->assertSessionHasNoErrors();
        $booking = $this->booking($provider, 'confirmed');
        $enquiry = $booking->enquiry;

        $this->actingAs($staff)->get(route('provider.enquiries.show', $enquiry))->assertOk();
        $this->actingAs($staff)->post(route('provider.enquiries.message', $enquiry), ['body' => 'hi', 'kind' => 'message'])->assertForbidden();
        $this->actingAs($staff)->get(route('provider.bookings'))->assertForbidden();
        $this->actingAs($staff)->get(route('provider.team'))->assertForbidden();

        // Invite someone without an account; they join on first visit after signing up.
        $this->actingAs($provider->owner)->post(route('provider.team.invite'), ['email' => 'new.staff@example.com', 'permissions' => ['bookings']]);
        $newcomer = User::create(['name' => 'New', 'email' => 'new.staff@example.com', 'password' => bcrypt('p')]);
        $this->actingAs($newcomer)->get(route('provider.bookings'))->assertOk()->assertSee($booking->reference);
    }

    public function test_sponsored_placement_is_labelled_and_admin_only(): void
    {
        $provider = $this->provider(['business_name' => 'Sponsored Studio']);
        $this->actingAs($provider->owner)->post(route('admin.marketplace.sponsorship', $provider), ['featured_until' => now()->addWeek()->toDateString()])->assertForbidden();

        $this->actingAs($this->user('super_admin'))->post(route('admin.marketplace.sponsorship', $provider), ['featured_until' => now()->addWeek()->toDateString()])->assertSessionHasNoErrors();
        $this->assertTrue($provider->fresh()->isSponsoredNow());
        $this->assertSame('verified', $provider->fresh()->verification_status);

        $this->actingAs($this->user())->get(route('marketplace.index', ['category' => $provider->primary_category_id]))
            ->assertSee('Sponsored — paid placement, not a ranking or endorsement');
    }

    public function test_notification_preferences_turn_off_email(): void
    {
        Notification::fake();
        $provider = $this->provider();
        $this->actingAs($provider->owner)->post(route('notifications.preferences'), ['sms' => '1'])->assertSessionHasNoErrors();

        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $this->actingAs($planner)->post(route('marketplace.enquire', $provider), $this->enquiryData($wedding, $provider));

        Notification::assertSentTo($provider->owner, \App\Notifications\MarketplaceNotification::class,
            fn ($n, $channels) => $channels === ['database']);
    }

    public function test_insight_and_admin_pages_render(): void
    {
        $booking = $this->booking();
        $provider = $booking->provider;
        foreach (['provider.reviews', 'provider.team', 'provider.analytics'] as $r) {
            $this->actingAs($provider->owner)->get(route($r))->assertOk();
        }
        $admin = $this->user('super_admin');
        $this->actingAs($admin)->get(route('admin.marketplace.analytics'))->assertOk()->assertSee('Listed providers');
        $this->actingAs($admin)->get(route('admin.marketplace.provider', $provider))->assertOk()->assertSee('Sponsored placement');
        $this->actingAs($booking->wedding->user)->get(route('services.index', $booking->wedding))->assertOk()->assertSee('★ Review', false);
    }
}
