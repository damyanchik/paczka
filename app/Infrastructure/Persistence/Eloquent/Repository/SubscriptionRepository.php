<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\DTO\ExpiringCardDto;
use App\Domain\DTO\SubscriptionDto;
use App\Infrastructure\Persistence\Eloquent\Entity\SubscriptionEntity;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class SubscriptionRepository
{
    /** @return Collection<int, SubscriptionDto> */
    public function findDue(): Collection
    {
        return SubscriptionEntity::query()
            ->with('user')
            ->where('active', true)
            ->where('next_renewal', '<=', Carbon::now())
            ->get()
            ->map(
                static fn (SubscriptionEntity $subscription): SubscriptionDto =>
                new SubscriptionDto(
                    id: $subscription->id,
                    userId: $subscription->user_id,
                    email: $subscription->user->email,
                    cardToken: $subscription->card_token,
                    price: $subscription->price_amount,
                    nextRenewal: $subscription->next_renewal,
                )
            );
    }

    public function updateNextRenewal(
        int $subscriptionId,
        Carbon $nextRenewal,
    ): void {
        SubscriptionEntity::query()
            ->whereKey($subscriptionId)
            ->update([
                'next_renewal' => $nextRenewal,
            ]);
    }

    /** @return Collection<int, ExpiringCardDto> */
    public function findWithExpiringCards(
        Carbon $from,
        Carbon $to,
    ): Collection {
        return SubscriptionEntity::query()
            ->with('user')
            ->where('active', true)
            ->whereNotNull('card_expires_at')
            ->whereBetween('card_expires_at', [
                $from->toDateString(),
                $to->toDateString(),
            ])
            ->orderBy('card_expires_at')
            ->get()
            ->map(
                static fn (SubscriptionEntity $subscription): ExpiringCardDto =>
                new ExpiringCardDto(
                    subscriptionId: $subscription->id,
                    userId: $subscription->user_id,
                    email: $subscription->user->email,
                    cardExpiresAt: $subscription->card_expires_at,
                )
            );
    }
}
