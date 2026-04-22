<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreBranchRequest;
use Modules\Tally\Models\TallyBranch;

class BranchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TallyBranch::query()->withCount(['connections']);

        if ($request->query('company_id')) {
            $query->where('tally_company_id', (int) $request->query('company_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
            'message' => 'Branches retrieved successfully',
        ]);
    }

    public function show(TallyBranch $branch): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $branch->loadCount(['connections']),
            'message' => 'Branch retrieved successfully',
        ]);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);

        return response()->json([
            'success' => true,
            'data' => TallyBranch::create($validated),
            'message' => 'Branch created successfully',
        ], 201);
    }

    public function update(StoreBranchRequest $request, TallyBranch $branch): JsonResponse
    {
        $validated = $request->validated();
        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }
        $branch->update($validated);

        return response()->json([
            'success' => true,
            'data' => $branch->fresh(),
            'message' => 'Branch updated successfully',
        ]);
    }

    public function destroy(TallyBranch $branch): JsonResponse
    {
        $branch->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Branch deleted successfully',
        ]);
    }
}
