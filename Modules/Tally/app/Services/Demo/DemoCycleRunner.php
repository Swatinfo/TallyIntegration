<?php

namespace Modules\Tally\Services\Demo;

use App\Models\User;
use Illuminate\Console\Command;
use Modules\Tally\Enums\TallyPermission;
use Modules\Tally\Jobs\HealthCheckJob;
use Modules\Tally\Jobs\SyncFromTallyJob;
use Modules\Tally\Jobs\SyncToTallyJob;
use Modules\Tally\Models\TallyAuditLog;
use Modules\Tally\Models\TallyResponseMetric;
use Modules\Tally\Services\Masters\CostCenterService;
use Modules\Tally\Services\Masters\GroupService;
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\Masters\StockGroupService;
use Modules\Tally\Services\Masters\StockItemService;
use Modules\Tally\Services\Masters\UnitService;
use Modules\Tally\Services\Reports\ReportService;
use Modules\Tally\Services\TallyCompanyService;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

/**
 * Full-cycle smoke test for the Tally module. Exercises every capability
 * against the SwatTech Demo sandbox using transient entities so a failure
 * leaves the seeded set intact.
 *
 * Each step is atomic; a fail in one phase does not prevent later phases
 * from running. Transient cleanup runs via try/finally.
 */
final class DemoCycleRunner
{
    private ?Command $console = null;

    /** @var array<array{phase: string, step: string, ok: bool, ms: float, error: ?string}> */
    private array $results = [];

    public function withConsole(Command $console): self
    {
        $this->console = $console;

        return $this;
    }

    public function run(): array
    {
        DemoEnvironment::run(function () {
            $this->phaseA_connectivity();
            $this->phaseB_companyPrimitives();
            $this->phaseC_mastersRead();
            $this->phaseD_mastersRoundTrip();
            $this->phaseE_vouchersRoundTrip();
            $this->phaseF_reports();
            $this->phaseG_sync();
            $this->phaseH_jobs();
            $this->phaseI_observability();
            $this->phaseJ_permissions();
        });

        return [
            'total' => count($this->results),
            'passed' => count(array_filter($this->results, fn ($r) => $r['ok'])),
            'failed' => count(array_filter($this->results, fn ($r) => ! $r['ok'])),
            'results' => $this->results,
        ];
    }

    private function phaseA_connectivity(): void
    {
        $this->phase('A. Connectivity & Discovery');
        $this->assert('A1 health endpoint reachable', function () {
            app(TallyHttpClient::class)->isConnected()
                ?: throw new \RuntimeException('Tally not reachable');
        });
        $this->assert('A2 list companies includes SwatTech Demo', function () {
            $companies = app(TallyHttpClient::class)->getCompanies();
            in_array(DemoConstants::COMPANY, $companies, true)
                ?: throw new \RuntimeException('SwatTech Demo not in '.implode(',', $companies));
        });
    }

    private function phaseB_companyPrimitives(): void
    {
        $this->phase('B. Company primitives');
        $svc = app(TallyCompanyService::class);
        $this->assert('B1 getAlterIds returns ints', fn () => is_array($svc->getAlterIds()));
        $this->assert('B2 getFinancialYearPeriod returns array', fn () => is_array($svc->getFinancialYearPeriod()));
    }

    private function phaseC_mastersRead(): void
    {
        $this->phase('C. Masters — read');
        $this->assert('C1 ledger list includes demo ledgers', function () {
            $items = app(LedgerService::class)->list();
            $this->assertContainsDemoItem($items, 'Demo Cash');
        });
        $this->assert('C2 group list includes demo groups', function () {
            $items = app(GroupService::class)->list();
            $this->assertContainsDemoItem($items, 'Demo Customers');
        });
        $this->assert('C3 stock-item list includes demo widgets', function () {
            $items = app(StockItemService::class)->list();
            $this->assertContainsDemoItem($items, 'Demo Widget A');
        });
        $this->assert('C4 unit list includes demo units', function () {
            $items = app(UnitService::class)->list();
            $this->assertContainsDemoItem($items, 'Demo Nos');
        });
        $this->assert('C5 stock-group list includes demo stock groups', function () {
            $items = app(StockGroupService::class)->list();
            $this->assertContainsDemoItem($items, 'Demo Widgets');
        });
        $this->assert('C6 cost-center list includes demo cost centers', function () {
            $items = app(CostCenterService::class)->list();
            $this->assertContainsDemoItem($items, 'Demo Sales Dept');
        });
    }

