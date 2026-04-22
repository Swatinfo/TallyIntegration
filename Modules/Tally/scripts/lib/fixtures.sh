#!/usr/bin/env bash
# Modules/Tally/scripts/lib/fixtures.sh — sample data for a realistic software-company run.
#
# Every name uses the -DEMO- prefix so cleanup can identify what the script created.
# Change DEMO_PREFIX if you need a different marker — but remember to use the same
# prefix in both create + cleanup paths.

DEMO_PREFIX="${DEMO_PREFIX:--DEMO-}"

CONN_NAME="${CONN_NAME:-Demo HQ}"
CONN_HOST="${TALLY_HOST:-localhost}"
CONN_PORT="${TALLY_PORT:-9000}"
CONN_COMPANY="${TALLY_COMPANY:-SwatTech Demo}"

# -----------------------------------------------------------------------------
# Groups (2) — minimal CRUD coverage for the group endpoints. Ledgers below
# point directly to Tally's reserved groups (Sundry Debtors, Sales Accounts,
# Indirect Expenses, Bank Accounts, Cash-in-Hand, Duties & Taxes, etc.) so
# the test doesn't depend on creating intermediate custom groups. We keep two
# DEMO groups just to exercise create/list/show/update endpoints once.
# -----------------------------------------------------------------------------
DEMO_GROUPS=(
    '{"NAME":"-DEMO- Software Customers","PARENT":"Sundry Debtors"}'
    '{"NAME":"-DEMO- Cloud Vendors","PARENT":"Sundry Creditors"}'
)

# -----------------------------------------------------------------------------
# Ledgers (15)
# -----------------------------------------------------------------------------
DEMO_LEDGERS=(
    '{"NAME":"-DEMO- Acme Corp","PARENT":"Sundry Debtors","PARTYGSTIN":"27ABCDE1234F1Z5","EMAIL":"billing@acme.example","LEDGERPHONE":"+91-9811111111","CREDITPERIOD":"30 Days","CREDITLIMIT":500000}'
    '{"NAME":"-DEMO- TechNova Pvt Ltd","PARENT":"Sundry Debtors","PARTYGSTIN":"29FGHIJ5678K2L9","EMAIL":"ap@technova.example","CREDITPERIOD":"45 Days","CREDITLIMIT":1000000}'
    '{"NAME":"-DEMO- Global Retail Inc","PARENT":"Sundry Debtors","EMAIL":"accounts@globalretail.example","CURRENCYNAME":"USD"}'
    '{"NAME":"-DEMO- NorthStar LLC","PARENT":"Sundry Debtors","EMAIL":"ap@northstar.example","CURRENCYNAME":"USD"}'
    '{"NAME":"-DEMO- AWS India","PARENT":"Sundry Creditors","PARTYGSTIN":"06AAACA1111A1Z1"}'
    '{"NAME":"-DEMO- Google Cloud","PARENT":"Sundry Creditors"}'
    '{"NAME":"-DEMO- GitHub","PARENT":"Indirect Expenses"}'
    '{"NAME":"-DEMO- SaaS Subscription","PARENT":"Sales Accounts"}'
    '{"NAME":"-DEMO- Consulting Fees","PARENT":"Sales Accounts"}'
    '{"NAME":"-DEMO- AWS Hosting","PARENT":"Indirect Expenses"}'
    '{"NAME":"-DEMO- JetBrains Licenses","PARENT":"Indirect Expenses"}'
    '{"NAME":"-DEMO- Salary - Engineers","PARENT":"Indirect Expenses"}'
    '{"NAME":"-DEMO- Office Rent","PARENT":"Indirect Expenses"}'
    '{"NAME":"-DEMO- HDFC Current A/c","PARENT":"Bank Accounts"}'
    '{"NAME":"-DEMO- Cash in Hand","PARENT":"Cash-in-Hand"}'
    '{"NAME":"-DEMO- Output CGST @ 9%","PARENT":"Duties & Taxes"}'
    '{"NAME":"-DEMO- Output SGST @ 9%","PARENT":"Duties & Taxes"}'
    '{"NAME":"-DEMO- Output IGST @ 18%","PARENT":"Duties & Taxes"}'
    '{"NAME":"-DEMO- Input CGST @ 9%","PARENT":"Duties & Taxes"}'
    '{"NAME":"-DEMO- Input SGST @ 9%","PARENT":"Duties & Taxes"}'
    '{"NAME":"-DEMO- TDS Receivable","PARENT":"Loans & Advances (Asset)"}'
)

