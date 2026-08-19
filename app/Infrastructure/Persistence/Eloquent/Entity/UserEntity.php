<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Entity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $email
 */
class UserEntity extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    /** @return HasMany<SubscriptionEntity, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(SubscriptionEntity::class, 'user_id');
    }
}
