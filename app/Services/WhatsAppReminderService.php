<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;

class WhatsAppReminderService
{
    public function __construct(private WhatsAppDispatchService $dispatch) {}

    /**
     * Find subscriptions expiring in 7 days and 1 day and queue reminders.
     * Returns number of reminders queued.
     */
    public function sendDailyRenewalReminders(): int
    {
        $count = 0;

        $targets = [
            'template_renewal_7d' => now()->addDays(7)->toDateString(),
            'template_renewal_1d' => now()->addDays(1)->toDateString(),
        ];

        foreach ($targets as $templateKey => $date) {
            Subscription::query()
                ->whereNotNull('end_date')
                ->whereDate('end_date', $date)
                ->whereIn('status', [
                    SubscriptionStatus::Active,
                    SubscriptionStatus::Renewed,
                    SubscriptionStatus::ExpiringSoon,
                ])
                ->chunkById(100, function ($subscriptions) use (&$count, $templateKey) {
                    foreach ($subscriptions as $subscription) {
                        try {
                            $this->dispatch->queueRenewalReminder($subscription, $templateKey);
                            $count++;
                        } catch (\Throwable $e) {
                            report($e);
                        }
                    }
                });
        }

        return $count;
    }
}
