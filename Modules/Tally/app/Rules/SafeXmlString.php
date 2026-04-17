<?php

namespace Modules\Tally\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SafeXmlString implements ValidationRule
{
    /**
     * Reject strings that could manipulate the Tally XML envelope.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $dangerous = [
            '<!DOCTYPE',
            '<!ENTITY',
            '<![CDATA[',
            '<?xml',
            '<ENVELOPE',
            '<HEADER',
            '<TALLYMESSAGE',
            '<TALLYREQUEST',
        ];

        $upper = strtoupper($value);

        foreach ($dangerous as $pattern) {
            if (str_contains($upper, strtoupper($pattern))) {
                $fail('The :attribute contains potentially dangerous XML content.');

                return;
            }
        }
    }
}
