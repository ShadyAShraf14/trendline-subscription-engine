<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanService
{
    public function upsert(array $data, ?Plan $plan = null): Plan
    {
        return DB::transaction(function () use ($data, $plan) {
            $this->ensureNoDuplicatePriceCombinations($data['prices']);

            $plan ??= new Plan();

            $plan->fill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'trial_days' => $data['trial_days'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $plan->save();

            $submittedCombinations = [];

            foreach ($data['prices'] as $priceData) {
                $combinationKey = $priceData['currency'] . '|' . $priceData['billing_cycle'];
                $submittedCombinations[] = $combinationKey;

                $plan->prices()->updateOrCreate(
                    [
                        'currency' => $priceData['currency'],
                        'billing_cycle' => $priceData['billing_cycle'],
                    ],
                    [
                        'amount' => $priceData['amount'],
                        'is_active' => true,
                    ]
                );
            }

            $plan->prices()
                ->get()
                ->each(function ($price) use ($submittedCombinations) {
                    $key = $price->currency->value . '|' . $price->billing_cycle->value;

                    if (! in_array($key, $submittedCombinations, true)) {
                        $price->update(['is_active' => false]);
                    }
                });

            return $plan->refresh();
        });
    }

    public function deactivate(Plan $plan): void
    {
        DB::transaction(function () use ($plan) {
            $plan->update(['is_active' => false]);
            $plan->prices()->update(['is_active' => false]);
        });
    }

    private function ensureNoDuplicatePriceCombinations(array $prices): void
    {
        $seen = [];

        foreach ($prices as $price) {
            $key = $price['currency'] . '|' . $price['billing_cycle'];

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    'prices' => ['Duplicate currency/billing_cycle combinations are not allowed within the same plan.'],
                ]);
            }

            $seen[$key] = true;
        }
    }
}