<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Entity;

use App\Domain\Enum\SubscriptionRenewalStatusEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property SubscriptionRenewalStatusEnum $status
 * @property string $idempotency_key
 */
class SubscriptionRenewalEntity extends Model
{
    protected $table = 'subscription_renewals';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'renewal_at' => 'datetime',
            'status' => SubscriptionRenewalStatusEnum::class,
        ];
    }
}
