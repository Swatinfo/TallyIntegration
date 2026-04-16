<?php

namespace App\Http\Controllers\Api\Tally;

use App\Http\Controllers\Controller;
use App\Services\Tally\Masters\StockItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockItemController extends Controller
{
    public function __construct(
        private StockItemService $service,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->list(),
            'message' => 'Stock items retrieved successfully',
        ]);
    }

    public function show(string $name): JsonResponse
    {
        $item = $this->service->get(urldecode($name));

        if (! $item) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Stock item not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Stock item retrieved successfully']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'NAME' => 'required|string',
            'PARENT' => 'nullable|string',
            'BASEUNITS' => 'nullable|string',
            'OPENINGBALANCE' => 'nullable|string',
            'OPENINGRATE' => 'nullable|string',
        ]);

        $result = $this->service->create($validated);

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Stock item created successfully' : 'Failed to create stock item',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->all());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Stock item updated successfully' : 'Failed to update stock item',
        ]);
    }

    public function destroy(string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Stock item deleted successfully' : 'Failed to delete stock item',
        ]);
    }
}
