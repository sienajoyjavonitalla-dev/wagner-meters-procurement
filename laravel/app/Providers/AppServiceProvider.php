<?php

namespace App\Providers;

use App\Services\ClaudeResearchService;
use App\Services\DigiKeyClient;
use App\Services\MouserClient;
use App\Services\NexarClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClaudeResearchService::class, fn () => ClaudeResearchService::fromConfig());
        $this->app->singleton(DigiKeyClient::class, fn () => DigiKeyClient::fromConfig());
        $this->app->singleton(MouserClient::class, fn () => MouserClient::fromConfig());
        $this->app->singleton(NexarClient::class, fn () => NexarClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
