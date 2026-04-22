<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreEmployeeGroupRequest;
use Modules\Tally\Services\Concerns\PaginatesResults;
use Modules\Tally\Services\Masters\EmployeeGroupService;

class EmployeeGroupController extends Controller
{
    use PaginatesResults;

    public function __construct(
        private EmployeeGroupService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginated = $this->paginate($this->service->list(), $request);

        return response()->json([
            'success' => true,
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
            'message' => 'Employee groups retrieved successfully',
        ]);
    }

    public function show(string $connection, string $name): JsonResponse
    {
        $item = $this->service->get(urldecode($name));

        if (! $item) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Employee group not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $item, 'message' => 'Employee group retrieved successfully']);
    }

    public function store(StoreEmployeeGroupRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Employee group created successfully' : 'Failed to create employee group',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $connection, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->all());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Employee group updated successfully' : 'Failed to update employee group',
        ]);
    }

    public function destroy(string $connection, string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Employee group deleted successfully' : 'Failed to delete employee group',
        ]);
    }
}
