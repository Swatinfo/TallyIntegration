<?php

namespace App\Http\Middleware;

use App\Services\Tally\TallyConnectionManager;
use App\Services\Tally\TallyHttpClient;
use Closure;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class ResolveTallyConnection
{
    public function __construct(
        private TallyConnectionManager $manager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $code = $request->route('connection');

        if (! $code) {
            return $next($request);
        }

        try {
            $client = $this->manager->resolve($code);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 404);
        }

        // Rebind TallyHttpClient for this request so all injected services use the correct connection
        app()->instance(TallyHttpClient::class, $client);

        return $next($request);
    }
}
