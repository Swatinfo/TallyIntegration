<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tally\Models\TallyWebhookDelivery;
use Modules\Tally\Services\Integration\WebhookDispatcher;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $deliveryId) {}

    public function handle(WebhookDispatcher $dispatcher): void
    {
        $delivery = TallyWebhookDelivery::find($this->deliveryId);
        if (! $delivery || $delivery->status === 'delivered') {
            return;
        }

        $ok = $dispatcher->deliver($delivery);

        // If failed and we have a next_retry_at, schedule another attempt.
        if (! $ok && $delivery->fresh()->next_retry_at !== null) {
            $next = TallyWebhookDelivery::create([
                'tally_webhook_endpoint_id' => $delivery->tally_webhook_endpoint_id,
                'event' => $delivery->event,
                'payload' => $delivery->payload,
                'attempt_number' => $delivery->attempt_number + 1,
                'status' => 'pending',
            ]);
            self::dispatch($next->id)->delay($delivery->fresh()->next_retry_at);
        }
    }
}
