<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreGroupRequest;
use Modules\Tally\Services\Concerns\PaginatesResults;
use Modules\Tally\Services\Masters\GroupService;

class GroupController extends Controller
{
    use PaginatesResults;

    public function __construct(
        private GroupService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $groups = $this->service->list();
        $groups = $this->filterByField($groups, 'PARENT', $request->query('parent'));
        $paginated = $this->paginate($groups, $request);

        return response()->json([
            'success' => true,
            'data' => $paginated['data'],
            'meta' => $paginated['meta'],
            'message' => 'Groups retrieved successfully',
        ]);
    }

    public function show(string $connection, string $name): JsonResponse
    {
        $group = $this->service->get(urldecode($name));

        if (! $group) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Group not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $group, 'message' => 'Group retrieved successfully']);
    }

    public function store(StoreGroupRequest $request): JsonResponse
    {
        $result = $this->service->create($request->validated());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Group created successfully' : 'Failed to create group',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $connection, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->all());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Group updated successfully' : 'Failed to update group',
        ]);
    }

    public function destroy(string $connection, string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Group deleted successfully' : 'Failed to delete group',
        ]);
    }
}
