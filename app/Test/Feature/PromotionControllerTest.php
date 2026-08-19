<?php

declare(strict_types=1);

namespace App\Test\Feature;

use App\Domain\Enum\PromotionTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Entity\CartEntity;
use App\Infrastructure\Persistence\Eloquent\Entity\PromoCodeEntity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Money\Money;
use Tests\TestCase;

class PromotionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_applies_promotion_via_api(): void
    {
        $cart = CartEntity::query()->create([
            'total_amount' => Money::PLN(10000),
        ]);

        $promoCode = PromoCodeEntity::query()->create([
            'code' => 'PROMO10',
            'type' => PromotionTypeEnum::PERCENT,
            'discount_value' => 10,
            'expires_at' => Carbon::parse('2026-08-31'),
            'max_usages' => 100,
        ]);

        $response = $this->postJson(
            "/api/carts/{$cart->id}/promotion",
            [
                'code' => 'PROMO10',
                'email' => 'customer@example.com',
            ],
        );

        $response
            ->assertOk()
            ->assertJson([
                'discount' => '1000',
                'new_total' => '9000',
                'currency' => 'PLN',
            ]);

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

    public function test_code_is_required(): void
    {
        $response = $this->postJson(
            '/api/carts/1/promotion',
            [
                'email' => 'customer@example.com',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    public function test_email_must_be_valid(): void
    {
        $response = $this->postJson(
            '/api/carts/1/promotion',
            [
                'code' => 'PROMO10',
                'email' => 'not-an-email',
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }
}
