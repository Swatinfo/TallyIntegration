# 07 — Journal, Contra, Payroll & Loans

Non-cash adjustments (journal), cash/bank transfers (contra), salary processing, statutory deposits, EMI payments, and employee advances.

---

## Journal Entries

### Depreciation (Monthly)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Journal", "data": {
    "DATE": "20260430", "NARRATION": "Depreciation - April 2026", "VOUCHERNUMBER": "JV-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Depreciation Expense", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-5000" },
      { "LEDGERNAME": "Dep - Computer Equipment", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "5000" }
    ]
  }}'
```

### Bad Debt Write-Off

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Journal", "data": {
    "DATE": "20260430", "NARRATION": "Bad debt - Old Customer", "VOUCHERNUMBER": "JV-002",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Bad Debts Expense", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-15000" },
      { "LEDGERNAME": "Old Customer Ltd", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "15000" }
    ]
  }}'
```

### Expense Accrual (Month-End)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Journal", "data": {
    "DATE": "20260430", "NARRATION": "Electricity accrual - April", "VOUCHERNUMBER": "JV-003",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Electricity", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-12000" },
      { "LEDGERNAME": "Accrued Expenses", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "12000" }
    ]
  }}'
```

### Multi-Debit / Multi-Credit (Cost Allocation)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Journal", "data": {
    "DATE": "20260430", "NARRATION": "Marketing cost allocation", "VOUCHERNUMBER": "JV-004",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Marketing Expense - North", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-20000" },
      { "LEDGERNAME": "Marketing Expense - South", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-15000" },
      { "LEDGERNAME": "Marketing Expense - West", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-15000" },
      { "LEDGERNAME": "Marketing Budget Provision", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "50000" }
    ]
  }}'
```

### PHP — Journal

```php
$v = app(VoucherService::class);
$v->createJournal('20260430',
    [['ledger' => 'Depreciation Expense', 'amount' => 5000]],
    [['ledger' => 'Dep - Computer Equipment', 'amount' => 5000]],
    'JV-001', 'Depreciation - April'
);
```

---

## Contra Entries (Cash ↔ Bank)

### Deposit Cash to Bank

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Contra", "data": {
    "DATE": "20260416", "NARRATION": "Cash deposit to HDFC", "VOUCHERNUMBER": "CTR-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-100000" },
      { "LEDGERNAME": "Petty Cash", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "100000" }
    ]
  }}'
```

### Withdraw Cash

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Contra", "data": {
    "DATE": "20260416", "NARRATION": "Cash withdrawal", "VOUCHERNUMBER": "CTR-002",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Petty Cash", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-50000" },
      { "LEDGERNAME": "SBI Savings A/c", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "50000" }
    ]
  }}'
```

### Bank-to-Bank Transfer

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Contra", "data": {
    "DATE": "20260416", "NARRATION": "Fund transfer SBI → HDFC", "VOUCHERNUMBER": "CTR-003",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-200000" },
      { "LEDGERNAME": "SBI Savings A/c", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "200000" }
    ]
  }}'
```

---

## Payroll Processing

### Monthly Salary Entry (per employee)

Rajesh Kumar: Basic 40K + HRA 16K + Conv 3K = Gross 59K. Deductions: PF 4.8K + ESI 443 + PT 200 + TDS 2.5K.

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Journal", "data": {
    "DATE": "20260430", "NARRATION": "Salary - Rajesh Kumar - Apr 2026", "VOUCHERNUMBER": "SAL-RK",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Basic Salary", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-40000" },
      { "LEDGERNAME": "HRA", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-16000" },
      { "LEDGERNAME": "Conveyance Allowance", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-3000" },
      { "LEDGERNAME": "PF Employer Contribution", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-4800" },
      { "LEDGERNAME": "ESI Employer Contribution", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-443" },
      { "LEDGERNAME": "Salary Payable - Rajesh Kumar", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "51057" },
      { "LEDGERNAME": "PF Payable", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "9600" },
      { "LEDGERNAME": "ESI Payable", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "886" },
      { "LEDGERNAME": "Professional Tax Payable", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "200" },
      { "LEDGERNAME": "TDS on Salary Payable", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "2500" }
    ]
  }}'
```

