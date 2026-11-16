<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePackage extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected $casts = ['features' => 'array', 'extras' => 'array', 'price' => 'decimal:2'];

    public function service(): BelongsTo { return $this->belongsTo(ProviderService::class, 'service_id'); }
}
