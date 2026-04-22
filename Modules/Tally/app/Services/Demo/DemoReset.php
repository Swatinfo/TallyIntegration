<?php

namespace Modules\Tally\Services\Demo;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\Masters\CostCenterService;
use Modules\Tally\Services\Masters\GroupService;
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\Masters\StockGroupService;
use Modules\Tally\Services\Masters\StockItemService;
use Modules\Tally\Services\Masters\UnitService;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

/**
 * Prefix-scoped teardown of the demo sandbox.
 *
 * Every delete target is asserted to carry the demo prefix. DemoGuard
 * provides a second, independent check at the XML layer so even a bug
 * in this class can't route a non-demo delete through to Tally.
 */
final class DemoReset
{
    private ?Command $console = null;

    private bool $dryRun = true;

    private array $planned = [];

    public function dryRun(bool $dryRun = true): self
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    public function withConsole(Command $console): self
    {
        $this->console = $console;

        return $this;
    }

    public function run(): array
    {
        $summary = [
            'vouchers_cancelled' => 0, 'ledgers_deleted' => 0, 'groups_deleted' => 0,
            'stock_items_deleted' => 0, 'stock_groups_deleted' => 0, 'cost_centers_deleted' => 0,
            'units_deleted' => 0, 'db_rows_deleted' => 0, 'dry_run' => $this->dryRun,
            'planned' => [],
        ];

        $connection = TallyConnection::where('code', DemoConstants::CONNECTION_CODE)->first();

        if ($connection) {
            DemoEnvironment::run(function () use (&$summary) {
                $summary['vouchers_cancelled'] = $this->cancelDemoVouchers(app(VoucherService::class));

                $summary['ledgers_deleted'] = $this->deleteMasters(app(LedgerService::class), 'ledger');
                $summary['groups_deleted'] = $this->deleteMasters(app(GroupService::class), 'group');
                $summary['stock_items_deleted'] = $this->deleteMasters(app(StockItemService::class), 'stock-item');
                $summary['stock_groups_deleted'] = $this->deleteMasters(app(StockGroupService::class), 'stock-group');
                $summary['cost_centers_deleted'] = $this->deleteMasters(app(CostCenterService::class), 'cost-center');
                $summary['units_deleted'] = $this->deleteMasters(app(UnitService::class), 'unit');
            });
        }

        $summary['db_rows_deleted'] = $this->deleteDbRows();
        $summary['planned'] = $this->planned;

        if (! $this->dryRun) {
            DemoTokenVault::clear();
        }

        return $summary;
    }

    private function cancelDemoVouchers(VoucherService $service): int
    {
        $cancelled = 0;

        foreach (VoucherType::cases() as $type) {
            try {
                $vouchers = $service->list($type);
            } catch (\Throwable) {
                continue;
            }

            foreach ($vouchers as $voucher) {
                $number = (string) ($voucher['VOUCHERNUMBER'] ?? '');
                if (! str_starts_with($number, DemoConstants::VOUCHER_NUMBER_PREFIX)) {
                    continue;
                }

                $date = $this->reformatDate((string) ($voucher['DATE'] ?? ''));

                $this->plan("cancel voucher {$type->value} #{$number}");

                if ($this->dryRun) {
                    $cancelled++;

                    continue;
                }

                $result = $service->cancel($date, $number, $type, '[DEMO] Reset cancellation');
                if (($result['errors'] ?? 1) === 0) {
                    $cancelled++;
                }
            }
        }

        return $cancelled;
    }

    private function deleteMasters(object $service, string $label): int
    {
        $deleted = 0;

        try {
            $items = $service->list();
        } catch (\Throwable) {
            return 0;
        }

        foreach ($items as $item) {
            $name = (string) ($item['NAME'] ?? $item['@attributes']['NAME'] ?? '');
            if ($name === '' || ! str_starts_with($name, DemoConstants::MASTER_PREFIX)) {
                continue;
            }

            $this->plan("delete {$label} '{$name}'");

            if ($this->dryRun) {
                $deleted++;

                continue;
            }

            $result = $service->delete($name);
            if (($result['errors'] ?? 1) === 0) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function deleteDbRows(): int
    {
        $deleted = 0;

        $connection = TallyConnection::where('code', DemoConstants::CONNECTION_CODE)->first();
        if ($connection) {
            $this->plan("delete tally_connections row id={$connection->id} (cascades mirror/sync/audit)");
            if (! $this->dryRun) {
                $connection->delete();
            }
            $deleted++;
        }

        $user = User::where('email', DemoConstants::USER_EMAIL)->first();
        if ($user) {
            $this->plan("delete user {$user->email}");
            if (! $this->dryRun) {
                $user->tokens()->delete();
                $user->delete();
            }
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Tally voucher list returns dates as YYYYMMDD; cancel XML needs DD-Mon-YYYY.
     */
    private function reformatDate(string $raw): string
    {
        if (strlen($raw) === 8 && ctype_digit($raw)) {
            return date('d-M-Y', strtotime(substr($raw, 0, 4).'-'.substr($raw, 4, 2).'-'.substr($raw, 6, 2)));
        }

        $ts = strtotime($raw);

        return $ts ? date('d-M-Y', $ts) : date('d-M-Y');
    }

    private function plan(string $msg): void
    {
        $this->planned[] = $msg;
        if ($this->dryRun) {
            $this->console?->line("  [DRY-RUN] {$msg}");
        }
    }
}