# -----------------------------------------------------------------------------
# Stock groups (3) — top-level. Tally has NO reserved "Primary" stock group
# (unlike account groups), so PARENT must be empty for top-level entries.
# Sending PARENT="Primary" yields LINEERROR "Stock Group 'Primary' does not exist!"
# -----------------------------------------------------------------------------
DEMO_STOCK_GROUPS=(
    '{"NAME":"-DEMO- Software Licenses","PARENT":""}'
    '{"NAME":"-DEMO- Cloud Add-ons","PARENT":""}'
    '{"NAME":"-DEMO- Professional Services","PARENT":""}'
)

# -----------------------------------------------------------------------------
# Units (3) — simple unit masters. Per Tally docs Sample 7, a complete simple
# unit also carries ORIGINALNAME (long form) and DECIMALPLACES.
# -----------------------------------------------------------------------------
DEMO_UNITS=(
    '{"NAME":"Nos","ISSIMPLEUNIT":"Yes","ORIGINALNAME":"Numbers","DECIMALPLACES":0}'
    '{"NAME":"Hrs","ISSIMPLEUNIT":"Yes","ORIGINALNAME":"Hours","DECIMALPLACES":2}'
    '{"NAME":"Users","ISSIMPLEUNIT":"Yes","ORIGINALNAME":"Named Users","DECIMALPLACES":0}'
)

# -----------------------------------------------------------------------------
# Cost centres (3) — project / department tracking
# -----------------------------------------------------------------------------
DEMO_COST_CENTRES=(
    '{"NAME":"-DEMO- Engineering","PARENT":""}'
    '{"NAME":"-DEMO- Sales","PARENT":""}'
    '{"NAME":"-DEMO- Customer Success","PARENT":""}'
)

# -----------------------------------------------------------------------------
# Currencies (2) — multi-currency support (Phase 9B)
# -----------------------------------------------------------------------------
DEMO_CURRENCIES=(
    '{"NAME":"USD","MAILINGNAME":"US Dollars","FORMALNAME":"US Dollar","ISSUFFIX":"No","HASSYMBOLSPACE":"Yes","DECIMALPLACES":2,"DECIMALSYMBOL":"."}'
    '{"NAME":"EUR","MAILINGNAME":"Euros","FORMALNAME":"Euro","ISSUFFIX":"No","HASSYMBOLSPACE":"Yes","DECIMALPLACES":2,"DECIMALSYMBOL":"."}'
)

# -----------------------------------------------------------------------------
# Godowns (2) — Tally has a reserved root "Main Location" (NOT "Primary") for
# godowns. Empty PARENT works for top-level. Same lesson as stock-groups:
# don't reuse the account-group "Primary" name across master types.
# -----------------------------------------------------------------------------
DEMO_GODOWNS=(
    '{"NAME":"-DEMO- Mumbai Warehouse","PARENT":"","ADDRESS":"Mumbai, Maharashtra","STORAGETYPE":"Our Godown"}'
    '{"NAME":"-DEMO- Pune Warehouse","PARENT":"","ADDRESS":"Pune, Maharashtra","STORAGETYPE":"Our Godown"}'
)

# -----------------------------------------------------------------------------
# Voucher Types (2) — custom voucher types (Phase 9B)
# -----------------------------------------------------------------------------
DEMO_VOUCHER_TYPES=(
    '{"NAME":"-DEMO- Export Sale","PARENT":"Sales","ABBR":"ES","NUMBERINGMETHOD":"Automatic","AFFECTSSTOCK":"No"}'
    '{"NAME":"-DEMO- Advance Receipt","PARENT":"Receipt","ABBR":"ADV","NUMBERINGMETHOD":"Automatic"}'
)

