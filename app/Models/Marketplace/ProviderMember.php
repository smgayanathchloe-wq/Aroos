<?php

namespace App\Models\Marketplace;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderMember extends Model
{
    protected $guarded = [];

    protected $casts = ['permissions' => 'array'];

    public function provider(): BelongsTo { return $this->belongsTo(ProviderProfile::class, 'provider_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
