<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tally\Models\TallyImportJob as ImportJobModel;
use Modules\Tally\Services\Integration\ImportService;
use Modules\Tally\Services\TallyConnectionManager;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $importJobId) {}

    public function handle(ImportService $service, TallyConnectionManager $manager): void
    {
        $job = ImportJobModel::find($this->importJobId);
        if (! $job || $job->status !== ImportJobModel::STATUS_QUEUED) {
            return;
        }

        $manager->fromConnection($job->connection);
        $service->run($job);
    }
}
