<?php

namespace App\Http\Requests;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],

            'prices' => ['required', 'array', 'min:1'],
            'prices.*.currency' => ['required', new Enum(Currency::class)],
            'prices.*.billing_cycle' => ['required', new Enum(BillingCycle::class)],
            'prices.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}