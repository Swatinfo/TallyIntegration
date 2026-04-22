<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Manufacturing\ManufacturingService;

/**
 * Manufacturing operations — BOM management + manufacturing journal + job work.
 *
 * BOM is stored on the finished stock item's `COMPONENTLIST.LIST`; the endpoints
 * under /stock-items/{name}/bom are a convenience layer around stock-item ALTER.
 */
class ManufacturingController extends Controller
{
    public function __construct(
        private ManufacturingService $service,
    ) {}

    public function getBom(string $name): JsonResponse
    {
        $bom = $this->service->getBom(urldecode($name));

        if ($bom === null) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Stock item not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stock_item' => urldecode($name),
                'components' => $bom,
            ],
            'message' => 'BOM retrieved successfully',
        ]);
    }

    public function setBom(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'components' => ['required', 'array', 'min:1'],
            'components.*.name' => ['required', 'string', 'max:255', new SafeXmlString],
            'components.*.qty' => ['required', 'numeric', 'gt:0'],
            'components.*.unit' => ['nullable', 'string', 'max:50'],
        ]);

        $result = $this->service->setBom(urldecode($name), $validated['components']);

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'BOM updated successfully' : 'Failed to update BOM',
        ], $result['errors'] === 0 ? 200 : 422);
    }

    public function manufacture(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'string', 'size:8'],
            'product_item' => ['required', 'string', 'max:255', new SafeXmlString],
            'product_qty' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'product_godown' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'components' => ['required', 'array', 'min:1'],
            'components.*.name' => ['required', 'string', 'max:255', new SafeXmlString],
            'components.*.qty' => ['required', 'numeric', 'gt:0'],
            'components.*.unit' => ['nullable', 'string', 'max:50'],
            'components.*.godown' => ['nullable', 'string', 'max:255', new SafeXmlString],
            'voucher_number' => ['nullable', 'string', 'max:100', new SafeXmlString],
            'narration' => ['nullable', 'string', 'max:500', new SafeXmlString],
        ]);

        $result = $this->service->createManufacturingVoucher(
            $validated['date'],
            $validated['product_item'],
            (float) $validated['product_qty'],
            $validated['components'],
            $validated['product_godown'] ?? null,
            $validated['unit'] ?? 'Nos',
            $validated['voucher_number'] ?? null,
            $validated['narration'] ?? null,
        );

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Manufacturing voucher created' : 'Failed to create manufacturing voucher',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function jobWorkOut(Request $request): JsonResponse
    {
        $validated = $this->validateJobWork($request);

        $result = $this->service->createJobWorkOut(
            $validated['date'], $validated['job_worker_ledger'], $validated['stock_item'],
            (float) $validated['quantity'], $validated['unit'] ?? 'Nos',
            $validated['voucher_number'] ?? null, $validated['narration'] ?? null,
        );

        return $this->jobWorkResponse($result);
    }

    public function jobWorkIn(Request $request): JsonResponse
    {
        $validated = $this->validateJobWork($request);

        $result = $this->service->createJobWorkIn(
            $validated['date'], $validated['job_worker_ledger'], $validated['stock_item'],
            (float) $validated['quantity'], $validated['unit'] ?? 'Nos',
            $validated['voucher_number'] ?? null, $validated['narration'] ?? null,
        );

        return $this->jobWorkResponse($result);
    }

    private function validateJobWork(Request $request): array
    {
        return $request->validate([
            'date' => ['required', 'string', 'size:8'],
            'job_worker_ledger' => ['required', 'string', 'max:255', new SafeXmlString],
            'stock_item' => ['required', 'string', 'max:255', new SafeXmlString],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'voucher_number' => ['nullable', 'string', 'max:100', new SafeXmlString],
            'narration' => ['nullable', 'string', 'max:500', new SafeXmlString],
        ]);
    }

    private function jobWorkResponse(array $result): JsonResponse
    {
        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Job work voucher created' : 'Failed to create job work voucher',
        ], $result['errors'] === 0 ? 201 : 422);
    }
}
