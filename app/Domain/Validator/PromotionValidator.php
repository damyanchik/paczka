<?php

declare(strict_types=1);

namespace App\Domain\Validator;

use App\Domain\DTO\PromoCodeDto;
use Carbon\Carbon;
use DomainException;

class PromotionValidator
{
    public function validate(
        PromoCodeDto $promoCode,
        int $usageCount,
        bool $alreadyUsedForCart,
    ): void {
        if ($promoCode->expiresAt->isBefore(Carbon::today())) {
            throw new DomainException('Promotion has expired.');
        }

        if ($usageCount >= $promoCode->maxUsages) {
            throw new DomainException(
                'Promotion usage limit has been reached.'
            );
        }

        if ($alreadyUsedForCart) {
            throw new DomainException(
                'Promotion has already been used for this cart.'
            );
        }
    }
}
