<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreGodownRequest;
use Modules\Tally\Services\Concerns\PaginatesResults;
use Modules\Tally\Services\Masters\GodownService;

class GodownController extends Controller
{
    use PaginatesResults;

    public function __construct(
        private GodownService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->service->list();
        $paginated = $this->paginate($items, $request);

        return response()->json([
            'success' => true,
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
            'message' => 'Godowns retrieved successfully',
        ]);
    }

    public function show(string $connection, string $name): JsonResponse
    {
        $item = $this->service->get(urldecode($name));

        if (! $item) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Godown not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Godown retrieved successfully']);
    }

    public function store(StoreGodownRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Godown created successfully' : 'Failed to create godown',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $connection, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->all());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Godown updated successfully' : 'Failed to update godown',
        ]);
    }

    public function destroy(string $connection, string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Godown deleted successfully' : 'Failed to delete godown',
        ]);
    }
}
