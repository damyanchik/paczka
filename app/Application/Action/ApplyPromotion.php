<?php

declare(strict_types=1);

namespace App\Application\Action;

use App\Domain\Calculator\DiscountCalculator;
use App\Domain\Validator\PromotionValidator;
use App\Infrastructure\Persistence\Eloquent\Repository\CartRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\PromoCodeRepository;
use App\Infrastructure\Persistence\Eloquent\Repository\PromoUsageRepository;
use App\Application\DTO\ApplyPromotionResultDto;
use Illuminate\Database\ConnectionInterface;
use Throwable;

readonly class ApplyPromotion
{
    public function __construct(
        private ConnectionInterface $connection,
        private PromoCodeRepository $promoCodeRepository,
        private CartRepository $cartRepository,
        private PromoUsageRepository $promoUsageRepository,
        private PromotionValidator $promotionValidator,
        private DiscountCalculator $discountCalculator,
    ) {}

    /** @throws Throwable */
    public function execute(
        int $cartId,
        string $code,
        string $email,
    ): ApplyPromotionResultDto
    {
        return $this->connection->transaction(
            function () use ($cartId, $code, $email): ApplyPromotionResultDto {
                $promoCodeDto = $this->promoCodeRepository
                    ->findByCodeForUpdate($code);

                $cartDto = $this->cartRepository
                    ->findByIdForUpdate($cartId);

                $codeUsageCount = $this->promoUsageRepository
                    ->countForPromoCode($promoCodeDto->id);

                $alreadyUsedForCurrentCart = $this->promoUsageRepository
                    ->existsForCart(
                        promoCodeId: $promoCodeDto->id,
                        cartId: $cartDto->id,
                    );

                $this->promotionValidator->validate(
                    promoCode: $promoCodeDto,
                    usageCount: $codeUsageCount,
                    alreadyUsedForCart: $alreadyUsedForCurrentCart,
                );

                $discount = $this->discountCalculator->calculate(
                    cartTotal: $cartDto->total,
                    type: $promoCodeDto->type,
                    discountValue: $promoCodeDto->discountValue,
                );

                $newTotalPrice = $cartDto->total->subtract($discount);

                $this->cartRepository->updateTotal(
                    cartId: $cartDto->id,
                    total: $newTotalPrice,
                );

                $this->promoUsageRepository->create(
                    promoCodeId: $promoCodeDto->id,
                    cartId: $cartDto->id,
                    email: $email,
                );

                return new ApplyPromotionResultDto(
                    discount: $discount,
                    newTotal: $newTotalPrice,
                );
            }
        );
    }
}
