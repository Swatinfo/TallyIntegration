<?php

namespace Modules\Tally\Console;

use Illuminate\Console\Command;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\Demo\DemoConstants;
use Modules\Tally\Services\Demo\DemoCycleRunner;
use Modules\Tally\Services\Demo\DemoReset;
use Modules\Tally\Services\Demo\DemoSafetyException;
use Modules\Tally\Services\Demo\DemoSeeder;
use Modules\Tally\Services\Demo\DemoTokenVault;

class TallyDemoCommand extends Command
{
    protected $signature = 'tally:demo
        {action? : seed|reset|fresh|test|status|rotate-token}
        {--execute : Actually run a destructive operation (reset/fresh are dry-run by default)}
        {--rotate-token : Force a fresh Sanctum token instead of reusing the existing one}
        {--force : Required in production}
        {--json : Machine-readable output}';

    protected $description = 'SwatTech Demo sandbox: seed, reset, test, status. Idempotent. Safety-guarded.';

    public function handle(): int
    {
        $action = $this->argument('action') ?: $this->chooseInteractive();
        if ($action === null) {
            return self::SUCCESS;
        }

        try {
            return match ($action) {
                'seed' => $this->runSeed(),
                'reset' => $this->runReset(),
                'fresh' => $this->runFresh(),
                'test' => $this->runTest(),
                'status' => $this->runStatus(),
                'rotate-token' => $this->runRotateToken(),
                default => $this->unknownAction($action),
            };
        } catch (DemoSafetyException $e) {
            $this->error('Safety guard tripped: '.$e->getMessage());

            return 2;
        }
    }

    private function chooseInteractive(): ?string
    {
        $this->renderStatusHeader();

        $choice = $this->choice(
            'What would you like to do?',
            [
                '1' => 'Run full cycle test with existing data',
                '2' => 'Reset + re-seed + run full cycle test (DELETES all Demo-prefixed data)',
                '3' => 'Seed only (upsert missing pieces)',
                '4' => 'Reset only (teardown, dry-run first)',
                '5' => 'Show status',
                '6' => 'Rotate token',
                '0' => 'Exit',
            ],
            '1',
        );

        return match (true) {
            str_starts_with($choice, '1') => 'test',
            str_starts_with($choice, '2') => 'fresh',
            str_starts_with($choice, '3') => 'seed',
            str_starts_with($choice, '4') => 'reset',
            str_starts_with($choice, '5') => 'status',
            str_starts_with($choice, '6') => 'rotate-token',
            default => null,
        };
    }

    private function runSeed(): int
    {
        $this->info('Seeding SwatTech Demo sandbox...');
        $seeder = (new DemoSeeder)->withConsole($this);
        $summary = $seeder->run();

        $token = DemoTokenVault::resolve($this->option('rotate-token'));

        $this->printSeedSummary($summary, $token);

        return self::SUCCESS;
    }

    private function runReset(): int
    {
        if (! $this->confirmDestructive('reset')) {
            return 4;
        }

        $dryRun = ! $this->option('execute');
        $reset = (new DemoReset)->dryRun($dryRun)->withConsole($this);
        $summary = $reset->run();

        if ($dryRun) {
            $this->line('');
            $this->comment(sprintf('Dry-run complete: %d planned operations. Re-run with --execute to apply.', count($summary['planned'])));
        } else {
            $this->info('Reset complete.');
            $this->table(['Action', 'Count'], [
                ['Vouchers cancelled', $summary['vouchers_cancelled']],
                ['Ledgers deleted', $summary['ledgers_deleted']],
                ['Groups deleted', $summary['groups_deleted']],
                ['Stock items deleted', $summary['stock_items_deleted']],
                ['Stock groups deleted', $summary['stock_groups_deleted']],
                ['Cost centers deleted', $summary['cost_centers_deleted']],
                ['Units deleted', $summary['units_deleted']],
                ['DB rows deleted', $summary['db_rows_deleted']],
            ]);
        }

        return self::SUCCESS;
    }

