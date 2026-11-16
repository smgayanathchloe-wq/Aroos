<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    public const VOICE_TERMINAL = ['done', 'no_answer', 'voicemail', 'failed', 'opted_out'];

    protected $guarded = [];

    protected $casts = ['extracted' => 'array', 'started_at' => 'datetime', 'ended_at' => 'datetime'];

    public function wedding(): BelongsTo { return $this->belongsTo(Wedding::class); }
    public function pledge(): BelongsTo { return $this->belongsTo(Pledge::class); }
    public function turns(): HasMany { return $this->hasMany(ConversationTurn::class)->orderBy('id'); }
    public function promises(): HasMany { return $this->hasMany(PledgePromise::class); }

    public function addTurn(string $role, string $content, ?array $payload = null): ConversationTurn
    {
        return $this->turns()->create(['role' => $role, 'content' => $content, 'payload' => $payload, 'created_at' => now()]);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [...self::VOICE_TERMINAL, 'closed'], true);
    }
}
