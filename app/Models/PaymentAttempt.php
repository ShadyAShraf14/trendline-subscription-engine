<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentAttemptStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'status',
        'amount',
        'currency',
        'failure_reason',
        'attempted_at',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentAttemptStatus::class,
        'currency' => Currency::class,
        'amount' => 'decimal:2',
        'attempted_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}