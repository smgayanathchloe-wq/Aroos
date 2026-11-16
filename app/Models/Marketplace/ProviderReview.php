<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderReview extends Model
{
    public const ASPECTS = ['quality' => 'Service quality', 'communication' => 'Communication', 'professionalism' => 'Professionalism', 'value' => 'Value for money'];

    protected $guarded = [];

    protected $casts = ['responded_at' => 'datetime'];

    public function booking(): BelongsTo { return $this->belongsTo(ServiceBooking::class, 'booking_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(ProviderProfile::class, 'provider_id')->withTrashed(); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_user_id'); }

    /** Public display name: first name and initial only. */
    public function reviewerName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->customer?->name));

        return ($parts[0] ?? 'Aroos user').(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '');
    }
}
