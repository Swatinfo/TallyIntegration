<?php

namespace Modules\Tally\Exceptions;

use RuntimeException;

class TallyImportException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly array $importResult = [],
        public readonly array $lineErrors = [],
    ) {
        parent::__construct($message);
    }
}
