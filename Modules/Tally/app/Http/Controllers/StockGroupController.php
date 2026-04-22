<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreStockGroupRequest;
use Modules\Tally\Services\Concerns\PaginatesResults;
use Modules\Tally\Services\Masters\StockGroupService;

class StockGroupController extends Controller
{
    use PaginatesResults;

    public function __construct(
        private StockGroupService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $items = $this->service->list();
        $items = $this->filterByField($items, 'PARENT', $request->query('parent'));
        $items = $this->filterByZeroBalance($items, 'CLOSINGBALANCE', $request->boolean('zero_balance'));
        $paginated = $this->paginate($items, $request);

        return response()->json([
            'success' => true,
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
            'message' => 'Stock groups retrieved successfully',
        ]);
    }

    public function show(string $connection, string $name): JsonResponse
    {
        $item = $this->service->get(urldecode($name));

        if (! $item) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Stock group not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Stock group retrieved successfully']);
    }

    public function store(StoreStockGroupRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Stock group created successfully' : 'Failed to create stock group',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $connection, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->all());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Stock group updated successfully' : 'Failed to update stock group',
        ]);
    }

    public function destroy(string $connection, string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Stock group deleted successfully' : 'Failed to delete stock group',
        ]);
    }
}
