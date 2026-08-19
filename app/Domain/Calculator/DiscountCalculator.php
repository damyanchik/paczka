<?php

declare(strict_types=1);

namespace App\Domain\Calculator;

use App\Domain\Enum\PromotionTypeEnum;
use InvalidArgumentException;
use Money\Money;

readonly class DiscountCalculator
{
    public function calculate(
        Money $cartTotal,
        PromotionTypeEnum $type,
        int $discountValue,
    ): Money {
        if ($discountValue < 0) {
            throw new InvalidArgumentException('Discount value cannot be negative.');
        }

        $discount = match ($type) {
            PromotionTypeEnum::PERCENT => $this->calculatePercentDiscount(
                cartTotal: $cartTotal,
                percentage: $discountValue,
            ),
            PromotionTypeEnum::AMOUNT => Money::PLN($discountValue),
        };

        return $discount->greaterThan($cartTotal)
            ? $cartTotal
            : $discount;
    }

    private function calculatePercentDiscount(
        Money $cartTotal,
        int $percentage,
    ): Money {
        if ($percentage > 100) {
            throw new InvalidArgumentException(
                'Percentage discount cannot be greater than 100.'
            );
        }

        return $cartTotal
            ->multiply($percentage)
            ->divide(100);
    }
}
