<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreRecurringVoucherRequest;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyRecurringVoucher;
use Modules\Tally\Services\RecurringVoucherService;

class RecurringVoucherController extends Controller
{
    public function __construct(
        private RecurringVoucherService $service,
    ) {}

    public function index(TallyConnection $connection, Request $request): JsonResponse
    {
        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));
        $rows = TallyRecurringVoucher::query()
            ->where('tally_connection_id', $connection->id)
            ->orderBy('next_run_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
            'message' => 'Recurring vouchers retrieved successfully',
        ]);
    }

    public function show(TallyConnection $connection, TallyRecurringVoucher $recurringVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $recurringVoucher);

        return response()->json([
            'success' => true,
            'data' => $recurringVoucher,
            'message' => 'Recurring voucher retrieved successfully',
        ]);
    }

    public function store(StoreRecurringVoucherRequest $request, TallyConnection $connection): JsonResponse
    {
        $validated = $request->validated();
        $validated['tally_connection_id'] = $connection->id;
        $validated['is_active'] = $validated['is_active'] ?? true;

        // Bootstrap next_run_at based on frequency + start_date.
        $recurring = new TallyRecurringVoucher($validated);
        $recurring->next_run_at = $this->service->bootstrapNextRun($recurring);
        $recurring->save();

        return response()->json([
            'success' => true,
            'data' => $recurring,
            'message' => 'Recurring voucher created successfully',
        ], 201);
    }

    public function update(Request $request, TallyConnection $connection, TallyRecurringVoucher $recurringVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $recurringVoucher);

        $recurringVoucher->update($request->only([
            'name', 'voucher_type', 'frequency', 'day_of_month', 'day_of_week',
            'start_date', 'end_date', 'voucher_template', 'is_active',
        ]));

        return response()->json([
            'success' => true,
            'data' => $recurringVoucher->fresh(),
            'message' => 'Recurring voucher updated successfully',
        ]);
    }

    public function destroy(TallyConnection $connection, TallyRecurringVoucher $recurringVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $recurringVoucher);
        $recurringVoucher->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Recurring voucher deleted successfully',
        ]);
    }

    /**
     * Fire the recurrence immediately (manual override). Advances next_run_at.
     */
    public function run(TallyConnection $connection, TallyRecurringVoucher $recurringVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $recurringVoucher);

        $result = $this->service->fire($recurringVoucher);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => 'Recurring voucher fired successfully',
        ]);
    }

    private function abortIfMismatch(TallyConnection $connection, TallyRecurringVoucher $recurring): void
    {
        if ($recurring->tally_connection_id !== $connection->id) {
            abort(404, 'Recurring voucher not found on this connection');
        }
    }
}
