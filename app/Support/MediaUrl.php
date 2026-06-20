<?php

namespace App\Support;

class MediaUrl
{
    public static function absolute(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }

        $url = trim($url);

        if (preg_match('#^(https?:|data:|cid:)#i', $url)) {
            return $url;
        }

        $base = rtrim((string) config('app.url'), '/');

        if (str_starts_with($url, '//')) {
            return 'https:'.$url;
        }

        if (str_starts_with($url, '/')) {
            return $base.$url;
        }

        return $base.'/'.$url;
    }

    /** Light tinted background from a hex brand color (for email outer bg). */
    public static function softBackground(string $hex, float $mix = 0.12): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#e8edf2';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $br = (int) round(232 * (1 - $mix) + $r * $mix);
        $bg = (int) round(237 * (1 - $mix) + $g * $mix);
        $bb = (int) round(242 * (1 - $mix) + $b * $mix);

        return sprintf('#%02x%02x%02x', $br, $bg, $bb);
    }

    /** Very light tint for badge backgrounds in emails. */
    public static function softTint(string $hex, float $mix = 0.1): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '#eef2ff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $br = (int) round(255 * (1 - $mix) + $r * $mix);
        $bg = (int) round(255 * (1 - $mix) + $g * $mix);
        $bb = (int) round(255 * (1 - $mix) + $b * $mix);

        return sprintf('#%02x%02x%02x', $br, $bg, $bb);
    }
}
