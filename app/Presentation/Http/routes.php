<?php

declare(strict_types=1);

use App\Presentation\Http\Controller\ExpiringCardsController;
use App\Presentation\Http\Controller\PromotionController;
use App\Presentation\Http\Controller\PromotionStatsController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')
    ->group(function (): void {
        Route::post(
            '/carts/{cartId}/promotion',
            PromotionController::class,
        );

        Route::prefix('dashboard')
            ->group(function (): void {
                Route::get(
                    '/promotions/stats',
                    PromotionStatsController::class,
                );

                Route::get(
                    '/subscriptions/expiring-cards',
                    ExpiringCardsController::class,
                );
            });
    });
