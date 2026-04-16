<?php

namespace App\Http\Controllers\Api\Tally;

use App\Http\Controllers\Controller;
use App\Models\TallyConnection;
use App\Services\Tally\TallyConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TallyConnectionController extends Controller
{
    public function index(): JsonResponse
    {
        $connections = TallyConnection::all();

        return response()->json([
            'success' => true,
            'data' => $connections,
            'message' => 'Connections retrieved successfully',
        ]);
    }

    public function show(TallyConnection $connection): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $connection,
            'message' => 'Connection retrieved successfully',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:20|unique:tally_connections,code|alpha_num',
            'host' => 'required|string|max:255',
            'port' => 'required|integer|min:1|max:65535',
            'company_name' => 'nullable|string|max:255',
            'timeout' => 'nullable|integer|min:5|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $connection = TallyConnection::create($validated);

        return response()->json([
            'success' => true,
            'data' => $connection,
            'message' => 'Connection created successfully',
        ], 201);
    }

    public function update(Request $request, TallyConnection $connection): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:20|alpha_num|unique:tally_connections,code,'.$connection->id,
            'host' => 'nullable|string|max:255',
            'port' => 'nullable|integer|min:1|max:65535',
            'company_name' => 'nullable|string|max:255',
            'timeout' => 'nullable|integer|min:5|max:120',
            'is_active' => 'nullable|boolean',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $connection->update($validated);

        // Flush cached client so next request picks up new config
        app(TallyConnectionManager::class)->flush($connection->code);

        return response()->json([
            'success' => true,
            'data' => $connection->fresh(),
            'message' => 'Connection updated successfully',
        ]);
    }

    public function destroy(TallyConnection $connection): JsonResponse
    {
        $code = $connection->code;
        $connection->delete();

        app(TallyConnectionManager::class)->flush($code);

        return response()->json([
            'success' => true,
            'data' => null,
            'message' => 'Connection deleted successfully',
        ]);
    }

    /**
     * Health check for a specific connection.
     */
    public function health(TallyConnection $connection, TallyConnectionManager $manager): JsonResponse
    {
        $client = $manager->fromConnection($connection);
        $connected = $client->isConnected();

        $data = [
            'connection' => $connection->code,
            'name' => $connection->name,
            'connected' => $connected,
            'url' => $client->getUrl(),
        ];

        if ($connected) {
            $data['companies'] = $client->getCompanies();
        }

        return response()->json([
            'success' => $connected,
            'data' => $data,
            'message' => $connected ? 'TallyPrime is reachable' : 'Cannot connect to TallyPrime',
        ], $connected ? 200 : 503);
    }
}
