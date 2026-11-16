<?php

namespace App\Marketplace;

use App\Models\Marketplace\EnquiryMessage;
use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\QuotationVersion;
use App\Models\Marketplace\ServiceEnquiry;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Enquiry → quotation → negotiation. Every offer is a new QuotationVersion;
 * earlier ones are only ever marked superseded/declined/expired, never edited.
 * Only the wedding side can accept, and only the provider's latest live offer
 * (BookingService does that, inside its own transaction).
 */
class NegotiationService
{
    public function __construct(private readonly Notifier $notify)
    {
    }

    public function createEnquiry(User $user, Wedding $wedding, ProviderProfile $provider, array $data): ServiceEnquiry
    {
        if (! $provider->isListed()) {
            throw new MarketplaceException('This provider is not accepting requests right now.');
        }
        $service = $provider->services()->published()->find($data['service_id'] ?? 0)
            ?? throw new MarketplaceException('Choose one of the provider\'s services.');
        $package = isset($data['package_id']) && $data['package_id']
            ? ($service->activePackages()->find($data['package_id']) ?? throw new MarketplaceException('That package is not available.'))
            : null;

        $enquiry = DB::transaction(function () use ($user, $wedding, $provider, $service, $package, $data) {
            $enquiry = ServiceEnquiry::create([
                'reference' => $this->reference('ENQ'),
                'wedding_id' => $wedding->id,
                'customer_user_id' => $user->id,
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'package_id' => $package?->id,
                'event_name' => $data['event_name'] ?? $wedding->coupleNames(),
                'event_date' => $data['event_date'],
                'event_start' => $data['event_start'] ?? null,
                'event_end' => $data['event_end'] ?? null,
                'venue' => $data['venue'] ?? $wedding->venue_name,
                'region_id' => $data['region_id'] ?? null,
                'guests' => $data['guests'] ?? null,
                'budget' => $data['budget'] ?? null,
                'requirements' => $data['requirements'] ?? null,
                'preferred_contact' => $data['preferred_contact'] ?? 'aroos',
                'contact_name' => $data['contact_name'] ?? $user->name,
                'contact_phone' => $data['contact_phone'] ?? $user->phone,
                'contact_email' => $data['contact_email'] ?? $user->email,
                'status' => 'open',
                'expires_at' => now()->addDays(config('marketplace.enquiry_expiry_days')),
                'last_activity_at' => now(),
                'client_token' => $data['client_token'] ?? null,
            ]);
            $this->event($enquiry, 'customer', $user, 'Request sent.');

            return $enquiry;
        });

        $this->notify->provider($provider, 'enquiry.new', 'New request from '.$enquiry->contact_name,
            $service->title.' on '.$enquiry->event_date->format('j M Y').'.', route('provider.enquiries.show', $enquiry), "enquiry.new.{$enquiry->id}", $user);

        return $enquiry;
    }

    public function quote(ServiceEnquiry $enquiry, User $user, array $data): QuotationVersion
    {
        $version = $this->addVersion($enquiry, $user, 'provider', $data);
        $this->notify->wedding($enquiry->wedding, 'quote.received', 'Quotation from '.$enquiry->provider->business_name,
            Money::format($version->amount, $version->currency).' for '.$enquiry->service?->title.'.', route('services.enquiry', [$enquiry->wedding, $enquiry]), "quote.{$version->id}", $user);

        return $version;
    }

    public function counter(ServiceEnquiry $enquiry, User $user, array $data): QuotationVersion
    {
        $latest = $enquiry->latestVersion();
        if (! $latest || $latest->author_side !== 'provider' || $latest->status !== 'pending') {
            throw new MarketplaceException('You can counter once the provider has sent a quotation.');
        }
        $version = $this->addVersion($enquiry, $user, 'customer', $data + ['package_id' => $latest->package_id, 'proposed_date' => $latest->proposed_date?->toDateString()]);
        $this->notify->provider($enquiry->provider, 'quote.counter', 'Counter-offer on '.$enquiry->reference,
            $enquiry->contact_name.' proposes '.Money::format($version->amount, $version->currency).'.', route('provider.enquiries.show', $enquiry), "quote.{$version->id}", $user);

        return $version;
    }

    public function message(ServiceEnquiry $enquiry, User $user, string $side, string $body, string $kind = 'message'): void
    {
        $this->ensureOpen($enquiry);
        DB::transaction(function () use ($enquiry, $user, $side, $body, $kind) {
            EnquiryMessage::create(['enquiry_id' => $enquiry->id, 'user_id' => $user->id, 'side' => $side, 'kind' => $kind, 'body' => $body, 'created_at' => now()]);
            $update = ['last_activity_at' => now(), 'expires_at' => now()->addDays(config('marketplace.enquiry_expiry_days'))];
            if ($kind === 'info_request' && $enquiry->status === 'open') {
                $update['status'] = 'info_requested';
            }
            $enquiry->update($update);
        });

        $key = 'msg.'.$enquiry->id.'.'.$enquiry->messages()->max('id');
        $side === 'provider'
            ? $this->notify->wedding($enquiry->wedding, 'enquiry.message', $enquiry->provider->business_name.' replied', Str::limit($body, 140), route('services.enquiry', [$enquiry->wedding, $enquiry]), $key, $user)
            : $this->notify->provider($enquiry->provider, 'enquiry.message', $enquiry->contact_name.' replied', Str::limit($body, 140), route('provider.enquiries.show', $enquiry), $key, $user);
    }

