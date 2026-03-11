<?php

namespace App\Providers;

use App\Services\ClaudeResearchService;
use App\Services\DigiKeyClient;
use App\Services\GeminiResearchService;
use App\Services\MouserClient;
use App\Services\NexarClient;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ClaudeResearchService::class, fn () => ClaudeResearchService::fromConfig());
        $this->app->singleton(GeminiResearchService::class, fn () => GeminiResearchService::fromConfig());
        $this->app->singleton(DigiKeyClient::class, fn () => DigiKeyClient::fromConfig());
        $this->app->singleton(MouserClient::class, fn () => MouserClient::fromConfig());
        $this->app->singleton(NexarClient::class, fn () => NexarClient::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('manage-procurement', fn (User $user) => $user->isAdmin());
        Gate::define('manage-users', fn (User $user) => $user->isSuperAdmin());
        Gate::define('assign-super-admin', fn (User $user) => $user->isSuperAdmin());
    }
}
