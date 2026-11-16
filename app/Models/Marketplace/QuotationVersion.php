<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One offer or counter-offer. Rows are never edited after insert except for
 * `status`, so the full negotiation history is always preserved.
 */
class QuotationVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'previous_amount' => 'decimal:2',
        'proposed_date' => 'date',
        'valid_until' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $v) {
            if (array_diff(array_keys($v->getDirty()), ['status'])) {
                throw new \LogicException('Quotation versions are immutable; only their status can change.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Quotation versions cannot be deleted.'));
    }

    public function enquiry(): BelongsTo { return $this->belongsTo(ServiceEnquiry::class, 'enquiry_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_user_id'); }
    public function package(): BelongsTo { return $this->belongsTo(ServicePackage::class, 'package_id')->withTrashed(); }

    public function isExpired(): bool { return $this->valid_until !== null && $this->valid_until->isPast(); }
}
