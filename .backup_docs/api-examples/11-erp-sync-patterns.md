# 11 — ERP-to-Tally Sync Patterns

Strategies for integrating your ERP with Tally: incremental sync via AlterIDs, master sync, transaction sync, idempotency, two-way sync, error handling, reconciliation, and scheduled jobs.

---

## Incremental Sync via AlterIDs

The most efficient sync strategy. Tally tracks every change via AlterIDs — query them before pulling data.

```php
use Modules\Tally\Services\TallyCompanyService;
use Modules\Tally\Models\TallyConnection;

$company = app(TallyCompanyService::class);
$connection = TallyConnection::where('code', 'MUM')->first();

// Check if anything changed since last sync
$changes = $company->hasChangedSince(
    $connection->last_alter_master_id,
    $connection->last_alter_voucher_id,
);

if ($changes['masters_changed']) {
    // Masters changed — sync ledgers, groups, stock items
    $ledgers = app(LedgerService::class)->list();
    // ... process

    // Update stored IDs
    $connection->update([
        'last_alter_master_id' => $changes['current_master_id'],
        'last_alter_voucher_id' => $changes['current_voucher_id'],
        'last_synced_at' => now(),
    ]);
} else {
    // Nothing changed — skip sync entirely (zero API calls for data)
    logger()->info('No changes detected, skipping sync');
}
```

## Auto-Detect Financial Year

```php
$company = app(TallyCompanyService::class);
$period = $company->getFinancialYearPeriod();
// ['from' => '01-Apr-2025', 'to' => '31-Mar-2026']

// Use for date range queries
$sales = $vouchers->list(VoucherType::Sales, '20250401', '20260331');
```

## Batch Listing for Large Datasets

```php
// For companies with 100K+ transactions, batch by month
$sales = $vouchers->list(VoucherType::Sales, '20250401', '20260331', batchSize: 5000);
// → 12 monthly requests, results merged automatically
```

---

## Master Data Sync (ERP → Tally)

```php
use Modules\Tally\Services\Masters\LedgerService;

$ledgers = app(LedgerService::class);
$erpCustomers = DB::table('customers')->where('sync_to_tally', true)->get();

foreach ($erpCustomers as $customer) {
    $existing = $ledgers->get($customer->name);
    $data = [
        'NAME' => $customer->name,
        'PARENT' => 'Sundry Debtors',
        'GSTREGISTRATIONTYPE' => $customer->gst_type ?? 'Unknown',
        'PARTYGSTIN' => $customer->gstin ?? '',
        'STATENAME' => $customer->state ?? '',
    ];

    try {
        $result = $existing
            ? $ledgers->update($customer->name, $data)
            : $ledgers->create($data);

        if ($result['errors'] === 0) {
            DB::table('customers')->where('id', $customer->id)
                ->update(['tally_synced_at' => now()]);
        }
    } catch (\RuntimeException $e) {
        logger()->error("Tally sync failed: {$customer->name}: {$e->getMessage()}");
    }
}
```

## Transaction Sync (ERP → Tally)

```php
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

$vouchers = app(VoucherService::class);
$invoices = DB::table('invoices')->whereNull('tally_synced_at')->where('status', 'confirmed')->get();

foreach ($invoices as $inv) {
    $entries = [
        ['LEDGERNAME' => $inv->customer_name, 'ISDEEMEDPOSITIVE' => 'Yes', 'AMOUNT' => (string)(-$inv->total),
         'BILLALLOCATIONS.LIST' => [['NAME' => $inv->number, 'BILLTYPE' => 'New Ref', 'AMOUNT' => (string)(-$inv->total)]]],
        ['LEDGERNAME' => 'Product Sales', 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => (string)$inv->taxable],
    ];
    if ($inv->cgst > 0) {
        $entries[] = ['LEDGERNAME' => 'CGST Output @9%', 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => (string)$inv->cgst];
        $entries[] = ['LEDGERNAME' => 'SGST Output @9%', 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => (string)$inv->sgst];
    }

    try {
        $result = $vouchers->create(VoucherType::Sales, [
            'DATE' => date('Ymd', strtotime($inv->date)),
            'PARTYLEDGERNAME' => $inv->customer_name,
            'VOUCHERNUMBER' => $inv->number,
            'ALLLEDGERENTRIES.LIST' => $entries,
        ]);
        if ($result['errors'] === 0) {
            DB::table('invoices')->where('id', $inv->id)->update([
                'tally_synced_at' => now(), 'tally_vch_id' => $result['lastvchid'],
            ]);
        }
    } catch (\RuntimeException $e) {
        logger()->error("Invoice sync failed: {$inv->number}: {$e->getMessage()}");
    }
}
```