    public function decline(ServiceEnquiry $enquiry, User $user, string $side, string $reason): void
    {
        $this->ensureOpen($enquiry);
        DB::transaction(function () use ($enquiry, $user, $side, $reason) {
            $enquiry->versions()->where('status', 'pending')->get()->each->update(['status' => 'declined']);
            $enquiry->update(['status' => $side === 'provider' ? 'declined' : 'cancelled', 'decline_reason' => $reason, 'last_activity_at' => now()]);
            $this->event($enquiry, $side, $user, ($side === 'provider' ? 'Provider declined: ' : 'Request withdrawn: ').$reason);
        });

        $side === 'provider'
            ? $this->notify->wedding($enquiry->wedding, 'enquiry.declined', $enquiry->provider->business_name.' declined your request', $reason, route('services.enquiry', [$enquiry->wedding, $enquiry]), "declined.{$enquiry->id}", $user)
            : $this->notify->provider($enquiry->provider, 'enquiry.cancelled', 'Request '.$enquiry->reference.' withdrawn', $reason, route('provider.enquiries.show', $enquiry), "cancelled.{$enquiry->id}", $user);
    }

    /** Housekeeping: idle enquiries and stale offers expire. Returns counts. */
    public function expire(): array
    {
        $versions = QuotationVersion::where('status', 'pending')->whereNotNull('valid_until')->where('valid_until', '<', now())->get();
        $versions->each->update(['status' => 'expired']);

        $enquiries = ServiceEnquiry::whereIn('status', ServiceEnquiry::OPEN)->where('expires_at', '<', now())->get();
        foreach ($enquiries as $enquiry) {
            $enquiry->versions()->where('status', 'pending')->get()->each->update(['status' => 'expired']);
            $enquiry->update(['status' => 'expired']);
        }

        return ['versions' => $versions->count(), 'enquiries' => $enquiries->count()];
    }

    private function addVersion(ServiceEnquiry $enquiry, User $user, string $side, array $data): QuotationVersion
    {
        return DB::transaction(function () use ($enquiry, $user, $side, $data) {
            $enquiry = ServiceEnquiry::whereKey($enquiry->id)->lockForUpdate()->firstOrFail();
            $this->ensureOpen($enquiry);

            $previous = $enquiry->latestVersion();
            if ($previous && $previous->status === 'pending') {
                $previous->update(['status' => 'superseded']);
            }
            $packageId = $data['package_id'] ?? null;
            if ($packageId && ! $enquiry->service?->activePackages()->whereKey($packageId)->exists()) {
                throw new MarketplaceException('That package does not belong to this service.');
            }

            $version = QuotationVersion::create([
                'enquiry_id' => $enquiry->id,
                'version_no' => ($previous?->version_no ?? 0) + 1,
                'author_user_id' => $user->id,
                'author_side' => $side,
                'previous_amount' => $previous?->amount,
                'amount' => $data['amount'],
                'currency' => config('marketplace.currency'),
                'package_id' => $packageId,
                'proposed_date' => $data['proposed_date'] ?? null,
                'terms' => $data['terms'] ?? null,
                'message' => $data['message'] ?? null,
                'valid_until' => now()->addDays((int) ($data['valid_days'] ?? config('marketplace.quote_validity_days'))),
                'status' => 'pending',
                'created_at' => now(),
            ]);

            $enquiry->update([
                'status' => $side === 'provider' && ! $previous ? 'quoted' : 'negotiating',
                'last_activity_at' => now(),
                'expires_at' => now()->addDays(config('marketplace.enquiry_expiry_days')),
            ]);

            return $version;
        });
    }

    private function ensureOpen(ServiceEnquiry $enquiry): void
    {
        if (! $enquiry->isOpen()) {
            throw new MarketplaceException('This request is closed ('.$enquiry->status.').');
        }
    }

    private function event(ServiceEnquiry $enquiry, string $side, User $user, string $body): void
    {
        EnquiryMessage::create(['enquiry_id' => $enquiry->id, 'user_id' => $user->id, 'side' => $side, 'kind' => 'event', 'body' => $body, 'created_at' => now()]);
    }

    public function reference(string $prefix): string
    {
        return $prefix.'-'.now()->format('ym').'-'.strtoupper(Str::random(5));
    }
}
