# Tally XML API Reference

Based on official TallyPrime Demo Samples (`.docs/Demo Samples/`).

## Connection

- **Protocol**: HTTP POST
- **URL**: `http://{host}:{port}` (default `http://localhost:9000`)
- **Content-Type**: `text/xml; charset=utf-8`
- **No authentication** — Tally trusts all requests on the configured port
- **Works identically** across TallyPrime Standalone, Server, and Cloud Access

## XML Envelope — Three Export Types

### 1. Report Export (TYPE=Data) — Balance Sheet, Trial Balance, P&L, etc.

```xml
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Export</TALLYREQUEST>
    <TYPE>Data</TYPE>
    <ID>Balance Sheet</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
        <SVCURRENTCOMPANY>{CompanyName}</SVCURRENTCOMPANY>
        <EXPLODEFLAG>Yes</EXPLODEFLAG>
      </STATICVARIABLES>
    </DESC>
  </BODY>
</ENVELOPE>
```

### 2. Collection Export (TYPE=Collection) — List of Ledgers, Stock Items, etc.

```xml
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Export</TALLYREQUEST>
    <TYPE>Collection</TYPE>
    <ID>Ledger</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
        <SVCURRENTCOMPANY>{CompanyName}</SVCURRENTCOMPANY>
      </STATICVARIABLES>
    </DESC>
  </BODY>
</ENVELOPE>
```

**Collection response** (from sample `4_Collection Specification`):
```xml
<ENVELOPE>
  <HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER>
  <BODY><DESC></DESC><DATA><COLLECTION>
    <LEDGER NAME="Cash" RESERVEDNAME="">
      <PARENT TYPE="String">Cash-in-hand</PARENT>
      <CLOSINGBALANCE TYPE="Amount">18352572.24</CLOSINGBALANCE>
      ...
    </LEDGER>
  </COLLECTION></DATA></BODY>
</ENVELOPE>
```

### 3. Object Export (TYPE=Object) — Single entity by name

```xml
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Export</TALLYREQUEST>
    <TYPE>Object</TYPE>
    <SUBTYPE>Ledger</SUBTYPE>
    <ID TYPE="Name">Cash</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <SVCURRENTCOMPANY>ABC Company Ltd</SVCURRENTCOMPANY>
        <SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>
      </STATICVARIABLES>
      <FETCHLIST>
        <FETCH>Name</FETCH>
        <FETCH>Parent</FETCH>
        <FETCH>Closing Balance</FETCH>
      </FETCHLIST>
    </DESC>
  </BODY>
</ENVELOPE>
```

**Object response** (from sample `5_Object Specification`):
```xml
<ENVELOPE>
  <HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER>
  <BODY><DESC></DESC><DATA>
    <TALLYMESSAGE>
      <LEDGER NAME="Cash" RESERVEDNAME="">
        <NAME.LIST TYPE="String"><NAME>Cash</NAME></NAME.LIST>
        <PARENT TYPE="String">Cash-in-hand</PARENT>
        <CLOSINGBALANCE TYPE="Amount" BV="18352572.24"></CLOSINGBALANCE>
      </LEDGER>
    </TALLYMESSAGE>
  </DATA></BODY>
</ENVELOPE>
```

## Import Envelope

### Master Import (Create/Alter/Delete)

```xml
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Import</TALLYREQUEST>
    <TYPE>Data</TYPE>
    <ID>All Masters</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <SVCURRENTCOMPANY>{CompanyName}</SVCURRENTCOMPANY>
      </STATICVARIABLES>
    </DESC>
    <DATA>
      <TALLYMESSAGE xmlns:UDF="TallyUDF">
        <LEDGER NAME="Customer ABC" ACTION="Create">
          <NAME>Customer ABC</NAME>
          <PARENT>Sundry Debtors</PARENT>
        </LEDGER>
      </TALLYMESSAGE>
    </DATA>
  </BODY>
</ENVELOPE>
```

### Voucher Import (from sample `8_Import Vouchers`)

```xml
<ENVELOPE>
  <HEADER>
    <VERSION>1</VERSION>
    <TALLYREQUEST>Import</TALLYREQUEST>
    <TYPE>Data</TYPE>
    <ID>Vouchers</ID>
  </HEADER>
  <BODY>
    <DESC>
      <STATICVARIABLES>
        <IMPORTDUPS>@@DUPCOMBINE</IMPORTDUPS>
      </STATICVARIABLES>
    </DESC>
    <DATA>
      <TALLYMESSAGE>
        <VOUCHER>
          <DATE>20090603</DATE>
          <NARRATION>Ch. No. Tested</NARRATION>
          <VOUCHERTYPENAME>Payment</VOUCHERTYPENAME>
          <VOUCHERNUMBER>1</VOUCHERNUMBER>
          <ALLLEDGERENTRIES.LIST>
            <LEDGERNAME>Conveyance</LEDGERNAME>
            <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>
            <AMOUNT>12000.00</AMOUNT>
          </ALLLEDGERENTRIES.LIST>
          <ALLLEDGERENTRIES.LIST>
            <LEDGERNAME>Bank of India</LEDGERNAME>
            <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>
            <AMOUNT>-12000.00</AMOUNT>
          </ALLLEDGERENTRIES.LIST>
        </VOUCHER>
      </TALLYMESSAGE>
    </DATA>
  </BODY>
</ENVELOPE>
```

