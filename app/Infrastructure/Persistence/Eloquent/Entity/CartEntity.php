<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Entity;

use App\Infrastructure\Persistence\Cast\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Money\Money;

/**
 * @property int $id
 * @property Money $total_amount
 */
class CartEntity extends Model
{
    protected $table = 'carts';

    protected $fillable = [
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => MoneyCast::class,
        ];
    }

    /** @return HasMany<PromoUsageEntity, $this> */
    public function promoUsages(): HasMany
    {
        return $this->hasMany(PromoUsageEntity::class);
    }
}