## Idempotent Sync (Prevent Duplicates)

```php
function syncInvoice(object $invoice): string
{
    if ($invoice->tally_synced_at) return 'skipped';

    // Check if already in Tally
    $existing = app(VoucherService::class)->list(
        VoucherType::Sales,
        date('Ymd', strtotime($invoice->date)),
        date('Ymd', strtotime($invoice->date))
    );
    foreach ($existing as $v) {
        if (($v['VOUCHERNUMBER'] ?? '') === $invoice->number) {
            DB::table('invoices')->where('id', $invoice->id)->update(['tally_synced_at' => now()]);
            return 'already_exists';
        }
    }

    // Create in Tally... (same as above)
    return 'created';
}
```

## Two-Way Sync (Tally → ERP)

```php
// Pull new ledgers from Tally that don't exist in ERP
$tallyLedgers = app(LedgerService::class)->list();

foreach ($tallyLedgers as $ledger) {
    $name = $ledger['@attributes']['NAME'] ?? '';
    $parent = $ledger['PARENT'] ?? '';
    if (!$name) continue;

    if (str_contains($parent, 'Debtor') && !DB::table('customers')->where('name', $name)->exists()) {
        DB::table('customers')->insert(['name' => $name, 'source' => 'tally', 'created_at' => now()]);
    }
}
```

## Error Handling & Retry

```php
function syncWithRetry(callable $op, int $maxAttempts = 3): array
{
    for ($i = 1; $i <= $maxAttempts; $i++) {
        try {
            $result = $op();
            if ($result['errors'] === 0) return ['success' => true, 'result' => $result];
            return ['success' => false, 'result' => $result]; // Data error — don't retry
        } catch (\RuntimeException $e) {
            if ($i === $maxAttempts) return ['success' => false, 'error' => $e->getMessage()];
            sleep(2 * $i); // Backoff
        }
    }
}
```

## Reconciliation Check

```php
function reconcile(string $from, string $to): array
{
    $erpTotal = DB::table('invoices')->whereBetween('date', [...])->sum('total');
    $tallySales = app(VoucherService::class)->list(VoucherType::Sales, $from, $to);
    $tallyTotal = array_sum(array_map(fn($v) => abs(floatval($v['AMOUNT'] ?? 0)), $tallySales));

    return [
        'erp_total' => $erpTotal, 'tally_total' => $tallyTotal,
        'difference' => abs($erpTotal - $tallyTotal),
        'matched' => abs($erpTotal - $tallyTotal) < 1,
    ];
}
```

## Scheduled Sync Job

```php
// app/Jobs/SyncInvoicesToTally.php
class SyncInvoicesToTally implements ShouldQueue
{
    public function handle(VoucherService $vouchers): void
    {
        DB::table('invoices')->whereNull('tally_synced_at')->where('status', 'confirmed')
            ->limit(50)->get()->each(fn($inv) => $this->syncOne($inv, $vouchers));
    }
}

// routes/console.php
Schedule::job(new SyncInvoicesToTally)->everyFiveMinutes();
```

## ERP Integration Checklist

| Data | ERP → Tally | Tally → ERP |
|------|-------------|-------------|
| Customers/Vendors | Sync as ledgers | Pull new ledgers |
| Products | Sync as stock items | Pull stock items |
| Sales Invoices | Push confirmed invoices | Pull Tally-created |
| Purchase Bills | Push approved bills | Pull direct entries |
| Payments/Receipts | Push payment records | Pull confirmations |
| Credit/Debit Notes | Push returns | Pull returns |
| Reports | — | Pull P&L, BS, Outstandings |
| Reconciliation | Compare totals monthly | Flag mismatches |
