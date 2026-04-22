<?php

namespace Modules\Tally\Services\Vouchers;

use Modules\Tally\Services\Fields\TallyFieldRegistry;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class VoucherService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    /**
     * List vouchers by type and optional date range.
     * For large datasets, use $batchSize to split into date-range batches.
     *
     * @param  string  $fromDate  Format: YYYYMMDD (e.g., 20260101)
     * @param  string  $toDate  Format: YYYYMMDD (e.g., 20261231)
     * @param  int|null  $batchSize  Max vouchers per request. If set, splits into monthly batches.
     */
    public function list(VoucherType $type, ?string $fromDate = null, ?string $toDate = null, ?int $batchSize = null): array
    {
        // If no batch size or no date range, do a single request
        if (! $batchSize || ! $fromDate || ! $toDate) {
            return $this->fetchVouchers($type, $fromDate, $toDate);
        }

        // Batch by monthly date ranges for large datasets
        $allVouchers = [];
        $currentFrom = $fromDate;

        while ($currentFrom < $toDate) {
            // Calculate end of month from currentFrom
            $year = (int) substr($currentFrom, 0, 4);
            $month = (int) substr($currentFrom, 4, 2);

            // Last day of this month
            $lastDay = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $monthEnd = sprintf('%04d%02d%02d', $year, $month, $lastDay);

            // Don't go past the requested end date
            $currentTo = min($monthEnd, $toDate);

            $batch = $this->fetchVouchers($type, $currentFrom, $currentTo);
            $allVouchers = array_merge($allVouchers, $batch);

            // Move to first day of next month
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
            $currentFrom = sprintf('%04d%02d01', $year, $month);
        }

        return $allVouchers;
    }

    private function fetchVouchers(VoucherType $type, ?string $fromDate, ?string $toDate): array
    {
        $filters = ['VOUCHERTYPENAME' => $type->value];

        if ($fromDate) {
            $filters['SVFROMDATE'] = $fromDate;
        }
        if ($toDate) {
            $filters['SVTODATE'] = $toDate;
        }

        $xml = TallyXmlBuilder::buildExportRequest('Voucher Register', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractCollection($response, 'VOUCHER');
    }

    /**
     * Get a specific voucher by its master ID.
     */
    public function get(string $masterID): ?array
    {
        $filters = ['MASTERID' => $masterID];
        $xml = TallyXmlBuilder::buildExportRequest('Voucher', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractObject($response, 'VOUCHER');
    }

    /**
     * Create a new voucher in TallyPrime.
     *
     * Required keys depend on voucher type. Common keys:
     * - VOUCHERTYPENAME: Voucher type (Sales, Purchase, etc.) — set automatically from $type
     * - DATE: Date in YYYYMMDD format
     * - PARTYLEDGERNAME: Party ledger name
     * - ALLLEDGERENTRIES.LIST: Array of ledger entries with LEDGERNAME, ISDEEMEDPOSITIVE, AMOUNT
     *
     * For inventory vouchers, also include:
     * - ALLINVENTORYENTRIES.LIST: Array with STOCKITEMNAME, RATE, ACTUALQTY, AMOUNT
     */
    public function create(VoucherType $type, array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::VOUCHER, $data);
        $data['VOUCHERTYPENAME'] = $type->value;
        $data = self::applyInvoiceMode($data);
        $xml = TallyXmlBuilder::buildImportVoucherRequest($data, 'Create');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    /**
     * Create multiple vouchers in a single request (batch import).
     *
     * @param  array<array>  $vouchers  Each array must include voucher data
     */
    public function createBatch(VoucherType $type, array $vouchers): array
    {
        foreach ($vouchers as &$data) {
            $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::VOUCHER, $data);
            $data['VOUCHERTYPENAME'] = $type->value;
            $data = self::applyInvoiceMode($data);
        }
        unset($data);

        $xml = TallyXmlBuilder::buildBatchImportVoucherRequest($vouchers, 'Create');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    /**
     * Alter an existing voucher.
     */
    public function alter(string $masterID, VoucherType $type, array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::VOUCHER, $data);
        $data['VOUCHERTYPENAME'] = $type->value;
        $data['MASTERID'] = $masterID;
        $data = self::applyInvoiceMode($data);
        $xml = TallyXmlBuilder::buildImportVoucherRequest($data, 'Alter');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    /**
     * Auto-fill invoice-mode metadata when ISINVOICE=Yes.
     *
     * Per Tally sample-xml docs (Sample 11 — Sales Voucher Invoice Mode), an
     * invoice-mode voucher needs PERSISTEDVIEW + OBJVIEW set to "Invoice Voucher
     * View". Voucher-mode entries leave them off (or use "Accounting Voucher View").
     * Caller-supplied values are NEVER overwritten — this only fills sensible
     * defaults so clients can opt into invoice mode by setting ISINVOICE alone.
     */
    private static function applyInvoiceMode(array $data): array
    {
        $isInvoice = strtoupper((string) ($data['ISINVOICE'] ?? 'No')) === 'YES';

        if ($isInvoice) {
            $data['PERSISTEDVIEW'] = $data['PERSISTEDVIEW'] ?? 'Invoice Voucher View';
            $data['OBJVIEW'] = $data['OBJVIEW'] ?? 'Invoice Voucher View';
        }

        return $data;
    }

    /**
     * Cancel a voucher (preserves record with cancellation narration).
     * This is the audit-friendly way to void a voucher.
     *
     * @param  string  $date  Voucher date in DD-Mon-YYYY format (e.g., "03-Jun-2009")
     * @param  string  $voucherNumber  The voucher number
     */
    public function cancel(
        string $date,
        string $voucherNumber,
        VoucherType $type,
        ?string $narration = null,
    ): array {
        $xml = TallyXmlBuilder::buildCancelVoucherRequest(
            $date,
            $voucherNumber,
            $type->value,
            $narration,
        );
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    /**
     * Delete a voucher permanently.
     *
     * @param  string  $date  Voucher date in DD-Mon-YYYY format (e.g., "03-Jun-2009")
     * @param  string  $voucherNumber  The voucher number
     */
    public function delete(
        string $date,
        string $voucherNumber,
        VoucherType $type,
    ): array {
        $xml = TallyXmlBuilder::buildDeleteVoucherRequest(
            $date,
            $voucherNumber,
            $type->value,
        );
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    /**
     * Create a Sales voucher with a simplified interface.
     *
     * Amount signs per official Tally convention:
     * - Party (debtor): ISDEEMEDPOSITIVE=Yes, AMOUNT=negative (debit)
     * - Sales ledger: ISDEEMEDPOSITIVE=No, AMOUNT=positive (credit)
     */
    public function createSales(
        string $date,
        string $partyLedger,
        string $salesLedger,
        float $amount,
        ?string $voucherNumber = null,
        ?string $narration = null,
        array $inventoryEntries = [],
    ): array {
        $data = [
            'DATE' => $date,
            'PARTYLEDGERNAME' => $partyLedger,
            'NARRATION' => $narration ?? '',
            'ALLLEDGERENTRIES.LIST' => [
                [
                    'LEDGERNAME' => $partyLedger,
                    'ISDEEMEDPOSITIVE' => 'Yes',
                    'AMOUNT' => -$amount,
                ],
                [
                    'LEDGERNAME' => $salesLedger,
                    'ISDEEMEDPOSITIVE' => 'No',
                    'AMOUNT' => $amount,
                ],
            ],
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        if (! empty($inventoryEntries)) {
            $data['ALLINVENTORYENTRIES.LIST'] = $inventoryEntries;
        }

        return $this->create(VoucherType::Sales, $data);
    }

    /**
     * Create a Purchase voucher with a simplified interface.
     *
     * Amount signs per official Tally convention:
     * - Party (creditor): ISDEEMEDPOSITIVE=No, AMOUNT=positive (credit)
     * - Purchase ledger: ISDEEMEDPOSITIVE=Yes, AMOUNT=negative (debit)
     */
    public function createPurchase(
        string $date,
        string $partyLedger,
        string $purchaseLedger,
        float $amount,
        ?string $voucherNumber = null,
        ?string $narration = null,
        array $inventoryEntries = [],
    ): array {
        $data = [
            'DATE' => $date,
            'PARTYLEDGERNAME' => $partyLedger,
            'NARRATION' => $narration ?? '',
            'ALLLEDGERENTRIES.LIST' => [
                [
                    'LEDGERNAME' => $purchaseLedger,
                    'ISDEEMEDPOSITIVE' => 'Yes',
                    'AMOUNT' => -$amount,
                ],
                [
                    'LEDGERNAME' => $partyLedger,
                    'ISDEEMEDPOSITIVE' => 'No',
                    'AMOUNT' => $amount,
                ],
            ],
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        if (! empty($inventoryEntries)) {
            $data['ALLINVENTORYENTRIES.LIST'] = $inventoryEntries;
        }

        return $this->create(VoucherType::Purchase, $data);
    }

    /**
     * Create a Payment voucher.
     *
     * Amount signs per official Tally samples (8_Import Vouchers):
     * - Expense/party (debit): ISDEEMEDPOSITIVE=Yes, AMOUNT=positive
     * - Bank/cash (credit): ISDEEMEDPOSITIVE=No, AMOUNT=negative
     */
    public function createPayment(
        string $date,
        string $paymentLedger,
        string $partyLedger,
        float $amount,
        ?string $voucherNumber = null,
        ?string $narration = null,
    ): array {
        $data = [
            'DATE' => $date,
            'NARRATION' => $narration ?? '',
            'ALLLEDGERENTRIES.LIST' => [
                [
                    'LEDGERNAME' => $partyLedger,
                    'ISDEEMEDPOSITIVE' => 'Yes',
                    'AMOUNT' => $amount,
                ],
                [
                    'LEDGERNAME' => $paymentLedger,
                    'ISDEEMEDPOSITIVE' => 'No',
                    'AMOUNT' => -$amount,
                ],
            ],
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        return $this->create(VoucherType::Payment, $data);
    }

    /**
     * Create a Receipt voucher.
     *
     * Amount signs (mirror of Payment):
     * - Bank/cash (debit): ISDEEMEDPOSITIVE=Yes, AMOUNT=negative
     * - Party (credit): ISDEEMEDPOSITIVE=No, AMOUNT=positive
     */
    public function createReceipt(
        string $date,
        string $receivingLedger,
        string $partyLedger,
        float $amount,
        ?string $voucherNumber = null,
        ?string $narration = null,
    ): array {
        $data = [
            'DATE' => $date,
            'NARRATION' => $narration ?? '',
            'ALLLEDGERENTRIES.LIST' => [
                [
                    'LEDGERNAME' => $receivingLedger,
                    'ISDEEMEDPOSITIVE' => 'Yes',
                    'AMOUNT' => -$amount,
                ],
                [
                    'LEDGERNAME' => $partyLedger,
                    'ISDEEMEDPOSITIVE' => 'No',
                    'AMOUNT' => $amount,
                ],
            ],
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        return $this->create(VoucherType::Receipt, $data);
    }

    /**
     * Create a Journal voucher.
     */
    public function createJournal(
        string $date,
        array $debitEntries,
        array $creditEntries,
        ?string $voucherNumber = null,
        ?string $narration = null,
    ): array {
        $ledgerEntries = [];

        foreach ($debitEntries as $entry) {
            $ledgerEntries[] = [
                'LEDGERNAME' => $entry['ledger'],
                'ISDEEMEDPOSITIVE' => 'Yes',
                'AMOUNT' => -abs($entry['amount']),
            ];
        }

        foreach ($creditEntries as $entry) {
            $ledgerEntries[] = [
                'LEDGERNAME' => $entry['ledger'],
                'ISDEEMEDPOSITIVE' => 'No',
                'AMOUNT' => abs($entry['amount']),
            ];
        }

        $data = [
            'DATE' => $date,
            'NARRATION' => $narration ?? '',
            'ALLLEDGERENTRIES.LIST' => $ledgerEntries,
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        return $this->create(VoucherType::Journal, $data);
    }

    // --------------------------------------------------------------------
    // Phase 9F — inventory convenience helpers
    // --------------------------------------------------------------------

    /**
     * Transfer stock between godowns using a Stock Journal voucher.
     * Tally represents this as two inventory entries: one consumption (negative)
     * from the source godown, one destination (positive) at the target godown.
     */
    public function createStockTransfer(
        string $date,
        string $fromGodown,
        string $toGodown,
        string $stockItem,
        float $quantity,
        ?string $unit = 'Nos',
        ?float $rate = null,
        ?string $voucherNumber = null,
        ?string $narration = null,
    ): array {
        $rateStr = $rate !== null ? "{$rate}/{$unit}" : null;
        $amount = $rate !== null ? round($rate * $quantity, 2) : 0.0;

        $data = [
            'DATE' => $date,
            'NARRATION' => $narration ?? "Stock transfer {$fromGodown} → {$toGodown}",
            'ALLINVENTORYENTRIES.LIST' => [
                [
                    'STOCKITEMNAME' => $stockItem,
                    'ACTUALQTY' => "-{$quantity} {$unit}",
                    'BILLEDQTY' => "-{$quantity} {$unit}",
                    'RATE' => $rateStr ?? '',
                    'AMOUNT' => $amount ? -$amount : '',
                    'ISDEEMEDPOSITIVE' => 'Yes',
                    'BATCHALLOCATIONS.LIST' => [
                        [
                            'GODOWNNAME' => $fromGodown,
                            'BATCHNAME' => 'Primary Batch',
                            'ACTUALQTY' => "-{$quantity} {$unit}",
                            'BILLEDQTY' => "-{$quantity} {$unit}",
                            'AMOUNT' => $amount ? -$amount : '',
                        ],
                    ],
                ],
                [
                    'STOCKITEMNAME' => $stockItem,
                    'ACTUALQTY' => "{$quantity} {$unit}",
                    'BILLEDQTY' => "{$quantity} {$unit}",
                    'RATE' => $rateStr ?? '',
                    'AMOUNT' => $amount ?: '',
                    'ISDEEMEDPOSITIVE' => 'No',
                    'BATCHALLOCATIONS.LIST' => [
                        [
                            'GODOWNNAME' => $toGodown,
                            'BATCHNAME' => 'Primary Batch',
                            'ACTUALQTY' => "{$quantity} {$unit}",
                            'BILLEDQTY' => "{$quantity} {$unit}",
                            'AMOUNT' => $amount ?: '',
                        ],
                    ],
                ],
            ],
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        return $this->create(VoucherType::StockJournal, $data);
    }

    /**
     * Physical stock voucher — adjusts book stock to match an actual count.
     */
    public function createPhysicalStock(
        string $date,
        string $godown,
        string $stockItem,
        float $countedQuantity,
        ?string $unit = 'Nos',
        ?string $voucherNumber = null,
        ?string $narration = null,
    ): array {
        $data = [
            'DATE' => $date,
            'NARRATION' => $narration ?? "Physical stock adjustment — {$stockItem} @ {$godown}",
            'ALLINVENTORYENTRIES.LIST' => [
                [
                    'STOCKITEMNAME' => $stockItem,
                    'ACTUALQTY' => "{$countedQuantity} {$unit}",
                    'BATCHALLOCATIONS.LIST' => [
                        [
                            'GODOWNNAME' => $godown,
                            'BATCHNAME' => 'Primary Batch',
                            'ACTUALQTY' => "{$countedQuantity} {$unit}",
                        ],
                    ],
                ],
            ],
        ];

        if ($voucherNumber !== null) {
            $data['VOUCHERNUMBER'] = $voucherNumber;
        }

        return $this->create(VoucherType::PhysicalStock, $data);
    }
}
