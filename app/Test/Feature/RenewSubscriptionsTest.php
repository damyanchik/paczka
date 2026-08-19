<?php

declare(strict_types=1);

namespace App\Test\Feature;

use App\Application\Action\RenewSubscriptions;
use App\Application\Contract\PaymentGateway;
use App\Application\DTO\PaymentResultDto;
use App\Infrastructure\Persistence\Eloquent\Entity\SubscriptionEntity;
use App\Infrastructure\Persistence\Eloquent\Entity\UserEntity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Money\Money;
use Tests\TestCase;

class RenewSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_is_not_charged_twice(): void
    {
        CarbonImmutable::setTestNow(
            CarbonImmutable::parse('2026-08-19 12:00:00')
        );

        $user = UserEntity::query()->create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'test-password',
        ]);

        $subscription = new SubscriptionEntity();
        $subscription->user_id = $user->id;
        $subscription->card_token = 'card-token';
        $subscription->price_amount = Money::PLN(5000);
        $subscription->next_renewal = CarbonImmutable::now();
        $subscription->active = true;
        $subscription->save();

        $paymentGateway = new class implements PaymentGateway {
            public int $charges = 0;

            public function charge(
                string $cardToken,
                Money $amount,
                string $idempotencyKey,
            ): PaymentResultDto {
                $this->charges++;

                return new PaymentResultDto(
                    paymentId: 'payment-123',
                );
            }
        };

        $this->app->instance(
            PaymentGateway::class,
            $paymentGateway,
        );

        $action = $this->app->make(RenewSubscriptions::class);

        $action->execute();
        $action->execute();

        self::assertSame(
            1,
            $paymentGateway->charges,
        );

        $this->assertDatabaseCount(
            'subscription_renewals',
            1,
        );

        $this->assertDatabaseCount(
            'orders',
            1,
        );

        $this->assertDatabaseHas(
            'orders',
            [
                'subscription_id' => $subscription->id,
                'payment_id' => 'payment-123',
                'total_amount' => 5000,
            ],
        );
    }
}
