<?php

namespace App\Http\Controllers\Api\Tally;

use App\Http\Controllers\Controller;
use App\Services\Tally\TallyHttpClient;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(TallyHttpClient $client): JsonResponse
    {
        $connected = $client->isConnected();
        $data = [
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
