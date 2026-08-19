<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\DTO\PromotionStatsDto;
use App\Infrastructure\Persistence\Eloquent\Repository\PromotionStatsRepository;
use Illuminate\Support\Collection;

readonly class GetPromotionStats
{
    public function __construct(
        private PromotionStatsRepository $repository,
    ) {}

    /** @return Collection<int, PromotionStatsDto> */
    public function execute(string $code): Collection
    {
        return $this->repository->searchByCode($code);
    }
}
