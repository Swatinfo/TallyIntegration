<?php

namespace Modules\Tally\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TallyMasterUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $objectType,
        public readonly ?string $objectName,
        public readonly array $result,
        public readonly ?string $connectionCode = null,
    ) {}
}