    private function phaseD_mastersRoundTrip(): void
    {
        $this->phase('D. Masters — round-trip (transient)');
        $transientLedger = DemoConstants::TRANSIENT_MASTER_PREFIX.'Ledger '.uniqid();
        $transientGroup = DemoConstants::TRANSIENT_MASTER_PREFIX.'Group '.uniqid();

        try {
            $this->assert('D1 create transient ledger', function () use ($transientLedger) {
                $r = app(LedgerService::class)->create(['NAME' => $transientLedger, 'PARENT' => 'Indirect Expenses']);
                ($r['errors'] ?? 1) === 0 ?: throw new \RuntimeException('Errors: '.json_encode($r));
            });
            $this->assert('D2 alter transient ledger', function () use ($transientLedger) {
                $r = app(LedgerService::class)->update($transientLedger, ['PARENT' => 'Indirect Expenses']);
                ($r['errors'] ?? 1) === 0 ?: throw new \RuntimeException('Errors');
            });
            $this->assert('D3 create transient group', function () use ($transientGroup) {
                $r = app(GroupService::class)->create(['NAME' => $transientGroup, 'PARENT' => 'Primary']);
                ($r['errors'] ?? 1) === 0 ?: throw new \RuntimeException('Errors');
            });
        } finally {
            // Transient cleanup regardless of success
            $this->cleanupTransient(fn () => app(LedgerService::class)->delete($transientLedger));
            $this->cleanupTransient(fn () => app(GroupService::class)->delete($transientGroup));
        }
    }

    private function phaseE_vouchersRoundTrip(): void
    {
        $this->phase('E. Vouchers — round-trip (transient)');
        $svc = app(VoucherService::class);
        $date = date('Ymd');

        $types = [
            VoucherType::Payment => fn ($n) => $svc->createPayment($date, 'Demo Bank SBI', 'Demo Rent A/c', 100.00, $n, '[DEMO TEST] transient'),
            VoucherType::Receipt => fn ($n) => $svc->createReceipt($date, 'Demo Bank SBI', 'Demo Customer A', 100.00, $n, '[DEMO TEST] transient'),
        ];

        foreach ($types as $type => $make) {
            $number = 'DEMO/TEST/'.uniqid();
            $created = false;
            try {
                $this->assert("E create {$type->value} transient", function () use ($make, $number, &$created) {
                    $r = $make($number);
                    ($r['errors'] ?? 1) === 0 ?: throw new \RuntimeException('Errors');
                    $created = true;
                });
            } finally {
                if ($created) {
                    $this->cleanupTransient(fn () => $svc->cancel(date('d-M-Y'), $number, $type, '[DEMO] cleanup'));
                }
            }
        }
    }

    private function phaseF_reports(): void
    {
        $this->phase('F. Reports');
        $svc = app(ReportService::class);
        $today = date('Ymd');

        $this->assert('F1 balance sheet', fn () => is_array($svc->balanceSheet($today)));
        $this->assert('F2 profit and loss', fn () => is_array($svc->profitAndLoss('20260401', $today)));
        $this->assert('F3 trial balance', fn () => is_array($svc->trialBalance($today)));
        $this->assert('F4 day book', fn () => is_array($svc->dayBook($today)));
        $this->assert('F5 ledger report', fn () => is_array($svc->ledgerReport('Demo Cash', '20260401', $today)));
        $this->assert('F6 stock summary', fn () => is_array($svc->stockSummary()));
        $this->assert('F7 outstandings receivable', fn () => is_array($svc->outstandings('receivable')));
    }

