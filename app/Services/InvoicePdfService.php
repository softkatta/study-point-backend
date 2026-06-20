<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Setting;
use App\Support\AppearanceDefaults;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function __construct(
        private AppSettingsService $settings,
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
            'logoDataUri' => $this->logoDataUri($invoiceSettings, $appearance),
            'siteName' => $appearance['site_name'] ?? 'StudyPoint',
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
     * @param  array<string, mixed>  $invoiceSettings
     * @param  array<string, mixed>  $appearance
     */
    private function logoDataUri(array $invoiceSettings, array $appearance): ?string
    {
        $url = $appearance['logo_url'] ?? null;
        if (! $url) {
            return null;
        }

        $path = null;
        if (preg_match('#/storage/(.+)$#', (string) $url, $matches)) {
            $path = $matches[1];
        } elseif (str_starts_with((string) $url, 'storage/')) {
            $path = substr((string) $url, strlen('storage/'));
        }

        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
    }
}
