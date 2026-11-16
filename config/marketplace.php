<?php

return [
    'currency' => env('MARKETPLACE_CURRENCY', 'TZS'),

    // Days an enquiry waits for a provider before expiring, and default quote validity.
    'enquiry_expiry_days' => (int) env('MARKETPLACE_ENQUIRY_EXPIRY_DAYS', 14),
    'quote_validity_days' => (int) env('MARKETPLACE_QUOTE_VALIDITY_DAYS', 7),

    // Upload limits (also raise PHP upload_max_filesize / post_max_size for video).
    'max_image_mb' => (int) env('MARKETPLACE_MAX_IMAGE_MB', 10),
    'max_video_mb' => (int) env('MARKETPLACE_MAX_VIDEO_MB', 100),
    'max_media_per_provider' => (int) env('MARKETPLACE_MAX_MEDIA', 200),
    'max_document_mb' => 10,

    // Hosts accepted for social links and video links (exact host or subdomain).
    'social_hosts' => [
        'instagram' => ['instagram.com'],
        'tiktok' => ['tiktok.com'],
        'facebook' => ['facebook.com', 'fb.com'],
        'youtube' => ['youtube.com', 'youtu.be'],
        'linkedin' => ['linkedin.com'],
        'x' => ['x.com', 'twitter.com'],
    ],
    'video_link_hosts' => ['instagram.com', 'tiktok.com', 'youtube.com', 'youtu.be'],
];
