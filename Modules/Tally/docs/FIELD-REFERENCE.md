# Field Reference

Canonical XML tag names and TallyPrime-UI aliases for every master and voucher the module accepts. Any API client may pass either the canonical XML tag (e.g. `PARENT`) or any alias shown in the TallyPrime UI (e.g. `Under`, `Parent Name`) — `TallyFieldRegistry::canonicalize()` normalises to the canonical form before building the request.

**Lookup is case-insensitive and whitespace-insensitive.** `Parent Name`, `parent_name`, `PARENTNAME`, `Parent-Name` all resolve to the same canonical key.

Source: TallyPrime Masters Field Reference (316 mappings across 14 entity types).

Wire-up: `Modules\Tally\Services\Fields\TallyFieldRegistry` + `Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases` trait (auto-applied on every master Form Request). Master services' `create()` / `update()` and `VoucherService::create / createBatch / alter` all run through the registry before XML construction.

---

## Groups (`GROUP`)

| Canonical | Aliases |
|---|---|
| `PARENT` | Parent Group Name, Under, Parent Name |
| `ISSUBLEDGER` | Group behaves like a sub-ledger, Is Sub Ledger |
| `ISADDABLE` | Nett Credit/Debit Balances for Reporting, Is Addable |
| `LANGUAGENAME.LIST` | Language for Name (Except English), Language Alias |
| `BASICGROUPISCALCULABLE` | Used for calculation (for example: taxes, discounts), Basic Group Is Calculable |
| `ADDLALLOCTYPE` | Method to allocate when used in purchase invoice, Addl Alloc Type |

## Ledgers (`LEDGER`)

63 fields — see `Modules/Tally/app/Services/Fields/TallyFieldRegistry.php`. Highlights:

| Canonical | Aliases |
|---|---|
| `PARENT` | Group Name, Under, Parent |
| `NARRATION` | Notes |
| `PARTYGSTIN` | GST Registration -GSTIN/UIN, GST Number, GST No., GST Identification Number |
| `LEDGERPHONE` | Phone No., Ledger Phone, Phone Number |
| `EMAIL` | E-mail, E-mail address, E-mail ID |
| `LEDGERCURRENCY` | Currency of Ledger, Ledger Currency |
| `AFFECTSSTOCK` | Inventory values are affected, Affects Stock |
| `ISBILLWISEON` | Maintain balances bill-by-bill, Is Billwise On |
| `ISCOSTCENTRESON` | Cost centres are applicable, Is Cost Centres On |
| `ISCHEQUEPRINTINGENABLED` | Enable Cheque Printing, Is Cheque Printing Enabled |

Bank Account Details, Multi-mailing, Interest Calculation, e-Payments — see the registry file for the full set.

## Cost Centres / Cost Category / Stock Group / Stock Category / Unit / Godown / Stock Item

See the registry file for the full list. Every canonical tag and every alias documented in the TallyPrime UI is covered.

## Employee trio + Attendance Type

| Entity | Constant | Canonical fields |
|---|---|---|
| Employee Group | `TallyFieldRegistry::EMPLOYEE_GROUP` | `PARENT`, `CATEGORY`, `LANGUAGENAME.LIST` |
| Employee Category | `TallyFieldRegistry::EMPLOYEE_CATEGORY` | `LANGUAGENAME.LIST` |
| Employee | `TallyFieldRegistry::EMPLOYEE` | 16 fields — PARENT, CATEGORY, EMPDISPLAYNAME, MAILINGNAME, CONTACTNUMBERS, EMPLOYEEBANKDETAILS.\*, PAYROLLBANKINGDETAILS.\*, FPFACCOUNTNUMBER, IDENTITYNUMBER, IDENTITYEXPIRYDATE, etc. |
| Attendance Type | `TallyFieldRegistry::ATTENDANCE_TYPE` | `PARENT`, `ATTENDANCETYPE`, `BASEUNITS`, `LANGUAGENAME.LIST` |

## Voucher (`VOUCHER`)

149 fields. Highlights:

| Canonical | Aliases |
|---|---|
| `DATE` | Voucher Date, Invoice Date |
| `VOUCHERNUMBER` | Voucher Number, Invoice Number, Invoice No., Voucher No. |
| `REFERENCE` | Reference No., Supplier Invoice No. |
| `PARTYLEDGERNAME` | Customer's Name, Party Name |
| `STOCKITEMNAME` | Item Name, Stock Item, Name of Item |
| `ITEMALLOCATIONS.GODOWNNAME` | Item Allocations – Godown Name, Item Allocations – Location Name |
| `BANKALLOCATIONS.INSTRUMENTNUMBER` | Bank Allocations – Inst No., Bank Allocations – Instrument Number |
| `BUYERGSTIN` | Buyer/Supplier – GSTIN/UIN, Buyer/Supplier – GST Number |
| `IRNACKNO` | e-Invoice – Ack No., IRN Ack No. |
| `GSTHSNNAME` | HSN/SAC, GST HSN Name |

Plus: bill/bank/POS/GST allocation trees, item allocations with batch/mfg date/expiry, e-Invoice, component allocation, stat adjustment (GST/TDS/TCS), TDS/TCS party details. See registry file for the full list.

---

## Using aliases in practice

### HTTP payload

```bash
curl -X POST https://your-host/api/tally/MUM/ledgers \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{
    "NAME": "Acme Corp",
    "Under": "Sundry Debtors",                   // alias for PARENT
    "GST Number": "27ABCDE1234F1Z5",             // alias for PARTYGSTIN
    "Phone Number": "+91-9811111111",            // alias for LEDGERPHONE
    "Ledger Currency": "INR"                     // alias for LEDGERCURRENCY
  }'
```

All four alias keys resolve to canonical form before XML is built.

### PHP direct call

```php
use Modules\Tally\Services\Fields\TallyFieldRegistry;

$data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::LEDGER, [
    'NAME' => 'Acme Corp',
    'Under' => 'Sundry Debtors',
]);
// $data === ['NAME' => 'Acme Corp', 'PARENT' => 'Sundry Debtors']
```

### Accept aliases in a new Form Request

```php
use Modules\Tally\Http\Requests\Concerns\AcceptsFieldAliases;
use Modules\Tally\Services\Fields\TallyFieldRegistry;

class StoreXyzRequest extends FormRequest
{
    use AcceptsFieldAliases;

    protected string $tallyEntity = TallyFieldRegistry::LEDGER;
    // rules() writes against canonical names only.
}
```

`prepareForValidation()` runs before `rules()`, so `$request->validated()` always returns canonical keys.
