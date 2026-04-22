<?php

namespace Modules\Tally\Services\Demo;

/**
 * Single source of truth for every demo identifier.
 *
 * Every name created by the demo command MUST carry a recognizable prefix so
 * DemoReset can safely delete only demo-generated data.
 */
final class DemoConstants
{
    public const COMPANY = 'SwatTech Demo';

    public const CONNECTION_CODE = 'DEMO';

    public const CONNECTION_NAME = 'SwatTech Demo Connection';

    public const USER_EMAIL = 'demo@tally.test';

    public const USER_NAME = 'SwatTech Demo User';

    public const USER_PASSWORD = 'password';

    public const TOKEN_FILE = 'tally-demo/token.txt';

    public const MASTER_PREFIX = 'Demo ';

    public const VOUCHER_NUMBER_PREFIX = 'DEMO/';

    public const VOUCHER_NARRATION_PREFIX = '[DEMO]';

    public const TRANSIENT_MASTER_PREFIX = 'Demo Test ';

    public const TRANSIENT_VOUCHER_NARRATION_PREFIX = '[DEMO TEST]';

    public const DEMOTEST_CONNECTION_CODE = 'DEMOTEST';

    public const UNITS = [
        ['NAME' => 'Demo Nos', 'ISSIMPLEUNIT' => 'Yes', 'BASEUNITS' => ''],
        ['NAME' => 'Demo Kg', 'ISSIMPLEUNIT' => 'Yes', 'BASEUNITS' => ''],
    ];

    public const STOCK_GROUPS = [
        ['NAME' => 'Demo Widgets', 'PARENT' => ''],
        ['NAME' => 'Demo Raw Materials', 'PARENT' => ''],
    ];

    public const STOCK_ITEMS = [
        ['NAME' => 'Demo Widget A', 'PARENT' => 'Demo Widgets', 'BASEUNITS' => 'Demo Nos'],
        ['NAME' => 'Demo Widget B', 'PARENT' => 'Demo Widgets', 'BASEUNITS' => 'Demo Nos'],
        ['NAME' => 'Demo Raw Metal', 'PARENT' => 'Demo Raw Materials', 'BASEUNITS' => 'Demo Kg'],
        ['NAME' => 'Demo Raw Plastic', 'PARENT' => 'Demo Raw Materials', 'BASEUNITS' => 'Demo Kg'],
    ];

    public const COST_CENTERS = [
        ['NAME' => 'Demo Sales Dept', 'PARENT' => ''],
        ['NAME' => 'Demo Admin Dept', 'PARENT' => ''],
    ];

    public const GROUPS = [
        ['NAME' => 'Demo Customers', 'PARENT' => 'Sundry Debtors'],
        ['NAME' => 'Demo Vendors', 'PARENT' => 'Sundry Creditors'],
        ['NAME' => 'Demo Banks', 'PARENT' => 'Bank Accounts'],
        ['NAME' => 'Demo Direct Costs', 'PARENT' => 'Direct Expenses'],
        ['NAME' => 'Demo Indirect Costs', 'PARENT' => 'Indirect Expenses'],
    ];

    public const LEDGERS = [
        ['NAME' => 'Demo Cash', 'PARENT' => 'Cash-in-Hand'],
        ['NAME' => 'Demo Bank SBI', 'PARENT' => 'Demo Banks'],
        ['NAME' => 'Demo Bank HDFC', 'PARENT' => 'Demo Banks'],
        ['NAME' => 'Demo Customer A', 'PARENT' => 'Demo Customers'],
        ['NAME' => 'Demo Customer B', 'PARENT' => 'Demo Customers'],
        ['NAME' => 'Demo Vendor A', 'PARENT' => 'Demo Vendors'],
        ['NAME' => 'Demo Vendor B', 'PARENT' => 'Demo Vendors'],
        ['NAME' => 'Demo Sales A/c', 'PARENT' => 'Sales Accounts'],
        ['NAME' => 'Demo Purchase A/c', 'PARENT' => 'Purchase Accounts'],
        ['NAME' => 'Demo Rent A/c', 'PARENT' => 'Demo Indirect Costs'],
        ['NAME' => 'Demo Electricity A/c', 'PARENT' => 'Demo Indirect Costs'],
        ['NAME' => 'Demo Salary A/c', 'PARENT' => 'Demo Indirect Costs'],
        ['NAME' => 'Demo Depreciation A/c', 'PARENT' => 'Demo Indirect Costs'],
        ['NAME' => 'Demo Round Off', 'PARENT' => 'Indirect Expenses'],
    ];

