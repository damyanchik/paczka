<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\Enum\PromotionTypeEnum;
use Money\Money;

class PromotionStatsDto
{
    public function __construct(
        public string            $code,
        public PromotionTypeEnum $type,
        public int               $discountValue,
        public int               $usages,
        public Money             $cartSum,
    ) {
    }
}
