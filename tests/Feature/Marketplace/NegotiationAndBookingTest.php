<?php

namespace Tests\Feature\Marketplace;

use App\Marketplace\BookingService;
use App\Marketplace\MarketplaceException;
use App\Models\Collaborator;
use App\Models\Marketplace\QuotationVersion;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Marketplace\ServiceEnquiry;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MarketplaceFixtures;
use Tests\TestCase;

class NegotiationAndBookingTest extends TestCase
{
    use MarketplaceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarketplace();
    }

    private function enquire($user, $wedding, $provider, array $over = []): ServiceEnquiry
    {
        $this->actingAs($user)->post(route('marketplace.enquire', $provider), $this->enquiryData($wedding, $provider, $over))->assertSessionHasNoErrors();

        return ServiceEnquiry::latest('id')->firstOrFail();
    }

    private function quote($provider, ServiceEnquiry $enquiry, int $amount, array $extra = []): void
    {
        $this->actingAs($provider->owner)->post(route('provider.enquiries.quote', $enquiry), ['amount' => $amount] + $extra)->assertSessionHasNoErrors();
    }

    public function test_full_negotiation_keeps_history_and_books_on_explicit_acceptance(): void
    {
        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider = $this->provider();

        $enquiry = $this->enquire($planner, $wedding, $provider);
        $this->assertSame('open', $enquiry->status);
        $this->assertTrue($provider->owner->notifications()->where('data->event', 'enquiry.new')->exists());

        $this->quote($provider, $enquiry, 1000000);
        $this->actingAs($planner)->post(route('services.counter', [$wedding, $enquiry]), ['amount' => 800000, 'message' => 'Our budget is tighter'])->assertSessionHasNoErrors();
        $this->quote($provider, $enquiry, 900000, ['message' => 'Meet in the middle']);

        $versions = $enquiry->versions()->get();
        $this->assertSame([1000000.0, 800000.0, 900000.0], $versions->map(fn ($v) => (float) $v->amount)->all());
        $this->assertSame(['superseded', 'superseded', 'pending'], $versions->pluck('status')->all());
        $this->assertSame(['provider', 'customer', 'provider'], $versions->pluck('author_side')->all());
        $this->assertSame(800000.0, (float) $versions[2]->previous_amount);
        $this->assertSame('negotiating', $enquiry->fresh()->status);

        // Acceptance requires the explicit confirmation box.
        $this->actingAs($planner)->post(route('services.accept', [$wedding, $enquiry]), ['version_id' => $versions[2]->id])->assertSessionHasErrors('confirm');
        $this->assertSame(0, ServiceBooking::count());

        $this->actingAs($planner)->post(route('services.accept', [$wedding, $enquiry]), ['version_id' => $versions[2]->id, 'confirm' => '1'])->assertSessionHasNoErrors();

        $booking = ServiceBooking::sole();
        $this->assertSame(900000.0, (float) $booking->agreed_amount);
        $this->assertSame($versions[2]->id, $booking->accepted_version_id);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('accepted', $enquiry->fresh()->status);
        $this->assertEquals(900000, $booking->terms_snapshot['quotation']['amount'], 'Agreed terms are snapshotted.');

        // Bridged into the wedding's own vendor list (Budget / Report).
        $vendor = Vendor::sole();
        $this->assertSame($wedding->id, $vendor->wedding_id);
        $this->assertSame('booked', $vendor->status);
        $this->assertSame(900000.0, (float) $vendor->price_estimate);
        $this->assertTrue($provider->owner->notifications()->where('data->event', 'booking.confirmed')->exists());
        $this->assertTrue($planner->notifications()->where('data->event', 'booking.confirmed')->doesntExist(), 'The person who accepted is not notified of their own action.');
    }

    public function test_offers_are_immutable(): void
    {
        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider = $this->provider();
        $enquiry = $this->enquire($planner, $wedding, $provider);
        $this->quote($provider, $enquiry, 500000);

        $this->expectException(\LogicException::class);
        QuotationVersion::sole()->update(['amount' => 1]);
    }

    public function test_only_the_latest_provider_offer_can_be_accepted(): void
    {
        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider = $this->provider();
        $enquiry = $this->enquire($planner, $wedding, $provider);
        $this->quote($provider, $enquiry, 1000000);
        $this->actingAs($planner)->post(route('services.counter', [$wedding, $enquiry]), ['amount' => 700000]);

        $first = $enquiry->versions()->first();
        $counter = $enquiry->versions()->reorder('version_no', 'desc')->first();
        foreach ([$first, $counter] as $v) {
            $this->actingAs($planner)->post(route('services.accept', [$wedding, $enquiry]), ['version_id' => $v->id, 'confirm' => '1'])
                ->assertSessionHasErrors('marketplace');
        }
        $this->assertSame(0, ServiceBooking::count());
    }

    public function test_couples_can_request_and_book_and_strangers_cannot_see_anything(): void
    {
        $owner = $this->user();
        $wedding = $this->wedding($owner);
        $couple = $this->user('couple');
        Collaborator::create(['wedding_id' => $wedding->id, 'user_id' => $couple->id, 'status' => 'active', 'email' => $couple->email, 'role' => 'couple']);
        $provider = $this->provider();

        $enquiry = $this->enquire($couple, $wedding, $provider);
        $this->quote($provider, $enquiry, 600000);

        // Other provider, other wedding's user and a random user see nothing.
        $otherProvider = $this->provider();
        $this->actingAs($otherProvider->owner)->get(route('provider.enquiries.show', $enquiry))->assertForbidden();
        $this->actingAs($otherProvider->owner)->post(route('provider.enquiries.quote', $enquiry), ['amount' => 1])->assertForbidden();
        $stranger = $this->user();
        $this->actingAs($stranger)->get(route('services.enquiry', [$wedding, $enquiry]))->assertForbidden();
        $this->actingAs($stranger)->get(route('services.index', $wedding))->assertForbidden();

        // The wedding owner sees the couple's request; the provider sees the couple's contact.
        $this->actingAs($owner)->get(route('services.enquiry', [$wedding, $enquiry]))->assertOk();
        $this->actingAs($provider->owner)->get(route('provider.enquiries.show', $enquiry))->assertOk()->assertSee('+255700111222');

        $version = $enquiry->versions()->sole();
        $this->actingAs($couple)->post(route('services.accept', [$wedding, $enquiry]), ['version_id' => $version->id, 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', ServiceBooking::sole()->status);
    }

    public function test_capacity_prevents_double_booking_on_the_server(): void
    {
        $provider = $this->provider();
        $date = now()->addMonths(2)->toDateString();
        $offers = [];
        foreach ([1, 2] as $i) {
            $planner = $this->user();
            $wedding = $this->wedding($planner);
            $enquiry = $this->enquire($planner, $wedding, $provider, ['event_date' => $date]);
            $this->quote($provider, $enquiry, 700000);
            $offers[] = [$planner, $wedding, $enquiry, $enquiry->versions()->sole()];
        }

        [$p1, $w1, $e1, $v1] = $offers[0];
        [$p2, $w2, $e2, $v2] = $offers[1];
        $this->actingAs($p1)->post(route('services.accept', [$w1, $e1]), ['version_id' => $v1->id, 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->actingAs($p2)->post(route('services.accept', [$w2, $e2]), ['version_id' => $v2->id, 'confirm' => '1'])
            ->assertSessionHasErrors(['marketplace' => 'The provider is fully booked on this date. Ask the provider to propose another date.']);

        $this->assertSame(1, ServiceBooking::count());
        $this->assertSame('quoted', $e2->fresh()->status, 'The losing quote stays open so a new date can be offered.');

        // A caterer with capacity 2 takes both.
        $this->serviceOf($provider)->update(['capacity_per_day' => 2]);
        $this->actingAs($p2)->post(route('services.accept', [$w2, $e2]), ['version_id' => $v2->id, 'confirm' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(2, ServiceBooking::count());
    }

    public function test_time_slots_and_blocked_dates(): void
    {
        $provider = $this->provider([], ['scheduling' => 'per_slot', 'buffer_minutes' => 60, 'title' => 'MC']);
        $service = $this->serviceOf($provider);
        $date = now()->addMonth()->toDateString();
        $availability = app(\App\Marketplace\AvailabilityService::class);

        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $enquiry = $this->enquire($planner, $wedding, $provider, ['event_date' => $date, 'event_start' => '10:00', 'event_end' => '13:00']);
        $this->quote($provider, $enquiry, 400000);
        app(BookingService::class)->accept($enquiry, $enquiry->versions()->sole()->id, $planner);

        $this->assertNotNull($availability->conflict($service, $date, '13:30', '16:00'), 'Inside the 60-minute buffer.');
        $this->assertNull($availability->conflict($service, $date, '15:00', '18:00'));

        $provider->blackouts()->create(['starts_on' => $date, 'ends_on' => $date]);
        $this->assertSame('The provider is not available on this date.', $availability->conflict($service, $date, '18:00', '20:00'));
    }

    public function test_cancellation_updates_vendor_and_notifies_the_other_side(): void
    {
        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider = $this->provider();
        $enquiry = $this->enquire($planner, $wedding, $provider);
        $this->quote($provider, $enquiry, 500000);
        $booking = app(BookingService::class)->accept($enquiry, $enquiry->versions()->sole()->id, $planner);

        $this->actingAs($planner)->post(route('services.bookings.cancel', [$wedding, $booking]), ['reason' => 'Change of plans'])->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame('cancelled_by_customer', $booking->status);
        $this->assertSame(['confirmed', 'cancelled_by_customer'], $booking->history()->pluck('to_status')->all());
        $this->assertSame('rejected', $booking->vendor->status);
        $this->assertTrue($provider->owner->notifications()->where('data->event', 'booking.cancelled')->exists());

        // A cancelled booking frees the date.
        $this->assertNull(app(\App\Marketplace\AvailabilityService::class)->conflict($this->serviceOf($provider), $booking->event_date->toDateString()));
    }

    public function test_housekeeping_expires_offers_and_completes_past_bookings(): void
    {
        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider = $this->provider();
        $enquiry = $this->enquire($planner, $wedding, $provider);
        $this->quote($provider, $enquiry, 500000, ['valid_days' => 1]);

        $this->travel(2)->days();
        $this->artisan('marketplace:housekeeping')->assertSuccessful();
        $this->assertSame('expired', $enquiry->versions()->sole()->status);
        $this->actingAs($planner)->post(route('services.accept', [$wedding, $enquiry]), ['version_id' => $enquiry->versions()->sole()->id, 'confirm' => '1'])
            ->assertSessionHasErrors('marketplace');

        $this->quote($provider, $enquiry, 450000);
        $booking = app(BookingService::class)->accept($enquiry, $enquiry->latestVersion()->id, $planner);
        $this->travelTo($booking->event_date->copy()->addDays(2));
        $this->artisan('marketplace:housekeeping')->assertSuccessful();
        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_double_submitted_request_creates_one_enquiry(): void
    {
        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider = $this->provider();
        $data = $this->enquiryData($wedding, $provider);

        $this->actingAs($planner)->post(route('marketplace.enquire', $provider), $data)->assertRedirect();
        $this->actingAs($planner)->post(route('marketplace.enquire', $provider), $data)->assertRedirect();

        $this->assertSame(1, ServiceEnquiry::count());
        $this->assertSame(1, $provider->owner->notifications()->count());
    }

    public function test_requests_need_access_to_the_wedding_and_a_listed_provider(): void
    {
        $provider = $this->provider();
        $someoneElsesWedding = $this->wedding();
        $this->actingAs($this->user())->post(route('marketplace.enquire', $provider), $this->enquiryData($someoneElsesWedding, $provider))->assertForbidden();

        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider->update(['status' => 'hidden']);
        $this->actingAs($planner)->post(route('marketplace.enquire', $provider), $this->enquiryData($wedding, $provider))->assertForbidden();
        $this->assertSame(0, ServiceEnquiry::count());
    }

    public function test_wedding_pages_render(): void
    {
        $planner = $this->user();
        $wedding = $this->wedding($planner);
        $provider = $this->provider();
        $e1 = $this->enquire($planner, $wedding, $provider);
        $this->quote($provider, $e1, 500000);
        $e2 = $this->enquire($planner, $wedding, $this->provider());

        $this->actingAs($planner)->get(route('services.index', $wedding))->assertOk()->assertSee($provider->business_name);
        $this->actingAs($planner)->get(route('services.enquiry', [$wedding, $e1]))->assertOk()->assertSee('Accept & book', false);
        $this->actingAs($planner)->get(route('services.compare', [$wedding, 'ids' => [$e1->id, $e2->id]]))->assertOk()->assertSee('Latest offer');
        $this->actingAs($provider->owner)->get(route('provider.calendar'))->assertOk();
        $this->actingAs($planner)->get(route('notifications.index'))->assertOk();
    }
}
