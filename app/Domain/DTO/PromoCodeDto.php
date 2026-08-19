<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use App\Domain\Enum\PromotionTypeEnum;
use Carbon\Carbon;

readonly class PromoCodeDto
{
    public function __construct(
        public int $id,
        public string $code,
        public PromotionTypeEnum $type,
        public int $discountValue,
        public Carbon $expiresAt,
        public int $maxUsages,
    ) {
    }
}
