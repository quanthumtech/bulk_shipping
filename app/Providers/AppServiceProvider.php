<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\ChatwootService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ChatwootService::class, function ($app) {
            return new ChatwootService();
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
