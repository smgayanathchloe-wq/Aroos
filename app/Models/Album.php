<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Album extends Model
{
    protected $guarded = [];

    protected $casts = ['public' => 'boolean'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function photos(): HasMany { return $this->hasMany(Photo::class); }
}
