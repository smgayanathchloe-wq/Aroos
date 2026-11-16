<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderProfile;
use App\Models\Marketplace\ProviderReview;
use App\Models\Marketplace\ServiceBooking;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Reviews come only from the wedding that booked, after the booking is
 * completed, once per booking, and never from the provider's own team.
 * Ratings are recomputed from published reviews only.
 */
class ReviewService
{
    public function __construct(private readonly Notifier $notify)
    {
    }

    /** @return string|null why this user may not review this booking */
    public function ineligibility(ServiceBooking $booking, User $user): ?string
    {
        return match (true) {
            ! $user->canAccessWedding($booking->wedding) => 'Only the wedding that booked can review.',
            $booking->provider->memberFor($user) !== null => 'You cannot review your own business.',
            $booking->status !== 'completed' => 'Reviews open once the booking is completed.',
            ProviderReview::where('booking_id', $booking->id)->exists() => 'This booking has already been reviewed.',
            default => null,
        };
    }

    public function create(ServiceBooking $booking, User $user, array $data): ProviderReview
    {
        if ($reason = $this->ineligibility($booking, $user)) {
            throw new MarketplaceException($reason);
        }

        $review = DB::transaction(function () use ($booking, $user, $data) {
            $review = ProviderReview::create([
                'booking_id' => $booking->id,
                'provider_id' => $booking->provider_id,
                'wedding_id' => $booking->wedding_id,
                'customer_user_id' => $user->id,
                'overall' => $data['overall'],
                'quality' => $data['quality'] ?? null,
                'communication' => $data['communication'] ?? null,
                'professionalism' => $data['professionalism'] ?? null,
                'value' => $data['value'] ?? null,
                'body' => $data['body'] ?? null,
            ]);
            $this->recompute($booking->provider);

            return $review;
        });

        $this->notify->provider($booking->provider, 'review.new', 'New '.$review->overall.'★ review',
            \Illuminate\Support\Str::limit((string) $review->body, 140) ?: 'A couple rated your service.', route('provider.reviews'), "review.{$review->id}", $user);

        return $review;
    }

    public function respond(ProviderReview $review, string $response): void
    {
        $review->update(['provider_response' => $response, 'responded_at' => now()]);
    }

    public function setStatus(ProviderReview $review, string $status, ?string $note = null): void
    {
        DB::transaction(function () use ($review, $status, $note) {
            $review->update(['status' => $status, 'moderation_note' => $note]);
            $this->recompute($review->provider);
        });
    }

    public function recompute(ProviderProfile $provider): void
    {
        $stats = ProviderReview::where('provider_id', $provider->id)->where('status', 'published')
            ->selectRaw('count(*) as c, avg(overall) as a')->first();
        $provider->forceFill([
            'rating_count' => (int) $stats->c,
            'rating_avg' => $stats->c ? round((float) $stats->a, 2) : null,
        ])->saveQuietly();
    }
}
