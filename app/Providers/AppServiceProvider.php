<?php

namespace App\Providers;

use App\Support\TestDatabaseGuard;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('testing')) {
            if ($this->app->configurationIsCached()) {
                throw new \RuntimeException('Test ditolak: configuration cache masih aktif.');
            }
            TestDatabaseGuard::assertSafe(config('database'));
            // Explicit requests for an application connection must fail in tests.
            config(['database.connections' => ['testing' => config('database.connections.testing')]]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
