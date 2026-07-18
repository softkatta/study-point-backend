<?php

namespace SoftKatta\Licensing\Support;

final class HmacSigner
{
    public static function canonicalString(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $productSlug,
        string $domain,
        string $productVersion,
        string $installationId,
        string $serverFingerprint,
        string $rawBody = '',
    ): string {
        $bodyHash = hash('sha256', $rawBody);

        return implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $productSlug,
            strtolower($domain),
            $productVersion,
            $installationId,
            $serverFingerprint,
            $bodyHash,
        ]);
    }

    public static function sign(string $canonical, string $secret): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }
}
