<?php
/**
 * Reports database rows whose uploaded file is missing from disk.
 *
 * `storage/app/public` holds couples' photos, memories and website
 * imagery. It is not in source control because it is user content, so a
 * deployment that forgets to copy it leaves these rows pointing at
 * nothing and the site renders broken images.
 *
 * Run through tinker:
 *   php artisan tinker --execute="require 'database/check-uploads.php';"
 *
 * Prints MISSING=<n>, and lists the paths when there are any.
 */

use Illuminate\Support\Facades\Storage;

$missing = [];
$checked = 0;

/** Columns that hold a path on the public disk. */
$targets = [
    'photos' => ['file_path'],
    'memories' => ['file_path'],
    'inspirations' => ['file_path'],
    'weddings' => ['cover_photo'],
    'website_settings' => ['hero_image', 'banner_image', 'bride_photo', 'groom_photo'],
];

$check = function (string $path, string $label) use (&$missing, &$checked) {
    if (! $path) {
        return;
    }

    // Remote URLs are somebody else's problem, not ours to ship.
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return;
    }

    $checked++;
    $relative = ltrim(str_replace('storage/', '', $path), '/');

    if (! Storage::disk('public')->exists($relative)) {
        $missing[] = "{$label}: {$path}";
    }
};

foreach ($targets as $table => $columns) {
    if (! Schema::hasTable($table)) {
        continue;
    }

    foreach ($columns as $column) {
        if (! Schema::hasColumn($table, $column)) {
            continue;
        }

        foreach (DB::table($table)->pluck($column) as $path) {
            $check((string) $path, "{$table}.{$column}");
        }
    }
}

// The website slideshow stores its images inside a JSON column, so they
// need unpacking rather than a plain column read.
if (Schema::hasTable('website_settings') && Schema::hasColumn('website_settings', 'slides')) {
    foreach (DB::table('website_settings')->pluck('slides') as $slides) {
        if (! $slides) {
            continue;
        }

        $decoded = is_string($slides) ? json_decode($slides, true) : $slides;

        foreach ((array) $decoded as $slide) {
            $check((string) ($slide['image'] ?? ''), 'website_settings.slides');
        }
    }
}

foreach ($missing as $line) {
    fwrite(STDERR, "missing: {$line}\n");
}

echo 'CHECKED=' . $checked . ' MISSING=' . count($missing);
