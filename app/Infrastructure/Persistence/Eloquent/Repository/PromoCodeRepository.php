<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\DTO\PromoCodeDto;
use App\Infrastructure\Persistence\Eloquent\Entity\PromoCodeEntity;

class PromoCodeRepository
{
    public function findByCodeForUpdate(string $code): PromoCodeDto
    {
        $model = PromoCodeEntity::query()
            ->where('code', $code)
            ->lockForUpdate()
            ->firstOrFail();

        return new PromoCodeDto(
            id: $model->id,
            code: $model->code,
            type: $model->type,
            discountValue: $model->discount_value,
            expiresAt: $model->expires_at,
            maxUsages: $model->max_usages,
        );
    }
}
