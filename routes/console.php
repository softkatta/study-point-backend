<?php

use App\Services\SubscriptionService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:sync-status', function (SubscriptionService $subscriptions) {
    $subscriptions->syncExpiryStatuses();
    $this->info('Subscription and student expiry statuses synced.');
})->purpose('Mark expired and expiring-soon subscriptions');
