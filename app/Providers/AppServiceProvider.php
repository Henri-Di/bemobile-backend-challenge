<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GatewayPaymentInterface;
use App\Contracts\GatewayRepositoryInterface;
use App\Contracts\TransactionRepositoryInterface;
use App\Repositories\Eloquent\EloquentGatewayRepository;
use App\Repositories\Eloquent\EloquentTransactionRepository;
use App\Services\Gateways\GatewayOneService;
use App\Services\Gateways\GatewayTwoService;
use App\Services\PaymentService;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            GatewayRepositoryInterface::class,
            EloquentGatewayRepository::class
        );

        $this->app->bind(
            TransactionRepositoryInterface::class,
            EloquentTransactionRepository::class
        );

        $this->app->when(PaymentService::class)
            ->needs('$gatewayOneService')
            ->give(GatewayOneService::class);

        $this->app->when(PaymentService::class)
            ->needs('$gatewayTwoService')
            ->give(GatewayTwoService::class);
    }

    public function boot(): void
    {
    }
}