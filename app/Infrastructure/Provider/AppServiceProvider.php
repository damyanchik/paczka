<?php

namespace App\Infrastructure\Provider;

use App\Application\Contract\PaymentGateway;
use App\Payment\HttpPaymentGateway;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            PaymentGateway::class,
            HttpPaymentGateway::class,
        );

        $this->mergeConfigFrom(app_path('Resource/config/services.php'), 'services');
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(app_path('Presentation/Http/routes.php'));

        $this->schedules();
    }

    private function schedules(): void
    {
        Schedule::command('subscriptions:renew')
            ->hourly()
            ->withoutOverlapping();
    }
}
