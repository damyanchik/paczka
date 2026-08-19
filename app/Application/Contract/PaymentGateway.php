<?php

declare(strict_types=1);

namespace App\Application\Contract;

use App\Application\DTO\PaymentResultDto;
use Money\Money;

interface PaymentGateway
{
    public function charge(
        string $cardToken,
        Money $amount,
        string $idempotencyKey,
    ): PaymentResultDto;
}
