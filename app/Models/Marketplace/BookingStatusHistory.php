<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'booking_status_history';

    protected $guarded = [];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
