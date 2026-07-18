<?php

namespace SoftKatta\Licensing\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use SoftKatta\Licensing\Contracts\CreatesAdminUser;

class InstallOrchestrator
{
    public function __construct(
        private LicenseService $license,
        private ServerFingerprintService $fingerprint,
    ) {}

    public function status(): array
    {
        $publicKey = (string) config('softkatta.public_api_key');
        $secret = (string) config('softkatta.api_secret');

        $databaseUnavailable = false;

        try {
            $installed = $this->license->isInstalled();
            $state = \SoftKatta\Licensing\Models\LicenseState::query()->first();
            $hasToken = filled($state?->install_token);
            $hardBlocked = \SoftKatta\Licensing\Support\LicenseErrorCode::isHardFailure($state?->last_error_code);
            // After SoftKatta suspend, token may still exist locally but is dead — allow re-activation.
            $hasLicense = $hasToken && ! $hardBlocked;
        } catch (\Throwable) {
            // DB unavailable: lock file means install already finished — never reopen the wizard.
            $installed = File::exists(storage_path('app/installed'));
            $state = null;
            $hasLicense = false;
            $databaseUnavailable = $installed;
        }

        return [
            'installed' => $installed,
            'product_slug' => $this->resolveProductSlug(),
            'product_version' => config('softkatta.product_version'),
            'domain' => $this->resolvePublicDomain(),
            'fingerprint' => $this->fingerprint->generate(),
            'has_license' => $hasLicense,
            'needs_reactivation' => $installed && ! $hasLicense,
            'last_error_code' => $databaseUnavailable ? 'DATABASE_UNAVAILABLE' : $state?->last_error_code,
            'database_unavailable' => $databaseUnavailable,
            'bound_domain' => $state?->bound_domain,
            'company_api_configured' => $this->isCompanyApiConfigured(),
            'company_api' => [
                'company_api_url' => (string) config('softkatta.company_api_url'),
                'public_api_key' => $this->maskSecret($publicKey),
                'api_secret_set' => $secret !== '',
                'product_slug' => $this->resolveProductSlug(),
                'product_version' => (string) config('softkatta.product_version'),
                'app_url' => (string) config('app.url'),
                'require_https' => (bool) config('softkatta.require_https'),
                'offline_grace_days' => (int) config('softkatta.offline_grace_days'),
                'verify_interval_hours' => (int) config('softkatta.verify_interval_hours'),
            ],
            'configuration' => ($hasLicense && $state)
                ? $this->license->configurationProfile()
                : null,
            'entitlements' => $installed ? $this->license->entitlements() : null,
        ];
    }

    /**
     * Prefer APP_URL / fingerprint, then browser Origin (SPA on public host → API on localhost/API host).
     */
    private function resolvePublicDomain(): string
    {
        $domain = $this->fingerprint->currentDomain();
        if ($domain !== '' && ! $this->isLoopbackHost($domain)) {
            return $domain;
        }

        foreach (['Origin', 'Referer'] as $header) {
            $value = (string) request()->headers->get($header);
            if ($value === '') {
                continue;
            }
            $host = parse_url($value, PHP_URL_HOST);
            if (is_string($host) && $host !== '' && ! $this->isLoopbackHost($host)) {
                return strtolower($host);
            }
        }

        return $domain !== '' ? $domain : 'localhost';
    }

    private function resolveProductSlug(): string
    {
        $slug = trim((string) config('softkatta.product_slug'));
        if ($slug !== '' && str_contains($slug, 'study-point')) {
            return $slug;
        }

        return 'study-point-management-software';
    }

