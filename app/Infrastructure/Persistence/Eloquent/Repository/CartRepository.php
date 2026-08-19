<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repository;

use App\Domain\DTO\CartDto;
use App\Infrastructure\Persistence\Eloquent\Entity\CartEntity;
use Money\Money;

class CartRepository
{
    public function findByIdForUpdate(int $id): CartDto
    {
        $model = CartEntity::query()
            ->lockForUpdate()
            ->findOrFail($id);

        return new CartDto(
            id: $model->id,
            total: $model->total_amount,
        );
    }

    public function updateTotal(
        int $cartId,
        Money $total,
    ): void
    {
        $cart = CartEntity::query()->findOrFail($cartId);

        $cart->update([
            'total_amount' => $total,
        ]);
    }
}
