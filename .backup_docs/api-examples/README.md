# API Examples — Complete Accounting Guide

Real-world curl + PHP examples for every API operation. 12 files, logically grouped by business operation.

## Prerequisites

1. TallyPrime running in server mode
2. At least one connection registered (see 01)
3. Replace `MUM` with your connection code
4. Replace `http://localhost:8000` with your app URL

## Files

| # | File | What's Inside |
|---|------|---------------|
| 01 | [Setup & Connectivity](01-setup-connectivity.md) | Register connections, multi-location, CRUD, health checks |
| 02 | [Chart of Accounts](02-chart-of-accounts.md) | ALL ledgers (customers, vendors, banks, income, expenses, taxes, payroll, assets, loans) + ALL groups (sub-groups, built-in groups reference) — with GST, addresses, credit terms, forex |
| 03 | [Inventory Setup](03-inventory-setup.md) | Units, stock groups, stock items, godowns/warehouses, cost centers, currencies, batch tracking |
| 04 | [Sales Cycle](04-sales-cycle.md) | Sales Order → Delivery Note → Invoice (simple, GST, IGST, multi-rate, discount, inventory, e-way bill, e-invoice, export, forex) → Credit Note — with bill-wise allocation |
| 05 | [Purchase Cycle](05-purchase-cycle.md) | Purchase Order → Receipt Note → Bill (simple, GST, RCM, inventory, forex) → Debit Note — with bill-wise allocation |
| 06 | [Payments & Receipts](06-payments-receipts.md) | Payments (bank, cash, salary, TDS, forex, advance), Receipts (full, partial, multi-bill, forex, loan), bill-wise settlement |
| 07 | [Journal, Contra, Payroll & Loans](07-journal-contra-payroll.md) | Journal (depreciation, bad debt, accrual, cost allocation), Contra (cash/bank transfers), Payroll (salary, PF, ESI, TDS, PT), EMI, employee advances |
| 08 | [Inventory Operations](08-inventory-operations.md) | Stock journal, godown transfer, manufacturing/BOM, wastage, physical stock adjustment |
| 09 | [Voucher Lifecycle](09-voucher-lifecycle.md) | List/filter, get, alter, cancel, delete, batch create — applies to ALL voucher types |
| 10 | [Reports & Analytics](10-reports-analytics.md) | Balance Sheet, P&L, Trial Balance, ledger statements, outstandings, stock, day book, GST data, cash flow, multi-branch, ERP dashboard queries |
| 11 | [ERP Sync Patterns](11-erp-sync-patterns.md) | Master sync, transaction sync, idempotency, two-way sync, retry/error handling, reconciliation, scheduled jobs |
| 12 | [Complete Workflow](12-complete-workflow.md) | Full month end-to-end: setup → 8 transactions → reports → corrections |

## Quick API Reference

```
POST   /api/tally/connections                  Register connection
GET    /api/tally/{conn}/health                Health check
GET    /api/tally/{conn}/ledgers               List ledgers
POST   /api/tally/{conn}/ledgers               Create ledger
GET    /api/tally/{conn}/ledgers/{name}        Get ledger
PUT    /api/tally/{conn}/ledgers/{name}        Update ledger
DELETE /api/tally/{conn}/ledgers/{name}        Delete ledger
       (same for /groups, /stock-items)
GET    /api/tally/{conn}/vouchers?type=Sales   List vouchers
POST   /api/tally/{conn}/vouchers              Create voucher
PUT    /api/tally/{conn}/vouchers/{id}         Alter voucher
DELETE /api/tally/{conn}/vouchers/{id}         Cancel/delete voucher
GET    /api/tally/{conn}/reports/{type}        Fetch report
```

## Voucher Types & Amount Signs

| Type | Debit Entry | Credit Entry |
|------|-------------|-------------|
| **Sales** | Customer (Yes, negative) | Sales + Tax (No, positive) |
| **Purchase** | Purchase + Input Tax (Yes, negative) | Vendor (No, positive) |
| **Payment** | Party/Expense (Yes, positive) | Bank/Cash (No, negative) |
| **Receipt** | Bank/Cash (Yes, negative) | Customer (No, positive) |
| **Journal** | Expense (Yes, negative) | Liability (No, positive) |
| **Contra** | Receiving account (Yes, negative) | Sending account (No, positive) |
| **Credit Note** | Sales return (Yes, negative) | Customer (No, positive) |
| **Debit Note** | Vendor (Yes, negative) | Purchase return (No, positive) |
