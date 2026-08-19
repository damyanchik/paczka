<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Infrastructure\Persistence\Eloquent\Entity\PromoUsageEntity;
use Carbon\Carbon;

readonly class PromoUsageRepository
{
    public function countForPromoCode(int $promoCodeId): int
    {
        return PromoUsageEntity::query()
            ->where('promo_code_id', $promoCodeId)
            ->count();
    }

    public function existsForCart(
        int $promoCodeId,
        int $cartId,
    ): bool
    {
        return PromoUsageEntity::query()
            ->where('promo_code_id', $promoCodeId)
            ->where('cart_id', $cartId)
            ->exists();
    }

    public function create(
        int $promoCodeId,
        int $cartId,
        string $email,
    ): void
    {
        PromoUsageEntity::query()->create([
            'promo_code_id' => $promoCodeId,
            'cart_id' => $cartId,
            'email' => $email,
            'used_at' => Carbon::now(),
        ]);
    }
}
