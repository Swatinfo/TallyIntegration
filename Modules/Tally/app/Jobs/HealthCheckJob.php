<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tally\Events\TallyConnectionHealthChanged;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\TallyConnectionManager;

class HealthCheckJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TallyConnectionManager $manager): void
    {
        $connections = TallyConnection::where('is_active', true)->get();

        foreach ($connections as $connection) {
            $client = $manager->fromConnection($connection);
            $isHealthy = $client->isConnected();

            TallyConnectionHealthChanged::dispatch($connection, $isHealthy);
        }
    }
}
