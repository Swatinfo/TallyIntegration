<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreCompanyRequest;
use Modules\Tally\Models\TallyCompany;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = TallyCompany::query()->withCount(['branches', 'connections']);

        if ($request->query('organization_id')) {
            $query->where('tally_organization_id', (int) $request->query('organization_id'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
            'message' => 'Companies retrieved successfully',
        ]);
    }

    public function show(TallyCompany $company): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $company->loadCount(['branches', 'connections']),
            'message' => 'Company retrieved successfully',
        ]);
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);

        return response()->json([
            'success' => true,
            'data' => TallyCompany::create($validated),
            'message' => 'Company created successfully',
        ], 201);
    }

    public function update(StoreCompanyRequest $request, TallyCompany $company): JsonResponse
    {
        $validated = $request->validated();
        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }
        $company->update($validated);

        return response()->json([
            'success' => true,
            'data' => $company->fresh(),
            'message' => 'Company updated successfully',
        ]);
    }

    public function destroy(TallyCompany $company): JsonResponse
    {
        $company->delete();

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Company deleted successfully',
        ]);
    }
}
