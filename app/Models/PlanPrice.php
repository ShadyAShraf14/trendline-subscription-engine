<?php

namespace App\Models;

use App\Enums\BillingCycle;
use App\Enums\Currency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanPrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'currency',
        'amount',
        'billing_cycle',
        'is_active',
    ];

    protected $casts = [
        'currency' => Currency::class,
        'billing_cycle' => BillingCycle::class,
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}