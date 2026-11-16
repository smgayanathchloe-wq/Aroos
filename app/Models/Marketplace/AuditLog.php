<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'marketplace_audit_log';

    protected $guarded = [];

    protected $casts = ['data' => 'array'];

    public static function record(string $action, Model $subject, array $data = []): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'data' => $data ?: null,
        ]);
    }
}
