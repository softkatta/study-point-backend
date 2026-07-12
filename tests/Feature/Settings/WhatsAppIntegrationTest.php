<?php

namespace Tests\Feature\Settings;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Admission;
use App\Models\AttendanceLog;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppMessageLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_whatsapp_send_queues_message_and_job_sends_via_meta(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        \App\Models\Setting::saveSection('whatsapp', [
            'provider' => 'meta_cloud',
            'meta_phone_number_id' => '1096089260264884',
            'meta_access_token' => 'test-token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.test123']]], 200),
        ]);

        $this->postJson('/api/v1/whatsapp/send', [
            'phone' => '+917887969118',
            'message' => 'Hello from StudyPoint',
        ])
            ->assertOk()
            ->assertJsonPath('data.queued', true);

        $message = WhatsAppMessage::first();
        $this->assertNotNull($message);
        $this->assertSame('sent', $message->fresh()->status);
        $this->assertSame('wamid.test123', $message->external_id);
    }

    public function test_attendance_alert_is_queued_for_student_and_emergency_contact(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        Setting::saveSection('whatsapp', [
            'provider' => 'meta_cloud',
            'meta_phone_number_id' => '1096089260264884',
            'meta_access_token' => 'test-token',
            'notify_attendance' => true,
            'template_attendance' => 'studypoint_attendance',
        ]);

        Bus::fake();

        $admission = Admission::create([
            'admission_code' => 'ADM-100',
            'source' => 'online',
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'name' => 'Test Student',
            'email' => 'student@test.com',
            'phone' => '9876543210',
            'start_date' => now()->toDateString(),
            'plan_name' => 'Monthly',
            'amount' => 1000,
            'payment_status' => 'paid',
            'emergency_name' => 'Parent',
            'emergency_phone' => '9123456789',
            'emergency_relation' => 'Father',
        ]);

        $student = Student::create([
            'student_code' => 'STU-100',
            'verify_token' => 'verifytest100',
            'qr_token' => 'qrtest100',
            'name' => 'Test Student',
            'email' => 'student@test.com',
            'phone' => '9876543210',
            'status' => 'active',
            'plan_name' => 'Monthly',
            'admission_id' => $admission->id,
        ]);

        $log = AttendanceLog::create([
            'student_id' => $student->id,
            'branch_id' => null,
            'check_in' => now(),
            'status' => 'present',
            'source' => 'manual',
        ]);

        $dispatch = app(\App\Services\WhatsAppDispatchService::class);
        $dispatch->queueAttendanceAlert($student, $log);

        $this->assertDatabaseHas('whatsapp_messages', ['to_phone' => '9876543210']);
        $this->assertDatabaseHas('whatsapp_messages', ['to_phone' => '9123456789']);

        Bus::assertDispatched(SendWhatsAppMessageJob::class, 2);
    }

    public function test_meta_template_with_named_placeholders_orders_parameters(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        \App\Models\Setting::saveSection('whatsapp', [
            'provider' => 'meta_cloud',
            'meta_phone_number_id' => '1096089260264884',
            'meta_access_token' => 'test-token',
        ]);

        Http::fake([
            'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.named123']]], 200),
        ]);

        $dispatch = app(\App\Services\WhatsAppDispatchService::class);
        $message = $dispatch->queueTemplate(
            '+917887969118',
            'studypoint_custom_named',
            [
                'customer_name' => 'Ravi',
                'payment_code' => 'P123',
                'amount' => '₹500',
            ],
            'en',
        );

        $this->assertNotNull($message);

        $job = new \App\Jobs\SendWhatsAppMessageJob($message->id);
        $job->handle(app(\App\Services\WhatsAppSenderService::class), app(\App\Services\WhatsAppMessageLogService::class));
        $message = $message->fresh();

        Http::assertSent(function ($request) {
            $payload = json_decode($request->body(), true);

            return str_contains($request->url(), 'graph.facebook.com')
                && isset($payload['template']['name'], $payload['template']['components'][0]['parameters'])
                && $payload['template']['name'] === 'studypoint_custom_named'
                && $payload['template']['components'][0]['parameters'][0]['text'] === 'Ravi'
                && $payload['template']['components'][0]['parameters'][1]['text'] === 'P123'
                && $payload['template']['components'][0]['parameters'][2]['text'] === '₹500';
        });

        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.named123', $message->external_id);
    }

    public function test_meta_webhook_updates_delivery_status(): void
    {
        $message = WhatsAppMessage::create([
            'to_phone' => '917887969118',
            'message_type' => 'text',
            'body' => 'Test',
            'status' => 'sent',
            'external_id' => 'wamid.webhook123',
            'provider' => 'meta_cloud',
            'sent_at' => now(),
        ]);

        $this->postJson('/api/v1/webhooks/whatsapp/meta', [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.webhook123',
                            'status' => 'delivered',
                        ]],
                    ],
                ]],
            ]],
        ])->assertOk();

        $message->refresh();
        $this->assertSame('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_delivery_status_endpoint_returns_message_record(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $message = WhatsAppMessage::create([
            'to_phone' => '917887969118',
            'message_type' => 'text',
            'body' => 'Test',
            'status' => 'read',
            'external_id' => 'wamid.read123',
            'read_at' => now(),
        ]);

        $this->getJson("/api/v1/whatsapp/campaigns/{$message->id}/status")
            ->assertOk()
            ->assertJsonPath('data.status', 'read');
    }
}
