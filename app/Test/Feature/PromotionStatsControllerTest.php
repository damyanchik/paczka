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

final class PromotionStatsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_promotion_statistics(): void
    {
        $promoCode = new PromoCodeEntity();
        $promoCode->code = 'PROMO10';
        $promoCode->type = PromotionTypeEnum::PERCENT;
        $promoCode->discount_value = 10;
        $promoCode->expires_at = Carbon::parse('2026-12-31');
        $promoCode->max_usages = 100;
        $promoCode->save();

        $firstCart = new CartEntity();
        $firstCart->total_amount = Money::PLN(10000);
        $firstCart->save();

        $secondCart = new CartEntity();
        $secondCart->total_amount = Money::PLN(20000);
        $secondCart->save();

        $firstUsage = new PromoUsageEntity();
        $firstUsage->promo_code_id = $promoCode->id;
        $firstUsage->cart_id = $firstCart->id;
        $firstUsage->email = 'first@example.com';
        $firstUsage->used_at = Carbon::now();
        $firstUsage->save();

        $secondUsage = new PromoUsageEntity();
        $secondUsage->promo_code_id = $promoCode->id;
        $secondUsage->cart_id = $secondCart->id;
        $secondUsage->email = 'second@example.com';
        $secondUsage->used_at = Carbon::now();
        $secondUsage->save();

        $response = $this->getJson(
            '/api/promotions/stats?code=PROMO'
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
