<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\Enum;
use Modules\Tally\Http\Requests\DestroyVoucherRequest;
use Modules\Tally\Http\Requests\StoreVoucherRequest;
use Modules\Tally\Http\Requests\UpdateVoucherRequest;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

class VoucherController extends Controller
{
    public function __construct(
        private VoucherService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', new Enum(VoucherType::class)],
            'from_date' => 'nullable|string|size:8',
            'to_date' => 'nullable|string|size:8',
        ]);

        $type = VoucherType::from($validated['type']);
        $vouchers = $this->service->list(
            $type,
            $validated['from_date'] ?? null,
            $validated['to_date'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $vouchers,
            'message' => 'Vouchers retrieved successfully',
        ]);
    }

    public function show(string $connection, string $masterID): JsonResponse
    {
        $voucher = $this->service->get($masterID);

        if (! $voucher) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Voucher not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $voucher, 'message' => 'Voucher retrieved successfully']);
    }

    public function store(StoreVoucherRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $type = VoucherType::from($validated['type']);
        $result = $this->service->create($type, $validated['data']);

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Voucher created successfully' : 'Failed to create voucher',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    /**
     * Bulk-import multiple vouchers of the same type in one Tally request.
     *
     * Payload: { type: "Sales", vouchers: [ {...}, {...}, ... ] }
     */
    public function batch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', new Enum(VoucherType::class)],
            'vouchers' => ['required', 'array', 'min:1'],
            'vouchers.*' => ['array'],
        ]);

        $type = VoucherType::from($validated['type']);
        $result = $this->service->createBatch($type, $validated['vouchers']);

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0
                ? 'Vouchers created successfully'
                : 'Failed to create one or more vouchers',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(UpdateVoucherRequest $request, string $connection, string $masterID): JsonResponse
    {
        $validated = $request->validated();
        $type = VoucherType::from($validated['type']);
        $result = $this->service->alter($masterID, $type, $validated['data']);

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Voucher updated successfully' : 'Failed to update voucher',
        ]);
    }

    public function destroy(DestroyVoucherRequest $request, string $connection, string $masterID): JsonResponse
    {
        $validated = $request->validated();

        $type = VoucherType::from($validated['type']);
        $action = $validated['action'] ?? 'delete';

        if ($action === 'cancel') {
            $result = $this->service->cancel(
                $validated['date'],
                $validated['voucher_number'],
                $type,
                $validated['narration'] ?? null,
            );
            $message = 'Voucher cancelled successfully';
        } else {
            $result = $this->service->delete(
                $validated['date'],
                $validated['voucher_number'],
                $type,
            );
            $message = 'Voucher deleted successfully';
        }

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? $message : 'Failed to '.($action === 'cancel' ? 'cancel' : 'delete').' voucher',
        ]);
    }
}