### Salary Disbursement (Bank Transfer)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260501", "NARRATION": "Salary credit - Rajesh Kumar", "VOUCHERNUMBER": "SAL-PAY-RK",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Salary Payable - Rajesh Kumar", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "51057" },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-51057" }
    ]
  }}'
```

### Statutory Deposit (PF/ESI to Government)

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260515", "NARRATION": "PF deposit - April", "VOUCHERNUMBER": "PF-APR",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "PF Payable", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "9600" },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-9600" }
    ]
  }}'
```

### PHP — Batch Payroll

```php
$employees = [
    ['name' => 'Rajesh Kumar', 'basic' => 40000, 'hra' => 16000, 'conv' => 3000, 'pf' => 4800, 'esi' => 443, 'pt' => 200, 'tds' => 2500],
    ['name' => 'Priya Sharma', 'basic' => 35000, 'hra' => 14000, 'conv' => 3000, 'pf' => 4200, 'esi' => 390, 'pt' => 200, 'tds' => 1800],
];

foreach ($employees as $emp) {
    $gross = $emp['basic'] + $emp['hra'] + $emp['conv'];
    $deductions = $emp['pf'] + $emp['esi'] + $emp['pt'] + $emp['tds'];
    $net = $gross - $deductions;

    $v->create(VoucherType::Journal, [
        'DATE' => '20260430', 'NARRATION' => "Salary - {$emp['name']} - Apr",
        'ALLLEDGERENTRIES.LIST' => [
            ['LEDGERNAME' => 'Basic Salary', 'ISDEEMEDPOSITIVE' => 'Yes', 'AMOUNT' => (string)(-$emp['basic'])],
            ['LEDGERNAME' => 'HRA', 'ISDEEMEDPOSITIVE' => 'Yes', 'AMOUNT' => (string)(-$emp['hra'])],
            ['LEDGERNAME' => 'Conveyance Allowance', 'ISDEEMEDPOSITIVE' => 'Yes', 'AMOUNT' => (string)(-$emp['conv'])],
            ['LEDGERNAME' => 'PF Employer Contribution', 'ISDEEMEDPOSITIVE' => 'Yes', 'AMOUNT' => (string)(-$emp['pf'])],
            ['LEDGERNAME' => "Salary Payable - {$emp['name']}", 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => (string)$net],
            ['LEDGERNAME' => 'PF Payable', 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => (string)($emp['pf'] * 2)],
            ['LEDGERNAME' => 'ESI Payable', 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => (string)($emp['esi'] * 2)],
            ['LEDGERNAME' => 'Professional Tax Payable', 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => (string)$emp['pt']],
            ['LEDGERNAME' => 'TDS on Salary Payable', 'ISDEEMEDPOSITIVE' => 'No', 'AMOUNT' => (string)$emp['tds']],
        ],
    ]);
}
```

---

## Loans & EMI

### EMI Payment (Principal + Interest)

Rs 45,000 EMI = Rs 35,000 principal + Rs 10,000 interest:

```bash
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260501", "NARRATION": "EMI #1 - HDFC Term Loan", "VOUCHERNUMBER": "EMI-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "HDFC Term Loan", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "35000" },
      { "LEDGERNAME": "Interest on Term Loan", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "10000" },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-45000" }
    ]
  }}'
```

### Employee Advance & Recovery

```bash
# Give advance
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Payment", "data": {
    "DATE": "20260415", "NARRATION": "Salary advance - Rajesh", "VOUCHERNUMBER": "ADV-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Advance - Rajesh Kumar", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "10000" },
      { "LEDGERNAME": "HDFC Bank - Current", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "-10000" }
    ]
  }}'

# Recover from salary (journal)
curl -X POST http://localhost:8000/api/tally/MUM/vouchers -H "Content-Type: application/json" \
  -d '{ "type": "Journal", "data": {
    "DATE": "20260430", "NARRATION": "Advance recovery - Rajesh", "VOUCHERNUMBER": "ADV-REC-001",
    "ALLLEDGERENTRIES.LIST": [
      { "LEDGERNAME": "Salary Payable - Rajesh Kumar", "ISDEEMEDPOSITIVE": "Yes", "AMOUNT": "-10000" },
      { "LEDGERNAME": "Advance - Rajesh Kumar", "ISDEEMEDPOSITIVE": "No", "AMOUNT": "10000" }
    ]
  }}'
```
