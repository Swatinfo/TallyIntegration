<?php

namespace Modules\Tally\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tally\Models\TallyConnection;

class TallyConnectionHealthChanged
{
    use Dispatchable;

    public function __construct(
        public readonly TallyConnection $connection,
        public readonly bool $isHealthy,
    ) {}
}
