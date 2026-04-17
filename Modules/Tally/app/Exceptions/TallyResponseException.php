<?php

namespace Modules\Tally\Exceptions;

use RuntimeException;

class TallyResponseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly string $rawBody = '',
    ) {
        parent::__construct($message);
    }
}
