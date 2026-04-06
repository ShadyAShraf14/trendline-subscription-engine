<?php

namespace Database\Seeders;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $starter = Plan::updateOrCreate(
            ['name' => 'Starter Plan'],
            [
                'description' => 'Ideal for individuals starting out.',
                'trial_days' => 7,
                'is_active' => true,
            ]
        );

        $this->syncPrices($starter, [
            ['currency' => Currency::USD->value, 'billing_cycle' => BillingCycle::MONTHLY->value, 'amount' => 9.99],
            ['currency' => Currency::USD->value, 'billing_cycle' => BillingCycle::YEARLY->value, 'amount' => 99.99],
            ['currency' => Currency::AED->value, 'billing_cycle' => BillingCycle::MONTHLY->value, 'amount' => 36.00],
            ['currency' => Currency::EGP->value, 'billing_cycle' => BillingCycle::MONTHLY->value, 'amount' => 450.00],
        ]);

        $pro = Plan::updateOrCreate(
            ['name' => 'Pro Plan'],
            [
                'description' => 'Advanced features for professionals.',
                'trial_days' => 0,
                'is_active' => true,
            ]
        );

        $this->syncPrices($pro, [
            ['currency' => Currency::USD->value, 'billing_cycle' => BillingCycle::MONTHLY->value, 'amount' => 29.99],
            ['currency' => Currency::USD->value, 'billing_cycle' => BillingCycle::YEARLY->value, 'amount' => 299.99],
            ['currency' => Currency::AED->value, 'billing_cycle' => BillingCycle::MONTHLY->value, 'amount' => 110.00],
            ['currency' => Currency::EGP->value, 'billing_cycle' => BillingCycle::MONTHLY->value, 'amount' => 1400.00],
        ]);
    }

    private function syncPrices(Plan $plan, array $prices): void
    {
        $submittedKeys = [];

        foreach ($prices as $price) {
            $submittedKeys[] = $price['currency'] . '|' . $price['billing_cycle'];

            $plan->prices()->updateOrCreate(
                [
                    'currency' => $price['currency'],
                    'billing_cycle' => $price['billing_cycle'],
                ],
                [
                    'amount' => $price['amount'],
                    'is_active' => true,
                ]
            );
        }

        $plan->prices()->get()->each(function ($price) use ($submittedKeys) {
            $key = $price->currency->value . '|' . $price->billing_cycle->value;

            if (! in_array($key, $submittedKeys, true)) {
                $price->update(['is_active' => false]);
            }
        });
    }
}