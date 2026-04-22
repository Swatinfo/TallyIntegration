<?php

namespace Modules\Tally\Services\Demo;

use Illuminate\Console\Command;
use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\Masters\CostCenterService;
use Modules\Tally\Services\Masters\GroupService;
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\Masters\StockGroupService;
use Modules\Tally\Services\Masters\StockItemService;
use Modules\Tally\Services\Masters\UnitService;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

/**
 * Idempotent seed of the SwatTech Demo sandbox.
 *
 * Create-if-missing, skip-if-present. All entities carry the demo prefix.
 * Safe to re-run.
 */
final class DemoSeeder
{
    private ?Command $console = null;

    public function withConsole(Command $console): self
    {
        $this->console = $console;

        return $this;
    }

    public function run(): array
    {
        $summary = ['connection' => false, 'user' => false, 'units' => 0, 'stock_groups' => 0,
            'stock_items' => 0, 'cost_centers' => 0, 'groups' => 0, 'ledgers' => 0, 'vouchers' => 0];

        $this->line('Verifying Tally side...');
        $this->assertDemoCompanyExistsInTally();

        $this->line('Seeding DEMO connection row...');
        $summary['connection'] = $this->upsertConnection();

        $this->line('Ensuring demo user...');
        DemoTokenVault::ensureUser();
        $summary['user'] = true;

        DemoEnvironment::run(function () use (&$summary) {
            $this->line('Seeding masters...');
            $summary['units'] = $this->seedMasters(app(UnitService::class), DemoConstants::UNITS);
            $summary['stock_groups'] = $this->seedMasters(app(StockGroupService::class), DemoConstants::STOCK_GROUPS);
            $summary['stock_items'] = $this->seedMasters(app(StockItemService::class), DemoConstants::STOCK_ITEMS);
            $summary['cost_centers'] = $this->seedMasters(app(CostCenterService::class), DemoConstants::COST_CENTERS);
            $summary['groups'] = $this->seedMasters(app(GroupService::class), DemoConstants::GROUPS);
            $summary['ledgers'] = $this->seedMasters(app(LedgerService::class), DemoConstants::LEDGERS);

            $this->line('Seeding vouchers...');
            $summary['vouchers'] = $this->seedVouchers(app(VoucherService::class));
        });

        return $summary;
    }

    private function assertDemoCompanyExistsInTally(): void
    {
        $connection = TallyConnection::where('code', DemoConstants::CONNECTION_CODE)->first();
        $client = $connection
            ? new TallyHttpClient($connection->host, $connection->port, DemoConstants::COMPANY, $connection->timeout)
            : TallyHttpClient::fromConfig();

        $companies = $client->getCompanies();

        if (! in_array(DemoConstants::COMPANY, $companies, true)) {
            throw new DemoSafetyException(
                "Company '".DemoConstants::COMPANY."' not found in Tally. "
                .'Open TallyPrime → File → Create Company → name it exactly "'.DemoConstants::COMPANY.'" → then re-run.',
            );
        }
    }

    private function upsertConnection(): bool
    {
        $existing = TallyConnection::where('code', DemoConstants::CONNECTION_CODE)->first();

        $attrs = [
            'name' => DemoConstants::CONNECTION_NAME,
            'host' => env('TALLY_HOST', 'localhost'),
            'port' => (int) env('TALLY_PORT', 9000),
            'company_name' => DemoConstants::COMPANY,
            'timeout' => (int) env('TALLY_TIMEOUT', 30),
            'is_active' => true,
        ];

        if ($existing) {
            $existing->update($attrs);

            return false;
        }

        TallyConnection::create(array_merge(['code' => DemoConstants::CONNECTION_CODE], $attrs));

        return true;
    }

    private function seedMasters(object $service, array $entities): int
    {
        $created = 0;

        foreach ($entities as $data) {
            DemoConstants::assertDemoMasterName($data['NAME']);
            $existing = $service->get($data['NAME']);
            if ($existing) {
                continue;
            }

            $result = $service->create($data);
            if (($result['errors'] ?? 1) === 0) {
                $created++;
            }
        }

        return $created;
    }

    private function seedVouchers(VoucherService $service): int
    {
        $created = 0;
        $date = date('Ymd');

        foreach (DemoConstants::seedVouchers(date('Y-m-d')) as $spec) {
            DemoConstants::assertDemoVoucherNumber($spec['number']);

            $type = $this->voucherTypeFromString($spec['type']);
            $result = $this->createVoucher($service, $type, $date, $spec);

            if (($result['errors'] ?? 1) === 0) {
                $created++;
            }
        }

        return $created;
    }

    private function voucherTypeFromString(string $type): VoucherType
    {
        return match ($type) {
            'Sales' => VoucherType::Sales,
            'Purchase' => VoucherType::Purchase,
            'Payment' => VoucherType::Payment,
            'Receipt' => VoucherType::Receipt,
            'Journal' => VoucherType::Journal,
            'Contra' => VoucherType::Contra,
            'Credit Note' => VoucherType::CreditNote,
            'Debit Note' => VoucherType::DebitNote,
        };
    }

    private function createVoucher(VoucherService $service, VoucherType $type, string $date, array $spec): array
    {
        $args = $spec['args'];
        $number = $spec['number'];
        $narration = $spec['narration'];

        return match ($type) {
            VoucherType::Payment => $service->createPayment($date, $args['paymentLedger'], $args['partyLedger'], $args['amount'], $number, $narration),
            VoucherType::Receipt => $service->createReceipt($date, $args['receivingLedger'], $args['partyLedger'], $args['amount'], $number, $narration),
            VoucherType::Sales => $service->createSales($date, $args['partyLedger'], $args['salesLedger'], $args['amount'], $number, $narration),
            VoucherType::Purchase => $service->createPurchase($date, $args['partyLedger'], $args['purchaseLedger'], $args['amount'], $number, $narration),
            VoucherType::Journal => $service->createJournal($date, $args['debits'], $args['credits'], $number, $narration),
            VoucherType::Contra => $service->create(VoucherType::Contra, [
                'DATE' => $date,
                'VOUCHERNUMBER' => $number,
                'NARRATION' => $narration,
                'ALLLEDGERENTRIES.LIST' => [
                    ['LEDGERNAME' => $args['from'], 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => $args['amount']],
                    ['LEDGERNAME' => $args['to'], 'ISDEEMEDPOSITIVE' => 'Yes', 'AMOUNT' => -$args['amount']],
                ],
            ]),
            VoucherType::CreditNote, VoucherType::DebitNote => $service->create($type, [
                'DATE' => $date,
                'VOUCHERNUMBER' => $number,
                'NARRATION' => $narration,
                'PARTYLEDGERNAME' => $args['partyLedger'],
                'ALLLEDGERENTRIES.LIST' => [
                    ['LEDGERNAME' => $args['partyLedger'], 'ISDEEMEDPOSITIVE' => $type === VoucherType::CreditNote ? 'No' : 'Yes', 'AMOUNT' => $type === VoucherType::CreditNote ? $args['amount'] : -$args['amount']],
                    ['LEDGERNAME' => $args['counterLedger'], 'ISDEEMEDPOSITIVE' => $type === VoucherType::CreditNote ? 'Yes' : 'No', 'AMOUNT' => $type === VoucherType::CreditNote ? -$args['amount'] : $args['amount']],
                ],
            ]),
        };
    }

    private function line(string $msg): void
    {
        $this->console?->line("  {$msg}");
    }
}
