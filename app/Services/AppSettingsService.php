<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\AppearanceDefaults;
use App\Support\MediaUrl;
use App\Support\BiometricDefaults;
use App\Support\CompanyDefaults;
use App\Support\GeneralDefaults;
use App\Support\GstDefaults;
use App\Support\InvoiceDefaults;
use App\Support\MailDefaults;
use App\Support\PaymentDefaults;
use App\Support\SecurityDefaults;
use App\Support\WhatsAppDefaults;

class AppSettingsService
{
    public function __construct(private HeadOfficeService $headOffice) {}

    public function general(): array
    {
        return $this->headOffice->generalProfile();
    }

    public function company(): array
    {
        return $this->headOffice->companyProfile();
    }

    public function gst(): array
    {
        return $this->headOffice->gstProfile();
    }

    public function invoice(): array
    {
        return InvoiceDefaults::merge(Setting::getSection('invoice'));
    }

    public function payment(): array
    {
        return PaymentDefaults::merge(Setting::getSection('payment'));
    }

    public function biometric(): array
    {
        return BiometricDefaults::merge(Setting::getSection('biometric'));
    }

    public function mail(): array
    {
        return MailDefaults::merge(Setting::getSection('mail'));
    }

    public function whatsapp(): array
    {
        return WhatsAppDefaults::configFromSection(Setting::getSection('whatsapp'));
    }

    public function security(): array
    {
        return SecurityDefaults::merge(Setting::getSection('security'));
    }

    public function gstRate(): float
    {
        return (float) ($this->gst()['gst_rate'] ?? 18);
    }

    public function calculateGst(float $amount): array
    {
        $rate = $this->gstRate();
        $gstAmount = round($amount * ($rate / 100), 2);

        return [
            'gst_rate' => $rate,
            'gst_amount' => $gstAmount,
            'total' => round($amount + $gstAmount, 2),
            'cgst_rate' => (float) ($this->gst()['cgst_rate'] ?? $rate / 2),
            'sgst_rate' => (float) ($this->gst()['sgst_rate'] ?? $rate / 2),
            'igst_rate' => (float) ($this->gst()['igst_rate'] ?? $rate),
        ];
    }

    public function publicPlatform(): array
    {
        try {
            $general = $this->general();
            $gst = $this->gst();
            $company = $this->company();
            $payment = $this->payment();

            return [
                'support_email' => $general['support_email'],
                'support_phone' => $general['support_phone'],
                'timezone' => $general['timezone'],
                'currency' => $general['currency'],
                'currency_symbol' => $general['currency_symbol'],
                'gst_rate' => (float) $gst['gst_rate'],
                'gstin' => $gst['gstin'],
                'company_name' => $company['trade_name'] ?: $company['legal_name'],
                'company_phone' => $company['phone'],
                'company_email' => $company['email'],
                'payment_gateway' => [
                    'enabled' => (bool) ($payment['enabled'] ?? false),
                    'provider' => $payment['provider'] ?? 'razorpay',
                    'test_mode' => (bool) ($payment['test_mode'] ?? true),
                ],
                'biometric' => [
                    'enabled' => (bool) ($this->biometric()['enabled'] ?? false),
                    'provider' => $this->biometric()['provider'] ?? 'manual',
                ],
                'student_self_register' => (bool) ($this->security()['allow_student_self_register'] ?? false),
                'require_2fa_admins' => (bool) ($this->security()['require_2fa_admins'] ?? false),
            ];
        } catch (\Throwable) {
            $general = GeneralDefaults::all();
            $gst = GstDefaults::all();
            $company = CompanyDefaults::all();
            $payment = PaymentDefaults::all();
            $biometric = BiometricDefaults::all();
            $security = SecurityDefaults::all();

            return [
                'support_email' => $general['support_email'],
                'support_phone' => $general['support_phone'],
                'timezone' => $general['timezone'],
                'currency' => $general['currency'],
                'currency_symbol' => $general['currency_symbol'],
                'gst_rate' => (float) ($gst['gst_rate'] ?? 18),
                'gstin' => $gst['gstin'] ?? '',
                'company_name' => $company['trade_name'] ?: ($company['legal_name'] ?? 'StudyPoint'),
                'company_phone' => $company['phone'] ?? '',
                'company_email' => $company['email'] ?? '',
                'payment_gateway' => [
                    'enabled' => (bool) ($payment['enabled'] ?? false),
                    'provider' => $payment['provider'] ?? 'razorpay',
                    'test_mode' => (bool) ($payment['test_mode'] ?? true),
                ],
                'biometric' => [
                    'enabled' => (bool) ($biometric['enabled'] ?? false),
                    'provider' => $biometric['provider'] ?? 'manual',
                ],
                'student_self_register' => (bool) ($security['allow_student_self_register'] ?? false),
                'require_2fa_admins' => (bool) ($security['require_2fa_admins'] ?? false),
            ];
        }
    }

    public function invoiceDocumentMeta(): array
    {
        $invoice = $this->invoice();
        $company = $this->company();
        $gst = $this->gst();
        $appearance = AppearanceDefaults::merge(Setting::getSection('appearance'));

        return [
            'company' => $company,
            'gst' => $gst,
            'branding' => [
                'site_name' => $appearance['site_name'] ?? 'StudyPoint',
                'logo_url' => MediaUrl::absolute($appearance['logo_url'] ?? null),
            ],
            'invoice' => [
                'template' => $invoice['template'] ?? 'modern',
                'payment_terms' => $invoice['payment_terms'],
                'footer_note' => $invoice['footer_note'],
                'hsn_sac_code' => $invoice['hsn_sac_code'],
                'show_gst_breakdown' => (bool) $invoice['show_gst_breakdown'],
                'include_branch_logo' => (bool) $invoice['include_branch_logo'],
                'show_bank_details' => (bool) $invoice['show_bank_details'],
                'bank_name' => $invoice['bank_name'],
                'bank_account' => $invoice['bank_account'],
                'bank_ifsc' => $invoice['bank_ifsc'],
            ],
        ];
    }
}
