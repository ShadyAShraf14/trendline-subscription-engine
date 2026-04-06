<?php

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
}