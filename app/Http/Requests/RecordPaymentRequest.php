<?php

namespace App\Http\Requests;

use App\Enums\PaymentAttemptStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RecordPaymentRequest extends FormRequest
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
            'status' => ['required', new Enum(PaymentAttemptStatus::class)],
            'failure_reason' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $this->input('status') === PaymentAttemptStatus::FAILED->value),
            ],
        ];
    }
}