<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tally\Events\TallySyncCompleted;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\Masters\CostCenterService;
use Modules\Tally\Services\Masters\GroupService;
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\Masters\StockGroupService;
use Modules\Tally\Services\Masters\StockItemService;
use Modules\Tally\Services\Masters\UnitService;
use Modules\Tally\Services\TallyCompanyService;
use Modules\Tally\Services\TallyConnectionManager;
use Modules\Tally\Services\TallyHttpClient;

class SyncMastersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $connectionCode,
        public readonly string $masterType = 'all',
        public readonly bool $force = false,
    ) {}

    public function handle(TallyConnectionManager $manager): void
    {
        $client = $manager->resolve($this->connectionCode);
        app()->instance(TallyHttpClient::class, $client);

        $connection = TallyConnection::where('code', $this->connectionCode)->first();

        if (! $connection) {
            return;
        }

        $newMasterId = null;
        $newVoucherId = null;

        // Incremental sync: check AlterIDs before pulling data
        if (! $this->force) {
            try {
                $companyService = app(TallyCompanyService::class);
                $changes = $companyService->hasChangedSince(
                    $connection->last_alter_master_id,
                    $connection->last_alter_voucher_id,
                );

                $newMasterId = $changes['current_master_id'];
                $newVoucherId = $changes['current_voucher_id'];

                if (! $changes['masters_changed']) {
                    TallySyncCompleted::dispatch($this->connectionCode, 'masters:skipped', 0);

                    return;
                }
            } catch (\Throwable) {
                // AlterID query failed — fall through to full sync
            }
        }

        $total = 0;

        $services = match ($this->masterType) {
            'ledger' => ['ledger' => app(LedgerService::class)],
            'group' => ['group' => app(GroupService::class)],
            'stock-item' => ['stock-item' => app(StockItemService::class)],
            default => [
                'ledger' => app(LedgerService::class),
                'group' => app(GroupService::class),
                'stock-item' => app(StockItemService::class),
                'stock-group' => app(StockGroupService::class),
                'unit' => app(UnitService::class),
                'cost-center' => app(CostCenterService::class),
            ],
        };

        foreach ($services as $service) {
            $items = $service->list();
            $total += count($items);
        }

        // Update sync tracking
        $updateData = ['last_synced_at' => now()];

        if ($newMasterId !== null) {
            $updateData['last_alter_master_id'] = $newMasterId;
        }

        if ($newVoucherId !== null) {
            $updateData['last_alter_voucher_id'] = $newVoucherId;
        }

        $connection->update($updateData);

        TallySyncCompleted::dispatch($this->connectionCode, "masters:{$this->masterType}", $total);
    }
}
