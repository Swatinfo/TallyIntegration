# 12 — Complete Month-End Accounting Workflow

End-to-end: setup company → record April transactions → generate reports → corrections. ABC Enterprises, April 2026.

---

## Step 1: Register Connection

```bash
curl -X POST http://localhost:8000/api/tally/connections -H "Content-Type: application/json" \
  -d '{ "name": "ABC Enterprises", "code": "ABC", "host": "192.168.1.10", "port": 9000, "company_name": "ABC Enterprises Pvt Ltd" }'

curl http://localhost:8000/api/tally/ABC/health
```

## Step 2: Create Chart of Accounts

```bash
# Groups
curl -X POST http://localhost:8000/api/tally/ABC/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Domestic Customers", "PARENT": "Sundry Debtors" }'
curl -X POST http://localhost:8000/api/tally/ABC/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Material Vendors", "PARENT": "Sundry Creditors" }'

# Bank & Cash
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "HDFC Bank", "PARENT": "Bank Accounts", "OPENINGBALANCE": "-1000000" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Petty Cash", "PARENT": "Cash-in-hand", "OPENINGBALANCE": "-50000" }'

# Customers & Vendors
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Acme Corp", "PARENT": "Domestic Customers" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Steel India", "PARENT": "Material Vendors" }'

# Income, Expense, Tax
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Product Sales", "PARENT": "Sales Accounts" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Raw Material Purchase", "PARENT": "Purchase Accounts" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Office Rent", "PARENT": "Indirect Expenses" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Staff Salaries", "PARENT": "Indirect Expenses" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Electricity", "PARENT": "Indirect Expenses" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "CGST @9%", "PARENT": "Duties & Taxes" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "SGST @9%", "PARENT": "Duties & Taxes" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "CGST Input @9%", "PARENT": "Duties & Taxes" }'
curl -X POST http://localhost:8000/api/tally/ABC/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "SGST Input @9%", "PARENT": "Duties & Taxes" }'
```

## Step 3: April Transactions

```bash
# Apr 1: Pay rent
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": { "DATE": "20260401", "NARRATION": "Rent - April", "VOUCHERNUMBER": "PAY-001", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "Office Rent", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "40000" },
    { "LEDGERNAME": "HDFC Bank", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-40000" }
  ]}}'

# Apr 3: Purchase materials (+ GST)
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Purchase", "data": { "DATE": "20260403", "PARTYLEDGERNAME": "Steel India", "VOUCHERNUMBER": "PI-001", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "Raw Material Purchase", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-200000" },
    { "LEDGERNAME": "CGST Input @9%", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-18000" },
    { "LEDGERNAME": "SGST Input @9%", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-18000" },
    { "LEDGERNAME": "Steel India", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "236000" }
  ]}}'

# Apr 8: Sell products (+ GST)
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Sales", "data": { "DATE": "20260408", "PARTYLEDGERNAME": "Acme Corp", "VOUCHERNUMBER": "SI-001", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-354000" },
    { "LEDGERNAME": "Product Sales", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "300000" },
    { "LEDGERNAME": "CGST @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "27000" },
    { "LEDGERNAME": "SGST @9%", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "27000" }
  ]}}'

# Apr 15: Receive from Acme Corp
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Receipt", "data": { "DATE": "20260415", "VOUCHERNUMBER": "REC-001", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "HDFC Bank", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-200000" },
    { "LEDGERNAME": "Acme Corp", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "200000" }
  ]}}'

# Apr 18: Pay Steel India
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": { "DATE": "20260418", "VOUCHERNUMBER": "PAY-002", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "Steel India", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "150000" },
    { "LEDGERNAME": "HDFC Bank", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-150000" }
  ]}}'

# Apr 28: Cash deposit
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Contra", "data": { "DATE": "20260428", "VOUCHERNUMBER": "CTR-001", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "HDFC Bank", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-30000" },
    { "LEDGERNAME": "Petty Cash", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "30000" }
  ]}}'

# Apr 30: Salaries
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": { "DATE": "20260430", "NARRATION": "Salaries - April", "VOUCHERNUMBER": "PAY-003", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "Staff Salaries", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "150000" },
    { "LEDGERNAME": "HDFC Bank", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-150000" }
  ]}}'

# Apr 30: Electricity accrual
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Journal", "data": { "DATE": "20260430", "NARRATION": "Electricity accrual", "VOUCHERNUMBER": "JV-001", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "Electricity", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-12000" },
    { "LEDGERNAME": "Steel India", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "12000" }
  ]}}'
```

## Step 4: Month-End Reports

```bash
curl "http://localhost:8000/api/tally/ABC/reports/trial-balance?date=20260430"
curl "http://localhost:8000/api/tally/ABC/reports/profit-and-loss?from=20260401&to=20260430"
curl "http://localhost:8000/api/tally/ABC/reports/balance-sheet?date=20260430"
curl "http://localhost:8000/api/tally/ABC/reports/outstandings?type=receivable"
curl "http://localhost:8000/api/tally/ABC/reports/outstandings?type=payable"
curl "http://localhost:8000/api/tally/ABC/reports/ledger?ledger=HDFC%20Bank&from=20260401&to=20260430"
curl "http://localhost:8000/api/tally/ABC/reports/day-book?date=20260430"
```

## Step 5: Corrections

```bash
# Cancel wrong voucher
curl -X DELETE http://localhost:8000/api/tally/ABC/vouchers/0 -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "date": "01-Apr-2026", "voucher_number": "PAY-001", "action": "cancel", "narration": "Wrong amount" }'

# Re-enter corrected
curl -X POST http://localhost:8000/api/tally/ABC/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": { "DATE": "20260401", "NARRATION": "Rent corrected", "VOUCHERNUMBER": "PAY-001R", "ALLLEDGERENTRIES.LIST": [
    { "LEDGERNAME": "Office Rent", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "35000" },
    { "LEDGERNAME": "HDFC Bank", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-35000" }
  ]}}'
```

## April Summary

| Date | Type | Description | Amount |
|------|------|-------------|--------|
| Apr 1 | Payment | Rent | 40,000 |
| Apr 3 | Purchase | Materials + GST | 2,36,000 |
| Apr 8 | Sales | Products + GST | 3,54,000 |
| Apr 15 | Receipt | From Acme Corp | 2,00,000 |
| Apr 18 | Payment | To Steel India | 1,50,000 |
| Apr 28 | Contra | Cash deposit | 30,000 |
| Apr 30 | Payment | Salaries | 1,50,000 |
| Apr 30 | Journal | Electricity accrual | 12,000 |
