<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'started_at' => $this->started_at?->toIso8601String(),
            'plan' => [
                'id' => $this->planPrice->plan->id,
                'name' => $this->planPrice->plan->name,
                'trial_days' => $this->planPrice->plan->trial_days,
            ],
            'plan_price_id' => $this->plan_price_id,
            'currency' => $this->planPrice->currency->value,
            'amount' => $this->planPrice->amount,
            'billing_cycle' => $this->planPrice->billing_cycle->value,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'grace_period_ends_at' => $this->grace_period_ends_at?->toIso8601String(),
            'canceled_at' => $this->canceled_at?->toIso8601String(),

            'latest_payment_attempt' => $this->whenLoaded('latestPaymentAttempt', function () {
                return new PaymentAttemptResource($this->latestPaymentAttempt);
            }),

            'payment_attempts' => $this->whenLoaded('paymentAttempts', function () {
                return PaymentAttemptResource::collection($this->paymentAttempts);
            }),
        ];
    }
}