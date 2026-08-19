<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Entity;

use Illuminate\Database\Eloquent\Model;

class PromoUsageEntity extends Model
{
    protected $table = 'promo_usages';

    protected $fillable = [
        'promo_code_id',
        'cart_id',
        'email',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }
}
