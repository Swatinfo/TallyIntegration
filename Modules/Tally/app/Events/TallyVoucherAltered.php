<?php

namespace Modules\Tally\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Tally\Services\Vouchers\VoucherType;

class TallyVoucherAltered
{
    use Dispatchable;

    public function __construct(
        public readonly VoucherType $type,
        public readonly string $masterID,
        public readonly array $result,
        public readonly ?string $connectionCode = null,
    ) {}
}
