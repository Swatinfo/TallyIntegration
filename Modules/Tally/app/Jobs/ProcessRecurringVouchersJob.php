<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Tally\Models\TallyRecurringVoucher;
use Modules\Tally\Services\RecurringVoucherService;
use Modules\Tally\Services\TallyConnectionManager;

/**
 * Runs daily (via TallyServiceProvider schedule). For each active recurrence
 * whose next_run_at is today or earlier, fires one voucher and advances the cursor.
 */
class ProcessRecurringVouchersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?string $connectionCode = null,
    ) {}

    public function handle(RecurringVoucherService $service, TallyConnectionManager $manager): void
    {
        $query = TallyRecurringVoucher::query()
            ->where('is_active', true)
            ->where('next_run_at', '<=', now()->startOfDay());

        if ($this->connectionCode) {
            $query->whereHas('connection', fn ($q) => $q->where('code', $this->connectionCode));
        }

        $query->chunk(50, function ($batch) use ($service, $manager) {
            foreach ($batch as $recurring) {
                try {
                    // Rebind the HTTP client for this connection before delegating.
                    $manager->fromConnection($recurring->connection);
                    $service->fire($recurring);
                } catch (\Throwable $e) {
                    Log::channel('tally')->error('Recurring voucher fire failed', [
                        'id' => $recurring->id,
                        'name' => $recurring->name,
                        'error' => $e->getMessage(),
                    ]);
                    $recurring->update([
                        'last_run_at' => now(),
                        'last_run_result' => ['errors' => 1, 'exception' => $e->getMessage()],
                    ]);
                }
            }
        });
    }
}
