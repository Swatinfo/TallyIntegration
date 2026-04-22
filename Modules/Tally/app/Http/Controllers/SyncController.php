<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Jobs\SyncFromTallyJob;
use Modules\Tally\Jobs\SyncToTallyJob;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallySync;
use Modules\Tally\Services\SyncTracker;

class SyncController extends Controller
{
    public function __construct(
        private SyncTracker $tracker,
    ) {}

    /**
     * Get sync statistics for a connection.
     */
    public function stats(TallyConnection $connection): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->tracker->stats($connection->id),
            'message' => 'Sync stats retrieved successfully',
        ]);
    }

    /**
     * Get pending sync records for a connection.
     */
    public function pending(TallyConnection $connection, Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        $pending = $this->tracker->getPending($connection->id, $limit);

        return response()->json([
            'success' => true,
            'data' => $pending,
            'message' => 'Pending syncs retrieved successfully',
        ]);
    }

    /**
     * Get conflicts for a connection.
     */
    public function conflicts(TallyConnection $connection): JsonResponse
    {
        $conflicts = $this->tracker->getConflicts($connection->id);

        return response()->json([
            'success' => true,
            'data' => $conflicts,
            'message' => 'Conflicts retrieved successfully',
        ]);
    }

    /**
     * Resolve a conflict.
     */
    public function resolveConflict(Request $request, TallySync $sync): JsonResponse
    {
        $validated = $request->validate([
            'strategy' => 'required|string|in:erp_wins,tally_wins,merge,newest_wins,manual',
        ]);

        $this->tracker->resolveConflict($sync, $validated['strategy'], auth()->id());

        return response()->json([
            'success' => true,
            'data' => $sync->fresh(),
            'message' => 'Conflict resolved successfully',
        ]);
    }

    /**
     * Trigger inbound sync (Tally → ERP) for a connection.
     */
    public function triggerInbound(TallyConnection $connection): JsonResponse
    {
        SyncFromTallyJob::dispatch($connection->code, force: true);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Inbound sync job dispatched for '.$connection->code,
        ], 202);
    }

    /**
     * Trigger outbound sync (ERP → Tally) for a connection.
     */
    public function triggerOutbound(TallyConnection $connection): JsonResponse
    {
        SyncToTallyJob::dispatch($connection->code);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Outbound sync job dispatched for '.$connection->code,
        ], 202);
    }

    /**
     * Trigger full bidirectional sync for a connection.
     */
    public function triggerFull(TallyConnection $connection): JsonResponse
    {
        SyncFromTallyJob::dispatch($connection->code, force: true);
        SyncToTallyJob::dispatch($connection->code);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Full sync dispatched for '.$connection->code,
        ], 202);
    }

    // ----------------------------------------------------------------------
    // Phase 9C — Observability
    // ----------------------------------------------------------------------

    /**
     * Show a single sync record with full context (error_message, conflict_data, etc).
     */
    public function show(TallySync $sync): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $sync,
            'message' => 'Sync record retrieved successfully',
        ]);
    }

    /**
     * Cancel a pending sync. No-op if it's already completed/failed/cancelled.
     */
    public function cancel(TallySync $sync): JsonResponse
    {
        if (! in_array($sync->sync_status, ['pending', 'in_progress'], true)) {
            return response()->json([
                'success' => false,
                'data' => $sync,
                'message' => "Cannot cancel — current status is '{$sync->sync_status}'",
            ], 422);
        }

        $sync->update([
            'sync_status' => 'cancelled',
            'error_message' => 'Cancelled via API at '.now()->toIso8601String(),
        ]);

        return response()->json([
            'success' => true,
            'data' => $sync->fresh(),
            'message' => 'Sync cancelled successfully',
        ]);
    }

    /**
     * Sync history — completed + failed + cancelled records, newest first, paginated.
     */
    public function history(TallyConnection $connection, Request $request): JsonResponse
    {
        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));

        $records = TallySync::query()
            ->where('tally_connection_id', $connection->id)
            ->whereIn('sync_status', ['completed', 'failed', 'cancelled'])
            ->orderByDesc('last_sync_attempt')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
            'message' => 'Sync history retrieved successfully',
        ]);
    }

    /**
     * Bulk-resolve every open conflict on a connection with a single strategy.
     */
    public function resolveAll(Request $request, TallyConnection $connection): JsonResponse
    {
        $validated = $request->validate([
            'strategy' => 'required|string|in:erp_wins,tally_wins,merge,newest_wins,manual',
        ]);

        $conflicts = $this->tracker->getConflicts($connection->id);
        $resolved = 0;
        foreach ($conflicts as $sync) {
            $this->tracker->resolveConflict($sync, $validated['strategy'], auth()->id());
            $resolved++;
        }

        return response()->json([
            'success' => true,
            'data' => ['resolved' => $resolved, 'strategy' => $validated['strategy']],
            'message' => "Resolved {$resolved} conflict(s) with strategy {$validated['strategy']}",
        ]);
    }

    /**
     * Exception report — every failed / conflict sync for a connection, paginated.
     *
     * Mirrors the WebStatus Exception Report pattern from laxmantandon/tally_migration_tdl's
     * EXCEPTION_Reports.txt: surface everything that didn't sync cleanly so an operator
     * can see it at a glance. Filterable by entity_type.
     */
    public function exceptions(TallyConnection $connection, Request $request): JsonResponse
    {
        $perPage = min(200, max(1, (int) $request->query('per_page', 50)));
        $entityType = $request->query('entity_type');

        $query = TallySync::query()
            ->where('tally_connection_id', $connection->id)
            ->whereIn('sync_status', ['failed', 'conflict']);

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        $records = $query->orderByDesc('last_sync_attempt')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'last_page' => $records->lastPage(),
            ],
            'message' => 'Sync exceptions retrieved successfully',
        ]);
    }

    /**
     * Reset sync status — clear failed/conflict flags for retry.
     *
     * Mirrors the "Reset Status" button from laxmantandon/tally_migration_tdl. Marks
     * all matching rows as `pending` so the next sync run picks them up. Filters by
     * entity_type optionally.
     */
    public function resetStatus(TallyConnection $connection, Request $request): JsonResponse
    {
        $entityType = $request->input('entity_type');

        $query = TallySync::query()
            ->where('tally_connection_id', $connection->id)
            ->whereIn('sync_status', ['failed', 'conflict']);

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        $count = $query->update([
            'sync_status' => 'pending',
            'error_message' => null,
            'conflict_data' => null,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['reset' => $count],
            'message' => "Reset {$count} sync record(s) to pending",
        ]);
    }
}
