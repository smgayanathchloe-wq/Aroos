<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteSetting extends Model
{
    protected $guarded = [];

    protected $casts = ['published' => 'boolean', 'sections' => 'array', 'slides' => 'array'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
}
