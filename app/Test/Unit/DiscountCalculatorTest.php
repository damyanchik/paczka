<?php

declare(strict_types=1);

namespace App\Test\Unit;

use App\Domain\Calculator\DiscountCalculator;
use App\Domain\Enum\PromotionTypeEnum;
use InvalidArgumentException;
use Money\Money;
use PHPUnit\Framework\TestCase;

class DiscountCalculatorTest extends TestCase
{
    private DiscountCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new DiscountCalculator;
    }

    public function test_it_calculates_percent_discount(): void
    {
        $discount = $this->calculator->calculate(
            cartTotal: Money::PLN(10000),
            type: PromotionTypeEnum::PERCENT,
            discountValue: 10,
        );

        self::assertTrue(
            $discount->equals(Money::PLN(1000))
        );
    }

    public function test_it_calculates_amount_discount(): void
    {
        $discount = $this->calculator->calculate(
            cartTotal: Money::PLN(10000),
            type: PromotionTypeEnum::AMOUNT,
            discountValue: 2000,
        );

        self::assertTrue(
            $discount->equals(Money::PLN(2000))
        );
    }

    public function test_amount_discount_cannot_be_greater_than_cart_total(): void
    {
        $discount = $this->calculator->calculate(
            cartTotal: Money::PLN(5000),
            type: PromotionTypeEnum::AMOUNT,
            discountValue: 10000,
        );

        self::assertTrue(
            $discount->equals(Money::PLN(5000))
        );
    }

    public function test_percent_discount_cannot_be_greater_than_100(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Percentage discount cannot be greater than 100.'
        );

        $this->calculator->calculate(
            cartTotal: Money::PLN(10000),
            type: PromotionTypeEnum::PERCENT,
            discountValue: 101,
        );
    }

    public function test_discount_cannot_be_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Discount value cannot be negative.'
        );

        $this->calculator->calculate(
            cartTotal: Money::PLN(10000),
            type: PromotionTypeEnum::AMOUNT,
            discountValue: -1,
        );
    }

    public function test_100_percent_discount_returns_full_cart_total(): void
    {
        $discount = $this->calculator->calculate(
            cartTotal: Money::PLN(10000),
            type: PromotionTypeEnum::PERCENT,
            discountValue: 100,
        );

        self::assertTrue(
            $discount->equals(Money::PLN(10000))
        );
    }
}
