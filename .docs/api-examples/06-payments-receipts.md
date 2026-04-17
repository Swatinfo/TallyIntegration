# 06 — Payments & Receipts

ALL payment and receipt scenarios: bank/cash, salary, TDS, forex, bill-wise allocation, advances, partial payments.

---

## Payments (Money Going Out)

**Convention**: Party/expense = `ISDEEMEDPOSITIVE=Yes, AMOUNT=positive`, Bank/cash = `ISDEEMEDPOSITIVE=No, AMOUNT=negative`

### Pay Vendor by Bank

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260420", "NARRATION": "Payment to vendor", "VOUCHERNUMBER": "PAY-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "59000" },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-59000" }
    ]
  }}'
```

### Pay Cash Expense

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260401", "NARRATION": "Office rent", "VOUCHERNUMBER": "PAY-002",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Office Rent", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "40000" },
      { "LEDGERNAME": "Petty Cash", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-40000" }
    ]
  }}'
```

### Pay with TDS Deduction

Rs 1,00,000 to vendor, deduct 10% TDS. Net = Rs 90,000:

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260420", "NARRATION": "Payment with TDS @10%", "VOUCHERNUMBER": "PAY-003",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "IT Consultants Pvt Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "100000" },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-90000" },
      { "LEDGERNAME": "TDS Payable", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-10000" }
    ]
  }}'
```

### Pay with Bill-Wise Settlement

Pay against specific invoice:

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260425", "NARRATION": "Against BILL-042", "VOUCHERNUMBER": "PAY-004",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Tata Steel Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "200000",
        "BILLALLOCATIONS.LIST": [{ "NAME": "BILL-042", "BILLTYPE": "Agst Ref", "AMOUNT": "200000" }]
      },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-200000" }
    ]
  }}'
```

### Advance Payment (Before Invoice)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260401", "NARRATION": "Advance to new vendor", "VOUCHERNUMBER": "PAY-ADV-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "New Vendor Pvt Ltd", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "50000",
        "BILLALLOCATIONS.LIST": [{ "NAME": "ADV-001", "BILLTYPE": "Advance", "AMOUNT": "50000" }]
      },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-50000" }
    ]
  }}'
```

### Pay in Foreign Currency (with Exchange Difference)

```bash
# Pay €10,000 at Rs 92.50 (original invoice was at Rs 92) → exchange loss Rs 5,000
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260430", "NARRATION": "EUR payment with forex loss", "VOUCHERNUMBER": "PAY-FX-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "European Supplier GmbH", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "920000",
        "CURRENCYNAME": "EUR", "FOREIGNAMOUNT": "10000", "EXCHANGERATE": "92" },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-925000" },
      { "LEDGERNAME": "Exchange Rate Loss", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "5000" }
    ]
  }}'
```

---

## Receipts (Money Coming In)

**Convention**: Bank/cash = `ISDEEMEDPOSITIVE=Yes, AMOUNT=negative`, Customer = `ISDEEMEDPOSITIVE=No, AMOUNT=positive`

### Receive Full Payment

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Receipt", "data": {
    "DATE": "20260425", "NARRATION": "Payment from Acme Corp", "VOUCHERNUMBER": "REC-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-50000" },
      { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "50000" }
    ]
  }}'
```

### Receive Partial Payment

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Receipt", "data": {
    "DATE": "20260425", "NARRATION": "Partial payment from customer", "VOUCHERNUMBER": "REC-002",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "SBI Savings A/c", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-60000" },
      { "LEDGERNAME": "Reliance Industries Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "60000" }
    ]
  }}'
```

### Receipt with Bill-Wise Settlement (Settle Multiple Invoices)

```bash
# Acme pays 1,25,000: fully settles INV-001 (1L) + partial INV-002 (25K)
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Receipt", "data": {
    "DATE": "20260420", "NARRATION": "Multi-bill settlement", "VOUCHERNUMBER": "REC-003",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-125000" },
      { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "125000",
        "BILLALLOCATIONS.LIST": [
          { "NAME": "INV-001", "BILLTYPE": "Agst Ref", "AMOUNT": "100000" },
          { "NAME": "INV-002", "BILLTYPE": "Agst Ref", "AMOUNT": "25000" }
        ]
      }
    ]
  }}'
```

### Cash Receipt

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Receipt", "data": {
    "DATE": "20260416", "NARRATION": "Cash sale - walk-in", "VOUCHERNUMBER": "REC-004",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Petty Cash", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-5000" },
      { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "5000" }
    ]
  }}'
```

### Receipt in Foreign Currency (with Exchange Gain)

```bash
# Receive $5,000 at Rs 84.50 (invoice at Rs 84) → gain Rs 2,500
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Receipt", "data": {
    "DATE": "20260430", "NARRATION": "USD receipt with forex gain", "VOUCHERNUMBER": "REC-FX-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "HDFC Bank - USD Account", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-422500",
        "CURRENCYNAME": "USD", "FOREIGNAMOUNT": "-5000", "EXCHANGERATE": "84.50" },
      { "LEDGERNAME": "Global Trade Inc - USA", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "420000",
        "CURRENCYNAME": "USD", "FOREIGNAMOUNT": "5000", "EXCHANGERATE": "84" },
      { "LEDGERNAME": "Exchange Rate Gain", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "2500" }
    ]
  }}'
```

### Receive Bank Loan Disbursement

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Receipt", "data": {
    "DATE": "20260401", "NARRATION": "HDFC term loan disbursement", "VOUCHERNUMBER": "LOAN-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-2000000" },
      { "LEDGERNAME": "HDFC Term Loan", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "2000000" }
    ]
  }}'
```

---

## Bill Type Reference

| BILLTYPE | When to Use |
|----------|-------------|
| `New Ref` | Creating a new invoice/bill (in sales/purchase voucher) |
| `Agst Ref` | Settling against an existing bill (in payment/receipt) |
| `Advance` | Advance payment before any invoice |
| `On Account` | General payment not against any bill |

## PHP Convenience

```php
$v = app(VoucherService::class);

$v->createPayment('20260420', 'HDFC Bank - Current', 'Tata Steel Ltd', 59000, 'PAY-001', 'Vendor payment');
$v->createReceipt('20260425', 'HDFC Bank - Current', 'Acme Corp', 50000, 'REC-001', 'Customer payment');
```
