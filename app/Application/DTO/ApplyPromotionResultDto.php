<?php

declare(strict_types=1);

namespace App\Application\DTO;

use Money\Money;

readonly class ApplyPromotionResultDto
{
    public function __construct(
        public Money $discount,
        public Money $newTotal,
    ) {}
}