    private function runFresh(): int
    {
        if (! $this->option('execute')) {
            $this->warn('Fresh = reset + seed + test. Running dry-run for reset first.');
            (new DemoReset)->dryRun(true)->withConsole($this)->run();
            $this->line('');
            $this->comment('Re-run `tally:demo fresh --execute` to apply.');

            return self::SUCCESS;
        }

        if (! $this->confirmDestructive('fresh (reset + seed + test)')) {
            return 4;
        }

        (new DemoReset)->dryRun(false)->withConsole($this)->run();

        return $this->runSeed() === self::SUCCESS
            ? $this->runTest()
            : self::FAILURE;
    }

    private function runTest(): int
    {
        $this->info('Running Tally module full cycle test...');
        $result = (new DemoCycleRunner)->withConsole($this)->run();

        $this->line('');
        $this->line(sprintf('%d passed, %d failed, %d total', $result['passed'], $result['failed'], $result['total']));

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
        }

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function runStatus(): int
    {
        $this->renderStatusHeader();

        return self::SUCCESS;
    }

    private function runRotateToken(): int
    {
        DemoTokenVault::ensureUser();
        $token = DemoTokenVault::resolve(rotate: true);
        $this->info('Fresh token minted.');
        $this->line('');
        $this->line('Token: '.$token['token']);

        return self::SUCCESS;
    }

    private function unknownAction(string $action): int
    {
        $this->error("Unknown action: {$action}");
        $this->line('Valid actions: seed, reset, fresh, test, status, rotate-token');

        return 1;
    }

    private function renderStatusHeader(): void
    {
        $this->info('SwatTech Tally Demo Sandbox');
        $this->line('===========================');

        $conn = TallyConnection::where('code', DemoConstants::CONNECTION_CODE)->first();
        $this->line('Demo company:    '.DemoConstants::COMPANY);
        $this->line('DEMO connection: '.($conn ? "exists (id={$conn->id}, host={$conn->host}:{$conn->port})" : '<comment>not seeded — run `tally:demo seed`</comment>'));
        $this->line('Token vault:     '.(file_exists(DemoTokenVault::vaultPath()) ? DemoTokenVault::vaultPath() : '<comment>not present</comment>'));
        $this->line('Log directory:   '.storage_path('logs/tally'));
        $this->line('');
    }

    private function confirmDestructive(string $label): bool
    {
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('Refusing to run destructive operation in production without --force.');

            return false;
        }

        if (! $this->option('execute')) {
            return true;
        }

        if ($this->option('force')) {
            return true;
        }

        $typed = $this->ask(
            sprintf('About to %s. Type "%s" to confirm', $label, DemoConstants::COMPANY),
        );

        if ($typed !== DemoConstants::COMPANY) {
            $this->warn('Confirmation string did not match. Aborting.');

            return false;
        }

        return true;
    }

    private function printSeedSummary(array $summary, array $token): void
    {
        $this->line('');
        $this->info('Seed summary:');
        $this->table(['Entity', 'Created'], [
            ['Connection row', $summary['connection'] ? 'created' : 'existed'],
            ['Demo user', $summary['user'] ? 'ok' : 'error'],
            ['Units', $summary['units']],
            ['Stock groups', $summary['stock_groups']],
            ['Stock items', $summary['stock_items']],
            ['Cost centers', $summary['cost_centers']],
            ['Groups', $summary['groups']],
            ['Ledgers', $summary['ledgers']],
            ['Vouchers', $summary['vouchers']],
        ]);

        $this->line('');
        $this->info($token['reused'] ? 'Token (reused from vault):' : 'Token (fresh):');
        $this->line($token['token']);
        $this->line('');
        $this->line('Try it:');
        $this->line('  curl -H "Authorization: Bearer '.$token['token'].'" \\');
        $this->line('       http://localhost:8000/api/tally/'.DemoConstants::CONNECTION_CODE.'/ledgers');
    }
}
