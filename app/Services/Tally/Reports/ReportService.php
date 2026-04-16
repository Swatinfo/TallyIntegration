<?php

namespace App\Services\Tally\Reports;

use App\Services\Tally\TallyHttpClient;
use App\Services\Tally\TallyXmlBuilder;
use App\Services\Tally\TallyXmlParser;

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
}