# -----------------------------------------------------------------------------
# Stock Categories (2) — alternate classification axis (Phase 9F)
# -----------------------------------------------------------------------------
DEMO_STOCK_CATEGORIES=(
    '{"NAME":"-DEMO- Enterprise Tier","PARENT":""}'
    '{"NAME":"-DEMO- SMB Tier","PARENT":""}'
)

# -----------------------------------------------------------------------------
# Price Lists (2) — Wholesale vs Retail pricing
# Since the 9M refactor, Price Levels live on Company.PRICELEVELLIST (NOT as a
# standalone PRICELEVEL master), so the payload is a plain NAME — no other
# fields are accepted by Tally's XML API for Price Level names.
# -----------------------------------------------------------------------------
DEMO_PRICE_LISTS=(
    '{"NAME":"-DEMO- Retail"}'
    '{"NAME":"-DEMO- Wholesale"}'
)

# -----------------------------------------------------------------------------
# Cost Categories (2) — Phase 9N. Top-level master used by Cost Centres + Employees.
# -----------------------------------------------------------------------------
DEMO_COST_CATEGORIES=(
    '{"NAME":"-DEMO- Department","ALLOCATEREVENUE":"Yes","ALLOCATENONREVENUE":"Yes"}'
    '{"NAME":"-DEMO- Project","ALLOCATEREVENUE":"Yes","ALLOCATENONREVENUE":"No"}'
)

# -----------------------------------------------------------------------------
# Employee Categories (2) — Phase 9N. Tally maps these to CostCategory internally.
# -----------------------------------------------------------------------------
DEMO_EMPLOYEE_CATEGORIES=(
    '{"NAME":"-DEMO- Engineering Team"}'
    '{"NAME":"-DEMO- Sales Team"}'
)

# -----------------------------------------------------------------------------
# Employee Groups (2) — Phase 9N. Map to CostCentre + CATEGORY internally.
# -----------------------------------------------------------------------------
DEMO_EMPLOYEE_GROUPS=(
    '{"NAME":"-DEMO- Backend Team","PARENT":"","CATEGORY":"-DEMO- Engineering Team"}'
    '{"NAME":"-DEMO- Account Executives","PARENT":"","CATEGORY":"-DEMO- Sales Team"}'
)

# -----------------------------------------------------------------------------
# Employees (2) — Phase 9N.
# -----------------------------------------------------------------------------
DEMO_EMPLOYEES=(
    '{"NAME":"-DEMO- Alex Dev","PARENT":"-DEMO- Backend Team","CATEGORY":"-DEMO- Engineering Team","EMPDISPLAYNAME":"Alex Dev","EMAIL":"alex@demo.example"}'
    '{"NAME":"-DEMO- Priya Sales","PARENT":"-DEMO- Account Executives","CATEGORY":"-DEMO- Sales Team","EMPDISPLAYNAME":"Priya Sales","EMAIL":"priya@demo.example"}'
)

# -----------------------------------------------------------------------------
# Attendance Types (2) — Phase 9N.
# -----------------------------------------------------------------------------
DEMO_ATTENDANCE_TYPES=(
    '{"NAME":"-DEMO- Present","PARENT":"","ATTENDANCETYPE":"Attendance","BASEUNITS":"Days"}'
    '{"NAME":"-DEMO- Leave","PARENT":"","ATTENDANCETYPE":"Leave-without-Pay","BASEUNITS":"Days"}'
)

# -----------------------------------------------------------------------------
# Stock items (3)
# -----------------------------------------------------------------------------
DEMO_STOCK_ITEMS=(
    '{"NAME":"-DEMO- SKU-PRO Annual","PARENT":"-DEMO- Software Licenses","BASEUNITS":"Nos","HASBATCHES":"No"}'
    '{"NAME":"-DEMO- SKU-ENT Annual","PARENT":"-DEMO- Software Licenses","BASEUNITS":"Nos","HASBATCHES":"No"}'
    '{"NAME":"-DEMO- Analytics Add-on","PARENT":"-DEMO- Cloud Add-ons","BASEUNITS":"Nos","HASBATCHES":"No"}'
)

