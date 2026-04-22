<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreCostCategoryRequest;
use Modules\Tally\Services\Concerns\PaginatesResults;
use Modules\Tally\Services\Masters\CostCategoryService;

class CostCategoryController extends Controller
{
    use PaginatesResults;

    public function __construct(
        private CostCategoryService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->paginate($this->service->list(), $request);

        return response()->json([
            'success' => true,
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
            'message' => 'Cost categories retrieved successfully',
        ]);
    }

    public function show(string $connection, string $name): JsonResponse
    {
        $item = $this->service->get(urldecode($name));

        if (! $item) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Cost category not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Cost category retrieved successfully']);
    }

    public function store(StoreCostCategoryRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Cost category created successfully' : 'Failed to create cost category',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $connection, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->all());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Cost category updated successfully' : 'Failed to update cost category',
        ]);
    }

    public function destroy(string $connection, string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Cost category deleted successfully' : 'Failed to delete cost category',
        ]);
    }
}
