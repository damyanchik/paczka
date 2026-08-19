<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Entity;

use App\Infrastructure\Persistence\Cast\MoneyCast;
use Illuminate\Database\Eloquent\Model;

class OrderEntity extends Model
{
    protected $table = 'orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_amount' => MoneyCast::class,
        ];
    }
}
