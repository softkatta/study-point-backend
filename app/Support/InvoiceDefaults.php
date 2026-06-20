<?php

namespace App\Support;

class InvoiceDefaults
{
    public static function all(): array
    {
        return [
            'prefix' => 'INV',
            'next_number' => 1001,
            'number_padding' => 4,
            'template' => 'modern',
            'auto_generate_on_payment' => true,
            'show_gst_breakdown' => true,
            'include_branch_logo' => true,
            'default_due_days' => 7,
            'hsn_sac_code' => '999293',
            'payment_terms' => 'Payment is due within 7 days of invoice date. Late payments may attract interest at 1.5% per month.',
            'footer_note' => 'This is a computer-generated invoice and does not require a physical signature.',
            'send_email_on_generate' => true,
            'send_whatsapp_on_generate' => false,
            'show_bank_details' => true,
            'bank_name' => '',
            'bank_account' => '',
            'bank_ifsc' => '',
        ];
    }

    public static function merge(array $stored): array
    {
        return self::normalize(array_merge(self::all(), $stored));
    }

    public static function normalize(array $data): array
    {
        $defaults = self::all();
        $template = $data['template'] ?? $defaults['template'];

        return [
            'prefix' => (string) ($data['prefix'] ?? $defaults['prefix']),
            'next_number' => max(1, (int) ($data['next_number'] ?? $defaults['next_number'])),
            'number_padding' => max(1, min(8, (int) ($data['number_padding'] ?? $defaults['number_padding']))),
            'template' => in_array($template, ['classic', 'modern', 'minimal'], true) ? $template : $defaults['template'],
            'auto_generate_on_payment' => self::toBool($data['auto_generate_on_payment'] ?? $defaults['auto_generate_on_payment']),
            'show_gst_breakdown' => self::toBool($data['show_gst_breakdown'] ?? $defaults['show_gst_breakdown']),
            'include_branch_logo' => self::toBool($data['include_branch_logo'] ?? $defaults['include_branch_logo']),
            'default_due_days' => max(0, (int) ($data['default_due_days'] ?? $defaults['default_due_days'])),
            'hsn_sac_code' => (string) ($data['hsn_sac_code'] ?? $defaults['hsn_sac_code']),
            'payment_terms' => (string) ($data['payment_terms'] ?? $defaults['payment_terms']),
            'footer_note' => (string) ($data['footer_note'] ?? $defaults['footer_note']),
            'send_email_on_generate' => self::toBool($data['send_email_on_generate'] ?? $defaults['send_email_on_generate']),
            'send_whatsapp_on_generate' => self::toBool($data['send_whatsapp_on_generate'] ?? $defaults['send_whatsapp_on_generate']),
            'show_bank_details' => self::toBool($data['show_bank_details'] ?? $defaults['show_bank_details']),
            'bank_name' => (string) ($data['bank_name'] ?? $defaults['bank_name']),
            'bank_account' => (string) ($data['bank_account'] ?? $defaults['bank_account']),
            'bank_ifsc' => (string) ($data['bank_ifsc'] ?? $defaults['bank_ifsc']),
        ];
    }

    private static function toBool(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }

    public static function validationRules(): array
    {
        return [
            'prefix' => ['nullable', 'string', 'max:20'],
            'next_number' => ['nullable', 'integer', 'min:1'],
            'number_padding' => ['nullable', 'integer', 'min:1', 'max:8'],
            'template' => ['nullable', 'in:classic,modern,minimal'],
            'auto_generate_on_payment' => ['nullable', 'boolean'],
            'show_gst_breakdown' => ['nullable', 'boolean'],
            'include_branch_logo' => ['nullable', 'boolean'],
            'default_due_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'hsn_sac_code' => ['nullable', 'string', 'max:20'],
            'payment_terms' => ['nullable', 'string', 'max:1000'],
            'footer_note' => ['nullable', 'string', 'max:500'],
            'send_email_on_generate' => ['nullable', 'boolean'],
            'send_whatsapp_on_generate' => ['nullable', 'boolean'],
            'show_bank_details' => ['nullable', 'boolean'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:30'],
            'bank_ifsc' => ['nullable', 'string', 'max:15'],
        ];
    }
}