# -----------------------------------------------------------------------------
# Vouchers (10) — one of every type so every branch of VoucherService runs.
# DATE format is YYYYMMDD (Tally's import format).
# Sign rules: ISDEEMEDPOSITIVE=Yes (debit), No (credit with negative amount).
# -----------------------------------------------------------------------------

voucher_sales_acme() {
cat <<'JSON'
{"type":"Sales","data":{
    "DATE":"20260416","VOUCHERTYPENAME":"Sales","VOUCHERNUMBER":"-DEMO-SI-0001",
    "PARTYLEDGERNAME":"-DEMO- Acme Corp",
    "NARRATION":"-DEMO- SaaS Subscription - Acme Corp",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Acme Corp","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"120000.00"},
        {"LEDGERNAME":"-DEMO- SaaS Subscription","ISDEEMEDPOSITIVE":"No","AMOUNT":"-120000.00"}
    ]
}}
JSON
}

voucher_sales_technova() {
cat <<'JSON'
{"type":"Sales","data":{
    "DATE":"20260417","VOUCHERTYPENAME":"Sales","VOUCHERNUMBER":"-DEMO-SI-0002",
    "PARTYLEDGERNAME":"-DEMO- TechNova Pvt Ltd",
    "NARRATION":"-DEMO- Consulting fees - TechNova",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- TechNova Pvt Ltd","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"60000.00"},
        {"LEDGERNAME":"-DEMO- Consulting Fees","ISDEEMEDPOSITIVE":"No","AMOUNT":"-60000.00"}
    ]
}}
JSON
}

voucher_sales_global() {
cat <<'JSON'
{"type":"Sales","data":{
    "DATE":"20260418","VOUCHERTYPENAME":"Sales","VOUCHERNUMBER":"-DEMO-SI-0003",
    "PARTYLEDGERNAME":"-DEMO- Global Retail Inc",
    "NARRATION":"-DEMO- SaaS - Global Retail",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Global Retail Inc","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"200000.00"},
        {"LEDGERNAME":"-DEMO- SaaS Subscription","ISDEEMEDPOSITIVE":"No","AMOUNT":"-200000.00"}
    ]
}}
JSON
}

voucher_purchase_aws() {
cat <<'JSON'
{"type":"Purchase","data":{
    "DATE":"20260416","VOUCHERTYPENAME":"Purchase","VOUCHERNUMBER":"-DEMO-PB-0001",
    "PARTYLEDGERNAME":"-DEMO- AWS India",
    "NARRATION":"-DEMO- AWS hosting bill - April 2026",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- AWS Hosting","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"45000.00"},
        {"LEDGERNAME":"-DEMO- AWS India","ISDEEMEDPOSITIVE":"No","AMOUNT":"-45000.00"}
    ]
}}
JSON
}

voucher_payment_aws() {
cat <<'JSON'
{"type":"Payment","data":{
    "DATE":"20260416","VOUCHERTYPENAME":"Payment","VOUCHERNUMBER":"-DEMO-PMT-0001",
    "NARRATION":"-DEMO- Payment to AWS India",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- AWS India","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"45000.00"},
        {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-45000.00"}
    ]
}}
JSON
}

voucher_receipt_acme() {
cat <<'JSON'
{"type":"Receipt","data":{
    "DATE":"20260417","VOUCHERTYPENAME":"Receipt","VOUCHERNUMBER":"-DEMO-RCT-0001",
    "NARRATION":"-DEMO- Partial receipt - Acme Corp",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"50000.00"},
        {"LEDGERNAME":"-DEMO- Acme Corp","ISDEEMEDPOSITIVE":"No","AMOUNT":"-50000.00"}
    ]
}}
JSON
}

voucher_journal() {
cat <<'JSON'
{"type":"Journal","data":{
    "DATE":"20260416","VOUCHERTYPENAME":"Journal","VOUCHERNUMBER":"-DEMO-JV-0001",
    "NARRATION":"-DEMO- Accrued consulting revenue",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- TechNova Pvt Ltd","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"30000.00"},
        {"LEDGERNAME":"-DEMO- Consulting Fees","ISDEEMEDPOSITIVE":"No","AMOUNT":"-30000.00"}
    ]
}}
JSON
}

