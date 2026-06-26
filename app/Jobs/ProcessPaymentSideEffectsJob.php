<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\InvoicePdfService;
use App\Services\InvoiceService;
use App\Services\WhatsAppDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPaymentSideEffectsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $paymentId) {}

    public function handle(
        InvoiceService $invoices,
        InvoicePdfService $invoicePdf,
        WhatsAppDispatchService $whatsapp,
    ): void {
        $payment = Payment::with(['student.admission', 'admission'])->find($this->paymentId);
        if (! $payment?->student_id) {
            return;
        }

        $invoice = null;

        try {
            $invoice = $invoices->maybeAutoGenerate($payment);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($payment->admission_id) {
            return;
        }

        try {
            $whatsapp->queuePaymentReceipt($payment->fresh(['student.admission', 'admission']), $invoice, $invoicePdf);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
