<?php

namespace App\Marketplace;

use App\Models\Marketplace\BookingStatusHistory;
use App\Models\Marketplace\EnquiryMessage;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\QuotationVersion;
use App\Models\Marketplace\ServiceBooking;
use App\Models\Marketplace\ServiceEnquiry;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;

/**
 * Booking confirmation and lifecycle. Confirmation is one transaction that
 * locks the provider row, so two acceptances for the same slot are serialised
 * and the second one sees the first booking when it checks capacity.
 */
class BookingService
{
    public function __construct(
        private readonly AvailabilityService $availability,
        private readonly NegotiationService $negotiation,
        private readonly Notifier $notify,
    ) {
    }

    public function accept(ServiceEnquiry $enquiry, int $versionId, User $user): ServiceBooking
    {
        $booking = DB::transaction(function () use ($enquiry, $versionId, $user) {
            $provider = ProviderProfile::whereKey($enquiry->provider_id)->lockForUpdate()->firstOrFail();
            $enquiry = ServiceEnquiry::whereKey($enquiry->id)->lockForUpdate()->firstOrFail();
            $version = QuotationVersion::whereKey($versionId)->where('enquiry_id', $enquiry->id)->firstOrFail();
            $latest = $enquiry->latestVersion();

            if (! $enquiry->isOpen()) {
                throw new MarketplaceException('This request is closed ('.$enquiry->status.').');
            }
            if ($provider->isBanned()) {
                throw new MarketplaceException('This provider can no longer take bookings.');
            }
            if ($latest?->id !== $version->id || $version->author_side !== 'provider' || $version->status !== 'pending') {
                throw new MarketplaceException('Only the provider\'s latest offer can be accepted.');
            }
            if ($version->isExpired()) {
                $version->update(['status' => 'expired']);
                throw new MarketplaceException('This offer has expired. Ask the provider for a new quotation.');
            }

            $service = $enquiry->service ?? throw new MarketplaceException('The service on this request no longer exists.');
            $date = ($version->proposed_date ?? $enquiry->event_date)->toDateString();
            if ($reason = $this->availability->conflict($service, $date, $enquiry->event_start, $enquiry->event_end)) {
                throw new MarketplaceException($reason.' Ask the provider to propose another date.');
            }

            $version->update(['status' => 'accepted']);
            $enquiry->update(['status' => 'accepted', 'last_activity_at' => now()]);
            $package = $version->package;

            $booking = ServiceBooking::create([
                'reference' => $this->negotiation->reference('BK'),
                'enquiry_id' => $enquiry->id,
                'accepted_version_id' => $version->id,
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'package_id' => $package?->id,
                'wedding_id' => $enquiry->wedding_id,
                'customer_user_id' => $user->id,
                'event_date' => $date,
                'starts_at' => $enquiry->event_start,
                'ends_at' => $enquiry->event_end,
                'agreed_amount' => $version->amount,
                'currency' => $version->currency,
                'terms_snapshot' => [
                    'provider' => $provider->business_name,
                    'service' => $service->title,
                    'package' => $package ? $package->only(['name', 'description', 'price', 'features', 'extras', 'terms', 'deposit_percent', 'cancellation_policy']) : null,
                    'quotation' => ['version' => $version->version_no, 'amount' => (float) $version->amount, 'terms' => $version->terms, 'message' => $version->message],
                    'event' => ['date' => $date, 'start' => $enquiry->event_start, 'end' => $enquiry->event_end, 'venue' => $enquiry->venue, 'guests' => $enquiry->guests],
                    'accepted_by' => $user->name,
                    'accepted_at' => now()->toIso8601String(),
                ],
                'status' => 'confirmed',
            ]);
            BookingStatusHistory::create(['booking_id' => $booking->id, 'from_status' => null, 'to_status' => 'confirmed', 'user_id' => $user->id, 'created_at' => now()]);
            EnquiryMessage::create(['enquiry_id' => $enquiry->id, 'user_id' => $user->id, 'side' => 'customer', 'kind' => 'event', 'body' => 'Offer accepted. Booking '.$booking->reference.' confirmed.', 'created_at' => now()]);

            // Bridge into the wedding's own vendor list, so Budget and Report include it.
            $vendor = Vendor::create([
                'wedding_id' => $enquiry->wedding_id,
                'category' => $service->category?->name ?? $provider->primaryCategory?->name ?? 'Other',
                'name' => $provider->business_name,
                'email' => $provider->email,
                'phone' => $provider->phone,
                'website' => $provider->website,
                'price_estimate' => $version->amount,
                'amount_paid' => 0,
                'status' => 'booked',
                'notes' => 'Booked through the Aroos marketplace ('.$booking->reference.').',
            ]);
            $booking->update(['vendor_id' => $vendor->id]);

            return $booking;
        });

        $amount = Money::format($booking->agreed_amount, $booking->currency);
        $this->notify->provider($booking->provider, 'booking.confirmed', 'Booking confirmed: '.$booking->reference,
            $booking->service?->title.' on '.$booking->event_date->format('j M Y').' for '.$amount.'.', route('provider.bookings'), "booking.confirmed.{$booking->id}");
        $this->notify->wedding($booking->wedding, 'booking.confirmed', 'Booking confirmed with '.$booking->provider->business_name,
            $booking->reference.' · '.$booking->event_date->format('j M Y').' · '.$amount.'.', route('services.index', $booking->wedding), "booking.confirmed.{$booking->id}", $user);

        return $booking;
    }

    public function cancel(ServiceBooking $booking, User $user, string $side, string $reason): void
    {
        DB::transaction(function () use ($booking, $user, $side, $reason) {
            $booking = ServiceBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->status !== 'confirmed') {
                throw new MarketplaceException('Only confirmed bookings can be cancelled.');
            }
            $to = $side === 'provider' ? 'cancelled_by_provider' : 'cancelled_by_customer';
            $booking->update(['status' => $to, 'cancel_reason' => $reason]);
            BookingStatusHistory::create(['booking_id' => $booking->id, 'from_status' => 'confirmed', 'to_status' => $to, 'user_id' => $user->id, 'reason' => $reason, 'created_at' => now()]);
            $booking->vendor?->update(['status' => 'rejected', 'notes' => trim($booking->vendor->notes."\nBooking cancelled: ".$reason)]);
        });
        $booking->refresh();

        $title = 'Booking '.$booking->reference.' cancelled';
        $side === 'provider'
            ? $this->notify->wedding($booking->wedding, 'booking.cancelled', $title, $booking->provider->business_name.' cancelled: '.$reason, route('services.index', $booking->wedding), "booking.cancelled.{$booking->id}", $user)
            : $this->notify->provider($booking->provider, 'booking.cancelled', $title, 'The couple cancelled: '.$reason, route('provider.bookings'), "booking.cancelled.{$booking->id}", $user);
    }

    public function complete(ServiceBooking $booking, ?User $user = null): void
    {
        DB::transaction(function () use ($booking, $user) {
            $booking = ServiceBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($booking->status !== 'confirmed') {
                throw new MarketplaceException('Only confirmed bookings can be completed.');
            }
            if ($booking->event_date->isFuture()) {
                throw new MarketplaceException('A booking can be completed on or after the event date.');
            }
            $booking->update(['status' => 'completed']);
            BookingStatusHistory::create(['booking_id' => $booking->id, 'from_status' => 'confirmed', 'to_status' => 'completed', 'user_id' => $user?->id, 'reason' => $user ? null : 'Automatically after the event', 'created_at' => now()]);
        });
    }
}
