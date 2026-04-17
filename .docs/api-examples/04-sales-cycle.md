# 04 — Complete Sales Cycle

Sales Order → Delivery Note → Sales Invoice (with GST, discount, e-way bill, e-invoice, inventory) → Credit Note. All sales scenarios in one place.

---

## Simple Sales Invoice (No Inventory, No Tax)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416",
      "PARTYLEDGERNAME": "Acme Corp",
      "NARRATION": "Consulting services",
      "VOUCHERNUMBER": "SI-001",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-50000" },
        { "LEDGERNAME": "Service Revenue", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "50000" }
      ]
    }
  }'
```

## Sales Invoice with GST (Intra-State: CGST + SGST)

```bash
# Rs 1,00,000 + 9% CGST + 9% SGST = Rs 1,18,000
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Reliance Industries Ltd",
      "NARRATION": "Invoice #INV-042", "VOUCHERNUMBER": "SI-002",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Reliance Industries Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-118000" },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "100000" },
        { "LEDGERNAME": "CGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "9000" },
        { "LEDGERNAME": "SGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "9000" }
      ]
    }
  }'
```

## Sales Invoice with IGST (Inter-State)

```bash
# Sale from Maharashtra to Karnataka — IGST @18%
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Bangalore Tech Ltd",
      "NARRATION": "Interstate sale", "VOUCHERNUMBER": "SI-003",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Bangalore Tech Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-236000" },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "200000" },
        { "LEDGERNAME": "IGST Output @18%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "36000" }
      ]
    }
  }'
```

## Multi-Rate GST Invoice (Items at Different Rates)

```bash
# 50,000 @18% + 75,000 @12% + 10,000 @5% = 1,57,500 total
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "ABC Corporation Ltd",
      "VOUCHERNUMBER": "SI-004",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "ABC Corporation Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-157500" },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "50000" },
        { "LEDGERNAME": "CGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "4500" },
        { "LEDGERNAME": "SGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "4500" },
        { "LEDGERNAME": "Service Revenue", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "75000" },
        { "LEDGERNAME": "CGST Output @6%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "4500" },
        { "LEDGERNAME": "SGST Output @6%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "4500" },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "10000" },
        { "LEDGERNAME": "CGST Output @2.5%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "250" },
        { "LEDGERNAME": "SGST Output @2.5%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "250" }
      ]
    }
  }'
```

## Sales with Discount and Round-Off

```bash
# Rs 1,00,000 - 10% discount = 90,000 taxable + 18% GST = Rs 1,06,200
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Acme Corp", "VOUCHERNUMBER": "SI-005",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-106200" },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "100000" },
        { "LEDGERNAME": "Sales Discount", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-10000" },
        { "LEDGERNAME": "CGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "8100" },
        { "LEDGERNAME": "SGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "8100" }
      ]
    }
  }'
```

## Sales with Inventory (Stock Items + Godown)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Acme Corp", "VOUCHERNUMBER": "SI-006",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-275000" },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "275000" }
      ],
      "ALLINVENTORYENTRIES.LIST": [
        { "STOCKITEMNAME": "iPhone 15 Pro", "RATE": "130000/Nos", "ACTUALQTY": "1 Nos", "AMOUNT": "130000" },
        { "STOCKITEMNAME": "Widget A", "RATE": "600/Nos", "ACTUALQTY": "200 Nos", "AMOUNT": "120000",
          "BATCHALLOCATIONS.LIST": { "GODOWNNAME": "Main Warehouse - Mumbai", "BATCHNAME": "Primary Batch", "AMOUNT": "120000", "ACTUALQTY": "200 Nos" }
        },
        { "STOCKITEMNAME": "Steel Rods - 12mm", "RATE": "50/Kgs", "ACTUALQTY": "500 Kgs", "AMOUNT": "25000" }
      ]
    }
  }'
```

## Sales with Bill-Wise Allocation (Invoice Tracking)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Acme Corp", "VOUCHERNUMBER": "INV-001",
      "ALLLEDGERENTRIES.LIST": [
        {
          "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-100000",
          "BILLALLOCATIONS.LIST": [{ "NAME": "INV-001", "BILLTYPE": "New Ref", "AMOUNT": "-100000" }]
        },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "100000" }
      ]
    }
  }'