voucher_contra() {
cat <<'JSON'
{"type":"Contra","data":{
    "DATE":"20260416","VOUCHERTYPENAME":"Contra","VOUCHERNUMBER":"-DEMO-CN-0001",
    "NARRATION":"-DEMO- Bank to Cash transfer",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Cash in Hand","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"10000.00"},
        {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-10000.00"}
    ]
}}
JSON
}

voucher_credit_note() {
cat <<'JSON'
{"type":"CreditNote","data":{
    "DATE":"20260417","VOUCHERTYPENAME":"Credit Note","VOUCHERNUMBER":"-DEMO-CN-0002",
    "PARTYLEDGERNAME":"-DEMO- TechNova Pvt Ltd",
    "NARRATION":"-DEMO- Credit note for over-billing",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Consulting Fees","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"5000.00"},
        {"LEDGERNAME":"-DEMO- TechNova Pvt Ltd","ISDEEMEDPOSITIVE":"No","AMOUNT":"-5000.00"}
    ]
}}
JSON
}

voucher_debit_note() {
cat <<'JSON'
{"type":"DebitNote","data":{
    "DATE":"20260417","VOUCHERTYPENAME":"Debit Note","VOUCHERNUMBER":"-DEMO-DN-0001",
    "PARTYLEDGERNAME":"-DEMO- AWS India",
    "NARRATION":"-DEMO- Vendor credit for outage",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- AWS India","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"3000.00"},
        {"LEDGERNAME":"-DEMO- AWS Hosting","ISDEEMEDPOSITIVE":"No","AMOUNT":"-3000.00"}
    ]
}}
JSON
}

# -----------------------------------------------------------------------------
# Realistic scenarios — GST breakdown, inventory, multi-currency, bill allocation
# -----------------------------------------------------------------------------

# Sales with full GST breakdown (intra-state: CGST + SGST, 18% total).
# Invoice: net 100000 + CGST 9000 + SGST 9000 = 118000 to Acme Corp.
voucher_sales_with_gst() {
cat <<'JSON'
{"type":"Sales","data":{
    "DATE":"20260419","VOUCHERTYPENAME":"Sales","VOUCHERNUMBER":"-DEMO-SI-GST-0001",
    "PARTYLEDGERNAME":"-DEMO- Acme Corp",
    "NARRATION":"-DEMO- SaaS subscription with CGST+SGST 18%",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Acme Corp","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"118000.00"},
        {"LEDGERNAME":"-DEMO- SaaS Subscription","ISDEEMEDPOSITIVE":"No","AMOUNT":"-100000.00"},
        {"LEDGERNAME":"-DEMO- Output CGST @ 9%","ISDEEMEDPOSITIVE":"No","AMOUNT":"-9000.00"},
        {"LEDGERNAME":"-DEMO- Output SGST @ 9%","ISDEEMEDPOSITIVE":"No","AMOUNT":"-9000.00"}
    ]
}}
JSON
}

# Inter-state sales with IGST 18% (single tax line instead of CGST+SGST).
voucher_sales_igst_interstate() {
cat <<'JSON'
{"type":"Sales","data":{
    "DATE":"20260419","VOUCHERTYPENAME":"Sales","VOUCHERNUMBER":"-DEMO-SI-IGST-0001",
    "PARTYLEDGERNAME":"-DEMO- TechNova Pvt Ltd",
    "NARRATION":"-DEMO- Inter-state SaaS sale with IGST 18%",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- TechNova Pvt Ltd","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"59000.00"},
        {"LEDGERNAME":"-DEMO- SaaS Subscription","ISDEEMEDPOSITIVE":"No","AMOUNT":"-50000.00"},
        {"LEDGERNAME":"-DEMO- Output IGST @ 18%","ISDEEMEDPOSITIVE":"No","AMOUNT":"-9000.00"}
    ]
}}
JSON
}

