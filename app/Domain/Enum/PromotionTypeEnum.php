<?php

declare(strict_types=1);

namespace App\Domain\Enum;

enum PromotionTypeEnum: string
{
    case PERCENT = 'percent';
    case AMOUNT = 'amount';
}
