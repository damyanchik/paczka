<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Query\GetExpiringCards;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

readonly class ExpiringCardsController
{
    public function __construct(
        private GetExpiringCards $getExpiringCards,
    ) {}

    public function __invoke(): JsonResponse
    {
        $cards = $this->getExpiringCards->execute();

        return response()->json(
            $cards->map(
                static fn ($card): array => [
                    'subscription_id' => $card->subscriptionId,
                    'user_id' => $card->userId,
                    'email' => $card->email,
                    'card_expires_at' => $card->cardExpiresAt->toDateString(),
                    'days_until_expiration' => Carbon::today()->diffInDays(
                        $card->cardExpiresAt,
                    ),
                ]
            )
        );
    }
}
