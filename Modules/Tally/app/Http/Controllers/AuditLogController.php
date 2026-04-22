<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Models\TallyAuditLog;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = $this->filteredQuery($request)->paginate($request->query('per_page', 50));

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

    /**
     * Return a single audit log with full request/response payloads (no truncation).
     */
    public function show(TallyAuditLog $auditLog): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $auditLog->load('connection'),
            'message' => 'Audit log retrieved successfully',
        ]);
    }

    /**
     * Stream filtered audit log as CSV. Accepts the same query params as index().
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'tally-audit-logs-'.date('Y-m-d-His').'.csv';
        $query = $this->filteredQuery($request);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id', 'created_at', 'user_id', 'connection_id',
                'action', 'object_type', 'object_name',
                'ip_address', 'user_agent',
            ]);

            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->id,
                        optional($r->created_at)->toIso8601String(),
                        $r->user_id,
                        $r->tally_connection_id,
                        $r->action,
                        $r->object_type,
                        $r->object_name,
                        $r->ip_address,
                        $r->user_agent,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(Request $request)
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

        return $query;
    }
}
