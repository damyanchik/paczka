<?php

declare(strict_types=1);

namespace App\Test\Feature;

use App\Domain\Enum\PromotionTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Entity\CartEntity;
use App\Infrastructure\Persistence\Eloquent\Entity\PromoCodeEntity;
use App\Infrastructure\Persistence\Eloquent\Entity\PromoUsageEntity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Money\Money;
use Tests\TestCase;

class PromotionStatsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_promotion_statistics(): void
    {
        $promoCode = PromoCodeEntity::query()->create([
            'code' => 'PROMO10',
            'type' => PromotionTypeEnum::PERCENT,
            'discount_value' => 10,
            'expires_at' => Carbon::parse('2026-12-31'),
            'max_usages' => 100,
        ]);

        $firstCart = CartEntity::query()->create([
            'total_amount' => Money::PLN(10000),
        ]);

        $secondCart = CartEntity::query()->create([
            'total_amount' => Money::PLN(20000),
        ]);

        PromoUsageEntity::query()->create([
            'promo_code_id' => $promoCode->id,
            'cart_id' => $firstCart->id,
            'email' => 'first@example.com',
            'used_at' => Carbon::now(),
        ]);

        PromoUsageEntity::query()->create([
            'promo_code_id' => $promoCode->id,
            'cart_id' => $secondCart->id,
            'email' => 'second@example.com',
            'used_at' => Carbon::now(),
        ]);

        $response = $this->getJson(
            '/api/dashboard/promotions/stats?code=PROMO'
        );

        $response
            ->assertOk()
            ->assertJson([
                [
                    'code' => 'PROMO10',
                    'type' => 'percent',
                    'discount_value' => 10,
                    'usages' => 2,
                    'cart_sum' => '30000',
                    'currency' => 'PLN',
                ],
            ]);
    }
}
