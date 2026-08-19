<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Query\GetPromotionStats;
use App\Presentation\Http\Request\GetPromotionStatsRequest;
use Illuminate\Http\JsonResponse;

readonly class PromotionStatsController
{
    public function __construct(
        private GetPromotionStats $getPromotionStats,
    ) {}

    public function __invoke(GetPromotionStatsRequest $request): JsonResponse
    {
        $stats = $this->getPromotionStats->execute(
            code: $request->code(),
        );

        return response()->json(
            $stats->map(
                static fn ($stat): array => [
                    'code' => $stat->code,
                    'type' => $stat->type->value,
                    'discount_value' => $stat->discountValue,
                    'usages' => $stat->usages,
                    'cart_sum' => $stat->cartSum->getAmount(),
                    'currency' => 'PLN',
                ]
            )
        );
    }
}
