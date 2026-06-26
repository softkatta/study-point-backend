<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use App\Support\AppearanceDefaults;
use Dompdf\Dompdf;
use Dompdf\Options;

class InvoicePdfService
{
    public function __construct(
        private AppSettingsService $settings,
        private BrandingPdfCacheService $brandingPdf,
    ) {}

    /**
     * @return array{content: string, filename: string, mime: string}
     */
    public function build(Invoice $invoice): array
    {
        $invoice->loadMissing(['student.branch', 'student.admission', 'payment']);

        $invoiceSettings = $this->settings->invoice();
        $template = in_array($invoiceSettings['template'] ?? 'modern', ['classic', 'modern', 'minimal'], true)
            ? $invoiceSettings['template']
            : 'modern';
        $appearance = AppearanceDefaults::merge(Setting::getSection('appearance'));
        $gstAmount = (float) $invoice->gst_amount;
        $logoSrc = $this->brandingPdf->logoDataUri($appearance['logo_url'] ?? null);

        $view = match ($template) {
            'classic' => 'pdf.invoice-classic',
            'minimal' => 'pdf.invoice-minimal',
            default => 'pdf.invoice-modern',
        };

        $html = view($view, [
            'invoice' => $invoice,
            'student' => $invoice->student,
            'company' => $this->settings->company(),
            'gst' => $this->settings->gst(),
            'invoiceSettings' => $invoiceSettings,
            'cgst' => round($gstAmount / 2, 2),
            'sgst' => round($gstAmount / 2, 2),
            'logoSrc' => $logoSrc,
            'siteName' => $appearance['site_name'] ?? 'StudyPoint',
            ...$this->partyContext($invoice),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return [
            'content' => $dompdf->output(),
            'filename' => $invoice->invoice_code.'.pdf',
            'mime' => 'application/pdf',
        ];
    }

    /**
     * @return array{branch: ?\App\Models\Branch, branchAddressLine: string, studentAddress: string}
     */
    private function partyContext(Invoice $invoice): array
    {
        $student = $invoice->student;
        $branch = $student?->branch;
        $admission = $student?->admission;

        return [
            'branch' => $branch,
            'branchAddressLine' => collect([$branch?->address, $branch?->city])->filter()->implode(', '),
            'studentAddress' => collect([
                $admission?->address,
                $admission?->city ?? $student?->city,
                $admission?->state,
                $admission?->pincode,
            ])->filter()->implode(', '),
        ];
    }
}
