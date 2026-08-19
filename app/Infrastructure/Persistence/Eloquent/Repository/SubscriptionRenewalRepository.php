<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\DTO\SubscriptionRenewalDto;
use App\Domain\Enum\SubscriptionRenewalStatusEnum;
use App\Infrastructure\Persistence\Eloquent\Entity\SubscriptionRenewalEntity;
use Carbon\Carbon;

readonly class SubscriptionRenewalRepository
{
    public function getOrCreate(
        int $subscriptionId,
        Carbon $renewalAt,
        string $idempotencyKey,
    ): SubscriptionRenewalDto {
        $entity = SubscriptionRenewalEntity::query()->firstOrCreate(
            [
                'subscription_id' => $subscriptionId,
                'renewal_at' => $renewalAt,
            ],
            [
                'idempotency_key' => $idempotencyKey,
                'status' => SubscriptionRenewalStatusEnum::PENDING,
            ],
        );

        return new SubscriptionRenewalDto(
            id: $entity->id,
            status: $entity->status,
            idempotencyKey: $entity->idempotency_key,
        );
    }

    public function markSucceeded(
        int $renewalId,
        string $paymentId,
    ): void {
        SubscriptionRenewalEntity::query()
            ->whereKey($renewalId)
            ->update([
                'status' => SubscriptionRenewalStatusEnum::SUCCEED,
                'provider_payment_id' => $paymentId,
                'error' => null,
            ]);
    }

    public function markFailed(
        int $renewalId,
        string $error,
    ): void {
        SubscriptionRenewalEntity::query()
            ->whereKey($renewalId)
            ->update([
                'status' => SubscriptionRenewalStatusEnum::FAILED,
                'error' => $error,
            ]);
    }
}
