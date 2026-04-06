<?php

namespace App\Http\Requests;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class SubscribeRequest extends FormRequest
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
            'plan_price_id' => ['required', 'integer', 'exists:plan_prices,id'],
            'initial_payment_status' => ['nullable', new Enum(PaymentAttemptStatus::class)],
            'failure_reason' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $this->input('initial_payment_status') === PaymentAttemptStatus::FAILED->value),
            ],
        ];
    }
}