<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoragePlan extends Model
{
    protected $guarded = [];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function wedding(): BelongsTo
    {
        return $this->belongsTo(Wedding::class);
    }

    /**
     * Get available storage in bytes
     */
    public function availableBytes(): int
    {
        return $this->total_bytes - $this->used_bytes;
    }

    /**
     * Get percentage used
     */
    public function percentageUsed(): float
    {
        return $this->total_bytes > 0 
            ? round(($this->used_bytes / $this->total_bytes) * 100, 2)
            : 0;
    }

    /**
     * Check if storage is full
     */
    public function isFull(): bool
    {
        return $this->availableBytes() <= 0;
    }

    /**
     * Check if usage is above 90%
     */
    public function isNearlyFull(): bool
    {
        return $this->percentageUsed() >= 90;
    }

    /**
     * Format bytes to human readable
     */
    public static function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
