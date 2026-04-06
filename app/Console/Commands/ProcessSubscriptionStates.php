<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class ProcessSubscriptionStates extends Command
{
    protected $signature = 'subscriptions:process';

    protected $description = 'Process expired trials, expired active subscriptions, and expired grace periods.';

    public function handle(SubscriptionService $service): int
    {
        $this->info('Processing subscription states...');

        Subscription::query()
            ->where('status', SubscriptionStatus::TRIALING->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->chunkById(100, function (Collection $subscriptions) use ($service) {
                foreach ($subscriptions as $subscription) {
                    $service->markAsPastDue($subscription);
                    $this->line("Subscription #{$subscription->id}: trial expired -> past_due");
                }
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::ACTIVE->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now())
            ->chunkById(100, function (Collection $subscriptions) use ($service) {
                foreach ($subscriptions as $subscription) {
                    $service->markAsPastDue($subscription);
                    $this->line("Subscription #{$subscription->id}: billing period expired -> past_due");
                }
            });

        Subscription::query()
            ->where('status', SubscriptionStatus::PAST_DUE->value)
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<=', now())
            ->chunkById(100, function (Collection $subscriptions) use ($service) {
                foreach ($subscriptions as $subscription) {
                    $service->cancel($subscription);
                    $this->line("Subscription #{$subscription->id}: grace period expired -> canceled");
                }
            });

        $this->info('Processing complete.');

        return self::SUCCESS;
    }
}