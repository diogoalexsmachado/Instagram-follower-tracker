<?php

namespace App\Providers;

use App\Services\TrackerConfig;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TrackerConfig::class, fn () => new TrackerConfig());
    }

    public function boot(): void
    {
        //
    }
}
