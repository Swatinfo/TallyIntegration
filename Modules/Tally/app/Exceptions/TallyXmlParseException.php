<?php

namespace Modules\Tally\Exceptions;

use RuntimeException;

class TallyXmlParseException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $rawXml = '',
        public readonly array $xmlErrors = [],
    ) {
        parent::__construct($message);
    }
}
