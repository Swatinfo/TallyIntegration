<?php

namespace Modules\Tally\Console;

use Illuminate\Console\Command;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\TallyConnectionManager;

class TallyHealthCommand extends Command
{
    protected $signature = 'tally:health {connection? : Connection code (omit to check all)}';

    protected $description = 'Check TallyPrime connection health';

    public function handle(TallyConnectionManager $manager): int
    {
        $code = $this->argument('connection');

        $connections = $code
            ? TallyConnection::where('code', $code)->get()
            : TallyConnection::where('is_active', true)->get();

        if ($connections->isEmpty()) {
            $this->error($code ? "Connection '{$code}' not found." : 'No active connections found.');

            return self::FAILURE;
        }

        $this->table(['Code', 'Name', 'URL', 'Status', 'Companies'], $connections->map(function ($conn) use ($manager) {
            $client = $manager->fromConnection($conn);
            $connected = $client->isConnected();
            $companies = $connected ? implode(', ', $client->getCompanies()) : '-';

            return [
                $conn->code,
                $conn->name,
                $client->getUrl(),
                $connected ? '<fg=green>Connected</>' : '<fg=red>Disconnected</>',
                $companies,
            ];
        }));

        return self::SUCCESS;
    }
}
