<?php

namespace App\Services;

use App\Support\MediaUrl;
use Illuminate\Support\Facades\Storage;

class BrandingPdfCacheService
{
    /**
     * JPEG data URI for DomPDF (JPEG embedding does not require GD in DomPDF).
     */
    public function logoDataUri(?string $url): ?string
    {
        $relative = $this->resolveRelativePath($url);
        if (! $relative) {
            return null;
        }

        $absolute = Storage::disk('public')->path($relative);
        if (! is_readable($absolute)) {
            return null;
        }

        $jpegBytes = $this->jpegBytes($relative, $absolute);
        if (! $jpegBytes) {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($jpegBytes);
    }

    public function warmLogoCache(?string $url): bool
    {
        $relative = $this->resolveRelativePath($url);
        if (! $relative) {
            return false;
        }

        $absolute = Storage::disk('public')->path($relative);
        if (! is_readable($absolute)) {
            return false;
        }

        return $this->jpegBytes($relative, $absolute) !== null;
    }

    public function resolveRelativePath(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = $this->storagePathFromUrl($url);
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
            return null;
        }

        return $path;
    }

    private function jpegBytes(string $relative, string $absolute): ?string
    {
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        $cacheRelative = $this->cachePath($relative);
        $cacheAbsolute = Storage::disk('public')->path($cacheRelative);

        if (in_array($extension, ['jpg', 'jpeg'], true)) {
            $bytes = @file_get_contents($absolute);

            return ($bytes !== false && $bytes !== '') ? $bytes : null;
        }

        if (is_readable($cacheAbsolute) && filemtime($cacheAbsolute) >= filemtime($absolute)) {
            $cached = @file_get_contents($cacheAbsolute);
            if ($cached !== false && $cached !== '') {
                return $cached;
            }
        }

        $converted = $this->convertToJpegBytes($absolute, $extension);
        if (! $converted) {
            if (is_readable($cacheAbsolute)) {
                $cached = @file_get_contents($cacheAbsolute);

                return ($cached !== false && $cached !== '') ? $cached : null;
            }

            return null;
        }

        Storage::disk('public')->makeDirectory('branding/cache');
        @file_put_contents($cacheAbsolute, $converted);

        return $converted;
    }

    private function cachePath(string $relative): string
    {
        return 'branding/cache/'.pathinfo($relative, PATHINFO_FILENAME).'-pdf.jpg';
    }

    private function convertToJpegBytes(string $absolute, string $extension): ?string
    {
        $image = match ($extension) {
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absolute) : null,
            'gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($absolute) : null,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolute) : null,
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absolute) : null,
            default => null,
        };

        if (! $image) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $canvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        ob_start();
        imagejpeg($canvas, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($canvas);

        if ($jpeg === false || $jpeg === '') {
            return null;
        }

        return $jpeg;
    }

    private function storagePathFromUrl(string $url): ?string
    {
        $url = trim($url);

        if (preg_match('#/storage/(.+)$#', $url, $matches)) {
            return $matches[1];
        }

        if (str_starts_with($url, 'storage/')) {
            return substr($url, strlen('storage/'));
        }

        $absolute = MediaUrl::absolute($url);
        if ($absolute && preg_match('#/storage/(.+)$#', $absolute, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
