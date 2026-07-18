<?php

namespace SoftKatta\Licensing\Support;

final class LicenseErrorCode
{
    public const INVALID_LICENSE = 'INVALID_LICENSE';
    public const EXPIRED_SUBSCRIPTION = 'EXPIRED_SUBSCRIPTION';
    public const SUSPENDED_LICENSE = 'SUSPENDED_LICENSE';
    public const DOMAIN_NOT_AUTHORIZED = 'DOMAIN_NOT_AUTHORIZED';
    public const PRODUCT_DISABLED = 'PRODUCT_DISABLED';
    public const UNSUPPORTED_VERSION = 'UNSUPPORTED_VERSION';
    public const SERVER_VERIFICATION_FAILED = 'SERVER_VERIFICATION_FAILED';
    public const INVALID_SIGNATURE = 'INVALID_SIGNATURE';
    public const EXPIRED_TIMESTAMP = 'EXPIRED_TIMESTAMP';
    public const DUPLICATE_NONCE = 'DUPLICATE_NONCE';
    public const INVALID_INSTALL_TOKEN = 'INVALID_INSTALL_TOKEN';
    public const GRACE_EXPIRED = 'GRACE_EXPIRED';
    public const NOT_INSTALLED = 'NOT_INSTALLED';
    public const COMPANY_API_UNAVAILABLE = 'COMPANY_API_UNAVAILABLE';

    public static function frontendPath(string $code): string
    {
        return match ($code) {
            self::INVALID_LICENSE => '/license/invalid',
            self::EXPIRED_SUBSCRIPTION => '/license/expired',
            self::SUSPENDED_LICENSE => '/license/suspended',
            self::DOMAIN_NOT_AUTHORIZED => '/license/domain-not-authorized',
            self::PRODUCT_DISABLED => '/license/product-disabled',
            self::UNSUPPORTED_VERSION => '/license/unsupported-version',
            self::SERVER_VERIFICATION_FAILED => '/license/server-verification-failed',
            self::INVALID_INSTALL_TOKEN => '/license/invalid-install-token',
            self::GRACE_EXPIRED => '/license/grace-expired',
            self::COMPANY_API_UNAVAILABLE => '/license/company-api-unavailable',
            default => '/license/invalid',
        };
    }
}
