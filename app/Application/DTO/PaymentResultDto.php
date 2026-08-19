<?php

declare(strict_types=1);

namespace App\Application\DTO;

readonly class PaymentResultDto
{
    public function __construct(
        public string $paymentId,
    ) {}
}
