<?php

namespace Tests\Feature\Admission;

use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\AdmissionDocument;
use App\Models\AttendanceLog;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdmissionCascadeDeleteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_pending_admission_delete_removes_payment_and_documents(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $admission = Admission::create([
            'admission_code' => 'ADM777',
            'source' => 'online',
            'status' => AdmissionStatus::Pending,
            'payment_status' => 'pending',
            'first_name' => 'Pending',
            'name' => 'Pending User',
            'email' => 'pending-delete@test.com',
            'phone' => '8888888888',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'start_date' => now()->toDateString(),
            'duration_months' => 1,
            'amount' => 200,
        ]);

        $payment = Payment::create([
            'payment_code' => 'PAY-TEST-777',
            'admission_id' => $admission->id,
            'amount' => 200,
            'method' => 'Cash',
            'status' => 'pending',
        ]);

        $document = AdmissionDocument::create([
            'admission_id' => $admission->id,
            'type' => 'photo',
            'file_path' => "admissions/{$admission->id}/photo.jpg",
            'file_name' => 'photo.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
        ]);

        $response = $this->deleteJson("/api/v1/admissions/{$admission->id}");

        $response->assertOk()
            ->assertJsonPath('data.deleted.admission', true)
            ->assertJsonPath('data.permanent', true)
            ->assertJsonPath('data.deleted.documents', 1)
            ->assertJsonPath('data.deleted.payments', 1)
            ->assertJsonPath('data.deleted.student', false);

        $this->assertDatabaseMissing('admissions', ['id' => $admission->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('admission_documents', ['id' => $document->id]);
    }

    public function test_active_admission_delete_removes_student_subscription_and_portal_user(): void
    {
        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $create = $this->postJson('/api/v1/admissions', [
            'first_name' => 'Cascade',
            'last_name' => 'Delete',
            'email' => 'cascade-delete@test.com',
            'phone' => '+91 9876543299',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'payment_mode' => 'cash',
            'payment_date' => now()->toDateString(),
            'source' => 'admin',
        ]);

        $create->assertCreated();
        $admissionId = $create->json('data.id');

        $admission = Admission::findOrFail($admissionId);
        $student = Student::where('admission_id', $admission->id)->firstOrFail();
        $subscription = Subscription::where('student_id', $student->id)->firstOrFail();
        $payment = Payment::where('admission_id', $admission->id)->firstOrFail();
        $portalUserId = $student->user_id;

        $invoice = Invoice::create([
            'invoice_code' => 'INV-TEST-777',
            'student_id' => $student->id,
            'payment_id' => $payment->id,
            'amount' => 200,
            'gst_amount' => 0,
            'total' => 200,
            'status' => 'paid',
            'issued_at' => now(),
        ]);

        AttendanceLog::create([
            'student_id' => $student->id,
            'branch_id' => $branch->id,
            'check_in' => now(),
            'status' => 'present',
            'source' => 'qr',
        ]);

        $response = $this->deleteJson("/api/v1/admissions/{$admission->id}");

        $response->assertOk()
            ->assertJsonPath('data.permanent', true)
            ->assertJsonPath('data.deleted.student', true)
            ->assertJsonPath('data.deleted.portal_user', true)
            ->assertJsonPath('data.deleted.subscriptions', 1)
            ->assertJsonPath('data.deleted.payments', 1)
            ->assertJsonPath('data.deleted.invoices', 1)
            ->assertJsonPath('data.deleted.attendance_logs', 1);

        $this->assertDatabaseMissing('admissions', ['id' => $admission->id]);
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('subscriptions', ['id' => $subscription->id]);
        $this->assertDatabaseMissing('payments', ['id' => $payment->id]);
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('attendance_logs', ['student_id' => $student->id]);

        if ($portalUserId) {
            $this->assertDatabaseMissing('users', ['id' => $portalUserId]);
        }
    }
}
