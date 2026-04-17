<?php

namespace Modules\Tally\Exceptions;

use RuntimeException;

class TallyConnectionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $host = '',
        public readonly int $port = 0,
        public readonly ?string $connectionCode = null,
    ) {
        parent::__construct($message);
    }
}
