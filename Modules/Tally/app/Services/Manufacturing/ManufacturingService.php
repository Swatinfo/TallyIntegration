<?php

namespace Modules\Tally\Services\Manufacturing;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Masters\StockItemService;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

/**
 * Manufacturing + Job Work operations.
 *
 * BOM model: Tally stores the bill of materials on the finished stock item as
 * a `COMPONENTLIST.LIST` of child stock items + quantities. There is no separate
 * BOM master entity — it's a field on the parent stock item.
 */
class ManufacturingService
{
    public function __construct(
        private TallyHttpClient $client,
        private StockItemService $stockItems,
        private VoucherService $vouchers,
    ) {}

    /**
     * Read the BOM (component list) defined on a finished stock item.
     *
     * @return array<int, array{name:string, qty:string, unit:?string}>|null
     */
    public function getBom(string $finishedItem): ?array
    {
        $item = $this->stockItems->get($finishedItem);
        if (! $item) {
            return null;
        }

        $list = $item['COMPONENTLIST.LIST'] ?? $item['COMPONENTLIST_LIST'] ?? null;
        if (! $list) {
            return [];
        }

        // Normalise — Tally returns object when single entry, array when multiple.
        $rows = array_is_list($list) ? $list : [$list];

        return array_map(fn ($r) => [
            'name' => (string) ($r['STOCKITEMNAME'] ?? ''),
            'qty' => (string) ($r['ACTUALQTY'] ?? ''),
            'unit' => $r['UNIT'] ?? null,
        ], $rows);
    }

    /**
     * Set / replace the BOM on a stock item by altering it.
     *
     * @param  array<int, array{name:string, qty:float|string, unit?:string}>  $components
     */
    public function setBom(string $finishedItem, array $components): array
    {
        $componentList = array_map(fn ($c) => [
            'STOCKITEMNAME' => $c['name'],
            'ACTUALQTY' => is_numeric($c['qty']) ? "{$c['qty']} ".($c['unit'] ?? 'Nos') : $c['qty'],
        ], $components);

        $data = [
            'NAME' => $finishedItem,
            'COMPONENTLIST.LIST' => $componentList,
        ];

        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKITEM', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            app(AuditLogger::class)->log('alter', 'STOCKITEM', $finishedItem, $data, $result);
        }

        return $result;
    }

    /**
     * Create a Manufacturing Journal voucher: produces `$productQty` of `$productItem`
     * while consuming `$components`. Components can come from `getBom()` or be supplied inline.
     *
     * @param  array<int, array{name:string, qty:float, unit?:string, godown?:string}>  $components
     */
    public function createManufacturingVoucher(
        string $date,
        string $productItem,
        float $productQty,
        array $components,
        ?string $productGodown = null,
        ?string $unit = 'Nos',
        ?string $voucherNumber = null,
        ?string $narration = null,
    ): array {
        $inventoryEntries = [];

        // Production line (finished goods IN).
        $inventoryEntries[] = [
            'STOCKITEMNAME' => $productItem,
            'ACTUALQTY' => "{$productQty} {$unit}",
            'BILLEDQTY' => "{$productQty} {$unit}",
            'ISDEEMEDPOSITIVE' => 'No',
            'BATCHALLOCATIONS.LIST' => [
                [
                    'GODOWNNAME' => $productGodown ?? 'Main Location',
                    'BATCHNAME' => 'Primary Batch',
                    'ACTUALQTY' => "{$productQty} {$unit}",
                    'BILLEDQTY' => "{$productQty} {$unit}",
                ],
            ],
        ];

        // Consumption lines (raw materials OUT).
        foreach ($components as $c) {
            $cUnit = $c['unit'] ?? 'Nos';
            $inventoryEntries[] = [
                'STOCKITEMNAME' => $c['name'],
                'ACTUALQTY' => "-{$c['qty']} {$cUnit}",
                'BILLEDQTY' => "-{$c['qty']} {$cUnit}",
                'ISDEEMEDPOSITIVE' => 'Yes',
                'BATCHALLOCATIONS.LIST' => [
                    [
                        'GODOWNNAME' => $c['godown'] ?? $productGodown ?? 'Main Location',
                        'BATCHNAME' => 'Primary Batch',
                        'ACTUALQTY' => "-{$c['qty']} {$cUnit}",
                        'BILLEDQTY' => "-{$c['qty']} {$cUnit}",
                    ],
                ],
            ];
        }

        $data = [
            'DATE' => $date,
            'NARRATION' => $narration ?? "Manufacture {$productQty} × {$productItem}",
            'ALLINVENTORYENTRIES.LIST' => $inventoryEntries,
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        return $this->vouchers->create(VoucherType::ManufacturingJournal, $data);
    }

    /**
     * Create a Job Work Out Order — sending goods to a job worker for processing.
     */
    public function createJobWorkOut(
        string $date,
        string $jobWorkerLedger,
        string $stockItem,
        float $quantity,
        ?string $unit = 'Nos',
        ?string $voucherNumber = null,
        ?string $narration = null,
    ): array {
        return $this->jobWork(VoucherType::JobWorkOutOrder, $date, $jobWorkerLedger, $stockItem, $quantity, $unit, $voucherNumber, $narration);
    }

    /**
     * Create a Job Work In Order — receiving finished goods back from a job worker.
     */
    public function createJobWorkIn(
        string $date,
        string $jobWorkerLedger,
        string $stockItem,
        float $quantity,
        ?string $unit = 'Nos',
        ?string $voucherNumber = null,
        ?string $narration = null,
    ): array {
        return $this->jobWork(VoucherType::JobWorkInOrder, $date, $jobWorkerLedger, $stockItem, $quantity, $unit, $voucherNumber, $narration);
    }

    private function jobWork(VoucherType $type, string $date, string $party, string $item, float $qty, ?string $unit, ?string $voucherNumber, ?string $narration): array
    {
        $data = [
            'DATE' => $date,
            'PARTYLEDGERNAME' => $party,
            'NARRATION' => $narration ?? "{$type->value}: {$qty} × {$item}",
            'ALLINVENTORYENTRIES.LIST' => [
                [
                    'STOCKITEMNAME' => $item,
                    'ACTUALQTY' => "{$qty} {$unit}",
                    'BILLEDQTY' => "{$qty} {$unit}",
                ],
            ],
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        return $this->vouchers->create($type, $data);
    }
}
