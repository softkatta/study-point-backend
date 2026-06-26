<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo 'gd: '.(extension_loaded('gd') ? 'yes' : 'no').PHP_EOL;
$appearance = App\Models\Setting::getSection('appearance');
echo 'logo_url: '.($appearance['logo_url'] ?? 'none').PHP_EOL;

$pdf = app(App\Services\InvoicePdfService::class);
$invoice = App\Models\Invoice::with(['student.branch', 'student.admission'])->find((int) ($argv[1] ?? 30));
if (! $invoice) {
    echo "invoice not found\n";
    exit(1);
}

$ref = new ReflectionClass($pdf);
$method = $ref->getMethod('resolveLogoSrc');
$method->setAccessible(true);
$appearanceMerged = App\Support\AppearanceDefaults::merge($appearance);
$logo = $method->invoke($pdf, $appearanceMerged['logo_url'] ?? null);
echo 'logoSrc: '.($logo ? strlen($logo).' chars ('.substr($logo, 5, strpos($logo, ';') - 5).')' : 'null').PHP_EOL;

$built = $pdf->build($invoice);
file_put_contents(__DIR__.'/../storage/app/invoice-test-30.pdf', $built['content']);
echo 'pdf saved: storage/app/invoice-test-30.pdf ('.strlen($built['content'])." bytes)\n";
echo 'has image xobject: '.(str_contains($built['content'], '/Subtype /Image') ? 'yes' : 'no').PHP_EOL;