# Sale of a stock item (SKU-PRO × 2 @ ₹48000) — inventory entries included.
voucher_sales_with_inventory() {
cat <<'JSON'
{"type":"Sales","data":{
    "DATE":"20260419","VOUCHERTYPENAME":"Sales","VOUCHERNUMBER":"-DEMO-SI-INV-0001",
    "PARTYLEDGERNAME":"-DEMO- Acme Corp",
    "NARRATION":"-DEMO- License sale: 2x SKU-PRO Annual",
    "ALLINVENTORYENTRIES.LIST":[
        {
            "STOCKITEMNAME":"-DEMO- SKU-PRO Annual",
            "ACTUALQTY":"2 Nos",
            "BILLEDQTY":"2 Nos",
            "RATE":"48000/Nos",
            "AMOUNT":"-96000.00",
            "ISDEEMEDPOSITIVE":"No"
        }
    ],
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Acme Corp","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"96000.00"},
        {"LEDGERNAME":"-DEMO- SaaS Subscription","ISDEEMEDPOSITIVE":"No","AMOUNT":"-96000.00"}
    ]
}}
JSON
}

# Multi-currency USD sale to Global Retail Inc. Emitted by the smoke test only
# when USD exists as a currency in the target Tally company (the test does a
# pre-flight check via `cached_master_exists` before invoking this). If USD is
# absent (F11 multi-currency off), the wrapper falls back to voucher_sales_export_inr.
voucher_sales_usd_multicurrency() {
cat <<'JSON'
{"type":"Sales","data":{
    "DATE":"20260419","VOUCHERTYPENAME":"Sales","VOUCHERNUMBER":"-DEMO-SI-USD-0001",
    "PARTYLEDGERNAME":"-DEMO- Global Retail Inc",
    "NARRATION":"-DEMO- USD-denominated SaaS sale (export)",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Global Retail Inc","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"$ 2400","FOREXGAINLOSS":"0","RATEOFEXCHANGE":"83.5"},
        {"LEDGERNAME":"-DEMO- SaaS Subscription","ISDEEMEDPOSITIVE":"No","AMOUNT":"-200400.00"}
    ]
}}
JSON
}

# INR fallback for the export-style sale — used when USD currency isn't available
# in Tally. Keeps the chained voucher test running without depending on F11
# multi-currency being enabled.
voucher_sales_export_inr() {
cat <<'JSON'
{"type":"Sales","data":{
    "DATE":"20260419","VOUCHERTYPENAME":"Sales","VOUCHERNUMBER":"-DEMO-SI-EXPORT-0001",
    "PARTYLEDGERNAME":"-DEMO- Global Retail Inc",
    "NARRATION":"-DEMO- Export SaaS sale (INR — USD fallback since multi-currency off)",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Global Retail Inc","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"200400.00"},
        {"LEDGERNAME":"-DEMO- SaaS Subscription","ISDEEMEDPOSITIVE":"No","AMOUNT":"-200400.00"}
    ]
}}
JSON
}

# Purchase with input GST credit (CGST + SGST).
voucher_purchase_with_gst() {
cat <<'JSON'
{"type":"Purchase","data":{
    "DATE":"20260419","VOUCHERTYPENAME":"Purchase","VOUCHERNUMBER":"-DEMO-PB-GST-0001",
    "PARTYLEDGERNAME":"-DEMO- AWS India",
    "NARRATION":"-DEMO- AWS hosting with GST input credit",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- AWS Hosting","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"50000.00"},
        {"LEDGERNAME":"-DEMO- Input CGST @ 9%","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"4500.00"},
        {"LEDGERNAME":"-DEMO- Input SGST @ 9%","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"4500.00"},
        {"LEDGERNAME":"-DEMO- AWS India","ISDEEMEDPOSITIVE":"No","AMOUNT":"-59000.00"}
    ]
}}
JSON
}

