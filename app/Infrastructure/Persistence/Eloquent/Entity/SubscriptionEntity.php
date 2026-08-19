<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Entity;

use App\Infrastructure\Persistence\Cast\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEntity extends Model
{
    protected $table = 'subscriptions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'price_amount' => MoneyCast::class,
            'next_renewal' => 'datetime',
            'active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            UserEntity::class,
            'user_id',
        );
    }
}
