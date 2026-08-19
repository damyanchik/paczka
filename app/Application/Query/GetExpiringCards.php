<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\DTO\ExpiringCardDto;
use App\Infrastructure\Persistence\Eloquent\Repository\SubscriptionRepository;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class GetExpiringCards
{
    private const int EXPIRATION_WINDOW_DAYS = 30;

    public function __construct(
        private SubscriptionRepository $subscriptionRepository,
    ) {}

    /** @return Collection<int, ExpiringCardDto> */
    public function execute(): Collection
    {
        $today = Carbon::today();

        return $this->subscriptionRepository->findWithExpiringCards(
            from: $today,
            to: $today->copy()->addDays(self::EXPIRATION_WINDOW_DAYS),
        );
    }
}
