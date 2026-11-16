<?php

namespace App\Models\Marketplace;

use App\Models\User;
use App\Models\Vendor;
use App\Models\Wedding;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceBooking extends Model
{
    public const ACTIVE = ['confirmed', 'completed'];

    protected $guarded = [];

    protected $casts = ['event_date' => 'date', 'agreed_amount' => 'decimal:2', 'terms_snapshot' => 'array'];

    public function enquiry(): BelongsTo { return $this->belongsTo(ServiceEnquiry::class, 'enquiry_id'); }
    public function acceptedVersion(): BelongsTo { return $this->belongsTo(QuotationVersion::class, 'accepted_version_id'); }
    public function provider(): BelongsTo { return $this->belongsTo(ProviderProfile::class, 'provider_id')->withTrashed(); }
    public function service(): BelongsTo { return $this->belongsTo(ProviderService::class, 'service_id')->withTrashed(); }
    public function package(): BelongsTo { return $this->belongsTo(ServicePackage::class, 'package_id')->withTrashed(); }
    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function customer(): BelongsTo { return $this->belongsTo(User::class, 'customer_user_id'); }
    public function vendor(): BelongsTo { return $this->belongsTo(Vendor::class); }
    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(ProviderReview::class, 'booking_id'); }
    public function history(): HasMany { return $this->hasMany(BookingStatusHistory::class, 'booking_id')->orderBy('id'); }
}
