<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Entity;

use App\Infrastructure\Persistence\Cast\MoneyCast;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Money\Money;

/**
 * @property int $id
 * @property int $user_id
 * @property string $card_token
 * @property Money $price_amount
 * @property Carbon $next_renewal
 * @property Carbon|null $card_expires_at
 * @property bool $active
 * @property UserEntity $user
 */
class SubscriptionEntity extends Model
{
    protected $table = 'subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_amount' => MoneyCast::class,
            'next_renewal' => 'datetime',
            'card_expires_at' => 'date',
            'active' => 'boolean',
        ];
    }

    /** @return BelongsTo<UserEntity, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            UserEntity::class,
            'user_id',
        );
    }
}
