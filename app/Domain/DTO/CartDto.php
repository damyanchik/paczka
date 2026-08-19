<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Money\Money;

readonly class CartDto
{
    public function __construct(
        public int $id,
        public Money $total,
    ) {}
}
