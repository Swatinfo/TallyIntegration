<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Models\TallyOrganization;
use Modules\Tally\Services\Consolidation\ConsolidationService;

class ConsolidatedReportController extends Controller
{
    public function __construct(
        private ConsolidationService $service,
    ) {}

    public function balanceSheet(Request $request, TallyOrganization $organization): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->consolidatedBalanceSheet($organization, $request->query('date')),
            'message' => 'Consolidated balance sheet retrieved successfully',
        ]);
    }

    public function profitAndLoss(Request $request, TallyOrganization $organization): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['required', 'string', 'size:8'],
            'to' => ['required', 'string', 'size:8'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->service->consolidatedProfitAndLoss($organization, $validated['from'], $validated['to']),
            'message' => 'Consolidated profit & loss retrieved successfully',
        ]);
    }

    public function trialBalance(Request $request, TallyOrganization $organization): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->consolidatedTrialBalance($organization, $request->query('date')),
            'message' => 'Consolidated trial balance retrieved successfully',
        ]);
    }
}
