<?php

namespace Tests\Feature\Invoice;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_download_invoice_pdf(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $invoice = $this->createInvoiceForStudent();

        $response = $this->get("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_student_can_download_own_invoice_pdf(): void
    {
        $invoice = $this->createInvoiceForStudent();
        $student = $invoice->student;
        $user = User::find($student->user_id);
        $this->assertNotNull($user);
        Sanctum::actingAs($user);

        $response = $this->get("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_student_cannot_download_another_students_invoice_pdf(): void
    {
        $invoice = $this->createInvoiceForStudent();

        $otherStudent = Student::where('id', '!=', $invoice->student_id)
            ->whereNotNull('user_id')
            ->first();

        if (! $otherStudent) {
            $branch = Branch::first();
            $plan = Plan::where('slug', 'monthly')->first();
            $otherUser = User::factory()->create(['email' => 'other-student@test.com']);
            $otherUser->assignRole('student');
            $otherStudent = Student::create([
                'student_code' => 'SP9999901',
                'verify_token' => 'OTHER01',
                'name' => 'Other Student',
                'email' => 'other-student@test.com',
                'phone' => '9999999901',
                'branch_id' => $branch->id,
                'plan_name' => $plan->name,
                'status' => 'active',
                'user_id' => $otherUser->id,
                'valid_from' => now()->toDateString(),
                'expiry' => now()->addMonth()->toDateString(),
            ]);
        }

        Sanctum::actingAs(User::find($otherStudent->user_id));

        $this->get("/api/v1/invoices/{$invoice->id}/pdf")
            ->assertStatus(403);
    }

    private function createInvoiceForStudent(): Invoice
    {
        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $user = User::factory()->create(['email' => 'invoice-pdf@test.com']);
        $user->assignRole('student');

        $student = Student::create([
            'student_code' => 'SP'.random_int(1000000, 9999999),
            'verify_token' => 'PDFTEST1',
            'name' => 'Invoice Pdf Student',
            'email' => 'invoice-pdf@test.com',
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
            'invoice_code' => 'INV-TEST-'.random_int(1000, 9999),
            'student_id' => $student->id,
            'amount' => 1000,
            'gst_amount' => 180,
            'total' => 1180,
            'status' => 'paid',
            'issued_at' => now(),
        ]);
    }
}
