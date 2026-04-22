<?php

namespace Modules\Tally\Services\Demo;

use Modules\Tally\Services\TallyHttpClient;

/**
 * Decorated TallyHttpClient used during demo command execution.
 *
 * Every outbound XML goes through DemoGuard::assertSafe() first — if the
 * XML is missing the SwatTech Demo company tag or tries to delete a
 * non-prefixed entity, DemoSafetyException aborts the whole run before
 * any HTTP request is made.
 */
class DemoHttpClient extends TallyHttpClient
{
    public function sendXml(string $xml): string
    {
        DemoGuard::assertSafe($xml);

        return parent::sendXml($xml);
    }
}
