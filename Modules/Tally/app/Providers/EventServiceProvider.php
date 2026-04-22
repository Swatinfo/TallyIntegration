<?php

namespace Modules\Tally\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Tally\Events\TallyConnectionHealthChanged;
use Modules\Tally\Events\TallyMasterCreated;
use Modules\Tally\Events\TallyMasterDeleted;
use Modules\Tally\Events\TallyMasterUpdated;
use Modules\Tally\Events\TallySyncCompleted;
use Modules\Tally\Events\TallyVoucherAltered;
use Modules\Tally\Events\TallyVoucherCancelled;
use Modules\Tally\Events\TallyVoucherCreated;
use Modules\Tally\Listeners\DispatchWebhooksOnTallyEvent;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        // Phase 9I — webhook dispatcher hooks into every Tally event.
        TallyMasterCreated::class => [DispatchWebhooksOnTallyEvent::class],
        TallyMasterUpdated::class => [DispatchWebhooksOnTallyEvent::class],
        TallyMasterDeleted::class => [DispatchWebhooksOnTallyEvent::class],
        TallyVoucherCreated::class => [DispatchWebhooksOnTallyEvent::class],
        TallyVoucherAltered::class => [DispatchWebhooksOnTallyEvent::class],
        TallyVoucherCancelled::class => [DispatchWebhooksOnTallyEvent::class],
        TallySyncCompleted::class => [DispatchWebhooksOnTallyEvent::class],
        TallyConnectionHealthChanged::class => [DispatchWebhooksOnTallyEvent::class],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
