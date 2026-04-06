<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanPriceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency->value,
            'amount' => $this->amount,
            'billing_cycle' => $this->billing_cycle->value,
            'is_active' => $this->is_active,
        ];
    }
}