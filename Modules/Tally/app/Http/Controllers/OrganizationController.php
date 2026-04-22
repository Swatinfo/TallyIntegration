<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreOrganizationRequest;
use Modules\Tally\Models\TallyOrganization;

class OrganizationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => TallyOrganization::withCount(['companies', 'connections'])->get(),
            'message' => 'Organizations retrieved successfully',
        ]);
    }

    public function show(TallyOrganization $organization): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $organization->loadCount(['companies', 'connections']),
            'message' => 'Organization retrieved successfully',
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);

        return response()->json([
            'success' => true,
            'data' => TallyOrganization::create($validated),
            'message' => 'Organization created successfully',
        ], 201);
    }

    public function update(StoreOrganizationRequest $request, TallyOrganization $organization): JsonResponse
    {
        $validated = $request->validated();
        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }
        $organization->update($validated);

        return response()->json([
            'success' => true,
            'data' => $organization->fresh(),
            'message' => 'Organization updated successfully',
        ]);
    }

    public function destroy(TallyOrganization $organization): JsonResponse
    {
        $organization->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Organization deleted successfully',
        ]);
    }
}
