<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Infrastructure\Persistence\Eloquent\Repository\PromotionStatsRepository;
use Illuminate\Support\Collection;

readonly class GetPromotionStats
{
    public function __construct(
        private PromotionStatsRepository $repository,
    ) {
    }

    public function execute(string $code): Collection
    {
        return $this->repository->searchByCode($code);
    }
}
