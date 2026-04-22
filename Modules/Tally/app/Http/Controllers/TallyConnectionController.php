<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Tally\Http\Requests\StoreConnectionRequest;
use Modules\Tally\Http\Requests\UpdateConnectionRequest;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\CircuitBreaker;
use Modules\Tally\Services\MetricsCollector;
use Modules\Tally\Services\TallyConnectionManager;
use Modules\Tally\Services\TallyHttpClient;

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

    public function store(StoreConnectionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);
        $connection = TallyConnection::create($validated);

        return response()->json([
            'success' => true,
            'data' => $connection,
            'message' => 'Connection created successfully',
        ], 201);
    }

    public function update(UpdateConnectionRequest $request, TallyConnection $connection): JsonResponse
    {
        $validated = $request->validated();

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

    public function metrics(TallyConnection $connection, Request $request, MetricsCollector $metrics): JsonResponse
    {
        $period = $request->query('period', '1h');
        $stats = $metrics->getStats($connection->id, $period);

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Metrics retrieved successfully',
        ]);
    }

    public function discover(TallyConnection $connection, TallyConnectionManager $manager): JsonResponse
    {
        $client = $manager->fromConnection($connection);
        $companies = $client->getCompanies();

        return response()->json([
            'success' => true,
            'data' => ['companies' => $companies],
            'message' => 'Companies discovered successfully',
        ]);
    }

    /**
     * List loaded companies as a top-level resource (distinct from /discover
     * which emphasises the refresh aspect — this is the simple list).
     */
    public function companies(TallyConnection $connection, TallyConnectionManager $manager): JsonResponse
    {
        $client = $manager->fromConnection($connection);

        return response()->json([
            'success' => true,
            'data' => $client->getCompanies(),
            'message' => 'Companies retrieved successfully',
        ]);
    }

    /**
     * Read the circuit-breaker state for this connection. Useful for dashboards
     * — lets operators see whether the breaker has tripped before any user does.
     */
    public function circuitState(TallyConnection $connection, CircuitBreaker $breaker): JsonResponse
    {
        $state = $breaker->getState($connection->code);

        return response()->json([
            'success' => true,
            'data' => [
                'connection' => $connection->code,
                'state' => $state,
                'available' => $state !== 'open',
            ],
            'message' => "Circuit state for {$connection->code}: {$state}",
        ]);
    }

    public function test(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'host' => 'required|string',
            'port' => 'required|integer|min:1|max:65535',
            'company_name' => 'nullable|string',
            'timeout' => 'nullable|integer|min:5|max:120',
        ]);

        $client = new TallyHttpClient(
            $validated['host'],
            $validated['port'],
            $validated['company_name'] ?? '',
            $validated['timeout'] ?? 30,
        );

        $connected = $client->isConnected();
        $data = ['connected' => $connected, 'url' => $client->getUrl()];

        if ($connected) {
            $data['companies'] = $client->getCompanies();
        }

        return response()->json([
            'success' => $connected,
            'data' => $data,
            'message' => $connected ? 'Connection test successful' : 'Connection test failed',
        ], $connected ? 200 : 503);
    }
}
