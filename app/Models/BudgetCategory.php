<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetCategory extends Model
{
    protected $guarded = [];

    protected $casts = ['budgeted' => 'decimal:2', 'spent' => 'decimal:2'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
}
