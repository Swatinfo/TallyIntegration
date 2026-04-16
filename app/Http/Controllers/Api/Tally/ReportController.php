<?php

namespace App\Http\Controllers\Api\Tally;

use App\Http\Controllers\Controller;
use App\Services\Tally\Reports\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private ReportService $service,
    ) {}

    public function show(Request $request, string $type): JsonResponse
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
            default => null,
        };

        if ($data === null) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => "Unknown report type: {$type}. Valid types: balance-sheet, profit-and-loss, trial-balance, ledger, outstandings, stock-summary, day-book",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Report retrieved successfully',
        ]);
    }
}
