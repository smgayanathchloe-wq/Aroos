<?php

namespace App\Models\Marketplace;

use App\Models\User;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ServiceEnquiry extends Model
{
    public const OPEN = ['open', 'info_requested', 'quoted', 'negotiating'];

    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
        'budget' => 'decimal:2',
        'expires_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_user_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(ProviderProfile::class, 'provider_id'); }
    public function service(): BelongsTo { return $this->belongsTo(ProviderService::class, 'service_id')->withTrashed(); }
    public function package(): BelongsTo { return $this->belongsTo(ServicePackage::class, 'package_id')->withTrashed(); }
    public function region(): BelongsTo { return $this->belongsTo(TzRegion::class); }
    public function versions(): HasMany { return $this->hasMany(QuotationVersion::class, 'enquiry_id')->orderBy('version_no'); }
    public function messages(): HasMany { return $this->hasMany(EnquiryMessage::class, 'enquiry_id')->orderBy('id'); }
    public function booking(): HasOne { return $this->hasOne(ServiceBooking::class, 'enquiry_id'); }

    public function isOpen(): bool { return in_array($this->status, self::OPEN, true); }

    public function latestVersion(): ?QuotationVersion
    {
        return $this->versions()->reorder('version_no', 'desc')->first();
    }
}
