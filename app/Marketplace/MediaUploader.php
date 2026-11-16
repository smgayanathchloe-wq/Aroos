<?php

namespace App\Marketplace;

use App\Models\Marketplace\ProviderProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores provider images and videos on the public disk. Images are decoded and
 * re-encoded with GD, which drops EXIF/GPS metadata and anything that is not
 * really an image.
 */
class MediaUploader
{
    public function image(ProviderProfile $provider, UploadedFile $file, string $folder = 'portfolio', int $maxSide = 2000): string
    {
        $data = @file_get_contents($file->getRealPath());
        $img = $data !== false ? @imagecreatefromstring($data) : false;
        if (! $img) {
            throw ValidationException::withMessages(['file' => 'That file is not a readable image.']);
        }

        [$w, $h] = [imagesx($img), imagesy($img)];
        $scale = min(1, $maxSide / max($w, $h));
        if ($scale < 1) {
            $resized = imagescale($img, (int) round($w * $scale), (int) round($h * $scale));
            imagedestroy($img);
            $img = $resized;
        }

        ob_start();
        imagejpeg($img, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($img);

        $path = "providers/{$provider->id}/{$folder}/".Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        return $path;
    }

    public function video(ProviderProfile $provider, UploadedFile $file): string
    {
        $ext = match ($file->getMimeType()) {
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => throw ValidationException::withMessages(['file' => 'Videos must be MP4, WebM or MOV.']),
        };

        return $file->storeAs("providers/{$provider->id}/videos", Str::uuid().'.'.$ext, 'public');
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
