<?php

namespace Modules\Tally\Services;

use Illuminate\Support\Facades\Log;

class TallyRequestLogger
{
    public function log(
        string $requestXml,
        string $responseXml,
        float $durationMs,
        ?string $connectionCode = null,
    ): void {
        if (! config('tally.logging.enabled', true)) {
            return;
        }

        $channel = config('tally.logging.channel', 'tally');
        $maxBody = config('tally.logging.max_body_size', 10240);

        Log::channel($channel)->info('Tally API Request', [
            'connection' => $connectionCode,
            'duration_ms' => round($durationMs, 2),
            'request_size' => strlen($requestXml),
            'response_size' => strlen($responseXml),
            'request' => mb_substr($requestXml, 0, $maxBody),
            'response' => mb_substr($responseXml, 0, $maxBody),
        ]);
    }

    public function logError(
        string $requestXml,
        string $error,
        float $durationMs,
        ?string $connectionCode = null,
    ): void {
        if (! config('tally.logging.enabled', true)) {
            return;
        }

        $channel = config('tally.logging.channel', 'tally');
        $maxBody = config('tally.logging.max_body_size', 10240);

        Log::channel($channel)->error('Tally API Error', [
            'connection' => $connectionCode,
            'duration_ms' => round($durationMs, 2),
            'error' => $error,
            'request' => mb_substr($requestXml, 0, $maxBody),
        ]);
    }
}
