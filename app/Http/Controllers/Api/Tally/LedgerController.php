<?php

namespace App\Http\Controllers\Api\Tally;

use App\Http\Controllers\Controller;
use App\Services\Tally\Masters\LedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    public function __construct(
        private LedgerService $service,
    ) {}

    public function index(): JsonResponse
    {
        $ledgers = $this->service->list();

        return response()->json([
            'success' => true,
            'data' => $ledgers,
            'message' => 'Ledgers retrieved successfully',
        ]);
    }

    public function show(string $name): JsonResponse
    {
        $ledger = $this->service->get(urldecode($name));

        if (! $ledger) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => 'Ledger not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $ledger,
            'message' => 'Ledger retrieved successfully',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'NAME' => 'required|string',
            'PARENT' => 'required|string',
            'OPENINGBALANCE' => 'nullable|numeric',
        ]);

        $result = $this->service->create($validated);

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Ledger created successfully' : 'Failed to create ledger',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $validated = $request->validate([
            'PARENT' => 'nullable|string',
            'OPENINGBALANCE' => 'nullable|numeric',
        ]);

        $result = $this->service->update(urldecode($name), $validated);

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Ledger updated successfully' : 'Failed to update ledger',
        ]);
    }

    public function destroy(string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Ledger deleted successfully' : 'Failed to delete ledger',
        ]);
    }
}
