<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallySync;
use Modules\Tally\Services\TallyConnectionManager;
use Modules\Tally\Services\TallyHttpClient;

class ProcessConflictsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $connectionCode,
        public readonly int $batchSize = 20,
    ) {}

    public function handle(TallyConnectionManager $manager): void
    {
        $client = $manager->resolve($this->connectionCode);
        app()->instance(TallyHttpClient::class, $client);

        $connection = TallyConnection::where('code', $this->connectionCode)->first();

        if (! $connection) {
            return;
        }

        // Get resolved conflicts that have a strategy and are pending re-sync
        $resolved = TallySync::where('tally_connection_id', $connection->id)
            ->where('sync_status', 'pending')
            ->whereNotNull('resolution_strategy')
            ->whereNotNull('resolved_at')
            ->limit($this->batchSize)
            ->get();

        foreach ($resolved as $sync) {
            $this->applyResolution($sync, $connection);
        }
    }

    private function applyResolution(TallySync $sync, TallyConnection $connection): void
    {
        $sync->markInProgress();

        try {
            match ($sync->resolution_strategy) {
                'erp_wins' => $this->pushLocalToTally($sync),
                'tally_wins' => $this->pullTallyToLocal($sync),
                'newest_wins' => $this->resolveByTimestamp($sync),
                default => null, // 'manual' and 'merge' require human intervention
            };
        } catch (\Throwable $e) {
            $sync->markFailed("Conflict resolution failed: {$e->getMessage()}");
        }
    }

    private function pushLocalToTally(TallySync $sync): void
    {
        // Re-queue as outbound sync
        SyncToTallyJob::dispatch($sync->connection->code);
        $sync->markCompleted();
    }

    private function pullTallyToLocal(TallySync $sync): void
    {
        // Re-queue as inbound sync
        SyncFromTallyJob::dispatch($sync->connection->code, force: true);
        $sync->markCompleted();
    }

    private function resolveByTimestamp(TallySync $sync): void
    {
        // Compare updated_at of local entity vs last Tally sync
        $entity = $sync->entity();

        if (! $entity) {
            $sync->markFailed('Entity not found');

            return;
        }

        $localUpdatedAt = $entity->updated_at;
        $tallyLastSync = $sync->last_synced_at;

        if ($localUpdatedAt && $tallyLastSync && $localUpdatedAt->gt($tallyLastSync)) {
            $this->pushLocalToTally($sync);
        } else {
            $this->pullTallyToLocal($sync);
        }
    }
}
