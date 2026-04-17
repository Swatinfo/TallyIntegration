<?php

namespace Modules\Tally\Exceptions;

use RuntimeException;

class TallyValidationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message);
    }
}
