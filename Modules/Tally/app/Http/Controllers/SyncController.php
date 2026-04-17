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
}
