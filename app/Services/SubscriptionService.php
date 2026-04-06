<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\PaymentAttemptStatus;
use App\Enums\SubscriptionStatus;
use App\Models\PlanPrice;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionService
{
    public function subscribe(
        User $user,
        PlanPrice $planPrice,
        ?PaymentAttemptStatus $initialPaymentStatus = null,
        ?string $failureReason = null
    ): Subscription {
        return DB::transaction(function () use ($user, $planPrice, $initialPaymentStatus, $failureReason) {
            $this->cancelExistingAccessGrantingSubscriptions($user);

            $planPrice->loadMissing('plan');
            $plan = $planPrice->plan;

            if ($plan->trial_days > 0) {
                return $user->subscriptions()->create([
                    'plan_price_id' => $planPrice->id,
                    'status' => SubscriptionStatus::TRIALING,
                    'started_at' => now(),
                    'trial_ends_at' => now()->addDays($plan->trial_days),
                    'ends_at' => null,
                    'grace_period_ends_at' => null,
                    'canceled_at' => null,
                ]);
            }

            if (! $initialPaymentStatus) {
                throw ValidationException::withMessages([
                    'initial_payment_status' => ['This field is required for plans without a trial period.'],
                ]);
            }

            $subscription = $user->subscriptions()->create([
                'plan_price_id' => $planPrice->id,
                'status' => $initialPaymentStatus === PaymentAttemptStatus::SUCCESS
                    ? SubscriptionStatus::ACTIVE
                    : SubscriptionStatus::PAST_DUE,
                'started_at' => now(),
                'trial_ends_at' => null,
                'ends_at' => $initialPaymentStatus === PaymentAttemptStatus::SUCCESS
                    ? $this->calculateEndsAt($planPrice, now())
                    : null,
                'grace_period_ends_at' => $initialPaymentStatus === PaymentAttemptStatus::FAILED
                    ? now()->addDays(3)
                    : null,
                'canceled_at' => null,
            ]);

            $this->createPaymentAttempt($subscription, $initialPaymentStatus, $failureReason);

            return $subscription->refresh();
        });
    }

    public function recordPayment(
        Subscription $subscription,
        PaymentAttemptStatus $status,
        ?string $failureReason = null
    ): Subscription {
        if ($subscription->status === SubscriptionStatus::CANCELED) {
            throw ValidationException::withMessages([
                'subscription' => ['Canceled subscriptions cannot accept new payments. Please create a new subscription instead.'],
            ]);
        }

        if ($subscription->status === SubscriptionStatus::TRIALING) {
            throw ValidationException::withMessages([
                'subscription' => ['Trialing subscriptions do not require payment until the trial ends.'],
            ]);
        }

        return DB::transaction(function () use ($subscription, $status, $failureReason) {
            $subscription->loadMissing('planPrice');

            $this->createPaymentAttempt($subscription, $status, $failureReason);

            if ($status === PaymentAttemptStatus::SUCCESS) {
                $renewalStart = now();

                if (
                    $subscription->status === SubscriptionStatus::ACTIVE
                    && $subscription->ends_at
                    && $subscription->ends_at->isFuture()
                ) {
                    $renewalStart = $subscription->ends_at->copy();
                }

                $subscription->update([
                    'status' => SubscriptionStatus::ACTIVE,
                    'ends_at' => $this->calculateEndsAt($subscription->planPrice, $renewalStart),
                    'grace_period_ends_at' => null,
                    'canceled_at' => null,
                ]);

                return $subscription->refresh();
            }

            return $this->markAsPastDue($subscription);
        });
    }

    public function markAsPastDue(Subscription $subscription): Subscription
    {
        if ($subscription->status === SubscriptionStatus::CANCELED) {
            return $subscription;
        }

        $subscription->update([
            'status' => SubscriptionStatus::PAST_DUE,
            'grace_period_ends_at' => $subscription->grace_period_ends_at ?? now()->addDays(3),
        ]);

        return $subscription->refresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::CANCELED,
            'canceled_at' => now(),
            'grace_period_ends_at' => null,
        ]);

        return $subscription->refresh();
    }

    private function createPaymentAttempt(
        Subscription $subscription,
        PaymentAttemptStatus $status,
        ?string $failureReason = null
    ): void {
        $subscription->loadMissing('planPrice');

        $subscription->paymentAttempts()->create([
            'status' => $status,
            'amount' => $subscription->planPrice->amount,
            'currency' => $subscription->planPrice->currency,
            'failure_reason' => $status === PaymentAttemptStatus::FAILED ? $failureReason : null,
            'attempted_at' => now(),
            'paid_at' => $status === PaymentAttemptStatus::SUCCESS ? now() : null,
        ]);
    }

    private function cancelExistingAccessGrantingSubscriptions(User $user): void
    {
        $user->subscriptions()
            ->whereIn(
                'status',
                array_map(
                    fn (SubscriptionStatus $status) => $status->value,
                    SubscriptionStatus::accessGranting()
                )
            )
            ->update([
                'status' => SubscriptionStatus::CANCELED,
                'canceled_at' => now(),
                'grace_period_ends_at' => null,
            ]);
    }

    private function calculateEndsAt(PlanPrice $planPrice, Carbon $from): Carbon
    {
        $base = $from->copy();

        return match ($planPrice->billing_cycle) {
            BillingCycle::MONTHLY => $base->addMonth(),
            BillingCycle::YEARLY => $base->addYear(),
        };
    }
}