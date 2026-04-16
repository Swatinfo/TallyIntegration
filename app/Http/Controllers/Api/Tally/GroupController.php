<?php

namespace App\Http\Controllers\Api\Tally;

use App\Http\Controllers\Controller;
use App\Services\Tally\Masters\GroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    public function __construct(
        private GroupService $service,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->service->list(),
            'message' => 'Groups retrieved successfully',
        ]);
    }

    public function show(string $name): JsonResponse
    {
        $group = $this->service->get(urldecode($name));

        if (! $group) {
            return response()->json(['success' => false, 'data' => null, 'message' => 'Group not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $group, 'message' => 'Group retrieved successfully']);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'NAME' => 'required|string',
            'PARENT' => 'required|string',
        ]);

        $result = $this->service->create($validated);

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Group created successfully' : 'Failed to create group',
        ], $result['errors'] === 0 ? 201 : 422);
    }

    public function update(Request $request, string $name): JsonResponse
    {
        $result = $this->service->update(urldecode($name), $request->all());

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Group updated successfully' : 'Failed to update group',
        ]);
    }

    public function destroy(string $name): JsonResponse
    {
        $result = $this->service->delete(urldecode($name));

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Group deleted successfully' : 'Failed to delete group',
        ]);
    }
}
