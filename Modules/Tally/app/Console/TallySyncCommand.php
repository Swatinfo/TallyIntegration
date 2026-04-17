<?php

namespace Modules\Tally\Console;

use Illuminate\Console\Command;
use Modules\Tally\Jobs\SyncMastersJob;
use Modules\Tally\Models\TallyConnection;

class TallySyncCommand extends Command
{
    protected $signature = 'tally:sync {connection : Connection code} {--type=all : Master type (all, ledger, group, stock-item)}';

    protected $description = 'Sync master data from TallyPrime';

    public function handle(): int
    {
        $code = $this->argument('connection');
        $type = $this->option('type');

        $connection = TallyConnection::where('code', $code)->first();

        if (! $connection) {
            $this->error("Connection '{$code}' not found.");

            return self::FAILURE;
        }

        $this->info("Dispatching sync job for {$connection->name} ({$code}), type: {$type}...");
        SyncMastersJob::dispatch($code, $type);
        $this->info('Sync job dispatched. Check your queue worker for progress.');

        return self::SUCCESS;
    }
}
