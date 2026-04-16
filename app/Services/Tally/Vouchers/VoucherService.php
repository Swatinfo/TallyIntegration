<?php

namespace App\Services\Tally\Vouchers;

use App\Services\Tally\TallyHttpClient;
use App\Services\Tally\TallyXmlBuilder;
use App\Services\Tally\TallyXmlParser;

class VoucherService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    /**
     * List vouchers by type and optional date range.
     *
     * @param  string  $fromDate  Format: YYYYMMDD (e.g., 20260101)
     * @param  string  $toDate  Format: YYYYMMDD (e.g., 20261231)
     */
    public function list(VoucherType $type, ?string $fromDate = null, ?string $toDate = null): array
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
        $data['VOUCHERTYPENAME'] = $type->value;
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
            $data['VOUCHERTYPENAME'] = $type->value;
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
        $data['VOUCHERTYPENAME'] = $type->value;
        $data['MASTERID'] = $masterID;
        $xml = TallyXmlBuilder::buildImportVoucherRequest($data, 'Alter');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
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
}
