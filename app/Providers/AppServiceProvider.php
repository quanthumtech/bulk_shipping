<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ChatwootService;
use App\Services\SystemLogService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ChatwootService
        $this->app->singleton(ChatwootService::class, function ($app) {
            return new ChatwootService();
        });

        // WebhookLogService
        $this->app->singleton('WebhookLogService', function ($app) {
            return new \App\Services\WebhookLogService();
        });

        // SystemLogService
        $this->app->singleton(SystemLogService::class, function ($app) {
            return new SystemLogService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
