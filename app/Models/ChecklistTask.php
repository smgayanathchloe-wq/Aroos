<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistTask extends Model
{
    protected $guarded = [];

    protected $casts = ['completed' => 'boolean', 'due_date' => 'date'];

    public function phase(): BelongsTo { return $this->belongsTo(ChecklistPhase::class, 'checklist_phase_id'); }
}
