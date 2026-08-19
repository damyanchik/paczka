<?php

declare(strict_types=1);

namespace App\Test\Feature;

use App\Application\Action\ApplyPromotion;
use App\Domain\Calculator\DiscountCalculator;
use App\Domain\Enum\PromotionTypeEnum;
use App\Domain\Validator\PromotionValidator;
use App\Infrastructure\Persistence\Eloquent\Entity\CartEntity;
use App\Infrastructure\Persistence\Eloquent\Entity\PromoCodeEntity;
use App\Infrastructure\Persistence\Eloquent\Entity\PromoUsageEntity;
use App\Infrastructure\Persistence\Eloquent\Repository\CartRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\PromoCodeRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\PromoUsageRepository;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Money\Money;
use Tests\TestCase;

class ApplyPromotionTest extends TestCase
{
    use RefreshDatabase;

    private ApplyPromotion $applyPromotion;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-19 12:00:00');

        $this->applyPromotion = new ApplyPromotion(
            connection: DB::connection(),
            promoCodeRepository: new PromoCodeRepository(),
            cartRepository: new CartRepository(),
            promoUsageRepository: new PromoUsageRepository(),
            promotionValidator: new PromotionValidator(),
            discountCalculator: new DiscountCalculator(),
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_applies_percentage_promotion(): void
    {
        $cart = $this->createCart(
            total: Money::PLN(10000),
        );

        $promoCode = $this->createPromoCode(
            code: 'PROMO10',
            type: PromotionTypeEnum::PERCENT,
            discountValue: 10,
            expiresAt: Carbon::parse('2026-08-31'),
            maxUsages: 100,
        );

        $result = $this->applyPromotion->execute(
            cartId: $cart->id,
            code: 'PROMO10',
            email: 'customer@example.com',
        );

        self::assertTrue(
            $result->discount->equals(Money::PLN(1000))
        );

        self::assertTrue(
            $result->newTotal->equals(Money::PLN(9000))
        );

        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'total_amount' => 9000,
        ]);

        $this->assertDatabaseHas('promo_usages', [
            'promo_code_id' => $promoCode->id,
            'cart_id' => $cart->id,
            'email' => 'customer@example.com',
        ]);
    }

    public function test_it_rejects_expired_promotion(): void
    {
        $cart = $this->createCart(
            total: Money::PLN(10000),
        );

        $this->createPromoCode(
            code: 'EXPIRED10',
            type: PromotionTypeEnum::PERCENT,
            discountValue: 10,
            expiresAt: Carbon::parse('2026-08-18'),
            maxUsages: 100,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Promotion has expired.');

        try {
            $this->applyPromotion->execute(
                cartId: $cart->id,
                code: 'EXPIRED10',
                email: 'customer@example.com',
            );
        } finally {
            $this->assertDatabaseHas('carts', [
                'id' => $cart->id,
                'total_amount' => 10000,
            ]);

            $this->assertDatabaseCount('promo_usages', 0);
        }
    }

    public function test_it_rejects_promotion_when_usage_limit_is_reached(): void
    {
        $cart = $this->createCart(
            total: Money::PLN(10000),
        );

        $anotherCart = $this->createCart(
            total: Money::PLN(10000),
        );

        $promoCode = $this->createPromoCode(
            code: 'LIMITED',
            type: PromotionTypeEnum::AMOUNT,
            discountValue: 2000,
            expiresAt: Carbon::parse('2026-08-31'),
            maxUsages: 1,
        );

        $this->createPromoUsage(
            promoCodeId: $promoCode->id,
            cartId: $anotherCart->id,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Promotion usage limit has been reached.'
        );

        $this->applyPromotion->execute(
            cartId: $cart->id,
            code: 'LIMITED',
            email: 'customer@example.com',
        );
    }

    public function test_it_rejects_promotion_already_used_for_cart(): void
    {
        $cart = $this->createCart(
            total: Money::PLN(10000),
        );

        $promoCode = $this->createPromoCode(
            code: 'ONCE',
            type: PromotionTypeEnum::AMOUNT,
            discountValue: 2000,
            expiresAt: Carbon::parse('2026-08-31'),
            maxUsages: 100,
        );

        $this->createPromoUsage(
            promoCodeId: $promoCode->id,
            cartId: $cart->id,
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Promotion has already been used for this cart.'
        );

        $this->applyPromotion->execute(
            cartId: $cart->id,
            code: 'ONCE',
            email: 'customer@example.com',
        );
    }

    private function createCart(Money $total): CartEntity
    {
        return CartEntity::query()->create([
            'total_amount' => $total,
        ]);
    }

    private function createPromoCode(
        string $code,
        PromotionTypeEnum $type,
        int $discountValue,
        Carbon $expiresAt,
        int $maxUsages,
    ): PromoCodeEntity {
        return PromoCodeEntity::query()->create([
            'code' => $code,
            'type' => $type,
            'discount_value' => $discountValue,
            'expires_at' => $expiresAt,
            'max_usages' => $maxUsages,
        ]);
    }

    private function createPromoUsage(
        int $promoCodeId,
        int $cartId,
    ): PromoUsageEntity {
        return PromoUsageEntity::query()->create([
            'promo_code_id' => $promoCodeId,
            'cart_id' => $cartId,
            'email' => 'previous@example.com',
            'used_at' => Carbon::now(),
        ]);
    }
}
