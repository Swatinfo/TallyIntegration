<?php

namespace Modules\Tally\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tally\Services\Demo\DemoSafetyException;
use Modules\Tally\Services\Demo\DemoSeeder;
use Modules\Tally\Services\Demo\DemoTokenVault;

class TallyDatabaseSeeder extends Seeder
{
    /**
     * Seeds the SwatTech Demo sandbox. Delegates to DemoSeeder which is idempotent.
     * Skips silently if the Tally demo company isn't set up (allows migrations to run
     * without a live Tally dependency).
     */
    public function run(): void
    {
        try {
            (new DemoSeeder)->run();
            DemoTokenVault::resolve();
        } catch (DemoSafetyException $e) {
            $this->command?->warn('Tally demo seed skipped: '.$e->getMessage());
        } catch (\Throwable $e) {
            $this->command?->warn('Tally demo seed failed: '.$e->getMessage());
        }
    }
}
