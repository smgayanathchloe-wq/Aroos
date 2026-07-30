<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    protected $guarded = [];

    public function menu(): BelongsTo { return $this->belongsTo(Menu::class); }
}
