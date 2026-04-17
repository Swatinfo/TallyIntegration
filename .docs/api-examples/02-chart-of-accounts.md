# 02 — Chart of Accounts (Ledgers & Groups)

ALL ledger and group examples in one place: customers, vendors, banks, income, expenses, taxes, employees, assets, loans — with GST, addresses, credit terms, and forex.

---

## Account Groups

### List / Get Groups

```bash
curl http://localhost:8000/api/tally/MUM/groups
curl http://localhost:8000/api/tally/MUM/groups/Sundry%20Debtors
```

### Create Sub-Groups

```bash
# Customer segments
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Domestic Customers", "PARENT": "Sundry Debtors" }'
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Export Customers", "PARENT": "Sundry Debtors" }'

# Vendor categories
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Material Vendors", "PARENT": "Sundry Creditors" }'
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Service Vendors", "PARENT": "Sundry Creditors" }'

# Expense sub-groups
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Marketing Expenses", "PARENT": "Indirect Expenses" }'

# Payroll groups
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Salary Payable", "PARENT": "Current Liabilities" }'
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Employee Advances", "PARENT": "Loans & Advances (Asset)" }'

# Asset groups
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Office Equipment", "PARENT": "Fixed Assets" }'
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Vehicles", "PARENT": "Fixed Assets" }'
curl -X POST http://localhost:8000/api/tally/MUM/groups -H "Content-Type: application/json" \
  -d '{ "NAME": "Accumulated Depreciation", "PARENT": "Fixed Assets" }'
```

### Built-in Primary Groups (do NOT create these)

| Group | Type | Use For |
|-------|------|---------|
| Capital Account | Liability | Owner's equity |
| Secured Loans | Liability | Bank loans |
| Unsecured Loans | Liability | Director/partner loans |
| Current Liabilities | Liability | Short-term payables |
| Sundry Creditors | Liability | Vendor accounts |
| Duties & Taxes | Liability | GST, TDS, PT |
| Fixed Assets | Asset | Machinery, vehicles, buildings |
| Investments | Asset | FDs, mutual funds |
| Current Assets | Asset | Short-term assets |
| Sundry Debtors | Asset | Customer accounts |
| Cash-in-hand | Asset | Cash accounts |
| Bank Accounts | Asset | Bank current/savings |
| Loans & Advances (Asset) | Asset | Loans given, staff advances |
| Sales Accounts | Income | Revenue |
| Direct Incomes | Income | Service/operating income |
| Indirect Incomes | Income | Interest, rental income |
| Purchase Accounts | Expense | COGS |
| Direct Expenses | Expense | Manufacturing costs |
| Indirect Expenses | Expense | Admin, rent, salary |

---

## Ledgers — Customers (Sundry Debtors)

### Basic Customer

```bash
curl -X POST http://localhost:8000/api/tally/MUM/ledgers \
  -H "Content-Type: application/json" \
  -d '{ "NAME": "Acme Corp", "PARENT": "Domestic Customers" }'
```

### Customer with GST Details

```bash
curl -X POST http://localhost:8000/api/tally/MUM/ledgers \
  -H "Content-Type: application/json" \
  -d '{
    "NAME": "Reliance Industries Ltd",
    "PARENT": "Sundry Debtors",
    "GSTREGISTRATIONTYPE": "Regular",
    "PARTYGSTIN": "27AAACR5055K1Z5",
    "STATENAME": "Maharashtra",
    "COUNTRYNAME": "India"
  }'
```

GST Registration Types: `Regular`, `Composition`, `Unregistered/Consumer`, `Unknown`

