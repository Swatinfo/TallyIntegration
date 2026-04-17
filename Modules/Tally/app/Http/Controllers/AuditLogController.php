<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Models\TallyAuditLog;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TallyAuditLog::query()->latest('created_at');

        if ($request->query('connection')) {
            $query->whereHas('connection', fn ($q) => $q->where('code', $request->query('connection')));
        }

        if ($request->query('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->query('object_type')) {
            $query->where('object_type', $request->query('object_type'));
        }

        if ($request->query('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        $logs = $query->paginate($request->query('per_page', 50));

        return response()->json([
            'success' => true,
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'last_page' => $logs->lastPage(),
            ],
            'message' => 'Audit logs retrieved successfully',
        ]);
    }
}
