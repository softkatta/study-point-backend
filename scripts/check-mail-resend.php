<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$mail = app(App\Services\MailSenderService::class);
echo 'mail_configured: '.($mail->isConfigured() ? 'yes' : 'no').PHP_EOL;
$c = $mail->config();
echo 'provider='.($c['provider'] ?? '').' host='.($c['smtp_host'] ?? '').' user='.($c['smtp_username'] ?? '').PHP_EOL;
echo 'from_email='.($c['from_email'] ?? '').' has_password='.(empty($c['smtp_password']) ? 'no' : 'yes').PHP_EOL;

$student = App\Models\Student::where('student_code', 'SP2024004')->first()
    ?? App\Models\Student::where('email', 'like', '%sachin%')->first();

if (! $student) {
    echo "student: not found\n";
    exit(0);
}

echo "student: {$student->student_code} id={$student->id} email={$student->email}\n";
echo 'paid: '.($student->hasReceivedPayment() ? 'yes' : 'no').PHP_EOL;
echo 'user_id: '.($student->user_id ?? 'null').PHP_EOL;
echo 'admission_id: '.($student->admission_id ?? 'null').PHP_EOL;

if (! $mail->isConfigured()) {
    echo "SKIP send: mail not configured in settings table\n";
    exit(0);
}

try {
    app(App\Services\StudentAccountService::class)->resendPortalCredentials($student);
    echo "resend: OK\n";
} catch (Throwable $e) {
    echo 'resend FAILED: '.$e->getMessage().PHP_EOL;
}
