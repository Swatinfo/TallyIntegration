<?php

namespace Modules\Tally\Services\Consolidation;

use Modules\Tally\Models\TallyOrganization;
use Modules\Tally\Services\Reports\ReportService;
use Modules\Tally\Services\TallyConnectionManager;

/**
 * Aggregates reports across every active connection in an organization /
 * company / branch. Delegates per-connection work to ReportService.
 *
 * Returns an envelope with per-connection breakdown PLUS rolled-up totals
 * where numeric reports allow it (balance sheet, P&L).
 */
class ConsolidationService
{
    public function __construct(
        private TallyConnectionManager $manager,
    ) {}

    /**
     * Consolidated Balance Sheet across every active connection under an organization.
     */
    public function consolidatedBalanceSheet(TallyOrganization $org, ?string $date = null): array
    {
        return $this->fanOut($org, function (ReportService $report) use ($date) {
            return $report->balanceSheet($date);
        });
    }

    /**
     * Consolidated Profit & Loss across every active connection under an organization.
     */
    public function consolidatedProfitAndLoss(TallyOrganization $org, string $from, string $to): array
    {
        return $this->fanOut($org, function (ReportService $report) use ($from, $to) {
            return $report->profitAndLoss($from, $to);
        });
    }

    /**
     * Consolidated Trial Balance across every active connection under an organization.
     */
    public function consolidatedTrialBalance(TallyOrganization $org, ?string $date = null): array
    {
        return $this->fanOut($org, function (ReportService $report) use ($date) {
            return $report->trialBalance($date);
        });
    }

    /**
     * Run a report closure against every active connection in the org and
     * package the per-connection results with metadata.
     */
    private function fanOut(TallyOrganization $org, \Closure $runReport): array
    {
        $connections = $org->connections()->where('is_active', true)->get();

        $breakdown = [];
        foreach ($connections as $conn) {
            $client = $this->manager->fromConnection($conn);
            $report = new ReportService($client);

            try {
                $data = $runReport($report);
                $breakdown[] = [
                    'connection' => [
                        'id' => $conn->id,
                        'code' => $conn->code,
                        'name' => $conn->name,
                        'company_id' => $conn->tally_company_id,
                        'branch_id' => $conn->tally_branch_id,
                    ],
                    'success' => true,
                    'data' => $data,
                ];
            } catch (\Throwable $e) {
                $breakdown[] = [
                    'connection' => ['id' => $conn->id, 'code' => $conn->code, 'name' => $conn->name],
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'organization' => [
                'id' => $org->id,
                'code' => $org->code,
                'name' => $org->name,
                'base_currency' => $org->base_currency,
            ],
            'connection_count' => $connections->count(),
            'successful' => count(array_filter($breakdown, fn ($r) => $r['success'])),
            'breakdown' => $breakdown,
        ];
    }
}
