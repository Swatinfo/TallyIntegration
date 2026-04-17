<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyLedger;
use Modules\Tally\Models\TallySync;
use Modules\Tally\Models\TallyVoucher;
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\SyncTracker;
use Modules\Tally\Services\TallyConnectionManager;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

class SyncToTallyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $connectionCode,
        public readonly int $batchSize = 50,
    ) {}

    public function handle(TallyConnectionManager $manager, SyncTracker $tracker): void
    {
        $client = $manager->resolve($this->connectionCode);
        app()->instance(TallyHttpClient::class, $client);

        $connection = TallyConnection::where('code', $this->connectionCode)->first();

        if (! $connection) {
            return;
        }

        $pending = $tracker->getPending($connection->id, $this->batchSize);

        foreach ($pending as $sync) {
            $this->processSyncRecord($sync, $connection);
        }
    }

    private function processSyncRecord(TallySync $sync, TallyConnection $connection): void
    {
        // Skip if direction is from_tally only
        if ($sync->sync_direction === 'from_tally') {
            return;
        }

        $sync->markInProgress();

        try {
            $result = match ($sync->entity_type) {
                'ledger' => $this->syncLedger($sync, $connection),
                'voucher' => $this->syncVoucher($sync, $connection),
                default => ['errors' => 0, 'created' => 0, 'altered' => 0],
            };

            if (($result['errors'] ?? 0) === 0) {
                $entity = $sync->entity();
                $sync->markCompleted($entity?->computeDataHash());
            } else {
                $sync->markFailed('Tally import returned errors: '.json_encode($result));
            }
        } catch (\Throwable $e) {
            $sync->markFailed($e->getMessage());
        }
    }

    private function syncLedger(TallySync $sync, TallyConnection $connection): array
    {
        $ledger = TallyLedger::find($sync->entity_id);

        if (! $ledger) {
            return ['errors' => 1];
        }

        $service = app(LedgerService::class);

        $data = array_filter([
            'NAME' => $ledger->name,
            'PARENT' => $ledger->parent,
            'OPENINGBALANCE' => $ledger->opening_balance != 0 ? (string) $ledger->opening_balance : null,
            'GSTREGISTRATIONTYPE' => $ledger->gst_registration_type,
            'PARTYGSTIN' => $ledger->gstin,
            'STATENAME' => $ledger->state,
            'EMAIL' => $ledger->email,
            'LEDGERPHONE' => $ledger->phone,
            'LEDGERCONTACT' => $ledger->contact_person,
            'CREDITPERIOD' => $ledger->credit_period,
        ]);

        // Check if the ledger exists in Tally via sync record
        if ($sync->tally_data_hash) {
            return $service->update($ledger->name, $data);
        }

        return $service->create($data);
    }

    private function syncVoucher(TallySync $sync, TallyConnection $connection): array
    {
        $voucher = TallyVoucher::find($sync->entity_id);

        if (! $voucher) {
            return ['errors' => 1];
        }

        $service = app(VoucherService::class);
        $type = VoucherType::from($voucher->voucher_type);

        $data = array_filter([
            'DATE' => $voucher->date->format('Ymd'),
            'PARTYLEDGERNAME' => $voucher->party_name,
            'VOUCHERNUMBER' => $voucher->voucher_number,
            'NARRATION' => $voucher->narration,
            'ALLLEDGERENTRIES.LIST' => $voucher->ledger_entries,
        ]);

        if ($voucher->inventory_entries) {
            $data['ALLINVENTORYENTRIES.LIST'] = $voucher->inventory_entries;
        }

        return $service->create($type, $data);
    }
}