```

## Sales with E-Way Bill

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Bangalore Tech Ltd", "VOUCHERNUMBER": "EWB-001",
      "EWAYBILLDETAILS.LIST": {
        "BILLNUMBER": "381001234567",
        "TRANSPORTERID": "12ABCDE1234F1Z5",
        "VEHICLENUMBER": "MH01AB1234",
        "TRANSPORTMODE": "Road",
        "DISTANCE": "1000"
      },
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Bangalore Tech Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-236000" },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "200000" },
        { "LEDGERNAME": "IGST Output @18%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "36000" }
      ]
    }
  }'
```

## Sales with E-Invoice (IRN)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Reliance Industries Ltd", "VOUCHERNUMBER": "EINV-001",
      "IRNNUMBER": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6q7r8s9t0u1v2w3x4y5z6",
      "IRNACKDATE": "16-Apr-2026",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Reliance Industries Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-118000" },
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "100000" },
        { "LEDGERNAME": "CGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "9000" },
        { "LEDGERNAME": "SGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "9000" }
      ]
    }
  }'
```

## Export Sale (Zero-Rated GST)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Sales",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Global Trade Inc - USA",
      "NARRATION": "Export under LUT", "VOUCHERNUMBER": "EXP-001",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Global Trade Inc - USA", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-500000",
          "CURRENCYNAME": "USD", "FOREIGNAMOUNT": "-5952", "EXCHANGERATE": "84" },
        { "LEDGERNAME": "Export Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "500000" }
      ]
    }
  }'
```

## Sales Order → Delivery Note → Invoice Flow

```bash
# Step 1: Sales Order
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Sales", "data": {
    "DATE": "20260401", "VOUCHERTYPENAME": "Sales Order", "PARTYLEDGERNAME": "Acme Corp",
    "VOUCHERNUMBER": "SO-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-500000" },
      { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "500000" }
    ]
  }}'

# Step 2: Partial Delivery Note
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Sales", "data": {
    "DATE": "20260405", "VOUCHERTYPENAME": "Delivery Note", "PARTYLEDGERNAME": "Acme Corp",
    "VOUCHERNUMBER": "DN-001", "ORDERREF": "SO-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-200000" },
      { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "200000" }
    ]
  }}'

# Step 3: Invoice against delivery
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Sales", "data": {
    "DATE": "20260408", "PARTYLEDGERNAME": "Acme Corp",
    "VOUCHERNUMBER": "SI-DN-001", "DELIVERYNOTEREF": "DN-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-236000" },
      { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "200000" },
      { "LEDGERNAME": "CGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "18000" },
      { "LEDGERNAME": "SGST Output @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "18000" }
    ]
  }}'
```

## Credit Note (Sales Return)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Credit Note",
    "data": {
      "DATE": "20260420", "PARTYLEDGERNAME": "Acme Corp",
      "NARRATION": "Defective goods return", "VOUCHERNUMBER": "CN-001",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-10000" },
        { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "10000" }
      ]
    }
  }'
```

## PHP Convenience Methods

```php
use Modules\Tally\Services\Vouchers\VoucherService;

$v = app(VoucherService::class);

// Simple sale
$v->createSales('20260416', 'Acme Corp', 'Product Sales', 50000, 'SI-001', 'Invoice 001');

// Sale with inventory
$v->createSales('20260416', 'Acme Corp', 'Product Sales', 275000, 'SI-006', 'With stock', [
    ['STOCKITEMNAME' => 'iPhone 15 Pro', 'RATE' => '130000/Nos', 'ACTUALQTY' => '1 Nos', 'AMOUNT' => '130000'],
    ['STOCKITEMNAME' => 'Widget A', 'RATE' => '600/Nos', 'ACTUALQTY' => '200 Nos', 'AMOUNT' => '120000'],
]);
```

## Amount Convention — Sales

| Entry | ISDEEMEDPOSITIVE | AMOUNT | Accounting |
|-------|-----------------|--------|-----------|
| Customer (debtor) | Yes | **Negative** | Debit |
| Sales ledger | No | Positive | Credit |
| Tax ledgers | No | Positive | Credit |
| Discount | Yes | **Negative** | Contra-debit |
