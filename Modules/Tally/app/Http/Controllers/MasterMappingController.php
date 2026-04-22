<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreMasterMappingRequest;
use Modules\Tally\Models\TallyMasterMapping;

class MasterMappingController extends Controller
{
    public function index(Request $request, int $connection): JsonResponse
    {
        $query = TallyMasterMapping::query()->where('tally_connection_id', $connection);

        if ($type = $request->query('entity_type')) {
            $query->where('entity_type', $type);
        }

        $items = $query->orderBy('entity_type')->orderBy('erp_name')->paginate(50);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
            ],
            'message' => 'Master mappings retrieved successfully',
        ]);
    }

    public function store(StoreMasterMappingRequest $request, int $connection): JsonResponse
    {
        $mapping = TallyMasterMapping::updateOrCreate(
            [
                'tally_connection_id' => $connection,
                'entity_type' => $request->validated('entity_type'),
                'tally_name' => $request->validated('tally_name'),
            ],
            [
                'erp_name' => $request->validated('erp_name'),
                'metadata' => $request->validated('metadata'),
            ],
        );

        return response()->json([
            'success' => true,
            'data' => $mapping,
            'message' => 'Master mapping saved successfully',
        ], 201);
    }

    public function destroy(int $connection, int $mapping): JsonResponse
    {
        $deleted = TallyMasterMapping::query()
            ->where('tally_connection_id', $connection)
            ->where('id', $mapping)
            ->delete();

        return response()->json([
            'success' => (bool) $deleted,
            'data' => ['deleted' => $deleted],
            'message' => $deleted ? 'Master mapping deleted successfully' : 'Mapping not found',
        ], $deleted ? 200 : 404);
    }
}
