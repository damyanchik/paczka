<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Infrastructure\Persistence\Eloquent\Entity\OrderEntity;
use Money\Money;

readonly class OrderRepository
{
    public function createForRenewal(
        int $userId,
        int $subscriptionId,
        int $renewalId,
        string $paymentId,
        Money $total,
    ): void {
        OrderEntity::query()->create([
            'user_id' => $userId,
            'subscription_id' => $subscriptionId,
            'subscription_renewal_id' => $renewalId,
            'payment_id' => $paymentId,
            'total_amount' => $total,
        ]);
    }
}
