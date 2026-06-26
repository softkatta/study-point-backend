<?php

namespace Tests\Feature\Invoice;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\MailSenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class InvoiceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_email_invoice_to_student(): void
    {
        $mailer = Mockery::mock(MailSenderService::class);
        $mailer->shouldReceive('isConfigured')->andReturn(true);
        $mailer->shouldReceive('send')
            ->once()
            ->withArgs(function (string $to, string $subject, string $message, $html, array $template, array $attachments = []) {
                return $to === 'invoice-notify@test.com'
                    && str_contains($subject, 'INV-NOTIFY')
                    && count($attachments) === 1
                    && ($attachments[0]['mime'] ?? '') === 'application/pdf';
            });
        $this->instance(MailSenderService::class, $mailer);

        Setting::saveSection('mail', [
            'provider' => 'smtp',
            'smtp_host' => 'smtp.test.com',
            'smtp_username' => 'user@test.com',
            'smtp_password' => 'secret',
        ]);

        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $invoice = $this->createInvoiceForStudent();

        $this->postJson("/api/v1/invoices/{$invoice->id}/email")
            ->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.email', 'invoice-notify@test.com');
    }

    public function test_admin_can_send_invoice_via_whatsapp(): void
    {
        Http::fake([
            'api.interakt.ai/*' => Http::response(['result' => true], 200),
        ]);

        Setting::saveSection('whatsapp', [
            'provider' => 'interakt',
            'interakt_api_key' => 'test-key',
        ]);

        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $invoice = $this->createInvoiceForStudent();

        $this->postJson("/api/v1/invoices/{$invoice->id}/whatsapp")
            ->assertOk()
            ->assertJsonPath('data.queued', true)
            ->assertJsonPath('data.phone', '9999999900');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.interakt.ai'));
    }

    public function test_student_cannot_send_invoice_notifications(): void
    {
        $invoice = $this->createInvoiceForStudent();
        $user = User::find($invoice->student->user_id);
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/invoices/{$invoice->id}/email")
            ->assertStatus(403);

        $this->postJson("/api/v1/invoices/{$invoice->id}/whatsapp")
            ->assertStatus(403);
    }

    private function createInvoiceForStudent(): Invoice
    {
        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $user = User::factory()->create(['email' => 'invoice-notify@test.com']);
        $user->assignRole('student');

        $student = Student::create([
            'student_code' => 'SP'.random_int(1000000, 9999999),
            'verify_token' => 'INVNOTIF',
            'name' => 'Invoice Notify Student',
            'email' => 'invoice-notify@test.com',
            'phone' => '9999999900',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'user_id' => $user->id,
            'valid_from' => now()->toDateString(),
            'expiry' => now()->addMonth()->toDateString(),
        ]);

        return Invoice::create([
            'invoice_code' => 'INV-NOTIFY-'.random_int(1000, 9999),
            'student_id' => $student->id,
            'amount' => 1000,
            'gst_amount' => 180,
            'total' => 1180,
            'status' => 'paid',
            'issued_at' => now(),
        ]);
    }
}
