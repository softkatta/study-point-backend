<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'id' => 'TPL-OTP-001',
                'name' => 'OTP Verification',
                'meta_template_name' => 'studypoint_otp',
                'body' => 'Your StudyPoint OTP is {{otp_code}}. Do not share this code with anyone.',
                'variables' => '{{otp_code}}',
                'category' => 'otp',
                'status' => 'active',
            ],
            [
                'id' => 'TPL-PAYMENT-001',
                'name' => 'Payment Success',
                'meta_template_name' => 'studypoint_payment_success',
                'body' => 'Hi {{customer_name}}, your payment receipt {{payment_code}} of ₹{{amount}} has been received successfully.',
                'variables' => '{{customer_name}}, {{payment_code}}, {{amount}}',
                'category' => 'payment',
                'status' => 'active',
            ],
            [
                'id' => 'TPL-ORDER-001',
                'name' => 'Admission/Order Confirmation',
                'meta_template_name' => 'studypoint_order_confirmation',
                'body' => 'Hi {{customer_name}}, your admission/order {{admission_code}} for {{plan}} has been confirmed.',
                'variables' => '{{customer_name}}, {{admission_code}}, {{plan}}',
                'category' => 'admission',
                'status' => 'active',
            ],
            [
                'id' => 'TPL-INVOICE-001',
                'name' => 'Invoice Notification',
                'meta_template_name' => 'studypoint_invoice',
                'body' => 'Hi {{customer_name}}, invoice {{invoice_number}} for ₹{{total}} has been generated.',
                'variables' => '{{customer_name}}, {{invoice_number}}, {{total}}',
                'category' => 'invoice',
                'status' => 'active',
            ],
            [
                'id' => 'TPL-RENEWAL-7D',
                'name' => 'Renewal Reminder (7 days)',
                'meta_template_name' => 'studypoint_renewal_7d',
                'body' => 'Hi {{customer_name}}, your StudyPoint membership for {{plan}} expires on {{renewal_date}}. Renew now to continue access.',
                'variables' => '{{customer_name}}, {{plan}}, {{renewal_date}}',
                'category' => 'renewal',
                'status' => 'active',
            ],
            [
                'id' => 'TPL-RENEWAL-1D',
                'name' => 'Renewal Reminder (1 day)',
                'meta_template_name' => 'studypoint_renewal_1d',
                'body' => 'Hi {{customer_name}}, your StudyPoint membership expires tomorrow ({{renewal_date}}). Renew today to avoid disruption.',
                'variables' => '{{customer_name}}, {{renewal_date}}',
                'category' => 'renewal',
                'status' => 'active',
            ],
            [
                'id' => 'TPL-ATTENDANCE-001',
                'name' => 'Attendance Alert',
                'meta_template_name' => 'studypoint_attendance',
                'body' => 'Hi {{customer_name}}, your attendance has been recorded as {{status}} at {{timestamp}}.',
                'variables' => '{{customer_name}}, {{status}}, {{timestamp}}',
                'category' => 'attendance',
                'status' => 'active',
            ],
        ];

        Setting::saveSection('whatsapp', ['templates' => $templates]);
    }
}
