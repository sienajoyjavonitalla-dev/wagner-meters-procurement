<?php

namespace App\Providers;

use App\Services\GeminiResearchService;
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
        $this->app->singleton(GeminiResearchService::class, fn () => GeminiResearchService::fromConfig());
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
