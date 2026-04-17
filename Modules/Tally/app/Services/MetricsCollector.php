<?php

namespace Modules\Tally\Services;

use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Models\TallyResponseMetric;

class MetricsCollector
{
    public function record(string $endpoint, int $responseTimeMs, string $status, ?string $connectionCode = null): void
    {
        try {
            $connectionId = $connectionCode
                ? TallyConnection::where('code', $connectionCode)->value('id')
                : null;

            TallyResponseMetric::create([
                'tally_connection_id' => $connectionId,
                'endpoint' => $endpoint,
                'response_time_ms' => $responseTimeMs,
                'status' => $status,
            ]);
        } catch (\Throwable) {
            // Don't let metrics failures break the main flow
        }
    }

    public function getStats(int $connectionId, string $period = '1h'): array
    {
        $since = match ($period) {
            '1h' => now()->subHour(),
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            default => now()->subHour(),
        };

        $metrics = TallyResponseMetric::where('tally_connection_id', $connectionId)
            ->where('created_at', '>=', $since)
            ->get();

        if ($metrics->isEmpty()) {
            return ['total' => 0, 'avg_ms' => 0, 'p95_ms' => 0, 'error_rate' => 0];
        }

        $times = $metrics->pluck('response_time_ms')->sort()->values();
        $errors = $metrics->where('status', '!=', 'success')->count();

        return [
            'total' => $metrics->count(),
            'avg_ms' => round($times->avg()),
            'p95_ms' => round($times->get((int) ($times->count() * 0.95)) ?? 0),
            'max_ms' => $times->max(),
            'error_rate' => round(($errors / $metrics->count()) * 100, 1),
            'period' => $period,
        ];
    }
}
