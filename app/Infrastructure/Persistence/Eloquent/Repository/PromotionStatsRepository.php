<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Application\DTO\PromotionStatsDto;
use App\Domain\Enum\PromotionTypeEnum;
use App\Infrastructure\Persistence\Eloquent\Entity\PromoCodeEntity;
use Illuminate\Support\Collection;
use Money\Money;

readonly class PromotionStatsRepository
{
    /** @return Collection<int, PromotionStatsDto> */
    public function searchByCode(string $code): Collection
    {
        return PromoCodeEntity::query()
            ->leftJoin(
                'promo_usages',
                'promo_usages.promo_code_id',
                '=',
                'promo_codes.id',
            )
            ->leftJoin(
                'carts',
                'carts.id',
                '=',
                'promo_usages.cart_id',
            )
            ->where('promo_codes.code', 'like', '%'.$code.'%')
            ->groupBy(
                'promo_codes.id',
                'promo_codes.code',
                'promo_codes.type',
                'promo_codes.discount_value',
            )
            ->selectRaw(
                '
                    promo_codes.code,
                    promo_codes.type,
                    promo_codes.discount_value,
                    COUNT(promo_usages.id) AS usage_count,
                    COALESCE(SUM(carts.total_amount), 0) AS cart_sum
                '
            )
            ->toBase()
            ->get()
            ->map(
                static fn (object $row): PromotionStatsDto => new PromotionStatsDto(
                    code: (string) $row->code,
                    type: PromotionTypeEnum::from((string) $row->type),
                    discountValue: (int) $row->discount_value,
                    usages: (int) $row->usage_count,
                    cartSum: Money::PLN((int) $row->cart_sum),
                )
            );
    }
}
