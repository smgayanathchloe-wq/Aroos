<?php

namespace App\Models\Marketplace;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProviderMedia extends Model
{
    use SoftDeletes;

    protected $table = 'provider_media';

    protected $guarded = [];

    public function provider(): BelongsTo { return $this->belongsTo(ProviderProfile::class, 'provider_id'); }
    public function service(): BelongsTo { return $this->belongsTo(ProviderService::class, 'service_id'); }

    public function fileUrl(): ?string { return $this->path ? asset('storage/'.$this->path) : null; }

    /** Embeddable URL for YouTube links; Instagram/TikTok open on their own site. */
    public function embedUrl(): ?string
    {
        if ($this->type !== 'video_link' || ! $this->url) {
            return null;
        }
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|shorts/|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,15})~', $this->url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1];
        }

        return null;
    }

    public function platform(): ?string
    {
        $host = parse_url((string) $this->url, PHP_URL_HOST) ?: '';

        return match (true) {
            str_contains($host, 'instagram') => 'Instagram',
            str_contains($host, 'tiktok') => 'TikTok',
            str_contains($host, 'youtu') => 'YouTube',
            default => null,
        };
    }
}
