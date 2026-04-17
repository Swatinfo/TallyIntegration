# 05 — Complete Purchase Cycle

Purchase Order → Receipt Note → Purchase Invoice (with GST, RCM, inventory) → Debit Note. All purchase scenarios.

---

## Simple Purchase

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Purchase",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "IT Consultants Pvt Ltd",
      "NARRATION": "Office supplies", "VOUCHERNUMBER": "PI-001",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Office Supplies", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-5000" },
        { "LEDGERNAME": "IT Consultants Pvt Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "5000" }
      ]
    }
  }'
```

## Purchase with GST (Input Credit)

```bash
# Rs 50,000 + 9% CGST + 9% SGST = Rs 59,000
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Purchase",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Tata Steel Ltd",
      "NARRATION": "Raw materials + GST", "VOUCHERNUMBER": "PI-002",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-50000" },
        { "LEDGERNAME": "CGST Input @9%", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-4500" },
        { "LEDGERNAME": "SGST Input @9%", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-4500" },
        { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "59000" }
      ]
    }
  }'
```

## Reverse Charge Mechanism (RCM)

Purchase from unregistered dealer — you pay GST under reverse charge:

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Purchase",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Local Hardware Shop",
      "NARRATION": "Purchase under reverse charge", "VOUCHERNUMBER": "RCM-001",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-50000" },
        { "LEDGERNAME": "CGST on Reverse Charge", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-4500" },
        { "LEDGERNAME": "SGST on Reverse Charge", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-4500" },
        { "LEDGERNAME": "Local Hardware Shop", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "59000" }
      ]
    }
  }'
```

## Purchase with Inventory + Godown

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Purchase",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "Tata Steel Ltd",
      "NARRATION": "Steel rods to Factory Store", "VOUCHERNUMBER": "PI-003",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-13000" },
        { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "13000" }
      ],
      "ALLINVENTORYENTRIES.LIST": [{
        "STOCKITEMNAME": "Steel Rods - 12mm", "RATE": "65/Kgs", "ACTUALQTY": "200 Kgs", "AMOUNT": "13000",
        "BATCHALLOCATIONS.LIST": { "GODOWNNAME": "Factory Store", "BATCHNAME": "Primary Batch", "AMOUNT": "13000", "ACTUALQTY": "200 Kgs" }
      }]
    }
  }'
```

## Import Purchase in Foreign Currency

```bash
# €10,000 at Rs 92/EUR = Rs 9,20,000
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Purchase",
    "data": {
      "DATE": "20260416", "PARTYLEDGERNAME": "European Supplier GmbH",
      "NARRATION": "Import purchase - EUR", "VOUCHERNUMBER": "IMP-001",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-920000" },
        { "LEDGERNAME": "European Supplier GmbH", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "920000",
          "CURRENCYNAME": "EUR", "FOREIGNAMOUNT": "10000", "EXCHANGERATE": "92" }
      ]
    }
  }'
```

## Purchase with Bill-Wise Allocation

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Purchase",
    "data": {
      "DATE": "20260405", "PARTYLEDGERNAME": "Tata Steel Ltd", "VOUCHERNUMBER": "BILL-042",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-200000" },
        { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "200000",
          "BILLALLOCATIONS.LIST": [{ "NAME": "BILL-042", "BILLTYPE": "New Ref", "AMOUNT": "200000" }]
        }
      ]
    }
  }'
```

## Purchase Order → Receipt Note → Invoice Flow

```bash
# Step 1: Purchase Order
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Purchase", "data": {
    "DATE": "20260401", "VOUCHERTYPENAME": "Purchase Order", "PARTYLEDGERNAME": "Tata Steel Ltd",
    "VOUCHERNUMBER": "PO-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-325000" },
      { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "325000" }
    ],
    "ALLINVENTORYENTRIES.LIST": [
      { "STOCKITEMNAME": "Steel Rods - 12mm", "RATE": "65/Kgs", "ACTUALQTY": "5000 Kgs", "AMOUNT": "325000" }
    ]
  }}'

# Step 2: Receipt Note (goods received - partial)
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Purchase", "data": {
    "DATE": "20260410", "VOUCHERTYPENAME": "Receipt Note", "PARTYLEDGERNAME": "Tata Steel Ltd",
    "VOUCHERNUMBER": "RN-001", "ORDERREF": "PO-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-130000" },
      { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "130000" }
    ],
    "ALLINVENTORYENTRIES.LIST": [{
      "STOCKITEMNAME": "Steel Rods - 12mm", "RATE": "65/Kgs", "ACTUALQTY": "2000 Kgs", "AMOUNT": "130000",
      "BATCHALLOCATIONS.LIST": { "GODOWNNAME": "Factory Store", "BATCHNAME": "Primary Batch", "AMOUNT": "130000", "ACTUALQTY": "2000 Kgs" }
    }]
  }}'

# Step 3: Purchase Invoice (with GST)
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Purchase", "data": {
    "DATE": "20260412", "PARTYLEDGERNAME": "Tata Steel Ltd", "VOUCHERNUMBER": "PI-RN-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-130000" },
      { "LEDGERNAME": "CGST Input @9%", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-11700" },
      { "LEDGERNAME": "SGST Input @9%", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-11700" },
      { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "153400" }
    ]
  }}'
```

## Debit Note (Purchase Return)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers \
  -H "Content-Type: application/json" \
  -d '{
    "type": "Debit Note",
    "data": {
      "DATE": "20260420", "PARTYLEDGERNAME": "Tata Steel Ltd",
      "NARRATION": "Defective material return", "VOUCHERNUMBER": "DN-001",
      "ALLLEDGERENTRIES.LIST": [
        { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-8000" },
        { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "8000" }
      ]
    }
  }'
```

## PHP Convenience

```php
$v = app(VoucherService::class);
$v->createPurchase('20260416', 'Tata Steel Ltd', 'Raw Material Purchase', 50000, 'PI-001', 'Steel purchase');
```

## Amount Convention — Purchase

| Entry | ISDEEMEDPOSITIVE | AMOUNT | Accounting |
|-------|-----------------|--------|-----------|
| Purchase/expense ledger | Yes | **Negative** | Debit |
| Input tax ledgers | Yes | **Negative** | Debit |
| Vendor (creditor) | No | Positive | Credit |