**Key**: Multiple `<VOUCHER>` elements in one `<TALLYMESSAGE>` for batch import.

## Import Response (from samples)

```xml
<ENVELOPE>
  <HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER>
  <BODY><DATA>
    <IMPORTRESULT>
      <CREATED>2</CREATED>
      <ALTERED>0</ALTERED>
      <LASTVCHID>1305</LASTVCHID>
      <LASTMID>0</LASTMID>
      <COMBINED>0</COMBINED>
      <IGNORED>0</IGNORED>
      <ERRORS>0</ERRORS>
    </IMPORTRESULT>
  </DATA></BODY>
</ENVELOPE>
```

**Success**: `ERRORS=0` and (`CREATED>0` or `ALTERED>0` or `COMBINED>0`)

## Voucher Actions

### Cancel (from sample — preserves audit trail)

```xml
<VOUCHER DATE="03-Jun-2009" TAGNAME="Voucher Number"
  TAGVALUE="2" VCHTYPE="Payment" ACTION="Cancel">
  <NARRATION>Being cancelled due to XYZ Reasons</NARRATION>
</VOUCHER>
```

Response: `COMBINED=1, ERRORS=0`

### Delete (from sample — permanent removal)

```xml
<VOUCHER DATE="03-Jun-2009" TAGNAME="Voucher Number"
  TAGVALUE="1" VCHTYPE="Payment" ACTION="Delete">
</VOUCHER>
```

Response: `ALTERED=1, ERRORS=0`

### All Voucher Actions

| ACTION | Purpose | Response field |
|--------|---------|---------------|
| `Create` | New voucher | CREATED |
| `Alter` | Modify existing (needs MASTERID) | ALTERED |
| `Cancel` | Void with audit trail (needs DATE+TAGVALUE+VCHTYPE) | COMBINED |
| `Delete` | Permanent removal (needs DATE+TAGVALUE+VCHTYPE) | ALTERED |

## Amount Sign Convention (from Payment sample)

| Role | ISDEEMEDPOSITIVE | AMOUNT sign | Example |
|------|-----------------|-------------|---------|
| Debit entry (expense/party receiving) | Yes | Positive | `12000.00` |
| Credit entry (bank/cash paying) | No | Negative | `-12000.00` |

## Master Object Types

| Object | SUBTYPE (for Object export) | Collection ID |
|--------|---------------------------|---------------|
| Ledger | `Ledger` | `Ledger` |
| Group | `Group` | `Group` |
| Stock Item | `Stock Item` | `StockItem` |
| Stock Group | `Stock Group` | `StockGroup` |
| Unit | `Unit` | `Unit` |
| Cost Centre | `Cost Centre` | `CostCentre` |

## Report IDs (used in TYPE=Data exports)

| Report | ID value |
|--------|---------|
| Balance Sheet | `Balance Sheet` |
| Profit & Loss | `Profit and Loss A/c` |
| Trial Balance | `Trial Balance` |
| Day Book | `Day Book` |
| Ledger Vouchers | `Ledger Vouchers` (needs LEDGERNAME filter) |
| Voucher Register | `Voucher Register` (needs VOUCHERTYPENAME filter) |
| Bills Receivable | `Bills Receivable` |
| Bills Payable | `Bills Payable` |
| Stock Summary | `Stock Summary` |
| List of Companies | `List of Companies` |

## Common Static Variables

| Variable | Purpose | Example |
|----------|---------|---------|
| `SVCURRENTCOMPANY` | Target company | `MyCompany` |
| `SVFROMDATE` | Start date | `20250401` |
| `SVTODATE` | End date | `20260331` |
| `SVEXPORTFORMAT` | Response format | `$$SysName:XML` |
| `EXPLODEFLAG` | Expand sub-groups | `Yes` |
| `IMPORTDUPS` | Duplicate handling | `@@DUPCOMBINE` |
| `LEDGERNAME` | Filter by ledger | `Cash` |
| `VOUCHERTYPENAME` | Filter by voucher type | `Sales` |

## Demo Samples Reference

| Folder | Content |
|--------|---------|
| `1_XML Messaging Format` | Basic report requests (Balance Sheet, Hello TDL) |
| `2_Export Data` | Report exports with/without TDL |
| `4_Collection Specification` | Collection exports (list of ledgers) |
| `5_Object Specification` | Single object export by name |
| `8_Import Vouchers` | Voucher creation, cancellation, deletion |
| `9_Tally as Server` | VB frontend importing masters & vouchers |
| `10_Tally as Client` | PHP web service responding to Tally requests |

## Official Documentation

- [Integrate with TallyPrime](https://help.tallysolutions.com/integrate-with-tallyprime/)
- [JSON Integration](https://help.tallysolutions.com/tally-prime-integration-using-json-1/)
- [Integration Methods](https://help.tallysolutions.com/integration-methods-and-technologies/)
- [TallyPrime API Explorer](https://tallysolutions.com/tallyprime-api-explorer/#tally-api-explorer)
