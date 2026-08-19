<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Entity;

use App\Domain\Enum\PromotionTypeEnum;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property PromotionTypeEnum $type
 * @property int $discount_value
 * @property Carbon $expires_at
 * @property int $max_usages
 */
class PromoCodeEntity extends Model
{
    protected $table = 'promo_codes';

    protected $fillable = [
        'code',
        'type',
        'discount_value',
        'expires_at',
        'max_usages',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionTypeEnum::class,
            'expires_at' => 'date',
            'discount_value' => 'integer',
            'max_usages' => 'integer',
        ];
    }

    /** @return HasMany<PromoUsageEntity, $this> */
    public function usages(): HasMany
    {
        return $this->hasMany(PromoUsageEntity::class);
    }
}
