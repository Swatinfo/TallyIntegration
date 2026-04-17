<?php

namespace Modules\Tally\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Tally\Services\TallyConnectionManager;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

class BulkVoucherImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $connectionCode,
        public readonly VoucherType $type,
        public readonly array $vouchers,
    ) {}

    public function handle(TallyConnectionManager $manager): void
    {
        $client = $manager->resolve($this->connectionCode);
        app()->instance(TallyHttpClient::class, $client);

        $service = app(VoucherService::class);
        $service->createBatch($this->type, $this->vouchers);
    }
}
