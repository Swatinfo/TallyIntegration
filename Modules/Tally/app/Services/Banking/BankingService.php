<?php

namespace Modules\Tally\Services\Banking;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

/**
 * Banking operations — reconciliation, cheque register, statement import.
 *
 * Reconciliation model: Tally stores the bank statement's clearing date on a
 * voucher via BANKDETAILS.LIST/BANKERDATE. Marking a voucher as reconciled =
 * altering it to set that date. Unreconcile = clearing it (empty BANKERDATE).
 */
class BankingService
{
    public function __construct(
        private TallyHttpClient $client,
        private VoucherService $voucherService,
    ) {}

    /**
     * Mark a voucher as reconciled against a bank statement.
     *
     * @param  string  $voucherNumber  Voucher number in Tally
     * @param  string  $voucherDate  YYYYMMDD
     * @param  VoucherType  $type  Base voucher type (Payment, Receipt, Contra)
     * @param  string  $statementDate  DD-Mon-YYYY as shown on the bank statement
     * @param  string  $bankLedger  Bank ledger name (the one being reconciled)
     */
    public function reconcile(string $voucherNumber, string $voucherDate, VoucherType $type, string $statementDate, string $bankLedger): array
    {
        $alterData = [
            'VOUCHERTYPENAME' => $type->value,
            'VOUCHERNUMBER' => $voucherNumber,
            'DATE' => $voucherDate,
            'BANKALLOCATIONS.LIST' => [
                [
                    'LEDGERNAME' => $bankLedger,
                    'BANKERDATE' => $statementDate,
                ],
            ],
        ];

        // VoucherService::alter expects a masterID; for bank reconciliation we
        // use voucher-number-based targeting via the import envelope directly.
        $xml = TallyXmlBuilder::buildImportVoucherRequest($alterData, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            app(AuditLogger::class)->log('alter', 'VOUCHER', $voucherNumber, $alterData, $result);
        }

        return $result;
    }

    /**
     * Clear the reconciliation flag on a voucher.
     */
    public function unreconcile(string $voucherNumber, string $voucherDate, VoucherType $type, string $bankLedger): array
    {
        $alterData = [
            'VOUCHERTYPENAME' => $type->value,
            'VOUCHERNUMBER' => $voucherNumber,
            'DATE' => $voucherDate,
            'BANKALLOCATIONS.LIST' => [
                [
                    'LEDGERNAME' => $bankLedger,
                    'BANKERDATE' => '',
                ],
            ],
        ];

        $xml = TallyXmlBuilder::buildImportVoucherRequest($alterData, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            app(AuditLogger::class)->log('alter', 'VOUCHER', $voucherNumber, $alterData, $result);
        }

        return $result;
    }

    /**
     * Parse a bank statement CSV into structured rows.
     *
     * Expects header row with columns (case-insensitive): date, description,
     * debit, credit, amount, reference, cheque_number. Missing columns → null.
     *
     * @return array<int, array{date:?string, description:?string, amount:float, reference:?string, cheque_number:?string, raw:array}>
     */
    public function parseStatement(string $csvContent): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($csvContent));
        if (count($lines) < 2) {
            return [];
        }

        $headers = array_map(fn ($h) => strtolower(trim($h, "\"' ")), str_getcsv(array_shift($lines)));
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line);
            $raw = array_combine($headers, array_pad($cols, count($headers), null)) ?: [];

            // Amount resolution: prefer explicit 'amount' column, else credit - debit.
            $amount = (float) ($raw['amount'] ?? 0);
            if ($amount === 0.0) {
                $amount = (float) ($raw['credit'] ?? 0) - (float) ($raw['debit'] ?? 0);
            }

            $rows[] = [
                'date' => $raw['date'] ?? null,
                'description' => $raw['description'] ?? null,
                'amount' => $amount,
                'reference' => $raw['reference'] ?? $raw['ref'] ?? null,
                'cheque_number' => $raw['cheque_number'] ?? $raw['cheque'] ?? null,
                'raw' => $raw,
            ];
        }

        return $rows;
    }

    /**
     * Given parsed statement rows + a list of Tally vouchers, find match candidates.
     * Match is considered strong when amount matches exactly AND date is within
     * `dateToleranceDays` of the statement row (default ±3 days).
     *
     * @param  array  $statementRows  output of parseStatement()
     * @param  array  $vouchers  list of Tally vouchers (as returned by VoucherService::list)
     * @return array<int, array{statement:array, voucher:array, confidence:string}>
     */
    public function findMatches(array $statementRows, array $vouchers, int $dateToleranceDays = 3): array
    {
        $matches = [];

        foreach ($statementRows as $row) {
            $rowAmount = round(abs((float) $row['amount']), 2);
            $rowDate = $this->parseLooseDate($row['date'] ?? '');

            foreach ($vouchers as $v) {
                $vAmount = round(abs((float) ($v['AMOUNT'] ?? 0)), 2);
                if ($rowAmount !== $vAmount) {
                    continue;
                }

                $vDate = $this->parseLooseDate($v['DATE'] ?? $v['VOUCHERDATE'] ?? '');
                $confidence = 'low';

                if ($rowDate && $vDate) {
                    $diffDays = abs(($vDate->getTimestamp() - $rowDate->getTimestamp()) / 86400);
                    if ($diffDays === 0.0) {
                        $confidence = 'exact';
                    } elseif ($diffDays <= $dateToleranceDays) {
                        $confidence = 'high';
                    } else {
                        continue;
                    }
                }

                $matches[] = [
                    'statement' => $row,
                    'voucher' => $v,
                    'confidence' => $confidence,
                ];
            }
        }

        return $matches;
    }

    /**
     * Apply a batch of reconciliations.
     *
     * @param  array<int, array{voucher_number:string, voucher_date:string, voucher_type:string, statement_date:string, bank_ledger:string}>  $entries
     */
    public function batchReconcile(array $entries): array
    {
        $reconciled = 0;
        $failed = 0;
        $errors = [];

        foreach ($entries as $entry) {
            $result = $this->reconcile(
                $entry['voucher_number'],
                $entry['voucher_date'],
                VoucherType::from($entry['voucher_type']),
                $entry['statement_date'],
                $entry['bank_ledger'],
            );

            if ($result['errors'] === 0) {
                $reconciled++;
            } else {
                $failed++;
                $errors[] = ['voucher_number' => $entry['voucher_number'], 'result' => $result];
            }
        }

        return [
            'reconciled' => $reconciled,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    private function parseLooseDate(string $s): ?\DateTimeImmutable
    {
        $s = trim($s);
        if ($s === '') {
            return null;
        }

        // Try YYYYMMDD, DD-Mon-YYYY, DD/MM/YYYY, YYYY-MM-DD
        foreach (['Ymd', 'd-M-Y', 'd/m/Y', 'Y-m-d', 'd-m-Y'] as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $s);
            if ($dt !== false) {
                return $dt;
            }
        }

        return null;
    }
}