# Receipt with bill allocation — tags the receipt to a specific invoice number.
voucher_receipt_with_bill_alloc() {
cat <<'JSON'
{"type":"Receipt","data":{
    "DATE":"20260420","VOUCHERTYPENAME":"Receipt","VOUCHERNUMBER":"-DEMO-RCT-BILL-0001",
    "NARRATION":"-DEMO- Receipt tagged to invoice -DEMO-SI-0001",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"60000.00"},
        {
            "LEDGERNAME":"-DEMO- Acme Corp",
            "ISDEEMEDPOSITIVE":"No",
            "AMOUNT":"-60000.00",
            "BILLALLOCATIONS.LIST":[
                {"NAME":"-DEMO-SI-0001","BILLTYPE":"Agst Ref","AMOUNT":"-60000.00"}
            ]
        }
    ]
}}
JSON
}

# Payment with bill allocation — tags the payment to a specific vendor bill.
voucher_payment_with_bill_alloc() {
cat <<'JSON'
{"type":"Payment","data":{
    "DATE":"20260420","VOUCHERTYPENAME":"Payment","VOUCHERNUMBER":"-DEMO-PMT-BILL-0001",
    "NARRATION":"-DEMO- Payment tagged to AWS bill -DEMO-PB-0001",
    "ALLLEDGERENTRIES.LIST":[
        {
            "LEDGERNAME":"-DEMO- AWS India",
            "ISDEEMEDPOSITIVE":"Yes",
            "AMOUNT":"45000.00",
            "BILLALLOCATIONS.LIST":[
                {"NAME":"-DEMO-PB-0001","BILLTYPE":"Agst Ref","AMOUNT":"45000.00"}
            ]
        },
        {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-45000.00"}
    ]
}}
JSON
}

# Journal for monthly depreciation — no party; pure internal adjustment.
voucher_journal_depreciation() {
cat <<'JSON'
{"type":"Journal","data":{
    "DATE":"20260430","VOUCHERTYPENAME":"Journal","VOUCHERNUMBER":"-DEMO-JV-DEP-0001",
    "NARRATION":"-DEMO- Monthly depreciation — April 2026",
    "ALLLEDGERENTRIES.LIST":[
        {"LEDGERNAME":"-DEMO- Office Rent","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"25000.00"},
        {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-25000.00"}
    ]
}}
JSON
}

# Batch voucher import (3 Journal entries in one request).
voucher_batch_journals() {
cat <<'JSON'
{"type":"Journal","vouchers":[
    {
        "DATE":"20260421","VOUCHERTYPENAME":"Journal","VOUCHERNUMBER":"-DEMO-JV-BATCH-0001",
        "NARRATION":"-DEMO- Batch JV 1/3 — monthly expense accrual",
        "ALLLEDGERENTRIES.LIST":[
            {"LEDGERNAME":"-DEMO- AWS Hosting","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"5000.00"},
            {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-5000.00"}
        ]
    },
    {
        "DATE":"20260421","VOUCHERTYPENAME":"Journal","VOUCHERNUMBER":"-DEMO-JV-BATCH-0002",
        "NARRATION":"-DEMO- Batch JV 2/3 — dev tools subscription accrual",
        "ALLLEDGERENTRIES.LIST":[
            {"LEDGERNAME":"-DEMO- JetBrains Licenses","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"3000.00"},
            {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-3000.00"}
        ]
    },
    {
        "DATE":"20260421","VOUCHERTYPENAME":"Journal","VOUCHERNUMBER":"-DEMO-JV-BATCH-0003",
        "NARRATION":"-DEMO- Batch JV 3/3 — office rent accrual",
        "ALLLEDGERENTRIES.LIST":[
            {"LEDGERNAME":"-DEMO- Office Rent","ISDEEMEDPOSITIVE":"Yes","AMOUNT":"25000.00"},
            {"LEDGERNAME":"-DEMO- HDFC Current A/c","ISDEEMEDPOSITIVE":"No","AMOUNT":"-25000.00"}
        ]
    }
]}
JSON
}

# Connection row payload.
connection_payload() {
cat <<JSON
{
    "name":"${CONN_NAME}",
    "code":"${CONN_CODE}",
    "host":"${CONN_HOST}",
    "port":${CONN_PORT},
    "company_name":"${CONN_COMPANY}",
    "timeout":30,
    "is_active":true
}
JSON
}
