<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use Carbon\Carbon;

readonly class ExpiringCardDto
{
    public function __construct(
        public int $subscriptionId,
        public int $userId,
        public string $email,
        public Carbon $cardExpiresAt,
    ) {}
}
