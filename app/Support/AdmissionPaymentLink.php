<?php

namespace App\Support;

class AdmissionPaymentLink
{
    public static function sign(int $admissionId, ?int $expires = null): array
    {
        $expires ??= now()->addDays(30)->getTimestamp();
        $signature = self::hash($admissionId, $expires);

        return [
            'expires' => $expires,
            'signature' => $signature,
        ];
    }

    public static function verify(int $admissionId, int $expires, string $signature): bool
    {
        if ($expires < now()->getTimestamp()) {
            return false;
        }

        return hash_equals(self::hash($admissionId, $expires), $signature);
    }

    public static function frontendUrl(int $admissionId): string
    {
        $base = rtrim((string) (env('FRONTEND_URL') ?: config('app.url')), '/');
        $signed = self::sign($admissionId);

        return $base.'/admission/pay?'.http_build_query([
            'admission' => $admissionId,
            'expires' => $signed['expires'],
            'signature' => $signed['signature'],
        ]);
    }

    private static function hash(int $admissionId, int $expires): string
    {
        return hash_hmac('sha256', $admissionId.'|'.$expires, (string) config('app.key'));
    }
}
