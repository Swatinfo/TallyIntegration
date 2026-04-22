<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Services\Reports\ReportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $service,
    ) {}

    public function show(Request $request, string $connection, string $type): JsonResponse|StreamedResponse
    {
        $data = match ($type) {
            'balance-sheet' => $this->service->balanceSheet($request->query('date')),
            'profit-and-loss' => $this->service->profitAndLoss(
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'trial-balance' => $this->service->trialBalance($request->query('date')),
            'ledger' => $this->service->ledgerReport(
                $request->query('ledger', ''),
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'outstandings' => $this->service->outstandings($request->query('type', 'receivable')),
            'stock-summary' => $this->service->stockSummary(),
            'day-book' => $this->service->dayBook($request->query('date', '')),
            // Phase 9B additions
            'cash-book' => $this->service->cashBankBook(
                $request->query('ledger', ''),
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'sales-register' => $this->service->salesRegister(
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'purchase-register' => $this->service->purchaseRegister(
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'aging' => $this->service->agingAnalysis(
                $request->query('type', 'receivable'),
                $request->query('as_of'),
            ),
            'cash-flow' => $this->service->cashFlow(
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'funds-flow' => $this->service->fundsFlow(
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'receipts-payments' => $this->service->receiptsPayments(
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'stock-movement' => $this->service->stockMovement(
                $request->query('stock_item', ''),
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            // Phase 9D — banking reports
            'bank-reconciliation' => $this->service->bankReconciliation(
                $request->query('bank', ''),
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'cheque-register' => $this->service->chequeRegister(
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            'post-dated-cheques' => $this->service->postDatedCheques(
                $request->query('from', ''),
                $request->query('to', ''),
            ),
            default => null,
        };

        if ($data === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => "Unknown report type: {$type}. Valid types: balance-sheet, profit-and-loss, trial-balance, ledger, outstandings, stock-summary, day-book, cash-book, sales-register, purchase-register, aging, cash-flow, funds-flow, receipts-payments, stock-movement, bank-reconciliation, cheque-register, post-dated-cheques",
            ], 404);
        }

        // CSV export
        if ($request->query('format') === 'csv' || $request->header('Accept') === 'text/csv') {
            return $this->exportCsv($data, $type);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Report retrieved successfully',
        ]);
    }

    private function exportCsv(array $data, string $type): StreamedResponse
    {
        $filename = "{$type}-".date('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($data) {
            $output = fopen('php://output', 'w');

            $this->writeCsvRows($output, $data);

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @param  resource  $output
     */
    private function writeCsvRows($output, array $data, string $prefix = ''): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $this->writeCsvRows($output, $value, $prefix ? "{$prefix}.{$key}" : (string) $key);
            } else {
                fputcsv($output, [$prefix ? "{$prefix}.{$key}" : (string) $key, (string) $value]);
            }
        }
    }
}
