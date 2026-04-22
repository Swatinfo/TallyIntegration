<?php

namespace Modules\Tally\Listeners;

use Modules\Tally\Events\TallyConnectionHealthChanged;
use Modules\Tally\Events\TallyMasterCreated;
use Modules\Tally\Events\TallyMasterDeleted;
use Modules\Tally\Events\TallyMasterUpdated;
use Modules\Tally\Events\TallySyncCompleted;
use Modules\Tally\Events\TallyVoucherAltered;
use Modules\Tally\Events\TallyVoucherCancelled;
use Modules\Tally\Events\TallyVoucherCreated;
use Modules\Tally\Jobs\DeliverWebhookJob;
use Modules\Tally\Models\TallyWebhookEndpoint;
use Modules\Tally\Services\Integration\WebhookDispatcher;

/**
 * Listens to every Tally event and queues webhook deliveries to all active
 * endpoints that subscribe to that event. One listener, multi-event — each
 * event class is mapped to a canonical event name string.
 */
class DispatchWebhooksOnTallyEvent
{
    public function __construct(
        private WebhookDispatcher $dispatcher,
    ) {}

    public function handle(object $event): void
    {
        $eventName = $this->eventName($event);
        if (! $eventName) {
            return;
        }

        $payload = (array) $event;

        TallyWebhookEndpoint::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn ($ep) => $ep->subscribesTo($eventName))
            ->each(function (TallyWebhookEndpoint $endpoint) use ($eventName, $payload) {
                $delivery = $this->dispatcher->queue($endpoint, $eventName, $payload);
                DeliverWebhookJob::dispatch($delivery->id);
            });
    }

    private function eventName(object $event): ?string
    {
        return match (true) {
            $event instanceof TallyMasterCreated => 'master.created',
            $event instanceof TallyMasterUpdated => 'master.updated',
            $event instanceof TallyMasterDeleted => 'master.deleted',
            $event instanceof TallyVoucherCreated => 'voucher.created',
            $event instanceof TallyVoucherAltered => 'voucher.altered',
            $event instanceof TallyVoucherCancelled => 'voucher.cancelled',
            $event instanceof TallySyncCompleted => 'sync.completed',
            $event instanceof TallyConnectionHealthChanged => 'connection.health_changed',
            default => null,
        };
    }
}
