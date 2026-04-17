<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tally\Events\TallySyncCompleted;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyGroup;
use Modules\Tally\Models\TallyLedger;
use Modules\Tally\Models\TallyStockItem;
use Modules\Tally\Services\Masters\GroupService;
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\Masters\StockItemService;
use Modules\Tally\Services\SyncTracker;
use Modules\Tally\Services\TallyCompanyService;
use Modules\Tally\Services\TallyConnectionManager;
use Modules\Tally\Services\TallyHttpClient;

class SyncFromTallyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $connectionCode,
        public readonly bool $force = false,
    ) {}

    public function handle(TallyConnectionManager $manager, SyncTracker $tracker): void
    {
        $client = $manager->resolve($this->connectionCode);
        app()->instance(TallyHttpClient::class, $client);

        $connection = TallyConnection::where('code', $this->connectionCode)->first();

        if (! $connection) {
            return;
        }

        // Check AlterIDs for incremental sync
        if (! $this->force) {
            try {
                $company = app(TallyCompanyService::class);
                $changes = $company->hasChangedSince(
                    $connection->last_alter_master_id,
                    $connection->last_alter_voucher_id,
                );

                if (! $changes['masters_changed'] && ! $changes['vouchers_changed']) {
                    TallySyncCompleted::dispatch($this->connectionCode, 'inbound:skipped', 0);

                    return;
                }
            } catch (\Throwable) {
                // Fall through to full sync
            }
        }

        $total = 0;

        // Sync ledgers
        $total += $this->syncLedgers($connection, $tracker);

        // Sync groups
        $total += $this->syncGroups($connection, $tracker);

        // Sync stock items
        $total += $this->syncStockItems($connection, $tracker);

        // Update AlterIDs
        try {
            $company = app(TallyCompanyService::class);
            $ids = $company->getAlterIds();
            $connection->update([
                'last_alter_master_id' => $ids['master_id'],
                'last_alter_voucher_id' => $ids['voucher_id'],
                'last_synced_at' => now(),
            ]);
        } catch (\Throwable) {
            $connection->update(['last_synced_at' => now()]);
        }

        TallySyncCompleted::dispatch($this->connectionCode, 'inbound:masters', $total);
    }

    private function syncLedgers(TallyConnection $connection, SyncTracker $tracker): int
    {
        $tallyLedgers = app(LedgerService::class)->list();
        $count = 0;

        foreach ($tallyLedgers as $tallyData) {
            $name = $tallyData['@attributes']['NAME'] ?? ($tallyData['NAME'] ?? null);

            if (! $name) {
                continue;
            }

            $tallyHash = md5(json_encode($tallyData));

            $ledger = TallyLedger::updateOrCreate(
                ['tally_connection_id' => $connection->id, 'name' => $name],
                [
                    'parent' => $this->extractField($tallyData, 'PARENT'),
                    'closing_balance' => $this->extractNumeric($tallyData, 'CLOSINGBALANCE'),
                    'tally_raw_data' => $tallyData,
                    'data_hash' => $tallyHash,
                ],
            );

            // Track sync record
            $sync = $tracker->track($connection->id, 'ledger', $ledger->id, 'from_tally', 'normal', $name);

            // Detect conflict: check if local also changed
            if ($sync->local_data_hash && $sync->tally_data_hash && $sync->tally_data_hash !== $tallyHash) {
                $currentLocalHash = $ledger->computeDataHash();

                $change = $tracker->detectChange($sync, $currentLocalHash, $tallyHash);

                if ($change === 'conflict') {
                    $sync->markConflict(
                        $ledger->toArray(),
                        $tallyData,
                        ['data_hash_mismatch'],
                    );

                    continue;
                }
            }

            $sync->update([
                'tally_data_hash' => $tallyHash,
                'local_data_hash' => $ledger->computeDataHash(),
                'sync_status' => 'completed',
                'last_synced_at' => now(),
            ]);

            $count++;
        }

        return $count;
    }

    private function syncGroups(TallyConnection $connection, SyncTracker $tracker): int
    {
        $tallyGroups = app(GroupService::class)->list();
        $count = 0;

        foreach ($tallyGroups as $tallyData) {
            $name = $tallyData['@attributes']['NAME'] ?? ($tallyData['NAME'] ?? null);

            if (! $name) {
                continue;
            }

            $group = TallyGroup::updateOrCreate(
                ['tally_connection_id' => $connection->id, 'name' => $name],
                [
                    'parent' => $this->extractField($tallyData, 'PARENT'),
                    'tally_raw_data' => $tallyData,
                    'data_hash' => md5(json_encode($tallyData)),
                ],
            );

            $tracker->track($connection->id, 'group', $group->id, 'from_tally', 'low', $name);
            $count++;
        }

        return $count;
    }

    private function syncStockItems(TallyConnection $connection, SyncTracker $tracker): int
    {
        $tallyItems = app(StockItemService::class)->list();
        $count = 0;

        foreach ($tallyItems as $tallyData) {
            $name = $tallyData['@attributes']['NAME'] ?? ($tallyData['NAME'] ?? null);

            if (! $name) {
                continue;
            }

            $item = TallyStockItem::updateOrCreate(
                ['tally_connection_id' => $connection->id, 'name' => $name],
                [
                    'parent' => $this->extractField($tallyData, 'PARENT'),
                    'base_unit' => $this->extractField($tallyData, 'BASEUNITS'),
                    'closing_balance_value' => $this->extractNumeric($tallyData, 'CLOSINGBALANCE'),
                    'tally_raw_data' => $tallyData,
                    'data_hash' => md5(json_encode($tallyData)),
                ],
            );

            $tracker->track($connection->id, 'stock_item', $item->id, 'from_tally', 'low', $name);
            $count++;
        }

        return $count;
    }

    private function extractField(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        if (is_array($value)) {
            return $value['@attributes']['value'] ?? ($value[0] ?? null);
        }

        return $value ?: null;
    }

    private function extractNumeric(array $data, string $key): float
    {
        $value = $this->extractField($data, $key);

        return (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
    }
}
