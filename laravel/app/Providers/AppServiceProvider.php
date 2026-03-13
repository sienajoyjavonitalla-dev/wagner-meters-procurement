<?php

namespace App\Providers;

use App\Services\DigiKeyClient;
use App\Services\Element14Client;
use App\Services\GeminiResearchService;
use App\Services\MouserClient;
use App\Services\VendorApiResearchService;
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
        $this->app->singleton(DigiKeyClient::class, fn () => DigiKeyClient::fromConfig());
        $this->app->singleton(MouserClient::class, fn () => MouserClient::fromConfig());
        $this->app->singleton(Element14Client::class, fn () => Element14Client::fromConfig());
        $this->app->singleton(VendorApiResearchService::class, function () {
            return new VendorApiResearchService(
                $this->app->make(DigiKeyClient::class),
                $this->app->make(MouserClient::class),
                $this->app->make(Element14Client::class),
            );
        });
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