### Customer with Full Details (Address, Contact, Credit Terms)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/ledgers \
  -H "Content-Type: application/json" \
  -d '{
    "NAME": "ABC Corporation Ltd",
    "PARENT": "Domestic Customers",
    "ADDRESS.LIST": {
      "ADDRESS": ["123 Business Park", "Andheri East", "Mumbai - 400069", "Maharashtra"]
    },
    "LEDGERCONTACT": "Rajesh Kumar",
    "LEDGERPHONE": "+91-22-2345-6789",
    "EMAIL": "rajesh@abccorp.com",
    "INCOMETAXNUMBER": "AABCA1234F",
    "PARTYGSTIN": "27AABCA1234F1ZP",
    "GSTREGISTRATIONTYPE": "Regular",
    "STATENAME": "Maharashtra",
    "PINCODE": "400069",
    "CREDITPERIOD": "30 Days",
    "CREDITLIMIT": "500000",
    "OPENINGBALANCE": "-50000"
  }'
```

**Note**: Debtors opening balance = **negative** (debit balance = they owe you).

### Export Customer (Forex)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Global Trade Inc - USA", "PARENT": "Export Customers", "CURRENCYNAME": "USD" }'
```

---

## Ledgers — Vendors (Sundry Creditors)

```bash
# Regular vendor with GST
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{
    "NAME": "Tata Steel Ltd",
    "PARENT": "Material Vendors",
    "GSTREGISTRATIONTYPE": "Regular",
    "PARTYGSTIN": "23AAACT2727Q1ZS",
    "STATENAME": "Madhya Pradesh"
  }'

# Unregistered vendor (no GST / reverse charge applicable)
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{
    "NAME": "Local Hardware Shop",
    "PARENT": "Sundry Creditors",
    "GSTREGISTRATIONTYPE": "Unregistered/Consumer"
  }'

# Service vendor with payment terms
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "IT Consultants Pvt Ltd", "PARENT": "Service Vendors", "CREDITPERIOD": "45 Days" }'

# Import vendor (forex)
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "European Supplier GmbH", "PARENT": "Sundry Creditors", "CURRENCYNAME": "EUR" }'
```

**Note**: Creditors opening balance = **positive** (credit balance = you owe them).

---

## Ledgers — Bank & Cash

```bash
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "HDFC Bank - Current", "PARENT": "Bank Accounts", "OPENINGBALANCE": "-1000000" }'

curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "SBI Savings A/c", "PARENT": "Bank Accounts", "OPENINGBALANCE": "-500000" }'

curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "HDFC Bank - USD Account", "PARENT": "Bank Accounts", "CURRENCYNAME": "USD" }'

curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Petty Cash", "PARENT": "Cash-in-hand", "OPENINGBALANCE": "-50000" }'
```

**Note**: Bank/cash opening balance = **negative** (debit balance = asset).

---

## Ledgers — Income

```bash
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Product Sales", "PARENT": "Sales Accounts" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Export Sales", "PARENT": "Sales Accounts" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Service Revenue", "PARENT": "Direct Incomes" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Interest Income - FD", "PARENT": "Indirect Incomes" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Exchange Rate Gain", "PARENT": "Indirect Incomes" }'
```

---

## Ledgers — Expenses

```bash
# Purchase / COGS
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Raw Material Purchase", "PARENT": "Purchase Accounts" }'

# Direct expenses
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Sales Discount", "PARENT": "Direct Expenses" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Round Off", "PARENT": "Direct Expenses" }'

# Indirect / operating expenses
for ledger in "Office Rent" "Staff Salaries" "Electricity" "Internet & Phone" "Office Supplies" "Depreciation Expense" "Interest on Term Loan" "Exchange Rate Loss"; do
  curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
    -d "{\"NAME\": \"$ledger\", \"PARENT\": \"Indirect Expenses\"}"
done

# Payroll expense components
for ledger in "Basic Salary" "HRA" "Conveyance Allowance" "PF Employer Contribution" "ESI Employer Contribution"; do
  curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
    -d "{\"NAME\": \"$ledger\", \"PARENT\": \"Indirect Expenses\"}"
done
```

---

## Ledgers — Taxes (GST, TDS, PT)

