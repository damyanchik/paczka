<?php

declare(strict_types=1);

namespace App\Test\Feature;

use App\Infrastructure\Persistence\Eloquent\Entity\SubscriptionEntity;
use App\Infrastructure\Persistence\Eloquent\Entity\UserEntity;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Money\Money;
use Tests\TestCase;

class ExpiringCardsControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_only_subscriptions_with_cards_expiring_within_30_days(): void
    {
        Carbon::setTestNow('2026-08-19 12:00:00');

        $user = UserEntity::query()->create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'test-password',
        ]);

        SubscriptionEntity::query()->create([
            'user_id' => $user->id,
            'card_token' => 'card-1',
            'card_expires_at' => Carbon::parse('2026-08-31'),
            'price_amount' => Money::PLN(5000),
            'next_renewal' => Carbon::parse('2026-08-26'),
            'active' => true,
        ]);

        SubscriptionEntity::query()->create([
            'user_id' => $user->id,
            'card_token' => 'card-2',
            'card_expires_at' => Carbon::parse('2026-10-31'),
            'price_amount' => Money::PLN(5000),
            'next_renewal' => Carbon::parse('2026-08-26'),
            'active' => true,
        ]);

        $response = $this->getJson(
            '/api/dashboard/subscriptions/expiring-cards'
        );

        $response
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'subscription_id' => 1,
                    'user_id' => $user->id,
                    'email' => 'customer@example.com',
                    'card_expires_at' => '2026-08-31',
                    'days_until_expiration' => 12,
                ],
            ]);
    }
}
