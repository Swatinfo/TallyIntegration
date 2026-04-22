<?php

namespace Modules\Tally\Services;

use Carbon\CarbonImmutable;
use Modules\Tally\Models\TallyRecurringVoucher;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

/**
 * Orchestrates scheduled voucher templates. Does not talk to Tally directly
 * — delegates to VoucherService for the actual create, then advances next_run_at.
 */
class RecurringVoucherService
{
    public function __construct(
        private VoucherService $vouchers,
    ) {}

    /**
     * Fire a single recurrence: creates the voucher in Tally using the template,
     * then advances next_run_at based on the frequency.
     *
     * @return array{created_voucher:array, next_run_at:?string}
     */
    public function fire(TallyRecurringVoucher $recurring): array
    {
        $runDate = CarbonImmutable::parse($recurring->next_run_at);

        // Inject the scheduled date into the template so the voucher is dated correctly.
        $template = $recurring->voucher_template ?? [];
        $template['DATE'] = $runDate->format('Ymd');

        $result = $this->vouchers->create(
            VoucherType::from($recurring->voucher_type),
            $template,
        );

        $recurring->update([
            'last_run_at' => now(),
            'last_run_result' => $result,
            'next_run_at' => $this->calculateNextRun($recurring, $runDate),
            // Deactivate if we've passed the end_date.
            'is_active' => $this->shouldRemainActive($recurring, $runDate),
        ]);

        return [
            'created_voucher' => $result,
            'next_run_at' => $recurring->next_run_at?->toDateString(),
        ];
    }

    /**
     * Compute next_run_at by stepping forward one frequency unit from the run date.
     */
    public function calculateNextRun(TallyRecurringVoucher $recurring, ?CarbonImmutable $from = null): CarbonImmutable
    {
        $from = $from ?? CarbonImmutable::parse($recurring->next_run_at ?? $recurring->start_date);

        return match ($recurring->frequency) {
            'daily' => $from->addDay(),
            'weekly' => $from->addWeek(),
            'monthly' => $this->nextMonthly($from, $recurring->day_of_month),
            'quarterly' => $this->nextMonthly($from->addMonths(2), $recurring->day_of_month)->addMonthNoOverflow(),
            'yearly' => $from->addYear(),
            default => $from->addMonth(),
        };
    }

    /**
     * Bootstrap next_run_at for a freshly-created row. Picks the first valid
     * firing date on/after start_date matching the frequency constraints.
     */
    public function bootstrapNextRun(TallyRecurringVoucher $recurring): CarbonImmutable
    {
        $start = CarbonImmutable::parse($recurring->start_date);

        return match ($recurring->frequency) {
            'daily' => $start,
            'weekly' => $recurring->day_of_week !== null
                ? $start->next((int) $recurring->day_of_week)->startOfDay()
                : $start,
            'monthly', 'quarterly', 'yearly' => $this->nextMonthly($start->subDay(), $recurring->day_of_month),
            default => $start,
        };
    }

    private function nextMonthly(CarbonImmutable $from, ?int $dayOfMonth): CarbonImmutable
    {
        $dom = $dayOfMonth ?? (int) $from->format('j');
        // Clamp to 28 so every month has the date (no Feb-30 gotchas).
        $dom = min(max($dom, 1), 28);

        $candidate = $from->startOfMonth()->setDay($dom);
        if ($candidate->lte($from)) {
            $candidate = $candidate->addMonthNoOverflow();
        }

        return $candidate;
    }

    private function shouldRemainActive(TallyRecurringVoucher $recurring, CarbonImmutable $justRan): bool
    {
        if (! $recurring->end_date) {
            return true;
        }

        return $recurring->next_run_at && CarbonImmutable::parse($recurring->next_run_at)->lte($recurring->end_date);
    }
}