```bash
# GST Output (charged to customers) — all rates
for rate in 5 12 18 28; do
  half=$((rate/2))
  curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
    -d "{\"NAME\": \"CGST Output @${half}%\", \"PARENT\": \"Duties & Taxes\"}"
  curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
    -d "{\"NAME\": \"SGST Output @${half}%\", \"PARENT\": \"Duties & Taxes\"}"
  curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
    -d "{\"NAME\": \"IGST Output @${rate}%\", \"PARENT\": \"Duties & Taxes\"}"
done

# GST Input (claimed from purchases) — all rates
for rate in 5 12 18 28; do
  half=$((rate/2))
  curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
    -d "{\"NAME\": \"CGST Input @${half}%\", \"PARENT\": \"Duties & Taxes\"}"
  curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
    -d "{\"NAME\": \"SGST Input @${half}%\", \"PARENT\": \"Duties & Taxes\"}"
done

# Reverse charge, TDS, PT
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "CGST on Reverse Charge", "PARENT": "Duties & Taxes" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "SGST on Reverse Charge", "PARENT": "Duties & Taxes" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "TDS Payable", "PARENT": "Duties & Taxes" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "TDS on Salary Payable", "PARENT": "Duties & Taxes" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Professional Tax Payable", "PARENT": "Duties & Taxes" }'
```

---

## Ledgers — Payroll (Employees)

```bash
# Per-employee salary payable
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Salary Payable - Rajesh Kumar", "PARENT": "Salary Payable" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Salary Payable - Priya Sharma", "PARENT": "Salary Payable" }'

# Employee advances
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Advance - Rajesh Kumar", "PARENT": "Employee Advances" }'

# Statutory payable
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "PF Payable", "PARENT": "Current Liabilities" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "ESI Payable", "PARENT": "Current Liabilities" }'
```

---

## Ledgers — Fixed Assets & Depreciation

```bash
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Computer Equipment", "PARENT": "Office Equipment", "OPENINGBALANCE": "-500000" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Delivery Van", "PARENT": "Vehicles", "OPENINGBALANCE": "-800000" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Dep - Computer Equipment", "PARENT": "Accumulated Depreciation" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Dep - Vehicles", "PARENT": "Accumulated Depreciation" }'
```

---

## Ledgers — Loans & Investments

```bash
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "HDFC Term Loan", "PARENT": "Secured Loans", "OPENINGBALANCE": "2000000" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Vehicle Loan - ICICI", "PARENT": "Secured Loans", "OPENINGBALANCE": "600000" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Director Loan - Mr. Patel", "PARENT": "Unsecured Loans", "OPENINGBALANCE": "500000" }'
curl -X POST http://localhost:8000/api/tally/MUM/ledgers -H "Content-Type: application/json" \
  -d '{ "NAME": "Fixed Deposit - SBI", "PARENT": "Investments", "OPENINGBALANCE": "-1000000" }'
```

---

## Ledger CRUD Operations

```bash
# List all
curl http://localhost:8000/api/tally/MUM/ledgers

# Get single (URL-encode spaces)
curl http://localhost:8000/api/tally/MUM/ledgers/HDFC%20Bank%20-%20Current

# Update
curl -X PUT http://localhost:8000/api/tally/MUM/ledgers/Acme%20Corp \
  -H "Content-Type: application/json" \
  -d '{ "PARENT": "Export Customers" }'

# Delete (must have no transactions)
curl -X DELETE http://localhost:8000/api/tally/MUM/ledgers/Old%20Customer
```

## PHP

```php
use Modules\Tally\Services\Masters\LedgerService;
use Modules\Tally\Services\Masters\GroupService;

$groups = app(GroupService::class);
$ledgers = app(LedgerService::class);

$groups->create(['NAME' => 'Online Customers', 'PARENT' => 'Sundry Debtors']);
$ledgers->create(['NAME' => 'Flipkart', 'PARENT' => 'Online Customers', 'GSTREGISTRATIONTYPE' => 'Regular', 'PARTYGSTIN' => '29AABCF1234P1ZP']);
$ledger = $ledgers->get('Flipkart');
$all = $ledgers->list();
```
