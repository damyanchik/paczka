<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controller;

use App\Application\Action\ApplyPromotion;
use App\Presentation\Http\Request\ApplyPromotionRequest;
use Illuminate\Http\JsonResponse;

readonly class PromotionController
{
    public function __construct(private ApplyPromotion $applyPromotion) {}

    public function __invoke(
        int $cartId,
        ApplyPromotionRequest $request,
    ): JsonResponse {
        $result = $this->applyPromotion->execute(
            cartId: $cartId,
            code: $request->code(),
            email: $request->email(),
        );

        return response()->json([
            'discount' => $result->discount->getAmount(),
            'new_total' => $result->newTotal->getAmount(),
            'currency' => $result->newTotal->getCurrency()->getCode(),
        ]);
    }
}
