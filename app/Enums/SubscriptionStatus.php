<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';

    /**
     * Statuses that still grant access.
     *
     * @return array<int, self>
     */
    public static function accessGranting(): array
    {
        return [
            self::TRIALING,
            self::ACTIVE,
            self::PAST_DUE,
        ];
    }
}