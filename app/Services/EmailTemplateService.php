<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\AppearanceDefaults;
use App\Support\MediaUrl;

class EmailTemplateService
{
    public function __construct(
        private AppSettingsService $settings,
    ) {}

    public function render(array $data): string
    {
        $company = $this->settings->company();
        $general = $this->settings->general();
        $mail = $this->settings->mail();
        $appearance = AppearanceDefaults::merge(Setting::getSection('appearance'));
        $colors = $appearance['colors'] ?? AppearanceDefaults::colors();
        $siteName = $appearance['site_name'] ?? 'StudyPoint';
        $primaryColor = $colors['primary'] ?? '#3b5bdb';
        $secondaryColor = $colors['secondary'] ?? '#6366f1';

        return view('emails.studypoint', [
            'title' => $data['title'] ?? 'StudyPoint',
            'eyebrow' => $data['eyebrow'] ?? $data['badge'] ?? null,
            'paragraphs' => $data['paragraphs'] ?? [],
            'details' => $data['details'] ?? [],
            'ctaLabel' => $data['cta_label'] ?? $data['ctaLabel'] ?? null,
            'ctaUrl' => $data['cta_url'] ?? $data['ctaUrl'] ?? null,
            'preheader' => $data['preheader'] ?? null,
            'viewOnlineUrl' => $data['view_online_url'] ?? $data['viewOnlineUrl'] ?? $this->frontendUrl(),
            'siteName' => $siteName,
            'siteTagline' => $appearance['site_tagline'] ?? 'Study Library',
            'logoUrl' => MediaUrl::absolute($appearance['logo_url'] ?? null),
            'company' => $company,
            'general' => $general,
            'fromName' => $mail['from_name'] ?? 'StudyPoint',
            'signatureName' => $data['signature_name'] ?? $data['signatureName'] ?? ($mail['from_name'] ?? $siteName.' Team'),
            'signatureRole' => $data['signature_role'] ?? $data['signatureRole'] ?? ($appearance['site_tagline'] ?? 'Study Library'),
            'primaryColor' => $primaryColor,
            'secondaryColor' => $secondaryColor,
            'accentColor' => $primaryColor,
            'badgeBackground' => MediaUrl::softTint($primaryColor, 0.12),
            'backgroundColor' => MediaUrl::softBackground($primaryColor, 0.14),
            'initials' => $this->initials($siteName),
            'year' => date('Y'),
            'frontendUrl' => $this->frontendUrl(),
            'supportEmail' => $general['support_email'] ?? $company['email'] ?? '',
            'supportPhone' => $company['phone'] ?? $general['support_phone'] ?? null,
        ])->render();
    }

    /** @return list<string> */
    public function paragraphsFromText(string $text): array
    {
        $chunks = preg_split('/\R\s*\R/', trim($text)) ?: [];
        $paragraphs = array_values(array_filter(array_map('trim', $chunks)));

        if ($paragraphs !== []) {
            return $paragraphs;
        }

        $lines = preg_split('/\R/', trim($text)) ?: [];

        return array_values(array_filter(array_map('trim', $lines))) ?: [''];
    }

    public function excerpt(string $text, int $length = 140): string
    {
        $plain = trim(preg_replace('/\s+/', ' ', $text) ?? '');

        if (strlen($plain) <= $length) {
            return $plain;
        }

        return substr($plain, 0, $length - 3).'...';
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';

        foreach ($parts as $part) {
            if ($part !== '') {
                $letters .= strtoupper($part[0]);
            }
            if (strlen($letters) >= 2) {
                break;
            }
        }

        return $letters !== '' ? $letters : 'SP';
    }

    private function frontendUrl(): string
    {
        return rtrim((string) (env('FRONTEND_URL') ?: config('app.url')), '/');
    }
}
