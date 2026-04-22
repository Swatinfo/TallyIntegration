<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreDraftVoucherRequest;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyDraftVoucher;
use Modules\Tally\Services\WorkflowService;

class DraftVoucherController extends Controller
{
    public function __construct(
        private WorkflowService $workflow,
    ) {}

    public function index(TallyConnection $connection, Request $request): JsonResponse
    {
        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));

        $query = TallyDraftVoucher::query()->where('tally_connection_id', $connection->id);

        if ($request->query('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->query('created_by')) {
            $query->where('created_by', (int) $request->query('created_by'));
        }

        $rows = $query->latest('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $rows->items(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
                'last_page' => $rows->lastPage(),
            ],
            'message' => 'Draft vouchers retrieved successfully',
        ]);
    }

    public function show(TallyConnection $connection, TallyDraftVoucher $draftVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $draftVoucher);

        return response()->json([
            'success' => true,
            'data' => $draftVoucher,
            'message' => 'Draft voucher retrieved successfully',
        ]);
    }

    public function store(StoreDraftVoucherRequest $request, TallyConnection $connection): JsonResponse
    {
        $validated = $request->validated();

        $draft = TallyDraftVoucher::create([
            'tally_connection_id' => $connection->id,
            'voucher_type' => $validated['voucher_type'],
            'voucher_data' => $validated['voucher_data'],
            'narration' => $validated['narration'] ?? null,
            'amount' => $validated['amount'],
            'status' => TallyDraftVoucher::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $draft,
            'message' => 'Draft voucher created successfully',
        ], 201);
    }

    public function update(Request $request, TallyConnection $connection, TallyDraftVoucher $draftVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $draftVoucher);

        if (! $draftVoucher->isEditable()) {
            return response()->json([
                'success' => false,
                'data' => $draftVoucher,
                'message' => "Cannot edit — current status is '{$draftVoucher->status}'",
            ], 409);
        }

        $draftVoucher->update($request->only(['voucher_type', 'voucher_data', 'narration', 'amount']));

        return response()->json([
            'success' => true,
            'data' => $draftVoucher->fresh(),
            'message' => 'Draft voucher updated successfully',
        ]);
    }

    public function destroy(TallyConnection $connection, TallyDraftVoucher $draftVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $draftVoucher);

        if (! $draftVoucher->isEditable()) {
            return response()->json([
                'success' => false,
                'data' => $draftVoucher,
                'message' => "Cannot delete — current status is '{$draftVoucher->status}'",
            ], 409);
        }

        $draftVoucher->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Draft voucher deleted successfully',
        ]);
    }

    public function submit(TallyConnection $connection, TallyDraftVoucher $draftVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $draftVoucher);

        try {
            $fresh = $this->workflow->submit($draftVoucher, auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'data' => $draftVoucher, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'data' => $fresh,
            'message' => $fresh->status === TallyDraftVoucher::STATUS_PUSHED
                ? 'Draft auto-approved and pushed to Tally'
                : 'Draft submitted for approval',
        ]);
    }

    public function approve(TallyConnection $connection, TallyDraftVoucher $draftVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $draftVoucher);

        try {
            $fresh = $this->workflow->approve($draftVoucher, auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'data' => $draftVoucher, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'data' => $fresh,
            'message' => 'Draft approved and pushed to Tally',
        ]);
    }

    public function reject(Request $request, TallyConnection $connection, TallyDraftVoucher $draftVoucher): JsonResponse
    {
        $this->abortIfMismatch($connection, $draftVoucher);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        try {
            $fresh = $this->workflow->reject($draftVoucher, $validated['reason'], auth()->id());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'data' => $draftVoucher, 'message' => $e->getMessage()], 409);
        }

        return response()->json([
            'success' => true,
            'data' => $fresh,
            'message' => 'Draft rejected',
        ]);
    }

    private function abortIfMismatch(TallyConnection $connection, TallyDraftVoucher $draft): void
    {
        if ($draft->tally_connection_id !== $connection->id) {
            abort(404, 'Draft voucher not found on this connection');
        }
    }
}