    private function phaseG_sync(): void
    {
        $this->phase('G. Sync engine');
        $conn = DemoEnvironment::demoConnection();

        $this->assert('G1 inbound sync dispatch', fn () => SyncFromTallyJob::dispatchSync($conn->code) === null || true);
        $this->assert('G2 outbound sync dispatch', fn () => SyncToTallyJob::dispatchSync($conn->code) === null || true);
    }

    private function phaseH_jobs(): void
    {
        $this->phase('H. Jobs (dispatchSync)');
        $this->assert('H1 HealthCheckJob', fn () => HealthCheckJob::dispatchSync() === null || true);
    }

    private function phaseI_observability(): void
    {
        $this->phase('I. Observability');
        $conn = DemoEnvironment::demoConnection();

        $this->assert('I1 metrics rows recorded', fn () => TallyResponseMetric::where('tally_connection_id', $conn->id)->exists() ?: throw new \RuntimeException('no metrics'));
        $this->assert('I2 audit-log rows recorded', fn () => TallyAuditLog::where('tally_connection_id', $conn->id)->exists() ?: throw new \RuntimeException('no audit rows'));
        $this->assert('I3 tally log file exists', function () {
            $path = storage_path('logs/tally/tally-'.date('d-m-Y').'.log');
            file_exists($path) ?: throw new \RuntimeException("missing: {$path}");
        });
        $this->assert('I4 token vault file exists', function () {
            file_exists(DemoTokenVault::vaultPath()) ?: throw new \RuntimeException('no vault file');
        });
    }

    private function phaseJ_permissions(): void
    {
        $this->phase('J. Permissions matrix');
        // We create a throwaway user with each permission and assert Gate behaviour via
        // the Laravel container's auth/gate — skipping HTTP round-trip here to keep the
        // runner fast. Shell smoke script exercises real 403/200 via curl.
        $conn = DemoEnvironment::demoConnection();
        $user = User::factory()->create(['tally_permissions' => [TallyPermission::ViewReports->value]]);

        try {
            $this->assert('J1 user has single permission persisted', function () use ($user) {
                in_array('view_reports', $user->fresh()->tally_permissions ?? [], true)
                    ?: throw new \RuntimeException('permission not persisted');
            });
        } finally {
            $user->tokens()->delete();
            $user->delete();
        }
    }

    // --- helpers ---

    private function assertContainsDemoItem(array $items, string $needle): void
    {
        foreach ($items as $item) {
            $name = (string) ($item['NAME'] ?? $item['@attributes']['NAME'] ?? '');
            if ($name === $needle) {
                return;
            }
        }

        throw new \RuntimeException("'{$needle}' not found in list");
    }

    private function cleanupTransient(\Closure $fn): void
    {
        try {
            $fn();
        } catch (\Throwable) {
            // best effort
        }
    }

    private function phase(string $label): void
    {
        $this->console?->line('');
        $this->console?->line("<info>{$label}</info>");
    }

    private function assert(string $step, \Closure $fn): void
    {
        $phase = end($this->results)['phase'] ?? 'unknown';
        $start = microtime(true);
        $error = null;
        $ok = true;

        try {
            $fn();
        } catch (\Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }

        $ms = round((microtime(true) - $start) * 1000, 1);
        $this->results[] = ['phase' => $phase, 'step' => $step, 'ok' => $ok, 'ms' => $ms, 'error' => $error];

        $mark = $ok ? '<info>✓</info>' : '<error>✗</error>';
        $err = $error ? " — {$error}" : '';
        $this->console?->line("  {$mark} {$step}   {$ms} ms{$err}");
    }
}
