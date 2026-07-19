<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use App\Support\AppearanceDefaults;
use App\Support\AttendanceDefaults;
use App\Support\CompanyDefaults;
use App\Support\BiometricDefaults;
use App\Support\MailDefaults;
use App\Support\GstDefaults;
use App\Services\HeadOfficeService;
use App\Support\InvoiceDefaults;
use App\Support\PaymentDefaults;
use App\Support\SecurityDefaults;
use App\Support\WhatsAppDefaults;
use App\Support\GeneralDefaults;
use App\Support\HomepageHeroDefaults;
use App\Support\TopBarDefaults;
use App\Support\MediaUrl;
use App\Services\AppSettingsService;
use App\Services\AuditService;
use App\Services\BiometricService;
use App\Services\BrandingPdfCacheService;
use App\Services\MailSenderService;
use App\Services\PaymentGatewayService;
use App\Services\SecurityPolicyService;
use App\Services\WhatsAppSenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    public function __construct(
        private SecurityPolicyService $security,
        private AppSettingsService $appSettings,
        private AuditService $audit,
    ) {}

    public function publicAppearance(): JsonResponse
    {
        return ApiResponse::success($this->appearanceData());
    }

    public function publicPlatform(): JsonResponse
    {
        return ApiResponse::success($this->appSettings->publicPlatform());
    }

    public function publicSecurity(): JsonResponse
    {
        return ApiResponse::success($this->security->publicConfig());
    }

    public function show(string $section): JsonResponse
    {
        if ($section === 'appearance') {
            return ApiResponse::success($this->appearanceData());
        }

        if ($section === 'company') {
            return ApiResponse::success(app(HeadOfficeService::class)->companyProfile());
        }

        if ($section === 'general') {
            return ApiResponse::success(app(HeadOfficeService::class)->generalProfile());
        }

        if ($section === 'mail') {
            return ApiResponse::success(MailDefaults::merge(Setting::getSection('mail')));
        }

        if ($section === 'whatsapp') {
            return ApiResponse::success(WhatsAppDefaults::configFromSection(Setting::getSection('whatsapp')));
        }

        if ($section === 'biometric') {
            return ApiResponse::success(BiometricDefaults::merge(Setting::getSection('biometric')));
        }

        if ($section === 'payment') {
            return ApiResponse::success(PaymentDefaults::merge(Setting::getSection('payment')));
        }

        if ($section === 'invoice') {
            return ApiResponse::success(InvoiceDefaults::merge(Setting::getSection('invoice')));
        }

        if ($section === 'security') {
            return ApiResponse::success(SecurityDefaults::merge(Setting::getSection('security')));
        }

        if ($section === 'gst') {
            return ApiResponse::success(app(HeadOfficeService::class)->gstProfile());
        }

        if ($section === 'homepage_hero') {
            return ApiResponse::success(HomepageHeroDefaults::merge(Setting::getSection('homepage_hero')));
        }

        if ($section === 'public_top_bar') {
            return ApiResponse::success(TopBarDefaults::merge(Setting::getSection('public_top_bar')));
        }

        if ($section === 'attendance') {
            return ApiResponse::success(AttendanceDefaults::merge(Setting::getSection('attendance')));
        }

        return ApiResponse::success(Setting::getSection($section));
    }

    public function save(Request $request, string $section): JsonResponse
    {
        if ($section === 'appearance') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = $request->validate([
                'mode' => ['nullable', 'in:light,dark,system'],
                'fontFamily' => ['nullable', 'string', 'max:80'],
                'borderRadius' => ['nullable', 'in:none,sm,md,lg,xl'],
                'colors' => ['nullable', 'array'],
                'colors.primary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'colors.secondary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'colors.accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'colors.success' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'colors.warning' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'colors.error' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'announcements' => ['nullable', 'array'],
                'announcements.*.id' => ['required_with:announcements', 'string', 'max:100'],
                'announcements.*.text' => ['required_with:announcements', 'string', 'max:500'],
                'announcements.*.ctaLabel' => ['nullable', 'string', 'max:80'],
                'announcements.*.ctaHref' => ['nullable', 'string', 'max:200'],
                'announcements.*.gradient' => ['nullable', 'string', 'max:120'],
                'announcements.*.startDate' => ['nullable', 'string', 'max:20'],
                'announcements.*.endDate' => ['nullable', 'string', 'max:20'],
                'announcements.*.icon' => ['nullable', 'string', 'max:20'],
                'announcements.*.priority' => ['nullable', 'integer', 'min:1'],
                'announcements.*.active' => ['nullable', 'boolean'],
                'logo_url' => ['nullable', 'string', 'max:500'],
                'favicon_url' => ['nullable', 'string', 'max:500'],
                'id_card_accent_image_url' => ['nullable', 'string', 'max:500'],
                'id_card_back_image_url' => ['nullable', 'string', 'max:500'],
                'site_name' => ['nullable', 'string', 'max:100'],
                'site_tagline' => ['nullable', 'string', 'max:150'],
                'footer_copyright_text' => ['nullable', 'string', 'max:250'],
                'footer_developed_by_text' => ['nullable', 'string', 'max:120'],
                'footer_developed_by_url' => ['nullable', 'string', 'max:300'],
                'announcement_bar_visible' => ['nullable', 'boolean'],
                'meta' => ['nullable', 'array'],
                'meta.default_title' => ['nullable', 'string', 'max:120'],
                'meta.default_description' => ['nullable', 'string', 'max:300'],
                'meta.keywords' => ['nullable', 'string', 'max:500'],
                'meta.author' => ['nullable', 'string', 'max:100'],
                'meta.og_image' => ['nullable', 'string', 'max:500'],
                'meta.twitter_card' => ['nullable', 'in:summary,summary_large_image'],
                'meta.robots' => ['nullable', 'string', 'max:50'],
                'meta.pages' => ['nullable', 'array'],
                'meta.pages.*.title' => ['nullable', 'string', 'max:120'],
                'meta.pages.*.description' => ['nullable', 'string', 'max:300'],
                'policies' => ['nullable', 'array'],
                'policies.*.effective_date' => ['nullable', 'string', 'max:80'],
                'policies.*.sections' => ['nullable', 'array'],
                'policies.*.sections.*.title' => ['required_with:policies.*.sections', 'string', 'max:150'],
                'policies.*.sections.*.body' => ['required_with:policies.*.sections', 'array', 'min:1'],
                'policies.*.sections.*.body.*' => ['string', 'max:1000'],
            ]);

            Setting::saveSection('appearance', $data);

            return ApiResponse::success($this->appearanceData(), 'Appearance settings saved');
        }

        if ($section === 'company' || $section === 'gst') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            return ApiResponse::error('Company and GST details are managed in Head Office settings.', 422);
        }

        if ($section === 'general') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            return ApiResponse::error('Platform settings are managed in Head Office settings.', 422);
        }

        if ($section === 'mail') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = $request->validate(MailDefaults::validationRules());
            $existing = Setting::getSection('mail');

            foreach (['smtp_password', 'brevo_api_key', 'resend_api_key', 'ms_client_secret', 'gmail_client_secret', 'gmail_refresh_token'] as $secret) {
                if (array_key_exists($secret, $data) && ($data[$secret] === '' || str_contains((string) $data[$secret], '••••'))) {
                    $data[$secret] = $existing[$secret] ?? '';
                }
            }

            Setting::saveSection('mail', $data);

            return ApiResponse::success(MailDefaults::merge($data), 'Mail settings saved');
        }

        if ($section === 'whatsapp') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $request->merge(WhatsAppDefaults::normalizePayload($request->only(array_keys(WhatsAppDefaults::all()))));

            $data = $request->validate(WhatsAppDefaults::validationRules());
            $existing = Setting::getSection('whatsapp');

            $data = $this->mergeWhatsAppSecrets($data, $existing);

            $payload = $data;
            if (array_key_exists('templates', $existing)) {
                $payload['templates'] = $existing['templates'];
            }

            Setting::saveSection('whatsapp', $payload);

            return ApiResponse::success(WhatsAppDefaults::merge($data), 'WhatsApp settings saved');
        }

        if ($section === 'biometric') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = $request->validate(BiometricDefaults::validationRules());
            $existing = Setting::getSection('biometric');

            foreach (['smartoffice_api_key', 'zkteco_comm_key', 'essl_api_key', 'hikvision_app_secret', 'custom_api_key'] as $secret) {
                if (array_key_exists($secret, $data) && ($data[$secret] === '' || str_contains((string) $data[$secret], '••••'))) {
                    $data[$secret] = $existing[$secret] ?? '';
                }
            }

            Setting::saveSection('biometric', $data);

            return ApiResponse::success(BiometricDefaults::merge($data), 'Biometric settings saved');
        }

        if ($section === 'payment') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = $request->validate(PaymentDefaults::validationRules());
            $existing = Setting::getSection('payment');

            foreach (['razorpay_key_secret', 'razorpay_webhook_secret', 'stripe_secret_key', 'stripe_webhook_secret', 'payu_merchant_salt', 'cashfree_secret_key', 'phonepe_salt_key'] as $secret) {
                if (array_key_exists($secret, $data) && ($data[$secret] === '' || str_contains((string) $data[$secret], '••••'))) {
                    $data[$secret] = $existing[$secret] ?? '';
                }
            }

            Setting::saveSection('payment', $data);

            return ApiResponse::success(PaymentDefaults::merge($data), 'Payment gateway settings saved');
        }

        if ($section === 'invoice') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = InvoiceDefaults::normalize($request->all());
            $data = validator($data, InvoiceDefaults::validationRules())->validate();
            Setting::saveSection('invoice', $data);

            return ApiResponse::success(InvoiceDefaults::merge($data), 'Invoice settings saved');
        }

        if ($section === 'security') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = $request->validate(SecurityDefaults::validationRules());
            Setting::saveSection('security', $data);

            $purged = $this->audit->purgeExpired();

            return ApiResponse::success([
                ...SecurityDefaults::merge($data),
                'audit_purged' => $purged,
            ], 'Security settings saved');
        }

        if ($section === 'homepage_hero') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = $request->validate(HomepageHeroDefaults::validationRules());
            Setting::saveSection('homepage_hero', $data);

            return ApiResponse::success(HomepageHeroDefaults::merge($data), 'Homepage hero content saved');
        }

        if ($section === 'public_top_bar') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = $request->validate(TopBarDefaults::validationRules());
            Setting::saveSection('public_top_bar', $data);

            return ApiResponse::success(TopBarDefaults::merge($data), 'Top bar content saved');
        }

        if ($section === 'attendance') {
            if ($denied = $this->requireSuperAdmin($request)) {
                return $denied;
            }

            $data = $request->validate([
                'attendance_start' => ['required', 'string', 'max:10'],
                'late_after' => ['required', 'string', 'max:10'],
                'half_day_after' => ['nullable', 'string', 'max:10'],
                'allow_duplicate_scan' => ['sometimes', 'boolean'],
            ]);
            Setting::saveSection('attendance', $data);

            return ApiResponse::success(AttendanceDefaults::merge($data), 'Attendance settings saved');
        }

        Setting::saveSection($section, $request->all());

        return ApiResponse::success(Setting::getSection($section), 'Settings saved');
    }

    public function reset(string $section): JsonResponse
    {
        if ($section === 'appearance') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'appearance')->delete();

            return ApiResponse::success(AppearanceDefaults::all(), 'Appearance reset to defaults');
        }

        if ($section === 'company' || $section === 'gst') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            return ApiResponse::error('Company and GST details are managed in Head Office settings.', 422);
        }

        if ($section === 'general') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            return ApiResponse::error('Platform settings are managed in Head Office settings.', 422);
        }

        if ($section === 'mail') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'mail')->delete();

            return ApiResponse::success(MailDefaults::all(), 'Mail settings reset to defaults');
        }

        if ($section === 'whatsapp') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            $existing = Setting::getSection('whatsapp');
            $templates = $existing['templates'] ?? null;

            Setting::where('section', 'whatsapp')->delete();

            if ($templates !== null) {
                Setting::saveSection('whatsapp', ['templates' => $templates]);
            }

            return ApiResponse::success(WhatsAppDefaults::all(), 'WhatsApp settings reset to defaults');
        }

        if ($section === 'biometric') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'biometric')->delete();

            return ApiResponse::success(BiometricDefaults::all(), 'Biometric settings reset to defaults');
        }

        if ($section === 'payment') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'payment')->delete();

            return ApiResponse::success(PaymentDefaults::all(), 'Payment gateway settings reset to defaults');
        }

        if ($section === 'invoice') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'invoice')->delete();

            return ApiResponse::success(InvoiceDefaults::all(), 'Invoice settings reset to defaults');
        }

        if ($section === 'attendance') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'attendance')->delete();

            return ApiResponse::success(AttendanceDefaults::all(), 'Attendance settings reset to defaults');
        }

        if ($section === 'security') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'security')->delete();

            return ApiResponse::success(SecurityDefaults::all(), 'Security settings reset to defaults');
        }

        if ($section === 'homepage_hero') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'homepage_hero')->delete();

            return ApiResponse::success(HomepageHeroDefaults::all(), 'Homepage hero content reset to defaults');
        }

        if ($section === 'public_top_bar') {
            if ($denied = $this->requireSuperAdmin(request())) {
                return $denied;
            }

            Setting::where('section', 'public_top_bar')->delete();

            return ApiResponse::success(TopBarDefaults::all(), 'Top bar content reset to defaults');
        }

        Setting::where('section', $section)->delete();

        return ApiResponse::success([], 'Settings reset');
    }

    public function testSmtp(Request $request): JsonResponse
    {
        return $this->testMail($request);
    }

    public function testMail(Request $request, MailSenderService $mailer): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        $request->validate(['test_email' => ['required', 'email']]);

        try {
            $mailer->sendTest($request->input('test_email'));

            return ApiResponse::success([
                'ok' => true,
                'provider' => $mailer->config()['provider'] ?? 'smtp',
            ], 'Test email sent successfully');
        } catch (\Throwable $e) {
            return ApiResponse::error('Mail test failed: '.$e->getMessage(), 422);
        }
    }

    public function testGateway(Request $request, PaymentGatewayService $gateway): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        try {
            $result = $gateway->testConnection();

            return ApiResponse::success($result, 'Payment gateway connected successfully');
        } catch (\Throwable $e) {
            return ApiResponse::error('Payment gateway test failed: '.$e->getMessage(), 422);
        }
    }

    public function testBiometric(Request $request, BiometricService $biometric): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        try {
            $result = $biometric->testConnection();

            return ApiResponse::success($result, 'Biometric API connected successfully');
        } catch (\Throwable $e) {
            return ApiResponse::error('Biometric test failed: '.$e->getMessage(), 422);
        }
    }

    public function testWhatsApp(Request $request, WhatsAppSenderService $whatsapp): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        $request->merge(WhatsAppDefaults::normalizePayload($request->only(array_keys(WhatsAppDefaults::all()))));

        $waRules = [];
        foreach (WhatsAppDefaults::validationRules() as $key => $rules) {
            $waRules[$key] = array_merge(
                ['sometimes'],
                array_values(array_filter($rules, fn ($rule) => $rule !== 'required'))
            );
        }

        $request->validate(array_merge(
            ['test_phone' => ['required', 'string', 'max:20']],
            $waRules
        ));

        $configOverrides = $this->whatsappConfigFromRequest($request);

        try {
            $whatsapp->sendTest($request->input('test_phone'), $configOverrides);
            $config = $whatsapp->config($configOverrides);

            return ApiResponse::success([
                'ok' => true,
                'provider' => $config['provider'] ?? 'interakt',
            ], 'Test WhatsApp message sent successfully');
        } catch (\Throwable $e) {
            return ApiResponse::error('WhatsApp test failed: '.$e->getMessage(), 422);
        }
    }

    /** @return array<string, mixed> */
    private function whatsappConfigFromRequest(Request $request): array
    {
        $existing = Setting::getSection('whatsapp');
        $data = $request->only(array_keys(WhatsAppDefaults::all()));

        return $this->mergeWhatsAppSecrets($data, $existing);
    }

    /** @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $existing
     * @return array<string, mixed>
     */
    private function mergeWhatsAppSecrets(array $data, array $existing): array
    {
        foreach (['interakt_api_key', 'meta_access_token', 'gupshup_api_key', 'wati_access_token', 'twilio_auth_token', 'aisensy_api_key'] as $secret) {
            if (array_key_exists($secret, $data) && ($data[$secret] === '' || str_contains((string) $data[$secret], '••••'))) {
                $data[$secret] = $existing[$secret] ?? '';
            }
        }

        return $data;
    }

    public function uploadLogo(Request $request, BrandingPdfCacheService $brandingPdf): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        $request->validate(['logo' => ['required', 'image', 'max:2048']]);
        $path = $request->file('logo')->store('branding', 'public');
        $url = MediaUrl::absolute(Storage::disk('public')->url($path));

        Setting::saveSection('appearance', ['logo_url' => $url]);
        $brandingPdf->warmLogoCache($url);

        return ApiResponse::success(['url' => $url], 'Logo uploaded');
    }

    public function uploadFavicon(Request $request): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        $request->validate(['favicon' => ['required', 'image', 'max:512']]);
        $path = $request->file('favicon')->store('branding', 'public');
        $url = MediaUrl::absolute(Storage::disk('public')->url($path));

        Setting::saveSection('appearance', ['favicon_url' => $url]);

        return ApiResponse::success(['url' => $url], 'Favicon uploaded');
    }

    public function uploadOgImage(Request $request): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        $request->validate(['og_image' => ['required', 'image', 'max:3072']]);
        $path = $request->file('og_image')->store('branding', 'public');
        $url = MediaUrl::absolute(Storage::disk('public')->url($path));

        $current = Setting::getSection('appearance');
        $meta = is_array($current['meta'] ?? null) ? $current['meta'] : AppearanceDefaults::meta();
        $meta['og_image'] = $url;
        Setting::saveSection('appearance', ['meta' => $meta]);

        return ApiResponse::success(['url' => $url], 'OG image uploaded');
    }

    public function uploadIdCardAccentImage(Request $request): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        $request->validate(['id_card_accent_image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048']]);
        $path = $request->file('id_card_accent_image')->store('branding', 'public');
        $url = MediaUrl::absolute(Storage::disk('public')->url($path));

        Setting::saveSection('appearance', ['id_card_accent_image_url' => $url]);

        return ApiResponse::success(['url' => $url], 'ID card accent image uploaded');
    }

    public function uploadIdCardBackImage(Request $request): JsonResponse
    {
        if ($denied = $this->requireSuperAdmin($request)) {
            return $denied;
        }

        $request->validate(['id_card_back_image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:2048']]);
        $path = $request->file('id_card_back_image')->store('branding', 'public');
        $url = MediaUrl::absolute(Storage::disk('public')->url($path));

        Setting::saveSection('appearance', ['id_card_back_image_url' => $url]);

        return ApiResponse::success(['url' => $url], 'ID card back image uploaded');
    }

    private function appearanceData(): array
    {
        try {
            $data = AppearanceDefaults::merge(Setting::getSection('appearance'));
        } catch (\Throwable) {
            $data = AppearanceDefaults::merge([]);
        }

        try {
            foreach (['logo_url', 'favicon_url', 'id_card_accent_image_url', 'id_card_back_image_url'] as $key) {
                if (is_string($data[$key] ?? null) && $data[$key] !== '') {
                    $data[$key] = MediaUrl::absolute($data[$key]);
                } else {
                    $data[$key] = is_string($data[$key] ?? null) ? $data[$key] : null;
                }
            }
            if (is_array($data['meta'] ?? null) && is_string($data['meta']['og_image'] ?? null) && $data['meta']['og_image'] !== '') {
                $data['meta']['og_image'] = MediaUrl::absolute($data['meta']['og_image']);
            }
        } catch (\Throwable) {
            // Corrupt media URLs must not 500 public branding.
        }

        return $data;
    }

    private function requireSuperAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('super_admin')) {
            return ApiResponse::error('Only super admin can manage appearance settings.', 403);
        }

        return null;
    }
}
