<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\SmsGateway;
use App\Services\NullSmsGateway;
use App\Services\MramSmsGateway;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsGateway::class, MramSmsGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
    }
}
