<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Carbon\Carbon;
use Money\Money;

readonly class SubscriptionDto
{
    public function __construct(
        public int $id,
        public int $userId,
        public string $email,
        public string $cardToken,
        public Money $price,
        public Carbon $nextRenewal,
    ) {}
}