    /**
     * @return array<int, array{type:string, number:string, narration:string, data:array}>
     */
    public static function seedVouchers(string $date): array
    {
        $fy = self::financialYearTag($date);

        return [
            [
                'type' => 'Payment',
                'number' => "DEMO/{$fy}/0001",
                'narration' => '[DEMO] Office rent',
                'args' => ['paymentLedger' => 'Demo Bank SBI', 'partyLedger' => 'Demo Rent A/c', 'amount' => 25000.00],
            ],
            [
                'type' => 'Payment',
                'number' => "DEMO/{$fy}/0002",
                'narration' => '[DEMO] Electricity bill',
                'args' => ['paymentLedger' => 'Demo Cash', 'partyLedger' => 'Demo Electricity A/c', 'amount' => 3200.00],
            ],
            [
                'type' => 'Payment',
                'number' => "DEMO/{$fy}/0003",
                'narration' => '[DEMO] Staff salaries',
                'args' => ['paymentLedger' => 'Demo Bank HDFC', 'partyLedger' => 'Demo Salary A/c', 'amount' => 150000.00],
            ],
            [
                'type' => 'Receipt',
                'number' => "DEMO/{$fy}/0004",
                'narration' => '[DEMO] Customer A advance',
                'args' => ['receivingLedger' => 'Demo Bank SBI', 'partyLedger' => 'Demo Customer A', 'amount' => 50000.00],
            ],
            [
                'type' => 'Receipt',
                'number' => "DEMO/{$fy}/0005",
                'narration' => '[DEMO] Customer B settlement',
                'args' => ['receivingLedger' => 'Demo Cash', 'partyLedger' => 'Demo Customer B', 'amount' => 15000.00],
            ],
            [
                'type' => 'Contra',
                'number' => "DEMO/{$fy}/0006",
                'narration' => '[DEMO] Cash deposit to SBI',
                'args' => ['from' => 'Demo Cash', 'to' => 'Demo Bank SBI', 'amount' => 100000.00],
            ],
            [
                'type' => 'Contra',
                'number' => "DEMO/{$fy}/0007",
                'narration' => '[DEMO] HDFC to SBI transfer',
                'args' => ['from' => 'Demo Bank HDFC', 'to' => 'Demo Bank SBI', 'amount' => 25000.00],
            ],
            [
                'type' => 'Journal',
                'number' => "DEMO/{$fy}/0008",
                'narration' => '[DEMO] Monthly depreciation',
                'args' => [
                    'debits' => [['ledger' => 'Demo Depreciation A/c', 'amount' => 5000.00]],
                    'credits' => [['ledger' => 'Demo Round Off', 'amount' => 5000.00]],
                ],
            ],
            [
                'type' => 'Journal',
                'number' => "DEMO/{$fy}/0009",
                'narration' => '[DEMO] Round-off adjustment',
                'args' => [
                    'debits' => [['ledger' => 'Demo Round Off', 'amount' => 100.00]],
                    'credits' => [['ledger' => 'Demo Rent A/c', 'amount' => 100.00]],
                ],
            ],
            [
                'type' => 'Sales',
                'number' => "DEMO/{$fy}/0010",
                'narration' => '[DEMO] Sale of Widget A to Customer A',
                'args' => ['partyLedger' => 'Demo Customer A', 'salesLedger' => 'Demo Sales A/c', 'amount' => 12000.00],
            ],
            [
                'type' => 'Sales',
                'number' => "DEMO/{$fy}/0011",
                'narration' => '[DEMO] Sale of Widget B to Customer B',
                'args' => ['partyLedger' => 'Demo Customer B', 'salesLedger' => 'Demo Sales A/c', 'amount' => 30000.00],
            ],
            [
                'type' => 'Sales',
                'number' => "DEMO/{$fy}/0012",
                'narration' => '[DEMO] Cash sale',
                'args' => ['partyLedger' => 'Demo Cash', 'salesLedger' => 'Demo Sales A/c', 'amount' => 6000.00],
            ],
            [
                'type' => 'Purchase',
                'number' => "DEMO/{$fy}/0013",
                'narration' => '[DEMO] Purchase of Widget A from Vendor A',
                'args' => ['partyLedger' => 'Demo Vendor A', 'purchaseLedger' => 'Demo Purchase A/c', 'amount' => 40000.00],
            ],
            [
                'type' => 'Purchase',
                'number' => "DEMO/{$fy}/0014",
                'narration' => '[DEMO] Raw metal purchase from Vendor B',
                'args' => ['partyLedger' => 'Demo Vendor B', 'purchaseLedger' => 'Demo Purchase A/c', 'amount' => 25000.00],
            ],
            [
                'type' => 'Credit Note',
                'number' => "DEMO/{$fy}/0015",
                'narration' => '[DEMO] Sales return from Customer A',
                'args' => ['partyLedger' => 'Demo Customer A', 'counterLedger' => 'Demo Sales A/c', 'amount' => 2400.00],
            ],
            [
                'type' => 'Credit Note',
                'number' => "DEMO/{$fy}/0016",
                'narration' => '[DEMO] Price adjustment discount',
                'args' => ['partyLedger' => 'Demo Customer B', 'counterLedger' => 'Demo Sales A/c', 'amount' => 500.00],
            ],
            [
                'type' => 'Debit Note',
                'number' => "DEMO/{$fy}/0017",
                'narration' => '[DEMO] Purchase return to Vendor A',
                'args' => ['partyLedger' => 'Demo Vendor A', 'counterLedger' => 'Demo Purchase A/c', 'amount' => 800.00],
            ],
            [
                'type' => 'Debit Note',
                'number' => "DEMO/{$fy}/0018",
                'narration' => '[DEMO] Damaged goods return to Vendor B',
                'args' => ['partyLedger' => 'Demo Vendor B', 'counterLedger' => 'Demo Purchase A/c', 'amount' => 1500.00],
            ],
        ];
    }

    /**
     * Throws if a name does not carry one of the demo prefixes.
     */
    public static function assertDemoMasterName(string $name): void
    {
        if (! str_starts_with($name, self::MASTER_PREFIX)) {
            throw new DemoSafetyException(
                "Refusing to touch non-demo master name '{$name}'. Must start with '".self::MASTER_PREFIX."'.",
            );
        }
    }

    public static function assertDemoVoucherNumber(string $voucherNumber): void
    {
        if (! str_starts_with($voucherNumber, self::VOUCHER_NUMBER_PREFIX)) {
            throw new DemoSafetyException(
                "Refusing to touch non-demo voucher number '{$voucherNumber}'. Must start with '".self::VOUCHER_NUMBER_PREFIX."'.",
            );
        }
    }

    public static function financialYearTag(string $date): string
    {
        $ts = strtotime($date) ?: time();
        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);
        // Indian FY: Apr–Mar
        $fyStart = $month >= 4 ? $year : $year - 1;
        $fyEnd = ($fyStart + 1) % 100;

        return sprintf('%d-%02d', $fyStart, $fyEnd);
    }
}
