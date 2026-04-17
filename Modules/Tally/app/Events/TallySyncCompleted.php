<?php

namespace Modules\Tally\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TallySyncCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly string $connectionCode,
        public readonly string $syncType,
        public readonly int $recordCount,
    ) {}
}
