<?php

namespace Modules\Tally\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Tally\Console\TallyHealthCommand;
use Modules\Tally\Console\TallySyncCommand;
use Modules\Tally\Jobs\HealthCheckJob;
use Modules\Tally\Jobs\ProcessConflictsJob;
use Modules\Tally\Jobs\SyncAllConnectionsJob;
use Modules\Tally\Jobs\SyncFromTallyJob;
use Modules\Tally\Jobs\SyncToTallyJob;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\TallyConnectionManager;
use Modules\Tally\Services\TallyHttpClient;
use Nwidart\Modules\Support\ModuleServiceProvider;

class TallyServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Tally';

    protected string $nameLower = 'tally';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    protected array $commands = [
        TallyHealthCommand::class,
        TallySyncCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(TallyConnectionManager::class);

        $this->app->bind(TallyHttpClient::class, fn () => TallyHttpClient::fromConfig());
    }

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->job(new HealthCheckJob)->everyFiveMinutes();
        $schedule->job(new SyncAllConnectionsJob)->hourly();

        // Bidirectional sync every 10 min + conflict processing every 5 min
        $schedule->call(function () {
            TallyConnection::where('is_active', true)->each(function ($conn) {
                SyncFromTallyJob::dispatch($conn->code);
                SyncToTallyJob::dispatch($conn->code);
            });
        })->everyTenMinutes();

        $schedule->call(function () {
            TallyConnection::where('is_active', true)->each(function ($conn) {
                ProcessConflictsJob::dispatch($conn->code);
            });
        })->everyFiveMinutes();
    }

    public function boot(): void
    {
        parent::boot();

        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));

        if ($this->app->runningInConsole()) {
            $this->publishes([
                module_path($this->name, 'config/config.php') => config_path('tally.php'),
            ], 'tally-config');
        }
    }
}
