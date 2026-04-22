<?php

namespace Modules\Tally\Services\Integration;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Tally\Models\TallyWebhookDelivery;
use Modules\Tally\Models\TallyWebhookEndpoint;

/**
 * Delivers webhook payloads with HMAC-SHA256 signing + exponential backoff.
 *
 * The dispatcher works in two modes:
 *   - `queue(...)` — records a delivery row with status=pending, caller then
 *     dispatches DeliverWebhookJob.
 *   - `deliver(...)` — called from the job, performs the actual HTTP POST and
 *     updates the delivery row.
 */
class WebhookDispatcher
{
    public function queue(TallyWebhookEndpoint $endpoint, string $event, array $payload): TallyWebhookDelivery
    {
        return TallyWebhookDelivery::create([
            'tally_webhook_endpoint_id' => $endpoint->id,
            'event' => $event,
            'payload' => $payload,
            'attempt_number' => 1,
            'status' => 'pending',
        ]);
    }

    public function deliver(TallyWebhookDelivery $delivery): bool
    {
        $endpoint = $delivery->endpoint;
        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update(['status' => 'failed', 'response_body' => 'Endpoint inactive or missing']);

            return false;
        }

        $body = json_encode([
            'event' => $delivery->event,
            'delivered_at' => now()->toIso8601String(),
            'attempt' => $delivery->attempt_number,
            'payload' => $delivery->payload,
        ]);

        $signature = hash_hmac('sha256', $body, $endpoint->secret);

        $headers = array_merge($endpoint->headers ?? [], [
            'Content-Type' => 'application/json',
            'X-Tally-Event' => $delivery->event,
            'X-Tally-Signature' => 'sha256='.$signature,
            'User-Agent' => 'TallyIntegration/9I',
        ]);

        try {
            $response = Http::withHeaders($headers)
                ->timeout(config('tally.integration.webhooks.timeout_seconds', 10))
                ->send('POST', $endpoint->url, ['body' => $body]);

            $delivery->update([
                'status' => $response->successful() ? 'delivered' : 'failed',
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'delivered_at' => $response->successful() ? now() : null,
                'next_retry_at' => $response->successful() ? null : $this->nextRetry($delivery->attempt_number),
            ]);

            if ($response->successful()) {
                $endpoint->update(['failure_count' => 0]);

                return true;
            }

            $endpoint->increment('failure_count');
            $endpoint->update(['last_failure_at' => now()]);

            return false;
        } catch (\Throwable $e) {
            Log::channel('tally')->warning('Webhook delivery failed', [
                'endpoint' => $endpoint->url,
                'event' => $delivery->event,
                'error' => $e->getMessage(),
            ]);
            $delivery->update([
                'status' => 'failed',
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
                'next_retry_at' => $this->nextRetry($delivery->attempt_number),
            ]);

            return false;
        }
    }

    private function nextRetry(int $attempt): ?CarbonImmutable
    {
        $backoff = config('tally.integration.webhooks.backoff_seconds', [60, 300, 900, 3600, 14400]);
        $max = config('tally.integration.webhooks.max_attempts', 5);
        if ($attempt >= $max) {
            return null;
        }
        $seconds = $backoff[$attempt - 1] ?? end($backoff);

        return now()->toImmutable()->addSeconds($seconds);
    }
}
