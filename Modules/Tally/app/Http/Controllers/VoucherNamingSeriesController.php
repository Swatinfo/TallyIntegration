<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreVoucherNamingSeriesRequest;
use Modules\Tally\Models\TallyVoucherNamingSeries;

class VoucherNamingSeriesController extends Controller
{
    public function index(Request $request, int $connection): JsonResponse
    {
        $query = TallyVoucherNamingSeries::query()->where('tally_connection_id', $connection);

        if ($type = $request->query('voucher_type')) {
            $query->where('voucher_type', $type);
        }

        $items = $query->orderBy('voucher_type')->orderBy('series_name')->get();

        return response()->json([
            'success' => true,
            'data' => $items,
            'message' => 'Naming series retrieved successfully',
        ]);
    }

    public function store(StoreVoucherNamingSeriesRequest $request, int $connection): JsonResponse
    {
        $series = TallyVoucherNamingSeries::updateOrCreate(
            [
                'tally_connection_id' => $connection,
                'voucher_type' => $request->validated('voucher_type'),
                'series_name' => $request->validated('series_name'),
            ],
            $request->safe()->except(['voucher_type', 'series_name']),
        );

        return response()->json([
            'success' => true,
            'data' => $series,
            'message' => 'Naming series saved successfully',
        ], 201);
    }

    public function update(StoreVoucherNamingSeriesRequest $request, int $connection, int $series): JsonResponse
    {
        $row = TallyVoucherNamingSeries::query()
            ->where('tally_connection_id', $connection)
            ->where('id', $series)
            ->firstOrFail();

        $row->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => $row->fresh(),
            'message' => 'Naming series updated successfully',
        ]);
    }

    public function destroy(int $connection, int $series): JsonResponse
    {
        $deleted = TallyVoucherNamingSeries::query()
            ->where('tally_connection_id', $connection)
            ->where('id', $series)
            ->delete();

        return response()->json([
            'success' => (bool) $deleted,
            'data' => ['deleted' => $deleted],
            'message' => $deleted ? 'Naming series deleted successfully' : 'Series not found',
        ], $deleted ? 200 : 404);
    }
}
