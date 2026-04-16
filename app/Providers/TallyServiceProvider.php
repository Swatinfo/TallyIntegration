<?php

namespace App\Providers;

use App\Services\Tally\TallyConnectionManager;
use App\Services\Tally\TallyHttpClient;
use Illuminate\Support\ServiceProvider;

class TallyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            base_path('config/tally.php'),
            'tally',
        );

        $this->app->singleton(TallyConnectionManager::class);

        // Default binding: uses config values unless overridden by middleware
        $this->app->bind(TallyHttpClient::class, function () {
            return TallyHttpClient::fromConfig();
        });
    }

    public function boot(): void
    {
        //
    }
}
