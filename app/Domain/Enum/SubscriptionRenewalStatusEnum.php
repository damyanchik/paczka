<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum SubscriptionRenewalStatusEnum: string
{
    case PENDING = 'pending';
    case SUCCEED = 'succeeded';
    case FAILED = 'failed';
}
