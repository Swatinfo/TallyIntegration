<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Vouchers\VoucherService;

/**
 * Convenience endpoints for inventory operations that would otherwise require
 * the caller to assemble complex voucher payloads (stock transfer + physical count).
 *
 * These delegate to VoucherService helpers. Callers who need finer control can
 * still post directly to /{c}/vouchers with type = StockJournal / PhysicalStock.
 */
class InventoryController extends Controller
{
    public function __construct(
        private VoucherService $service,
    ) {}

    public function stockTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'string', 'size:8'],           // YYYYMMDD
            'from_godown' => ['required', 'string', 'max:255', new SafeXmlString],
            'to_godown' => ['required', 'string', 'max:255', new SafeXmlString],
            'stock_item' => ['required', 'string', 'max:255', new SafeXmlString],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'rate' => ['nullable', 'numeric', 'gte:0'],
            'voucher_number' => ['nullable', 'string', 'max:100', new SafeXmlString],
            'narration' => ['nullable', 'string', 'max:500', new SafeXmlString],
        ]);

        $result = $this->service->createStockTransfer(
            $validated['date'],
            $validated['from_godown'],
            $validated['to_godown'],
            $validated['stock_item'],
            (float) $validated['quantity'],
            $validated['unit'] ?? 'Nos',
            isset($validated['rate']) ? (float) $validated['rate'] : null,
            $validated['voucher_number'] ?? null,
            $validated['narration'] ?? null,
        );

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Stock transfer created' : 'Failed to create stock transfer',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function physicalStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'string', 'size:8'],
            'godown' => ['required', 'string', 'max:255', new SafeXmlString],
            'stock_item' => ['required', 'string', 'max:255', new SafeXmlString],
            'counted_quantity' => ['required', 'numeric', 'gte:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'voucher_number' => ['nullable', 'string', 'max:100', new SafeXmlString],
            'narration' => ['nullable', 'string', 'max:500', new SafeXmlString],
        ]);

        $result = $this->service->createPhysicalStock(
            $validated['date'],
            $validated['godown'],
            $validated['stock_item'],
            (float) $validated['counted_quantity'],
            $validated['unit'] ?? 'Nos',
            $validated['voucher_number'] ?? null,
            $validated['narration'] ?? null,
        );

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Physical stock voucher created' : 'Failed to create physical stock voucher',
        ], $result['errors'] === 0 ? 201 : 422);
    }
}
