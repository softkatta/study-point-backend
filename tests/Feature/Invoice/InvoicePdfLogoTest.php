<?php

namespace Tests\Feature\Invoice;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\BrandingPdfCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePdfLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_invoice_pdf_embeds_logo_when_configured(): void
    {
        if (! function_exists('imagecreatefrompng')) {
            $this->markTestSkipped('GD extension required to build logo cache.');
        }

        Storage::fake('public');

        $png = imagecreatetruecolor(40, 40);
        $red = imagecolorallocate($png, 255, 0, 0);
        imagefill($png, 0, 0, $red);
        ob_start();
        imagepng($png);
        $pngBytes = ob_get_clean();
        imagedestroy($png);

        Storage::disk('public')->put('branding/test-logo.png', $pngBytes);
        $logoUrl = '/storage/branding/test-logo.png';
        Setting::saveSection('appearance', ['logo_url' => $logoUrl]);

        app(BrandingPdfCacheService::class)->warmLogoCache($logoUrl);

        $admin = User::where('email', 'admin@studypoint.in')->first();
        Sanctum::actingAs($admin);

        $invoice = $this->createInvoiceForStudent();

        $response = $this->get("/api/v1/invoices/{$invoice->id}/pdf");

        $response->assertOk();
        $this->assertStringContainsString('/Subtype /Image', $response->getContent());
    }

    private function createInvoiceForStudent(): Invoice
    {
        $branch = Branch::first();
        $plan = Plan::where('slug', 'monthly')->first();

        $user = User::factory()->create(['email' => 'invoice-logo@test.com']);
        $user->assignRole('student');

        $student = Student::create([
            'student_code' => 'SP'.random_int(1000000, 9999999),
            'verify_token' => 'LOGOTST1',
            'name' => 'Invoice Logo Student',
            'email' => 'invoice-logo@test.com',
            'phone' => '9999999902',
            'branch_id' => $branch->id,
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'status' => 'active',
            'user_id' => $user->id,
            'valid_from' => now()->toDateString(),
            'expiry' => now()->addMonth()->toDateString(),
        ]);

        return Invoice::create([
            'invoice_code' => 'INV-LOGO-'.random_int(1000, 9999),
            'student_id' => $student->id,
            'amount' => 1000,
            'gst_amount' => 180,
            'total' => 1180,
            'status' => 'paid',
            'issued_at' => now(),
        ]);
    }
}
