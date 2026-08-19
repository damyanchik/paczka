<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Cast;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Money\Money;

/** @implements CastsAttributes<Money, Money> */
readonly class MoneyCast implements CastsAttributes
{
    public function get(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): Money {
        return Money::PLN((int) $value);
    }

    public function set(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): int {
        if (! $value instanceof Money) {
            throw new InvalidArgumentException(
                'Value must be an instance of Money.'
            );
        }

        return (int) $value->getAmount();
    }
}
