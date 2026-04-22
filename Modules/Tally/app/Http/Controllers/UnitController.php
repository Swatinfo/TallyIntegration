<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreUnitRequest;
use Modules\Tally\Services\Concerns\PaginatesResults;
use Modules\Tally\Services\Masters\UnitService;

class UnitController extends Controller
{
    use PaginatesResults;

    public function __construct(
        private UnitService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->service->list();
        $paginated = $this->paginate($items, $request);

        return response()->json([
            'success' => true,
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
            'message' => 'Units retrieved successfully',
        ]);
    }

    public function show(string $connection, string $name): JsonResponse
    {
        $item = $this->service->get(urldecode($name));

        if (! $item) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Unit not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Unit retrieved successfully']);
    }

    public function store(StoreUnitRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Unit created successfully' : 'Failed to create unit',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $connection, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->all());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Unit updated successfully' : 'Failed to update unit',
        ]);
    }

    public function destroy(string $connection, string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Unit deleted successfully' : 'Failed to delete unit',
        ]);
    }
}
