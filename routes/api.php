<?php

use App\Http\Controllers\Api\V1\AdmissionController;
use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BiometricDeviceController;
use App\Http\Controllers\Api\V1\BiometricIntegrationController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\ContactController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FacilityController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\TestimonialController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\HeadOfficeController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\StudentController;
use App\Http\Controllers\Api\V1\StudentPortalController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WhatsAppController;
use App\Http\Controllers\Api\V1\WhatsAppWebhookController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Controllers\Api\V1\InstallController;
use App\Http\Controllers\Api\V1\LicenseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::match(['get', 'post'], 'webhooks/whatsapp/meta', [WhatsAppWebhookController::class, 'meta']);
    Route::post('webhooks/payments/razorpay', [PaymentWebhookController::class, 'razorpay']);

    // Install wizard (locked after successful installation)
    Route::prefix('install')->middleware(['throttle:30,1', 'install.not_completed'])->group(function () {
        Route::get('status', [InstallController::class, 'status']);
        Route::get('requirements', [InstallController::class, 'requirements']);
        Route::post('database', [InstallController::class, 'database']);
        Route::post('admin', [InstallController::class, 'admin']);
        Route::post('activate', [InstallController::class, 'activate']);
        Route::get('configuration', [InstallController::class, 'downloadConfiguration']);
        Route::post('migrate', [InstallController::class, 'migrate']);
        Route::post('complete', [InstallController::class, 'complete']);
    });

    Route::get('license/entitlements', [LicenseController::class, 'entitlements']);
    Route::post('license/verify', [LicenseController::class, 'verify']);
    Route::post('license/activate', [InstallController::class, 'activate']);
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('2fa/verify', [AuthController::class, 'verifyTwoFactor']);
        Route::post('2fa/setup', [AuthController::class, 'setupTwoFactor']);
        Route::post('2fa/confirm-setup', [AuthController::class, 'confirmTwoFactorSetup']);
        Route::get('student/register/status', [AuthController::class, 'registrationStatus']);
        Route::post('student/register', [AuthController::class, 'studentRegister']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');

        Route::middleware(['auth:sanctum', 'session.timeout'])->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('change-password', [AuthController::class, 'changePassword']);
            Route::post('2fa/disable', [AuthController::class, 'disableTwoFactor']);
        });
    });

    Route::get('branches', [LookupController::class, 'branches']);
    Route::get('branches/public', [LookupController::class, 'publicBranches']);
    Route::get('facilities', [LookupController::class, 'facilities']);
    Route::get('faqs', [LookupController::class, 'faqs']);
    Route::get('testimonials', [LookupController::class, 'testimonials']);
    Route::get('homepage/stats', [LookupController::class, 'homepageStats']);
    Route::get('homepage/hero', [LookupController::class, 'homepageHero']);
    Route::get('topbar', [LookupController::class, 'topBar']);
    Route::get('head-office', [HeadOfficeController::class, 'show']);
    Route::get('plans', [LookupController::class, 'plans']);
    Route::get('payment/checkout-config', [LookupController::class, 'paymentCheckout']);
    Route::get('notification/channels', [LookupController::class, 'notificationChannels']);
    Route::post('contact', [ContactController::class, 'store']);

    Route::post('admissions', [AdmissionController::class, 'store']);
    Route::post('admissions/{admission}/documents', [AdmissionController::class, 'uploadDocuments']);
    Route::post('admissions/{admission}/checkout', [AdmissionController::class, 'checkout']);
    Route::post('admissions/{admission}/confirm-payment', [AdmissionController::class, 'confirmPayment']);
    Route::get('admissions/{admission}/resume-payment', [AdmissionController::class, 'resumePayment']);
    Route::get('appearance', [SettingController::class, 'publicAppearance']);
    Route::get('platform/config', [SettingController::class, 'publicPlatform']);
    Route::get('security/config', [SettingController::class, 'publicSecurity']);

    Route::middleware(['auth:sanctum', 'session.timeout', 'ip.whitelist', 'audit.request'])->group(function () {
        Route::get('dashboard/stats', [DashboardController::class, 'stats']);
        Route::get('dashboard/charts', [DashboardController::class, 'charts']);
        Route::get('dashboard/recent-admissions', [DashboardController::class, 'recentAdmissions']);

        Route::get('plan-catalog', [PlanController::class, 'catalog']);
        Route::post('plans', [PlanController::class, 'store']);
        Route::put('plans/{plan}', [PlanController::class, 'update']);
        Route::delete('plans/{plan}', [PlanController::class, 'destroy']);
        Route::patch('plans/{plan}/activate', [PlanController::class, 'activate']);
        Route::patch('plans/{plan}/deactivate', [PlanController::class, 'deactivate']);
        Route::post('plans/{plan}/duplicate', [PlanController::class, 'duplicate']);

        Route::get('payments', [PaymentController::class, 'index']);
        Route::post('payments', [PaymentController::class, 'store']);
        Route::get('payments/{payment}', [PaymentController::class, 'show']);
        Route::patch('payments/{payment}/verify', [PaymentController::class, 'verify']);
        Route::post('payments/{payment}/confirm', [PaymentController::class, 'confirm']);
        Route::post('payments/{payment}/collect', [PaymentController::class, 'collect']);
        Route::post('payments/{payment}/refund', [PaymentController::class, 'refund']);
        Route::delete('payments/{payment}', [PaymentController::class, 'destroy']);
        Route::post('payments/{payment}/invoice', [InvoiceController::class, 'fromPayment']);

        Route::get('invoices', [InvoiceController::class, 'index']);
        Route::post('invoices', [InvoiceController::class, 'store']);
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf']);
        Route::put('invoices/{invoice}', [InvoiceController::class, 'update']);
        Route::patch('invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);
        Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy']);

        Route::get('expenses', [ExpenseController::class, 'index']);
        Route::post('expenses', [ExpenseController::class, 'store']);
        Route::get('expenses/{expense}', [ExpenseController::class, 'show']);
        Route::put('expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
        Route::patch('expenses/{expense}/approve', [ExpenseController::class, 'approve']);
        Route::patch('expenses/{expense}/reject', [ExpenseController::class, 'reject']);
        Route::post('expenses/{expense}/bill', [ExpenseController::class, 'uploadBill']);
        Route::get('expenses/{expense}/bill', [ExpenseController::class, 'downloadBill']);

        Route::get('branches/manage', [BranchController::class, 'index']);
        Route::post('branches/manage', [BranchController::class, 'store']);
        Route::get('branches/manage/{branch}', [BranchController::class, 'show']);
        Route::put('branches/manage/{branch}', [BranchController::class, 'update']);
        Route::delete('branches/manage/{branch}', [BranchController::class, 'destroy']);
        Route::patch('branches/manage/{branch}/activate', [BranchController::class, 'activate']);
        Route::patch('branches/manage/{branch}/deactivate', [BranchController::class, 'deactivate']);
        Route::post('branches/manage/{branch}/transfer', [BranchController::class, 'transferStudents']);

        Route::get('head-office/manage', [HeadOfficeController::class, 'manageShow']);
        Route::put('head-office/manage', [HeadOfficeController::class, 'update']);

        Route::get('facilities/manage', [FacilityController::class, 'index']);
        Route::post('facilities/manage', [FacilityController::class, 'store']);
        Route::get('facilities/manage/{facility}', [FacilityController::class, 'show']);
        Route::put('facilities/manage/{facility}', [FacilityController::class, 'update']);
        Route::delete('facilities/manage/{facility}', [FacilityController::class, 'destroy']);
        Route::patch('facilities/manage/{facility}/activate', [FacilityController::class, 'activate']);
        Route::patch('facilities/manage/{facility}/deactivate', [FacilityController::class, 'deactivate']);

        Route::get('faqs/manage', [FaqController::class, 'index']);
        Route::post('faqs/manage', [FaqController::class, 'store']);
        Route::get('faqs/manage/{faq}', [FaqController::class, 'show']);
        Route::put('faqs/manage/{faq}', [FaqController::class, 'update']);
        Route::delete('faqs/manage/{faq}', [FaqController::class, 'destroy']);
        Route::patch('faqs/manage/{faq}/activate', [FaqController::class, 'activate']);
        Route::patch('faqs/manage/{faq}/deactivate', [FaqController::class, 'deactivate']);

        Route::get('testimonials/manage', [TestimonialController::class, 'index']);
        Route::post('testimonials/manage', [TestimonialController::class, 'store']);
        Route::get('testimonials/manage/{testimonial}', [TestimonialController::class, 'show']);
        Route::put('testimonials/manage/{testimonial}', [TestimonialController::class, 'update']);
        Route::delete('testimonials/manage/{testimonial}', [TestimonialController::class, 'destroy']);
        Route::patch('testimonials/manage/{testimonial}/activate', [TestimonialController::class, 'activate']);
        Route::patch('testimonials/manage/{testimonial}/deactivate', [TestimonialController::class, 'deactivate']);

        Route::get('permissions', [PermissionController::class, 'index']);
        Route::get('roles', [PermissionController::class, 'roles']);
        Route::middleware('permission:permissions.manage')->group(function () {
            Route::post('roles', [PermissionController::class, 'storeRole']);
            Route::put('roles/{role}', [PermissionController::class, 'updateRole']);
            Route::delete('roles/{role}', [PermissionController::class, 'destroyRole']);
            Route::patch('roles/{role}/permissions', [PermissionController::class, 'updateRolePermissions']);
        });

        Route::middleware('role_or_permission:users.view|permissions.manage')->group(function () {
            Route::get('users', [UserController::class, 'index']);
            Route::get('users/{user}', [UserController::class, 'show']);
            Route::get('users/{user}/permissions', [UserController::class, 'permissions']);
            Route::get('users/{user}/logs', [UserController::class, 'activityLogs']);
        });
        Route::middleware('role_or_permission:users.create|permissions.manage')->group(function () {
            Route::post('users', [UserController::class, 'store']);
        });
        Route::middleware('role_or_permission:users.update|permissions.manage')->group(function () {
            Route::put('users/{user}', [UserController::class, 'update']);
            Route::patch('users/{user}/activate', [UserController::class, 'activate']);
            Route::patch('users/{user}/deactivate', [UserController::class, 'deactivate']);
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        });
        Route::middleware('role_or_permission:users.delete|permissions.manage')->group(function () {
            Route::delete('users/{user}', [UserController::class, 'destroy']);
        });
        Route::middleware('role_or_permission:users.change_role|permissions.manage')->group(function () {
            Route::patch('users/{user}/role', [UserController::class, 'changeRole']);
        });
        Route::middleware('permission:permissions.manage')->group(function () {
            Route::patch('users/{user}/permissions', [UserController::class, 'syncPermissions']);
        });

        Route::post('attendance/scan', [AttendanceController::class, 'scan']);
        Route::get('attendance/branch-qr', [AttendanceController::class, 'branchQrCodes']);
        Route::post('attendance/branch-qr/{branch}/regenerate', [AttendanceController::class, 'regenerateBranchQr']);
        Route::get('attendance', [AttendanceController::class, 'index']);
        Route::get('attendance/summary', [AttendanceController::class, 'summary']);
        Route::post('attendance', [AttendanceController::class, 'store']);
        Route::patch('attendance/{attendanceLog}/check-out', [AttendanceController::class, 'checkOut']);
        Route::get('attendance/export', [AttendanceController::class, 'export']);

        Route::middleware('permission:biometric.view')->group(function () {
            Route::get('biometric/devices', [BiometricDeviceController::class, 'index']);
            Route::get('biometric/devices/{device}', [BiometricDeviceController::class, 'show']);
            Route::get('biometric/logs', [BiometricIntegrationController::class, 'logs']);
            Route::get('biometric/blocked-students', [BiometricIntegrationController::class, 'blockedStudents']);
            Route::get('biometric/devices/{device}/commands', [BiometricIntegrationController::class, 'deviceCommands']);
        });
        Route::middleware('permission:biometric.manage')->group(function () {
            Route::post('biometric/devices', [BiometricDeviceController::class, 'store']);
            Route::put('biometric/devices/{device}', [BiometricDeviceController::class, 'update']);
            Route::delete('biometric/devices/{device}', [BiometricDeviceController::class, 'destroy']);
            Route::post('biometric/devices/{device}/sync', [BiometricDeviceController::class, 'sync']);
            Route::patch('biometric/devices/{device}/enable', [BiometricDeviceController::class, 'enable']);
            Route::patch('biometric/devices/{device}/disable', [BiometricDeviceController::class, 'disable']);
            Route::post('biometric/students/{student}/enroll', [BiometricIntegrationController::class, 'enrollStudent']);
            Route::post('biometric/students/{student}/fingerprint', [BiometricIntegrationController::class, 'triggerFingerprint']);
            Route::post('biometric/students/{student}/face', [BiometricIntegrationController::class, 'uploadFace']);
            Route::patch('biometric/students/{student}/block', [BiometricIntegrationController::class, 'blockStudent']);
        });

        Route::post('reports/{type}', [ReportController::class, 'generate']);
        Route::get('reports/{type}/excel', [ReportController::class, 'exportExcel']);
        Route::get('reports/{type}/pdf', [ReportController::class, 'exportPdf']);

        Route::get('whatsapp/templates', [WhatsAppController::class, 'templates']);
        Route::get('whatsapp/messages', [WhatsAppController::class, 'messages']);
        Route::post('whatsapp/templates', [WhatsAppController::class, 'storeTemplate']);
        Route::put('whatsapp/templates/{id}', [WhatsAppController::class, 'updateTemplate']);
        Route::delete('whatsapp/templates/{id}', [WhatsAppController::class, 'deleteTemplate']);
        Route::post('whatsapp/send', [WhatsAppController::class, 'send']);
        Route::post('whatsapp/bulk', [WhatsAppController::class, 'sendBulk']);
        Route::post('whatsapp/schedule', [WhatsAppController::class, 'schedule']);
        Route::get('whatsapp/campaigns/{id}/status', [WhatsAppController::class, 'deliveryStatus']);

        Route::get('settings/{section}', [SettingController::class, 'show']);
        Route::put('settings/{section}', [SettingController::class, 'save']);
        Route::post('settings/{section}/reset', [SettingController::class, 'reset']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);
        Route::post('audit-logs/purge', [AuditLogController::class, 'purge']);
        Route::post('settings/smtp/test', [SettingController::class, 'testSmtp']);
        Route::post('settings/mail/test', [SettingController::class, 'testMail']);
        Route::post('settings/payment/test', [SettingController::class, 'testGateway']);
        Route::post('settings/biometric/test', [SettingController::class, 'testBiometric']);
        Route::post('settings/whatsapp/test', [SettingController::class, 'testWhatsApp']);
        Route::post('settings/branding/logo', [SettingController::class, 'uploadLogo']);
        Route::post('settings/branding/favicon', [SettingController::class, 'uploadFavicon']);
        Route::post('settings/branding/og-image', [SettingController::class, 'uploadOgImage']);
        Route::post('settings/branding/id-card-accent-image', [SettingController::class, 'uploadIdCardAccentImage']);
        Route::post('settings/branding/id-card-back-image', [SettingController::class, 'uploadIdCardBackImage']);

        Route::post('payments/{payment}/whatsapp', [NotificationController::class, 'send'])->defaults('channel', 'whatsapp')->defaults('resource', 'payments');
        Route::post('payments/{payment}/email', [NotificationController::class, 'send'])->defaults('channel', 'email')->defaults('resource', 'payments');
        Route::post('invoices/{invoice}/email', [InvoiceController::class, 'sendEmail']);
        Route::post('invoices/{invoice}/whatsapp', [InvoiceController::class, 'sendWhatsApp']);

        Route::get('student-portal/dashboard', [StudentPortalController::class, 'dashboard']);
        Route::get('student-portal/payments', [StudentPortalController::class, 'payments']);
        Route::get('student-portal/invoices', [StudentPortalController::class, 'invoices']);
        Route::get('student-portal/attendance', [StudentPortalController::class, 'attendance']);
        Route::post('student-portal/attendance/scan-branch', [StudentPortalController::class, 'scanBranchQr']);
        Route::post('student-portal/renew', [StudentPortalController::class, 'renew']);
        Route::post('student-portal/photo', [StudentPortalController::class, 'uploadPhoto']);

        Route::apiResource('admissions', AdmissionController::class)->except(['store']);
        Route::patch('admissions/{admission}/verify', [AdmissionController::class, 'verify']);
        Route::patch('admissions/{admission}/approve', [AdmissionController::class, 'approve']);
        Route::patch('admissions/{admission}/reject', [AdmissionController::class, 'reject']);
        Route::post('admissions/{admission}/collect-payment', [AdmissionController::class, 'collectPayment']);

        Route::apiResource('students', StudentController::class);
        Route::post('students/bulk-delete', [StudentController::class, 'bulkDelete']);
        Route::patch('students/{student}/activate', [StudentController::class, 'activate']);
        Route::patch('students/{student}/deactivate', [StudentController::class, 'deactivate']);
        Route::patch('students/{student}/suspend', [StudentController::class, 'suspend']);
        Route::post('students/{student}/resend-portal-credentials', [StudentController::class, 'resendPortalCredentials']);
        Route::get('students/verify/{token}', [StudentController::class, 'verify']);
        Route::post('students/verify/{token}/check-in', [StudentController::class, 'tokenCheckIn']);
        Route::post('students/verify/{token}/check-out', [StudentController::class, 'tokenCheckOut']);
        Route::post('students/{student}/qr-check-in', [StudentController::class, 'qrCheckIn']);
        Route::post('students/{student}/qr-check-out', [StudentController::class, 'qrCheckOut']);

        Route::apiResource('subscriptions', SubscriptionController::class);
        Route::patch('subscriptions/{subscription}/activate', [SubscriptionController::class, 'activate']);
        Route::patch('subscriptions/{subscription}/pause', [SubscriptionController::class, 'pause']);
        Route::patch('subscriptions/{subscription}/resume', [SubscriptionController::class, 'resume']);
        Route::patch('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);
        Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew']);
        Route::patch('subscriptions/{subscription}/upgrade', [SubscriptionController::class, 'upgrade']);
        Route::patch('subscriptions/{subscription}/downgrade', [SubscriptionController::class, 'downgrade']);
        Route::patch('subscriptions/{subscription}/extend', [SubscriptionController::class, 'extend']);
    });
});
