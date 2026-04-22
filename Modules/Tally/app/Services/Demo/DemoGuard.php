<?php

namespace Modules\Tally\Services\Demo;

/**
 * XML-level safety assertions. Every outbound demo XML passes through
 * assertSafe() before TallyHttpClient::sendXml. Throws on any violation.
 */
final class DemoGuard
{
    /**
     * Hard-assert that the XML about to be sent is confined to the demo sandbox.
     *
     * Rules:
     *   1. If XML is an Import (create/alter/delete/cancel), it MUST contain
     *      <SVCURRENTCOMPANY>SwatTech Demo</SVCURRENTCOMPANY>.
     *   2. Any ACTION="Delete" or ACTION="Cancel" must target a name/number
     *      that carries the demo prefix.
     *
     * Read requests may target any company (no damage possible), but for
     * defense in depth we still require the demo company tag on reads too
     * when called via the demo command path.
     */
    public static function assertSafe(string $xml): void
    {
        self::assertCompanyTag($xml);
        self::assertDeletesAreDemoOnly($xml);
    }

    private static function assertCompanyTag(string $xml): void
    {
        $expected = '<SVCURRENTCOMPANY>'.DemoConstants::COMPANY.'</SVCURRENTCOMPANY>';

        if (! str_contains($xml, $expected)) {
            throw new DemoSafetyException(
                'Refusing to send XML without <SVCURRENTCOMPANY>'.DemoConstants::COMPANY.'</SVCURRENTCOMPANY>. '
                .'This would route the operation to whatever company Tally has active.',
            );
        }
    }

    private static function assertDeletesAreDemoOnly(string $xml): void
    {
        $actions = ['Delete', 'Cancel'];

        foreach ($actions as $action) {
            if (! str_contains($xml, "ACTION=\"{$action}\"")) {
                continue;
            }

            self::assertTargetIsDemo($xml, $action);
        }
    }

    private static function assertTargetIsDemo(string $xml, string $action): void
    {
        // Master deletes: <LEDGER NAME="Foo" ACTION="Delete">
        if (preg_match_all('/NAME="([^"]+)"\s+ACTION="'.$action.'"/', $xml, $m)) {
            foreach ($m[1] as $name) {
                if (! str_starts_with($name, DemoConstants::MASTER_PREFIX)) {
                    throw new DemoSafetyException(
                        "Refusing to {$action} non-demo master '{$name}'. ".
                        "Must start with '".DemoConstants::MASTER_PREFIX."'.",
                    );
                }
            }
        }

        // Voucher deletes/cancels: <VOUCHER DATE="..." TAGNAME="..." TAGVALUE="DEMO/..." ...>
        if (preg_match_all('/TAGVALUE="([^"]+)"[^>]*ACTION="'.$action.'"/', $xml, $m)) {
            foreach ($m[1] as $voucherNumber) {
                if (! str_starts_with($voucherNumber, DemoConstants::VOUCHER_NUMBER_PREFIX)) {
                    throw new DemoSafetyException(
                        "Refusing to {$action} non-demo voucher '{$voucherNumber}'. ".
                        "Must start with '".DemoConstants::VOUCHER_NUMBER_PREFIX."'.",
                    );
                }
            }
        }
    }
}
