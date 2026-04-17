# 09 — Voucher Lifecycle (List, Alter, Cancel, Delete, Batch)

Operations on existing vouchers — applies to ALL voucher types.

---

## List Vouchers by Type & Date

```bash
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Sales"
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Sales&from_date=20260401&to_date=20260430"
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Payment&from_date=20260401&to_date=20260430"
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Receipt"
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Journal"
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Credit%20Note&from_date=20260401&to_date=20260430"
```

Valid types: `Sales`, `Purchase`, `Payment`, `Receipt`, `Journal`, `Contra`, `Credit Note`, `Debit Note`

## Get a Specific Voucher

```bash
curl http://localhost:8000/api/tally/MUM/vouchers/12345
# {masterID} is Tally's internal ID from the lastvchid field when creating
```

## Alter (Modify) an Existing Voucher

You must send the **complete** voucher data, not just changed fields:

```bash
curl -X PUT http://localhost:8000/api/tally/MUM/vouchers/12345 \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416",
      "PARTYLEDGERNAME": "Acme Corp",
      "NARRATION": "UPDATED: Consulting services April 2026",
      "VOUCHERNUMBER": "SI-001",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-55000" },
        { "LEDGERNAME": "Service Revenue", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "55000" }
      ]
    }
  }'
```

## Cancel a Voucher (Audit-Friendly)

Keeps the voucher in records but marks it cancelled. Use for compliance:

```bash
curl -X DELETE http://localhost:8000/api/tally/MUM/vouchers/12345 \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "date": "16-Apr-2026",
    "voucher_number": "SI-001",
    "action": "cancel",
    "narration": "Cancelled: incorrect billing"
  }'
```

**Response**: `{ "data": { "combined": 1, "errors": 0 } }` — `combined=1` confirms cancellation.

## Delete a Voucher (Permanent)

Permanently removes from Tally. Use only when necessary:

```bash
curl -X DELETE http://localhost:8000/api/tally/MUM/vouchers/12345 \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Payment",
    "date": "20-Apr-2026",
    "voucher_number": "PAY-001",
    "action": "delete"
  }'
```

**Response**: `{ "data": { "altered": 1, "errors": 0 } }` — `altered=1` confirms deletion.

## Batch Create (Multiple Vouchers at Once)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Payment",
    "data": [
      {
        "DATE": "20260430", "NARRATION": "Rent", "VOUCHERNUMBER": "PAY-010",
        "ALLLEDGERENTRIES.LIST": [
          { "LEDGERNAME": "Office Rent", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "30000" },
          { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-30000" }
        ]
      },
      {
        "DATE": "20260430", "NARRATION": "Electricity", "VOUCHERNUMBER": "PAY-011",
        "ALLLEDGERENTRIES.LIST": [
          { "LEDGERNAME": "Electricity", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "8500" },
          { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-8500" }
        ]
      },
      {
        "DATE": "20260430", "NARRATION": "Internet", "VOUCHERNUMBER": "PAY-012",
        "ALLLEDGERENTRIES.LIST": [
          { "LEDGERNAME": "Internet & Phone", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "2500" },
          { "LEDGERNAME": "Petty Cash", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-2500" }
        ]
      }
    ]
  }'
```

**Response**: `{ "data": { "created": 3, "errors": 0 } }`

## PHP

```php
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

$v = app(VoucherService::class);

// List
$sales = $v->list(VoucherType::Sales, '20260401', '20260430');

// Get
$voucher = $v->get('12345');

// Batch create
$v->createBatch(VoucherType::Payment, [
    ['DATE' => '20260430', 'NARRATION' => 'Rent', ...],
    ['DATE' => '20260430', 'NARRATION' => 'Power', ...],
]);

// Cancel
$v->cancel('16-Apr-2026', 'SI-001', VoucherType::Sales, 'Duplicate entry');

// Delete
$v->delete('20-Apr-2026', 'PAY-001', VoucherType::Payment);
```
