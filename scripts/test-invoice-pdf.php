<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = (int) ($argv[1] ?? 30);
$invoice = App\Models\Invoice::with(['student.branch', 'student.admission'])->find($id);

if (! $invoice) {
    echo "invoice {$id} not found\n";
    exit(1);
}

try {
    $built = app(App\Services\InvoicePdfService::class)->build($invoice);
    echo 'OK: '.$built['filename'].' ('.strlen($built['content'])." bytes)\n";
} catch (Throwable $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
    echo $e->getFile().':'.$e->getLine()."\n";
    exit(1);
}