    private function isLoopbackHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost');
    }

    public function requirements(): array
    {
        $checks = [
            'php_version' => [
                'label' => 'PHP >= 8.2',
                'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
                'value' => PHP_VERSION,
            ],
            'pdo_mysql' => [
                'label' => 'PDO MySQL',
                'ok' => extension_loaded('pdo_mysql'),
                'value' => extension_loaded('pdo_mysql') ? 'enabled' : 'missing',
            ],
            'openssl' => [
                'label' => 'OpenSSL',
                'ok' => extension_loaded('openssl'),
                'value' => extension_loaded('openssl') ? 'enabled' : 'missing',
            ],
            'mbstring' => [
                'label' => 'Mbstring',
                'ok' => extension_loaded('mbstring'),
                'value' => extension_loaded('mbstring') ? 'enabled' : 'missing',
            ],
            'tokenizer' => [
                'label' => 'Tokenizer',
                'ok' => extension_loaded('tokenizer'),
                'value' => extension_loaded('tokenizer') ? 'enabled' : 'missing',
            ],
            'json' => [
                'label' => 'JSON',
                'ok' => extension_loaded('json'),
                'value' => extension_loaded('json') ? 'enabled' : 'missing',
            ],
            'storage_writable' => [
                'label' => 'storage/ writable',
                'ok' => is_writable(storage_path()),
                'value' => storage_path(),
            ],
            'env_writable' => [
                'label' => '.env writable',
                'ok' => is_writable(base_path('.env')) || is_writable(base_path()),
                'value' => base_path('.env'),
            ],
        ];

        return [
            'checks' => $checks,
            'passed' => collect($checks)->every(fn ($c) => $c['ok'] === true),
        ];
    }

    public function configureDatabase(array $data): array
    {
        $host = $data['host'] ?? '127.0.0.1';
        $port = $data['port'] ?? '3306';
        $database = $data['database'] ?? '';
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        try {
            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '``', $database).'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable $e) {
            throw new RuntimeException('Database connection failed: '.$e->getMessage());
        }

        $this->writeEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $host,
            'DB_PORT' => (string) $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
        ]);

        Artisan::call('config:clear');

        return ['connected' => true, 'database' => $database];
    }

    /**
     * Persist SoftKatta Central credentials + app URL from the install wizard
     * so operators never need to hand-edit .env before activation.
     */
    public function configureCompanyApi(array $data): array
    {
        $companyApiUrl = rtrim((string) ($data['company_api_url'] ?? ''), '/');
        $publicApiKey = trim((string) ($data['public_api_key'] ?? ''));
        $apiSecret = trim((string) ($data['api_secret'] ?? ''));
        $productSlug = trim((string) ($data['product_slug'] ?? ''));
        $productVersion = trim((string) ($data['product_version'] ?? '1.0.0')) ?: '1.0.0';
        $appUrl = rtrim((string) ($data['app_url'] ?? config('app.url')), '/');
        $requireHttps = filter_var($data['require_https'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $offlineGraceDays = (int) ($data['offline_grace_days'] ?? config('softkatta.offline_grace_days', 5));
        $verifyIntervalHours = (int) ($data['verify_interval_hours'] ?? config('softkatta.verify_interval_hours', 24));

        if ($companyApiUrl === '' || ! filter_var($companyApiUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('A valid SoftKatta Company API URL is required.');
        }
        // Allow blank on re-save when credentials already exist in .env / config.
        if ($publicApiKey === '') {
            $publicApiKey = trim((string) config('softkatta.public_api_key'));
        }
        if ($apiSecret === '') {
            $apiSecret = trim((string) config('softkatta.api_secret'));
        }
        if ($publicApiKey === '') {
            throw new RuntimeException('SoftKatta public API key is required.');
        }
        if ($apiSecret === '') {
            throw new RuntimeException('SoftKatta API secret is required.');
        }
        if ($productSlug === '') {
            throw new RuntimeException('Product slug is required.');
        }
        if ($appUrl === '' || ! filter_var($appUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('A valid application URL (APP_URL) is required for domain binding.');
        }

        $this->writeEnv([
            'APP_URL' => $appUrl,
            'SOFTKATTA_LICENSING_ENABLED' => 'true',
            'SOFTKATTA_COMPANY_API_URL' => $companyApiUrl,
            'SOFTKATTA_PUBLIC_API_KEY' => $publicApiKey,
            'SOFTKATTA_API_SECRET' => $apiSecret,
            'SOFTKATTA_PRODUCT_SLUG' => $productSlug,
            'SOFTKATTA_PRODUCT_VERSION' => $productVersion,
            'SOFTKATTA_OFFLINE_GRACE_DAYS' => (string) max(1, $offlineGraceDays),
            'SOFTKATTA_VERIFY_INTERVAL_HOURS' => (string) max(1, $verifyIntervalHours),
            'SOFTKATTA_TIMESTAMP_SKEW' => (string) ((int) config('softkatta.timestamp_skew_seconds', 300)),
            'SOFTKATTA_REQUIRE_HTTPS' => $requireHttps ? 'true' : 'false',
        ]);

        // Apply immediately in this process so a same-request activate would work.
        config([
            'app.url' => $appUrl,
            'softkatta.enabled' => true,
            'softkatta.company_api_url' => $companyApiUrl,
            'softkatta.public_api_key' => $publicApiKey,
            'softkatta.api_secret' => $apiSecret,
            'softkatta.product_slug' => $productSlug,
            'softkatta.product_version' => $productVersion,
            'softkatta.offline_grace_days' => max(1, $offlineGraceDays),
            'softkatta.verify_interval_hours' => max(1, $verifyIntervalHours),
            'softkatta.require_https' => $requireHttps,
        ]);

        Artisan::call('config:clear');

        return [
            'configured' => true,
            'company_api_url' => $companyApiUrl,
            'product_slug' => $productSlug,
            'product_version' => $productVersion,
            'app_url' => $appUrl,
            'domain' => $this->fingerprint->currentDomain(),
        ];
    }

    public function isCompanyApiConfigured(): bool
    {
        return filled(config('softkatta.company_api_url'))
            && filled(config('softkatta.public_api_key'))
            && filled(config('softkatta.api_secret'))
            && filled(config('softkatta.product_slug'));
    }

    private function maskSecret(string $value): string
    {
        if ($value === '') {
            return '';
        }
        if (strlen($value) <= 8) {
            return str_repeat('•', strlen($value));
        }

        return substr($value, 0, 4).str_repeat('•', max(4, strlen($value) - 8)).substr($value, -4);
    }

    public function createAdmin(CreatesAdminUser $creator, array $data): object
    {
        return $creator->create($data);
    }

    public function migrate(): array
    {
        Artisan::call('migrate', ['--force' => true]);

        return [
            'output' => Artisan::output(),
        ];
    }

    public function downloadConfiguration(): array
    {
        if (! $this->license->state()->install_token) {
            throw new RuntimeException('License must be activated before downloading configuration.');
        }

        return $this->license->configurationProfile();
    }

    public function complete(): array
    {
        if (! $this->license->state()->install_token) {
            throw new RuntimeException('License must be activated before completing installation.');
        }

        $this->license->markInstalled();

        return $this->status();
    }

    public function writeEnv(array $values): void
    {
        $path = base_path('.env');
        if (! File::exists($path)) {
            throw new RuntimeException('.env file not found.');
        }

        $content = File::get($path);
        foreach ($values as $key => $value) {
            $escaped = $this->envValue((string) $value);
            if (preg_match("/^{$key}=.*/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
            } else {
                $content .= "\n{$key}={$escaped}";
            }
        }
        File::put($path, $content);
    }

    private function envValue(string $value): string
    {
        if ($value === '' || preg_match('/\s|#|"|\'/', $value)) {
            return '"'.str_replace('"', '\"', $value).'"';
        }

        return $value;
    }
}
