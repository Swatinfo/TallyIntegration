<?php

namespace Modules\Tally\Services;

class TallyCompanyService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    /**
     * Get the current AlterIDs for masters and vouchers.
     * Used for incremental sync — if IDs haven't changed, nothing to sync.
     *
     * @return array{master_id: int, voucher_id: int}
     */
    public function getAlterIds(): array
    {
        $xml = TallyXmlBuilder::buildAlterIdQueryRequest();
        $response = $this->client->sendXml($xml);
        $data = TallyXmlParser::parse($response);

        $masterIdRaw = $data['BODY']['ALTMSTID']
            ?? $data['BODY']['DATA']['ALTMSTID']
            ?? $data['BODY']['DATA']['TALLYSYNCLINE']['ALTMSTID']
            ?? '0';
        $voucherIdRaw = $data['BODY']['ALTVCHID']
            ?? $data['BODY']['DATA']['ALTVCHID']
            ?? $data['BODY']['DATA']['TALLYSYNCLINE']['ALTVCHID']
            ?? '0';

        return [
            'master_id' => (int) (is_array($masterIdRaw) ? ($masterIdRaw[0] ?? 0) : $masterIdRaw),
            'voucher_id' => (int) (is_array($voucherIdRaw) ? ($voucherIdRaw[0] ?? 0) : $voucherIdRaw),
        ];
    }

    /**
     * Invoke a Tally built-in function.
     * Examples: $$SystemPeriodFrom, $$SystemPeriodTo, $$NumStockItems
     */
    public function callFunction(string $functionName, array $params = []): string
    {
        $xml = TallyXmlBuilder::buildFunctionExportRequest($functionName, $params);
        $response = $this->client->sendXml($xml);

        // Function responses return the result directly in the body
        $data = TallyXmlParser::parse($response);

        return $data['BODY']['DATA']['RESULT'] ?? $data['BODY']['RESULT'] ?? trim(strip_tags($response));
    }

    /**
     * Get the financial year period (from/to dates) from TallyPrime.
     *
     * @return array{from: string, to: string}
     */
    public function getFinancialYearPeriod(): array
    {
        return [
            'from' => $this->callFunction('$$SystemPeriodFrom'),
            'to' => $this->callFunction('$$SystemPeriodTo'),
        ];
    }

    /**
     * Check if data has changed since last sync by comparing AlterIDs.
     */
    public function hasChangedSince(int $lastMasterId, int $lastVoucherId): array
    {
        $current = $this->getAlterIds();

        return [
            'masters_changed' => $current['master_id'] > $lastMasterId,
            'vouchers_changed' => $current['voucher_id'] > $lastVoucherId,
            'current_master_id' => $current['master_id'],
            'current_voucher_id' => $current['voucher_id'],
        ];
    }
}
