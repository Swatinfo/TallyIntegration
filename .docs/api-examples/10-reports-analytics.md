# 10 — Reports & Analytics

ALL reports in one place: financial statements, ledger details, outstandings, stock, GST data, cash flow, multi-branch, and ERP dashboard queries.

---

## Financial Statements

### Balance Sheet

```bash
curl "http://localhost:8000/api/tally/MUM/reports/balance-sheet?date=20260331"  # As of date
curl "http://localhost:8000/api/tally/MUM/reports/balance-sheet"                # Current
```

### Profit & Loss

```bash
curl "http://localhost:8000/api/tally/MUM/reports/profit-and-loss?from=20250401&to=20260331"  # Full FY
curl "http://localhost:8000/api/tally/MUM/reports/profit-and-loss?from=20260401&to=20260430"  # Monthly
curl "http://localhost:8000/api/tally/MUM/reports/profit-and-loss?from=20260401&to=20260630"  # Quarterly
```

### Trial Balance

```bash
curl "http://localhost:8000/api/tally/MUM/reports/trial-balance?date=20260331"
curl "http://localhost:8000/api/tally/MUM/reports/trial-balance"
```

---

## Ledger Statements (Account-Wise Detail)

```bash
# Cash book
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=Cash&from=20260401&to=20260430"

# Bank statement
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=HDFC%20Bank%20-%20Current&from=20260401&to=20260430"

# Customer statement
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=Acme%20Corp&from=20260401&to=20260430"

# Vendor statement
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=Tata%20Steel%20Ltd&from=20260401&to=20260430"

# Expense ledger
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=Office%20Rent&from=20260401&to=20260430"

# Sales ledger
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=Product%20Sales&from=20260401&to=20260430"
```

---

## Outstandings (Receivables & Payables)

```bash
# Who owes you money (customers)
curl "http://localhost:8000/api/tally/MUM/reports/outstandings?type=receivable"

# Whom you owe money (vendors)
curl "http://localhost:8000/api/tally/MUM/reports/outstandings?type=payable"
```

---

## Stock Reports

```bash
curl "http://localhost:8000/api/tally/MUM/reports/stock-summary"
```

---

## Day Book (All Transactions for a Date)

```bash
curl "http://localhost:8000/api/tally/MUM/reports/day-book?date=20260416"
```

---

## Sales & Purchase Registers (for GST Filing)

```bash
# Sales register (GSTR-1)
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Sales&from_date=20260401&to_date=20260430"
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Credit%20Note&from_date=20260401&to_date=20260430"

# Purchase register (GSTR-3B)
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Purchase&from_date=20260401&to_date=20260430"
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Debit%20Note&from_date=20260401&to_date=20260430"
```

---

## GST Tax Summary

```bash
# Output tax (collected)
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=CGST%20Output%20@9%25&from=20260401&to=20260430"
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=SGST%20Output%20@9%25&from=20260401&to=20260430"
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=IGST%20Output%20@18%25&from=20260401&to=20260430"

# Input tax (claimable)
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=CGST%20Input%20@9%25&from=20260401&to=20260430"
curl "http://localhost:8000/api/tally/MUM/reports/ledger?ledger=SGST%20Input%20@9%25&from=20260401&to=20260430"
```

---

## Cash Flow Analysis

```bash
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Payment&from_date=20260401&to_date=20260430"   # Outflow
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Receipt&from_date=20260401&to_date=20260430"   # Inflow
curl "http://localhost:8000/api/tally/MUM/vouchers?type=Contra&from_date=20260401&to_date=20260430"    # Transfers
```

---

## Multi-Branch Comparison

```bash
curl "http://localhost:8000/api/tally/MUM/reports/profit-and-loss?from=20260401&to=20260430"
curl "http://localhost:8000/api/tally/DEL/reports/profit-and-loss?from=20260401&to=20260430"
curl "http://localhost:8000/api/tally/PUN/reports/profit-and-loss?from=20260401&to=20260430"
```

---

## ERP Dashboard Queries (Quick Reference)

| Question | API Call |
|----------|---------|
| Today's transactions | `GET /{conn}/reports/day-book?date=YYYYMMDD` |
| Month-to-date P&L | `GET /{conn}/reports/profit-and-loss?from=...&to=...` |
| Bank balance | `GET /{conn}/ledgers/HDFC%20Bank` |
| Total receivables | `GET /{conn}/reports/outstandings?type=receivable` |
| Total payables | `GET /{conn}/reports/outstandings?type=payable` |
| Stock position | `GET /{conn}/reports/stock-summary` |
| Balance sheet | `GET /{conn}/reports/balance-sheet` |
| Trial balance | `GET /{conn}/reports/trial-balance` |
| Monthly sales | `GET /{conn}/vouchers?type=Sales&from_date=...&to_date=...` |
| Monthly expenses | `GET /{conn}/reports/profit-and-loss?from=...&to=...` |

---

## PHP

```php
use Modules\Tally\Services\Reports\ReportService;

$r = app(ReportService::class);

$r->balanceSheet('20260331');
$r->profitAndLoss('20250401', '20260331');
$r->trialBalance();
$r->ledgerReport('HDFC Bank - Current', '20260401', '20260430');
$r->outstandings('receivable');
$r->outstandings('payable');
$r->stockSummary();
$r->dayBook('20260416');
```
