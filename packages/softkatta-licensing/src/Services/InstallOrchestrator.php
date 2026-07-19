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
        $state = null;
        $installed = false;
        $hasLicense = false;
        $companyConfigured = false;

        try {
            $installed = $this->license->isInstalled();
            $state = \SoftKatta\Licensing\Models\LicenseState::query()->first();
            $hasToken = filled($state?->install_token);
            $hardBlocked = \SoftKatta\Licensing\Support\LicenseErrorCode::isHardFailure($state?->last_error_code);
            $companyConfigured = $this->isCompanyApiConfigured();
            // Local install_token alone is not enough — SoftKatta Product Integration credentials must exist.
            $hasLicense = $hasToken && ! $hardBlocked && $companyConfigured;
            if ($installed && $hasToken && ! $companyConfigured) {
                $state?->forceFill([
                    'last_error_code' => \SoftKatta\Licensing\Support\LicenseErrorCode::COMPANY_API_NOT_CONFIGURED,
                ])->save();
            }
        } catch (\Throwable) {
            // DB unavailable: lock file means install already finished — never reopen the wizard.
            $installed = File::exists(storage_path('app/installed'));
            $state = null;
            $hasLicense = false;
            $databaseUnavailable = $installed;
            $companyConfigured = false;
        }

        return [
            'installed' => $installed,
            'product_slug' => $this->resolveProductSlug(),
            'product_version' => config('softkatta.product_version'),
            'domain' => $this->resolvePublicDomain(),
            'fingerprint' => $this->fingerprint->generate(),
            'has_license' => $hasLicense,
            'needs_reactivation' => $installed && ! $hasLicense,
            'last_error_code' => $databaseUnavailable
                ? 'DATABASE_UNAVAILABLE'
                : (($installed && ! ($companyConfigured ?? $this->isCompanyApiConfigured()))
                    ? \SoftKatta\Licensing\Support\LicenseErrorCode::COMPANY_API_NOT_CONFIGURED
                    : $state?->last_error_code),
            'database_unavailable' => $databaseUnavailable,
            'bound_domain' => $state?->bound_domain,
            'company_api_configured' => $companyConfigured ?? $this->isCompanyApiConfigured(),
            'company_api' => [
                'company_api_url' => (string) config('softkatta.company_api_url'),
                // Public integration key is designed to be copied into the product — prefill Restore form.
                'public_api_key' => $publicKey,
                'api_secret_set' => $secret !== '',
                'product_slug' => $this->resolveProductSlug(),
                'product_version' => (string) config('softkatta.product_version'),
                'app_url' => (string) (
                    config('softkatta.frontend_url')
                    ?: config('app.frontend_url')
                    ?: config('app.url')
                ),
                'require_https' => (bool) config('softkatta.require_https'),
                'offline_grace_days' => (int) config('softkatta.offline_grace_days'),
                'verify_interval_hours' => (int) config('softkatta.verify_interval_hours'),
            ],
            'database' => [
                'host' => (string) config('database.connections.mysql.host', '127.0.0.1'),
                'port' => (string) config('database.connections.mysql.port', '3306'),
                'database' => (string) config('database.connections.mysql.database', ''),
                'username' => (string) config('database.connections.mysql.username', ''),
                // Never expose the password to the browser — operator re-enters it.
                'password_set' => filled(config('database.connections.mysql.password')),
            ],
            'configuration' => ($hasLicense && $state)
                ? $this->license->configurationProfile()
                : null,
            'entitlements' => $hasLicense ? $this->license->entitlements() : null,
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
        $canonical = 'study-point-management-software';
        $slug = trim((string) config('softkatta.product_slug'));

        if ($slug !== '' && str_contains($slug, 'study-point')) {
            return $slug;
        }

        // Heal leftover .env values from other SoftKatta products (e.g. kindergarten).
        if ($slug !== $canonical) {
            try {
                $this->writeEnv(['SOFTKATTA_PRODUCT_SLUG' => $canonical]);
                config(['softkatta.product_slug' => $canonical]);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate(base_path('.env'), true);
                }
            } catch (\Throwable) {
                // Read-only FS: still return the correct slug for this install response.
            }
        }

        return $canonical;
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
        $host = trim((string) ($data['host'] ?? 'localhost'));
        $port = trim((string) ($data['port'] ?? '3306')) ?: '3306';
        $database = trim((string) ($data['database'] ?? ''));
        $username = trim((string) ($data['username'] ?? ''));
        // Strip only CR/LF from paste; keep spaces inside the password.
        $password = preg_replace("/[\r\n]+/", '', (string) ($data['password'] ?? '')) ?? '';

        if ($database === '' || $username === '') {
            throw new RuntimeException('Database name and username are required.');
        }

        // Blank password = keep existing .env password (common on re-install).
        if ($password === '') {
            $password = (string) (config('database.connections.mysql.password') ?? '');
            if ($password === '') {
                $password = (string) $this->readEnvValue('DB_PASSWORD');
            }
        }

        if ($password === '') {
            throw new RuntimeException(
                'Database password is required. Paste the MySQL password from Hostinger hPanel '
                .'(Databases → MySQL → user ⋮ → Change password if unsure).'
            );
        }

        $hostsToTry = array_values(array_unique(array_filter([
            $host !== '' ? $host : 'localhost',
            'localhost',
            '127.0.0.1',
        ])));

        $lastError = null;
        $connectedHost = $hostsToTry[0];
        $connectedWithPort = true;

        foreach ($hostsToTry as $tryHost) {
            // Hostinger: localhost + explicit port often forces TCP and triggers 1045.
            // Try unix-socket style DSN (no port) first, then TCP with port.
            $dsnVariants = $this->isLocalMysqlHost($tryHost)
                ? [
                    "mysql:host={$tryHost};dbname={$database};charset=utf8mb4",
                    "mysql:host={$tryHost};port={$port};dbname={$database};charset=utf8mb4",
                ]
                : [
                    "mysql:host={$tryHost};port={$port};dbname={$database};charset=utf8mb4",
                ];

            foreach ($dsnVariants as $index => $dsn) {
                try {
                    $pdo = new PDO($dsn, $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 8,
                    ]);
                    $pdo->query('SELECT 1');
                    $connectedHost = $tryHost;
                    $connectedWithPort = ! ($this->isLocalMysqlHost($tryHost) && $index === 0);
                    $lastError = null;
                    break 2;
                } catch (\Throwable $e) {
                    $lastError = $e;
                }
            }
        }

        if ($lastError !== null) {
            $message = $lastError->getMessage();
            if (str_contains($message, '1045') || str_contains(strtolower($message), 'access denied')) {
                throw new RuntimeException(
                    'MySQL rejected this username/password (1045). '
                    .'Fix in Hostinger hPanel → Databases → MySQL: '
                    .'(1) user ⋮ → Change password, (2) paste the NEW password here (disable browser autofill), '
                    .'(3) confirm user "'.$username.'" is assigned to database "'.$database.'". '
                    .'Host should be localhost. Details: '.$message
                );
            }

            throw new RuntimeException('Database connection failed: '.$message);
        }

        $this->writeEnv([
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $connectedHost,
            'DB_PORT' => $port,
            'DB_DATABASE' => $database,
            'DB_USERNAME' => $username,
            'DB_PASSWORD' => $password,
        ]);

        // Apply immediately so later migrate/admin steps in this PHP process work.
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.host' => $connectedHost,
            'database.connections.mysql.port' => $port,
            'database.connections.mysql.database' => $database,
            'database.connections.mysql.username' => $username,
            'database.connections.mysql.password' => $password,
        ]);

        Artisan::call('config:clear');

        return [
            'connected' => true,
            'database' => $database,
            'host' => $connectedHost,
            'used_port_in_dsn' => $connectedWithPort,
        ];
    }

    private function isLocalMysqlHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * Read a raw value from .env without going through config cache.
     */
    private function readEnvValue(string $key): string
    {
        $path = base_path('.env');
        if (! File::exists($path)) {
            return '';
        }

        $content = File::get($path);
        if (! preg_match("/^{$key}=(.*)$/m", $content, $matches)) {
            return '';
        }

        $value = trim($matches[1]);
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
            $value = str_replace(['\\"', '\\$', '\\\\'], ['"', '$', '\\'], $value);
        }

        return $value;
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
        if ($productSlug === '' || ! str_contains($productSlug, 'study-point')) {
            $productSlug = 'study-point-management-software';
        }
        $productVersion = trim((string) ($data['product_version'] ?? '1.0.0')) ?: '1.0.0';
        $appUrl = rtrim((string) ($data['app_url'] ?? config('app.url')), '/');
        $requireHttps = filter_var($data['require_https'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $offlineGraceDays = (int) ($data['offline_grace_days'] ?? config('softkatta.offline_grace_days', 5));
        $verifyIntervalHours = max(0, (int) ($data['verify_interval_hours'] ?? config('softkatta.verify_interval_hours', 0)));
        // Runtime LicenseService caches by minutes; 0 hours => always re-check.
        $verifyIntervalMinutes = $verifyIntervalHours === 0
            ? 0
            : max(1, $verifyIntervalHours * 60);

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
        if (
            str_contains($publicApiKey, '@')
            || ! preg_match('/^sk_pub_[a-z0-9]+$/i', $publicApiKey)
        ) {
            throw new RuntimeException(
                'Public API Key must look like sk_pub_... from SoftKatta Admin → Product Integrations. Do not enter your SoftKatta login email.'
            );
        }
        if (
            str_contains($apiSecret, '@')
            || ! preg_match('/^sk_sec_[a-z0-9]+$/i', $apiSecret)
        ) {
            throw new RuntimeException(
                'API Secret must look like sk_sec_... from SoftKatta Admin → Product Integrations → Reveal. Do not enter your SoftKatta password.'
            );
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
            'SOFTKATTA_VERIFY_INTERVAL_HOURS' => (string) $verifyIntervalHours,
            'SOFTKATTA_VERIFY_INTERVAL_MINUTES' => (string) $verifyIntervalMinutes,
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
            'softkatta.verify_interval_hours' => $verifyIntervalHours,
            'softkatta.verify_interval_minutes' => $verifyIntervalMinutes,
            'softkatta.require_https' => $requireHttps,
        ]);

        Artisan::call('config:clear');

        // Credentials restored via UI — clear the hard block so verify/activate can proceed.
        try {
            $state = \SoftKatta\Licensing\Models\LicenseState::query()->first();
            if ($state && $state->last_error_code === \SoftKatta\Licensing\Support\LicenseErrorCode::COMPANY_API_NOT_CONFIGURED) {
                $state->forceFill(['last_error_code' => null])->save();
            }
        } catch (\Throwable) {
            // DB may be mid-install; ignore.
        }

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
        // Always double-quote and escape so $, #, spaces, quotes survive Dotenv parsing.
        return '"'.addcslashes($value, "\\\"$\n\r").'"';
    }
}
