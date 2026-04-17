<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreLedgerRequest;
use Modules\Tally\Http\Requests\UpdateLedgerRequest;
use Modules\Tally\Services\Concerns\PaginatesResults;
use Modules\Tally\Services\Masters\LedgerService;

class LedgerController extends Controller
{
    use PaginatesResults;

    public function __construct(
        private LedgerService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $ledgers = $this->service->list();
        $paginated = $this->paginate($ledgers, $request);

        return response()->json([
            'success' => true,
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
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

    public function store(StoreLedgerRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Ledger created successfully' : 'Failed to create ledger',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(UpdateLedgerRequest $request, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->validated());

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
