<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use App\Domain\Enum\SubscriptionRenewalStatusEnum;

readonly class SubscriptionRenewalDto
{
    public function __construct(
        public int $id,
        public SubscriptionRenewalStatusEnum $status,
        public string $idempotencyKey,
    ) {}
}
