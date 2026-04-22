<?php

namespace Modules\Tally\Services\Reports;

use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class ReportService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    /**
     * Fetch the Balance Sheet report.
     *
     * @param  string|null  $date  Date in YYYYMMDD format
     */
    public function balanceSheet(?string $date = null): array
    {
        $filters = [];
        if ($date) {
            $filters['SVTODATE'] = $date;
        }

        $xml = TallyXmlBuilder::buildExportRequest('Balance Sheet', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Fetch the Profit & Loss report.
     *
     * @param  string  $from  Date in YYYYMMDD format
     * @param  string  $to  Date in YYYYMMDD format
     */
    public function profitAndLoss(string $from, string $to): array
    {
        $filters = [
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Profit and Loss A/c', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Fetch the Trial Balance report.
     *
     * @param  string|null  $date  Date in YYYYMMDD format
     */
    public function trialBalance(?string $date = null): array
    {
        $filters = [];
        if ($date) {
            $filters['SVTODATE'] = $date;
        }

        $xml = TallyXmlBuilder::buildExportRequest('Trial Balance', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Fetch a ledger-wise voucher report (statement of a specific ledger).
     */
    public function ledgerReport(string $ledgerName, string $from, string $to): array
    {
        $filters = [
            'LEDGERNAME' => $ledgerName,
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Ledger Vouchers', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Fetch outstanding receivables or payables.
     *
     * @param  string  $type  'receivable' or 'payable'
     */
    public function outstandings(string $type = 'receivable'): array
    {
        $reportName = $type === 'payable'
            ? 'Bills Payable'
            : 'Bills Receivable';

        $xml = TallyXmlBuilder::buildExportRequest($reportName);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Fetch the Stock Summary report.
     */
    public function stockSummary(): array
    {
        $xml = TallyXmlBuilder::buildExportRequest('Stock Summary');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Fetch the Day Book for a specific date.
     *
     * @param  string  $date  Date in YYYYMMDD format
     */
    public function dayBook(string $date): array
    {
        $filters = [
            'SVFROMDATE' => $date,
            'SVTODATE' => $date,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Day Book', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    // -----------------------------------------------------------------------
    // Phase 9B additions — management + operational reports
    // -----------------------------------------------------------------------

    /**
     * Cash / Bank Book — daily cash & bank movements for a specific ledger.
     *
     * @param  string  $ledger  Cash or bank ledger name (e.g. "HDFC Current A/c")
     * @param  string  $from  YYYYMMDD
     * @param  string  $to  YYYYMMDD
     */
    public function cashBankBook(string $ledger, string $from, string $to): array
    {
        $filters = [
            'LEDGERNAME' => $ledger,
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Cash/Bank Book', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Sales Register — all sales vouchers in a period.
     */
    public function salesRegister(string $from, string $to): array
    {
        $filters = [
            'VOUCHERTYPENAME' => 'Sales',
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Voucher Register', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Purchase Register — all purchase vouchers in a period.
     */
    public function purchaseRegister(string $from, string $to): array
    {
        $filters = [
            'VOUCHERTYPENAME' => 'Purchase',
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Voucher Register', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Ageing Analysis — receivables / payables bucketed by age.
     *
     * @param  string  $type  'receivable' or 'payable'
     * @param  string|null  $asOf  YYYYMMDD cutoff date
     */
    public function agingAnalysis(string $type = 'receivable', ?string $asOf = null): array
    {
        $reportName = $type === 'payable' ? 'Bills Payable' : 'Bills Receivable';
        $filters = ['SHOWAGEWISE' => 'Yes'];
        if ($asOf) {
            $filters['SVTODATE'] = $asOf;
        }

        $xml = TallyXmlBuilder::buildExportRequest($reportName, [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Cash Flow Statement.
     */
    public function cashFlow(string $from, string $to): array
    {
        $filters = [
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Cash Flow', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Funds Flow Statement.
     */
    public function fundsFlow(string $from, string $to): array
    {
        $filters = [
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Funds Flow', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Receipts and Payments report (non-accrual summary).
     */
    public function receiptsPayments(string $from, string $to): array
    {
        $filters = [
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Receipts and Payments', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Stock Item Movement Analysis — ins/outs of a specific stock item.
     */
    public function stockMovement(string $stockItem, string $from, string $to): array
    {
        $filters = [
            'STOCKITEMNAME' => $stockItem,
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Stock Item Movement Analysis', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    // -----------------------------------------------------------------------
    // Phase 9D — Banking reports
    // -----------------------------------------------------------------------

    /**
     * Bank Reconciliation — shows reconciled vs unreconciled entries for a bank ledger.
     */
    public function bankReconciliation(string $bankLedger, string $from, string $to): array
    {
        $filters = [
            'LEDGERNAME' => $bankLedger,
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Bank Reconciliation', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Cheque Register — all cheques (issued/received/cleared) in a period.
     */
    public function chequeRegister(string $from, string $to): array
    {
        $filters = [
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Cheque Register', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }

    /**
     * Post-dated cheques — future-dated payments / receipts not yet active.
     */
    public function postDatedCheques(string $from, string $to): array
    {
        $filters = [
            'SVFROMDATE' => $from,
            'SVTODATE' => $to,
        ];

        $xml = TallyXmlBuilder::buildExportRequest('Post-Dated Summary', [], $filters);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractReport($response);
    }
}
