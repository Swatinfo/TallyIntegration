<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\Tally\Services\Masters\CostCenterService;
use Modules\Tally\Services\Masters\CurrencyService;
use Modules\Tally\Services\Masters\GodownService;
use Modules\Tally\Services\Masters\GroupService;
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\Masters\StockGroupService;
use Modules\Tally\Services\Masters\StockItemService;
use Modules\Tally\Services\Masters\UnitService;
use Modules\Tally\Services\Masters\VoucherTypeService;

/**
 * Cross-cutting operational endpoints: cache flush, dashboard stats, unified
 * master search. Each hits multiple master services at once, so it lives in
 * its own controller rather than being shoehorned into one of the master ones.
 */
class OperationsController extends Controller
{
    /**
     * Invalidate every master cache for the current connection.
     * Useful after a direct edit inside Tally when stale reads are showing up.
     */
    public function flushCache(): JsonResponse
    {
        $prefixes = [
            'ledger', 'group', 'stock-item', 'stockgroup',
            'unit', 'cost-centre', 'currency', 'godown', 'vouchertype',
        ];

        $flushed = 0;
        foreach ($prefixes as $prefix) {
            // Each service uses Cache::forget on exact keys; here we brute-force
            // the list-level cache. Individual-entity caches expire naturally.
            Cache::forget('tally.'.$prefix.':list');
            $flushed++;
        }

        return response()->json([
            'success' => true,
            'data' => ['flushed_prefixes' => $flushed],
            'message' => 'Master caches flushed',
        ]);
    }

    /**
     * Dashboard counts — how many of each master exist in Tally right now.
     * Uses the services' cached list() so repeated hits are cheap.
     */
    public function stats(
        LedgerService $ledgers,
        GroupService $groups,
        StockItemService $stockItems,
        StockGroupService $stockGroups,
        UnitService $units,
        CostCenterService $costCentres,
        CurrencyService $currencies,
        GodownService $godowns,
        VoucherTypeService $voucherTypes,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => [
                'ledgers' => count($ledgers->list()),
                'groups' => count($groups->list()),
                'stock_items' => count($stockItems->list()),
                'stock_groups' => count($stockGroups->list()),
                'units' => count($units->list()),
                'cost_centres' => count($costCentres->list()),
                'currencies' => count($currencies->list()),
                'godowns' => count($godowns->list()),
                'voucher_types' => count($voucherTypes->list()),
            ],
            'message' => 'Stats retrieved successfully',
        ]);
    }

    /**
     * Cross-master search — looks for a substring (case-insensitive) in the
     * NAME field of ledgers, groups, and stock items. Returns top matches per type.
     */
    public function search(
        Request $request,
        LedgerService $ledgers,
        GroupService $groups,
        StockItemService $stockItems,
    ): JsonResponse {
        $q = trim((string) $request->query('q', ''));
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        if ($q === '') {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Query parameter q is required',
            ], 422);
        }

        $match = fn (array $row) => stripos((string) ($row['NAME'] ?? ''), $q) !== false;

        return response()->json([
            'success' => true,
            'data' => [
                'query' => $q,
                'ledgers' => array_slice(array_values(array_filter($ledgers->list(), $match)), 0, $limit),
                'groups' => array_slice(array_values(array_filter($groups->list(), $match)), 0, $limit),
                'stock_items' => array_slice(array_values(array_filter($stockItems->list(), $match)), 0, $limit),
            ],
            'message' => 'Search completed successfully',
        ]);
    }
}
