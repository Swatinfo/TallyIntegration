<?php

namespace Modules\Tally\Services\Fields;

/**
 * Canonical field names + aliases for every Tally master / voucher the module
 * accepts. Callers can pass either the canonical Tally XML tag (e.g. `PARENT`)
 * or any human-readable alias shown in the TallyPrime UI (e.g. `Under`,
 * `Parent Name`) — `canonicalize()` normalises to the XML tag before request
 * building.
 *
 * Source: TallyPrime's own Masters Field Reference documentation — 316 mappings
 * across 14 entity types. Every alias here mirrors the TallyPrime "Aliases"
 * column verbatim so an accountant's terminology maps 1:1.
 *
 * Field naming conventions:
 *   - Canonical values are the uppercase XML tag Tally itself emits on export.
 *   - Aliases are case-insensitive and space-insensitive — `Parent Name`,
 *     `parent_name`, `PARENTNAME` all match the same canonical key.
 */
final class TallyFieldRegistry
{
    /**
     * Entity identifiers accepted by canonicalize().
     */
    public const GROUP = 'Group';

    public const LEDGER = 'Ledger';

    public const COST_CENTRE = 'Cost Centre';

    public const COST_CATEGORY = 'Cost Category';

    public const STOCK_GROUP = 'Stock Group';

    public const STOCK_CATEGORY = 'Stock Category';

    public const UNIT = 'Unit';

    public const GODOWN = 'Godown';

    public const STOCK_ITEM = 'Stock Item';

    public const EMPLOYEE_GROUP = 'Employee Group';

    public const EMPLOYEE_CATEGORY = 'Employee Category';

    public const EMPLOYEE = 'Employee';

    public const ATTENDANCE_TYPE = 'Attendance Type';

    public const VOUCHER = 'Voucher';

    /**
     * Canonical field → array of aliases, grouped by entity.
     * Aliases are the strings an accountant would use verbatim from TallyPrime's UI.
     *
     * @var array<string, array<string, array<int, string>>>
     */
    private const MAP = [
        self::GROUP => [
            'PARENT' => ['Parent Group Name', 'Under', 'Parent Name'],
            'ISSUBLEDGER' => ['Group behaves like a sub-ledger', 'Is Sub Ledger'],
            'ISADDABLE' => ['Nett Credit/Debit Balances for Reporting', 'Is Addable'],
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
            'BASICGROUPISCALCULABLE' => ['Used for calculation (for example: taxes, discounts)', 'Basic Group Is Calculable'],
            'ADDLALLOCTYPE' => ['Method to allocate when used in purchase invoice', 'Addl Alloc Type'],
        ],

        self::LEDGER => [
            'PARENT' => ['Group Name', 'Under', 'Parent'],
            'NARRATION' => ['Notes'],
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
            'ISCOSTCENTRESON' => ['Cost centres are applicable', 'Is Cost Centres On'],
            'ISINTERESTON' => ['Activate Interest Calculation', 'Is Interest On'],
            'ISCREDITDAYSCHKON' => ['Check for credit days during voucher entry', 'Is Credit Days Chk On'],
            'AFFECTSSTOCK' => ['Inventory values are affected', 'Affects Stock'],
            'ISCOSTTRACKINGON' => ['Allow cost allocation (stock item)', 'Is Cost Tracking On'],
            'RATEOFTAXCALCULATION' => ['Tax/Duty – Percentage of Calculation', 'Rate Of Tax Calculation'],
            'CESSVALUATIONMETHOD' => ['Tax/Duty – Valuation Type', 'Cess Valuation Method'],
            'LEDGERCURRENCY' => ['Currency of Ledger', 'Ledger Currency'],
            'ISBEHAVEASDUTY' => ['Behave as Duties & Taxes Ledger', 'Is Behave as Duty'],
            'TDSRATENAME' => ['Nature of Payment/Goods', 'TDS Rate Name'],
            'INCOMETAXNUMBER' => ['Tax/Unique Identification Number', 'Tax Identification No.'],
            'SORTPOSITION' => ['Position Index in Reports', 'Sort Position'],
            'STARTINGFROM' => ['Effective Date for Reconciliation', 'Starting From'],
            'BANKACCHOLDERNAME' => ["Bank Account Details – A/c Holder's Name", 'Bank Acc Holder Name'],
            'BANKACCOUNTDETAILS.ACCOUNTNUMBER' => ['Bank Account Details – A/c No.', 'Bank Account Details – Account Number', 'Bank Account Details – Account No.'],
            'BANKBRANCHNAME' => ['Bank Account Details – Branch', 'Bank Branch Name'],
            'BANKBSRCODE' => ['Bank Account Details – BSR Code', 'Bank BSR Code'],
            'BANKCLIENTCODE' => ['Bank Account Details – Client Code', 'Bank Client Code'],
            'ISCHEQUEPRINTINGENABLED' => ['Enable Cheque Printing', 'Is Cheque Printing Enabled'],
            'ISABCENABLED' => ['Enable Auto Reconciliation', 'Is ABC Enabled'],
            'BANKDETAILS.ACCOUNTNUMBER' => ['Bank Details – A/c No.', 'Bank Details – Account Number', 'Bank Details – Account No.'],
            'BANKDETAILS.REFERENCEID' => ['Bank Details – Ref ID', 'Bank Details – Reference ID'],
            'ISBILLWISEON' => ['Maintain balances bill-by-bill', 'Is Billwise On'],
            'BANKERSDATE' => ['Opening Bank Reconciliation – Bank Date', 'Bankers Date'],
            'LEDGERMOBILE' => ['Primary Mobile No.', 'Primary Mobile Number', 'Ledger Mobile'],
            'MOBILENUMBERS.LIST.MOBILENUMBER' => ['Multiple Mobile Nos. – Mobile No.', 'Multiple Mobile Nos. – Mobile Number'],
            'LEDGERCONTACT' => ['Contact Name', 'Ledger Contact'],
            'LEDGERPHONE' => ['Phone No.', 'Ledger Phone', 'Phone Number'],
            'LEDGERFAX' => ['Fax No.', 'Ledger Fax'],
            'EMAIL' => ['E-mail', 'E-mail address', 'E-mail ID'],
            'APPROPRIATEFOR' => ['Include in Assessable Value calculation – Duty/Tax Type', 'Appropriate For'],
            'GSTAPPROPRIATETO' => ['Include in Assessable Value calculation – Appropriate to', 'GST Appropriate To'],
            'EXCISEALLOCTYPE' => ['Include in Assessable Value calculation – Method of calculation', 'Excise Alloc Type'],
            'PANAPPLICABLEFROM' => ['PAN Effective Date', 'PAN Applicable From'],
            'ISOTHTERRITORYASSESSEE' => ['GST Registration – Assessee of Other Territory', 'Is Oth Territory Assessee'],
            'PARTYGSTIN' => ['GST Registration -GSTIN/UIN', 'GST Number', 'GST No.', 'GST Identification Number'],
            'ISCOMMONPARTY' => ['GST Registration – Use Ledger as common Party', 'Is Common Party'],
            'ISTRANSPORTER' => ['Is the Party a Transporter', 'Is Transporter'],
            'VATAPPLICABLEDATE' => ['Date of VAT Registration', 'VAT Applicable Date'],
            'VATTAXEXEMPTIONNATURE' => ['Type of Party', 'VAT Tax Exemption Nature'],
            'VATTAXEXEMPTIONNUMBER' => ['Exemption Certificate No.', 'VAT Tax Exemption Number'],
            'INTERESTINCLDAYOFADDITION' => ['Include transaction date for interest calculation – For amounts added', 'Interest Incl Day Of Addition'],
            'INTERESTINCLDAYOFDEDUCTION' => ['Include transaction date for interest calculation – For amounts deducted', 'Interest Incl Day Of Deduction'],
            'INTERESTONBILLWISE' => ['Calculate Interest Transaction-by-Transaction', 'Interest on Bill-wise'],
            'TYPEOFINTERESTON' => ['Calculate Interest Based on', 'Type of Interest on'],
            'OVERRIDEINTEREST' => ['Override Parameters for each Transaction', 'Override Interest'],
            'OVERRIDEADVINTEREST' => ['Override advance parameters', 'Override Adv Interest'],
            'MULTIPLEMAILINGDETAILS.GSTIN' => ['Multiple Mailing Details – GSTIN/UIN', 'Multiple Mailing Details – GST Number', 'Multiple Mailing Details – GST No.'],
            'MULTIPLEMAILINGDETAILS.ISOTHERTERRITORY' => ['Multiple Mailing Details – Assessee of Other Territory', 'Multiple Mailing Details – Is Oth Assessee Territory Assessee'],
            'MAILINGNAMENATIVE' => ['Mailing details in Local Language – Name', 'Mailing Name Native'],
            'ADDRESSNATIVE' => ['Mailing details in Local Language – Address', 'Address Native'],
            'COUNTRYNAMENATIVE' => ['Mailing details in Local Language – Country', 'Country Name Native'],
            'ISEBANKINGENABLED' => ['Enable e-Payments', 'Is E-banking Enabled'],
            'PAYINSISBATCHAPPLICABLE' => ['Generate Payment Instructions in Batches', 'Pay Ins is Batch Applicable'],
            'PRODUCTCODETYPE' => ['Specify Product Code based on', 'Product Code Type'],
            'ISEXPORTONVCHCREATE' => ['Export/Upload Payment instructions on Voucher Creation', 'Is Export On Vch Create'],
            'ALLOWEXPORTWITHERRORS' => ['Allow export of transactions with mismatch on bank details', 'Allow Export with Errors'],
            'PAYMENTINSTLOCATION' => ['Folder Path – Payment Instructions', 'Payment Inst Location'],
            'NEWIMFLOCATION' => ['Folder Path – New Intermediate Files', 'New IMF Location'],
            'IMPORTEDIMFLOCATION' => ['Folder Path – Imported Intermediate Files', 'Imported IMF Location'],
        ],

        self::COST_CENTRE => [
            'CATEGORY' => ['Category Name', 'Cost Category Name'],
            'PARENT' => ['Parent Name', 'Under'],
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
            'REVENUELEDFOROPBAL' => ['Show opening balance for revenue Items in reports', 'Revenue Led For OpBal'],
            'BANKDETAILS.TRANSACTIONTYPE' => ['Bank Details – Transaction Type', 'Bank Details – Account Number', 'Bank Details – Account No.'],
            'BANKDETAILS.REFERENCEID' => ['Bank Details – Ref ID', 'Bank Details – Reference ID'],
        ],

        self::COST_CATEGORY => [
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
        ],

        self::STOCK_GROUP => [
            'PARENT' => ['Parent Group Name', 'Under', 'Parent Name'],
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
        ],

        self::STOCK_CATEGORY => [
            'PARENT' => ['Stock Category', 'Under'],
            'LANGUAGENAME.LIST' => ['Language Alias'],
        ],

        self::UNIT => [
            'ORIGINALNAME' => ['Formal name', 'Original Symbol'],
            'REPORTINGUQCNAME' => ['Unit Quantity Code (UQC)', 'Reporting UQC Name'],
            'BASEUNITS' => ['First unit', 'Base Units'],
            'ADDITIONALUNITS' => ['Second unit', 'Additional Units'],
        ],

        self::GODOWN => [
            'PARENT' => ['Under'],
            'HASNOSPACE' => ['Has no Space'],
            'ISINTERNAL' => ['Is Internal'],
            'ISEXTERNAL' => ['Is External'],
            'JOBNAME' => ['Job Name'],
            'LANGUAGENAME.LIST' => ['Language Alias'],
        ],

        self::STOCK_ITEM => [
            'PARTNUMBER' => ['Part No.', 'Part Number'],
            'ALIAS.PARTNUMBER' => ['Alias – Part No.', 'Alias – Part Number'],
            'NARRATION' => ['Notes'],
            'PARENT' => ['Group Name', 'Parent Name', 'Under'],
            'CATEGORY' => ['Category', 'Stock Category'],
            'BASEUNITS' => ['Units', 'UOM', 'Base Units'],
            'ADDITIONALUNITS' => ['Alternate units', 'Additional Units'],
            'OPENINGVALUE' => ['Opening Balance – Value', 'Opening Value'],
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
            'ITEMALLOCATIONS.GODOWNNAME' => ['Item Allocations – Godown', 'Item Allocations – Location'],
            'ITEMALLOCATIONS.MFGDATE' => ['Item Allocations – Mfg. Date', 'Item Allocations – Manufacturing Date', 'Item Allocations – Mfd On'],
            'HASMFGDATE' => ['Track date of manufacturing', 'Has Mfg Date'],
            'ISPERISHABLEON' => ['Use expiry dates', 'Is Perishable on'],
            'ISCOSTTRACKINGON' => ['Enable Cost Tracking', 'Is Cost Tracking On'],
            'ISBATCHWISEON' => ['Maintain in Batches', 'Is Batchwise on'],
            'IGNOREPHYSICALDIFFERENCE' => ['Ignore difference due to physical counting', 'Ignore Physical Difference'],
            'IGNORENEGATIVESTOCK' => ['Ignore negative balances', 'Ignore Negative Stock'],
            'TREATSALESASMANUFACTURED' => ['Treat all sales as new manufacture', 'Treat Sales as Manufactured'],
            'TREATPURCHASESASCONSUMED' => ['Treat all purchases as consumed', 'Treat Purchases as Consumed'],
            'TREATREJECTSASSCRAP' => ['Treat all rejections inward as scrap', 'Treat Rejects as Scrap'],
            'ALLOWUSEOFEXPIREDITEMS' => ['Use expired batches during voucher entry', 'Allow Use of Expired Items'],
            'COMPONENTLISTNAME' => ['Name of BOM', 'Component List Name'],
            'COMPONENTBASICQTY' => ['Unit of manufacture', 'Component Basic Qty'],
            'BOMCOMPONENT.NAME' => ['BOM Component – Item Name', 'BOM Component – Name Of Item', 'BOM Component – Stock Item Name'],
            'BOMCOMPONENT.GODOWN' => ['BOM Component – Godown', 'BOM Component – Location'],
            'BOMCOMPONENT.NATURE' => ['BOM Component – Type of Item', 'BOM Component – Nature of Item'],
            'BOMCOMPONENT.RATE' => ['BOM Component – Rate (%)', 'Addl Cost Alloc Perc'],
            'ACCOUNTINGALLOCATIONPURCHASE.CLASSRATE' => ['Accounting Allocation (Purchase) -Percentage', 'Accounting Allocation (Purchase) – Class rate'],
            'TAXCLASSIFICATION.PURCHASE.GST' => ['Tax classification Purchase – GST', 'Tax classification Purchase – GST Classification Nature'],
            'TAXCLASSIFICATION.PURCHASE.TDS' => ['Tax classification Purchase – TDS', 'Tax classification Purchase – TDS Classification Name'],
            'TAXCLASSIFICATION.PURCHASE.VAT' => ['Tax classification Purchase – VAT', 'Tax classification Purchase – VAT Classification Name'],
            'ACCOUNTINGALLOCATIONSALES.CLASSRATE' => ['Accounting Allocation (Sales) – Percentage', 'Accounting Allocation (Sales) – Class rate'],
            'TAXCLASSIFICATION.SALES.GST' => ['Tax classification Sales – GST', 'Tax classification Sales – GST Classification Nature'],
            'TAXCLASSIFICATION.SALES.TCS' => ['Tax classification Sales – TCS', 'Tax classification Sales – TCS Classification Name'],
            'TAXCLASSIFICATION.SALES.VAT' => ['Tax classification Sales – VAT', 'Tax classification Sales – VAT Classification Name'],
            'MODIFYMRPRATE' => ['Allow MRP modification in voucher', 'Modify MRP Rate'],
            'BASICRATEOFEXCISE' => ['Rate of Duty', 'Basic Rate of Excise', 'Rate of VAT'],
            'PRICELIST.STARTINGFROM' => ['Item Quantities – From', 'Starting From'],
            'PRICELIST.ENDINGAT' => ['Item Quantities – Less than', 'Ending At'],
            'GVATISEXCISEAPPL' => ['Is Excise Applicable', 'GVAT Is Excise Appl'],
            'REORDERBASE' => ['Reorder – Quantity', 'Reorder Base'],
            'REORDERPERIOD' => ['Reorder Level – Period', 'Reorder Period'],
            'REORDERASHIGHER' => ['Reorder Level – Criteria', 'Reorder as Higher'],
            'REORDERROUNDTYPE' => ['Reorder Level – Rounding Method', 'Reorder Round Type'],
            'REORDERROUNDLIMIT' => ['Reorder Level – Rounding Limit', 'Reorder Round Limit'],
            'MINIMUMORDERBASE' => ['Minimum Order – Quantity', 'Minimum Order Base'],
            'MINORDERASHIGHER' => ['Minimum Order – Criteria', 'Min Order As Higher'],
            'MINORDERROUNDTYPE' => ['Minimum Order – Rounding Type', 'Min Order Round Type'],
            'HSNNAME' => ['HSN/SAC', 'GST HSN Name'],
            'HSNDESCRIPTION' => ['HSN Description', 'GST HSN Description'],
            'HSNITEMSOURCE' => ['HSN/SAC Source of Details', 'HSN Item Source'],
            'HSNOVRDNCLASSIFICATION' => ['HSN/SAC Classification', 'HSN Ovrdn Classification'],
        ],

        self::EMPLOYEE_GROUP => [
            'CATEGORY' => ['Category', 'Cost Category'],
            'PARENT' => ['Parent Group Name', 'Under', 'Parent Name'],
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
        ],

        self::EMPLOYEE_CATEGORY => [
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
        ],

        self::EMPLOYEE => [
            'PARENT' => ['Group Name', 'Under', 'Parent Name'],
            'CATEGORY' => ['Category', 'Employee Category'],
            'EMPDISPLAYNAME' => ['Display name in reports', 'Emp Display Name'],
            'NARRATION' => ['Notes'],
            'DEACTIVATIONDATE' => ['Date of resignation/retirement', 'Deactivation Date'],
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
            'PERIODFROM' => ['Salary – Effective From', 'Period From'],
            'EMPTIMERATE' => ['Salary – Rate', 'Emp Time Rate'],
            'MAILINGNAME' => ['Employee Number', 'Mailing Name'],
            'CONTACTNUMBERS' => ['Phone No.', 'Contact Numbers'],
            'EMPLOYEEBANKDETAILS.ACCOUNTNUMBER' => ['Employee Bank Details – A/c No.', 'Employee Bank Details – Account Number', 'Employee Bank Details – Account No.'],
            'PAYROLLBANKINGDETAILS.ACCOUNTNUMBER' => ['Payroll Banking Details – A/c No.', 'Payroll Banking Details – Account Number', 'Payroll Banking Details – Account No.'],
            'PAYROLLBANKINGDETAILS.REFERENCEID' => ['Payroll Banking Details – Ref ID', 'Payroll Banking Details – Reference ID'],
            'FPFACCOUNTNUMBER' => ['EPS account number', 'FPF Account Number'],
            'IDENTITYNUMBER' => ['Emirates ID Number', 'Identity Number'],
            'IDENTITYEXPIRYDATE' => ['Emirates ID Expiry Date', 'Identity Expiry Date'],
        ],

        self::ATTENDANCE_TYPE => [
            'PARENT' => ['Parent Name', 'Under'],
            'LANGUAGENAME.LIST' => ['Language for Name (Except English)', 'Language Alias'],
            'ATTENDANCETYPE' => ['Attendance Type', 'Production Type'],
            'BASEUNITS' => ['Unit', 'Base Units', 'UOM'],
        ],

        self::VOUCHER => [
            'DATE' => ['Voucher Date', 'Invoice Date'],
            'VOUCHERNUMBER' => ['Voucher Number', 'Invoice Number', 'Invoice No.', 'Voucher No.'],
            'REFERENCE' => ['Reference No.', 'Supplier Invoice No.'],
            'REFERENCEDATE' => ['Reference Date', 'Supplier Invoice Date'],
            'ACTIVETO' => ['Voucher Applicable Upto', 'Active To'],
            'DESTINATIONGODOWN' => ['Destination Godown', 'Destination Location'],
            'SOURCEGODOWN' => ['Source Godown', 'Source Location'],
            'ORDERNUMBER' => ['Order No.', 'Order Number'],
            'BASICVOUCHERCHEQUENAME' => ['Name on Receipt', 'Basic Voucher Cheque Name'],
            'VCHGSTCLASS' => ['GST Type', 'Vch GST Class'],
            'RATEOFINVOICETAX' => ['Ledger Rate', 'Rate Of Invoice Tax'],
            'BASICUSERDESCRIPTION' => ['Description of Ledger', 'Basic User Description'],
            'STOCKITEMNAME' => ['Item Name', 'Stock Item', 'Name of Item'],
            'STOCKITEMAMOUNT' => ['Item Amount', 'Stock Item Amount'],
            'STOCKITEMDESCRIPTION' => ['Item Description', 'Stock Item Description'],
            'UOM' => ['Quantity UOM'],
            'STOCKITEMRATE' => ['Item Rate', 'Stock Item Rate'],
            'STOCKITEMRATEPER' => ['Item Rate per', 'Stock Item Rate Per'],
            'ISSCRAP' => ['Consider as Scrap', 'Is Scrap'],
            'EIDISCOUNTAMT' => ['Discount Amount (Cash/Trade)', 'EI Discount Amt'],
            'BASICPACKAGEMARKS' => ['Marks', 'Basic Package Marks'],
            'BASICNUMPACKAGES' => ['No. of Packages', 'Basic Num Packages'],
            'BASICPURCHASEORDERNO' => ['Order No(s)', 'Basic Purchase Order No'],
            'BASICORDERDATE' => ['Order – Date', 'Basic Order Date'],
            'BASICDUEDATEOFPYMT' => ['Mode/Terms of Payment', 'Basic Due Date of Pymt'],
            'BASICORDERREF' => ['Other References', 'Basic Order Ref'],
            'BASICORDERTERMS' => ['Terms of Delivery', 'Basic Order Terms'],
            'BASICSHIPPEDBY' => ['Dispatched through', 'Basic Shipped by'],
            'BASICFINALDESTINATION' => ['Destination', 'Basic Final Destination'],
            'EICHECKPOST' => ['Carrier Name/Agent', 'EI Check Post'],
            'BASICDATETIMEOFINVOICE' => ['Date and Time of Issue', 'Basic Date Time Of Invoice'],
            'BASICSHIPVESSELNO' => ['Motor Vehicle No.', 'Basic Ship Vessel No', 'Vessel/Flight No.', 'Basic Flight No.'],
            'BASICPLACEOFRECEIPT' => ['Place of Receipt by Shipper', 'Basic Place of Receipt'],
            'BASICPORTOFLOADING' => ['Port of Loading', 'Basic Port of Loading'],
            'BASICPORTOFDISCHARGE' => ['Port of Discharge', 'Basic Port of Discharge'],
            'BASICDESTINATIONCOUNTRY' => ['Country to', 'Basic Destination Country'],
            'GSTNATUREOFRETURN' => ['Reason for Issuing Note', 'GST Nature of Return'],
            'BUYERSVATPARTYTRANSRETURNNUMBER' => ["Buyer's Debit Note No.", "Buyer's VAT Party Trans Return Number"],
            'BUYERSVATPARTYTRANSRETURNDATE' => ["Buyer's Debit Note – Date", "Buyer's VAT Party Trans Return Date"],
            'SUPPLIERSVATPARTYTRANSRETURNNUMBER' => ["Supplier's Debit/Credit Note No.", "Supplier's VAT Party Trans Return Number"],
            'SUPPLIERSVATPARTYTRANSRETURNDATE' => ["Supplier's Debit/Credit Note – Date", "Supplier's VAT Party Trans Return Date"],
            'ATTDTYPEVALUE' => ['Attendance – Value', 'Attendance – Attd Type Value'],
            'ITEMALLOCATIONS.GODOWNNAME' => ['Item Allocations – Godown Name', 'Item Allocations – Location Name'],
            'ITEMALLOCATIONS.TRACKINGNUMBER' => ['Item Allocations – Tracking No.', 'Item Allocations – Tracking Number'],
            'ITEMALLOCATIONS.BATCHNAME' => ['Item Allocations – Batch/Lot No.', 'Item Allocations – Batch Name'],
            'ITEMALLOCATIONS.SOURCEGODOWN' => ['Item Allocations – Source Godown', 'Item Allocations – Source Location'],
            'ITEMALLOCATIONS.DESTINATIONGODOWN' => ['Item Allocations – Destination Godown', 'Item Allocations – Destination Location'],
            'ITEMALLOCATIONS.MFGDATE' => ['Item Allocations – Mfg. Date', 'Item Allocations – Mfd on'],
            'ITEMALLOCATIONS.EXPIRYDATE' => ['Item Allocations – Expiry Date', 'Item Allocations – Expiry Period'],
            'ITEMALLOCATIONS.ORDERDUEDATE' => ['Item Allocations – Order Due on', 'Item Allocations – Order Due Date'],
            'ITEMALLOCATIONS.ORDERPRECLOSUREQTY' => ['Item Allocations – Pre-Close Quantity', 'Item Allocations – Order Pre closure Qty'],
            'ITEMALLOCATIONS.ORDERPRECLOSUREREASON' => ['Item Allocations – Reason for Pre-Close', 'Item Allocations – Order Pre closure Reason'],
            'ITEMALLOCATIONS.ORDERPRECLOSUREDATE' => ['Item Allocations – Pre-Close Date', 'Item Allocations – Order Pre closure Date'],
            'ITEMALLOCATIONS.BATCHDISCOUNT' => ['Item Allocations – Disc%', 'Item Allocations – Batch Discount'],
            'ITEMALLOCATIONS.DYNAMICCSTNO' => ['Item Allocations – Cost Tracking To', 'Item Allocations – Dynamic Cst No'],
            'ITEMALLOCATIONS.ISTRACKCOMPONENT' => ['Item Allocations – Track Component', 'Item Allocations – Is Track Component'],
            'INTERESTSTYLE' => ['Interest – % per', 'Interest Style'],
            'INTERESTBALANCETYPE' => ['Interest – on', 'Interest Balance Type'],
            'INTERESTAPPLFROM' => ['Interest – By', 'Interest Appl From'],
            'ROUNDTYPE' => ['Interest – Rounding', 'Round Type'],
            'ROUNDLIMIT' => ['Interest – Limit', 'Round Limit'],
            'COMPONENTALLOCATION.LOCATIONNAME' => ['Component Allocation – Godown Name', 'Component Allocation – Location Name'],
            'COMPONENTALLOCATION.NAMEOFITEM' => ['Component Allocation – Item Name', 'Component Allocation – Name of Item'],
            'COMPONENTALLOCATION.NATUREOFCOMPONENT' => ['Component Allocation – Track', 'Component Allocation – Nature of Component'],
            'COMPONENTALLOCATION.ORDERDUEDATE' => ['Component Allocation – Due on', 'Component Allocation – Order Due Date'],
            'COMPONENTALLOCATION.BILLEDQTY' => ['Component Allocation – Actual Quantity', 'Component Allocation – Billed Qty'],
            'COMPONENTALLOCATION.MFDON' => ['Component Allocation – Mfg Dt.', 'Component Allocation – Mfd On'],
            'COMPONENTALLOCATION.EXPIRYPERIOD' => ['Component Allocation – Expiry Date', 'Component Allocation – Expiry Period'],
            'PARTYLEDGERNAME' => ["Customer's Name", 'Party Name'],
            'IRNACKNO' => ['e-Invoice – Ack No.', 'IRN Ack No.'],
            'IRNACKDATE' => ['e-Invoice – Ack Date', 'IRN Ack Date'],
            'IRNCANCELREASON' => ['e-Invoice Cancellation – Reason for Cancellation', 'e-Invoice Cancellation – IRN Cancel Reason'],
            'IRNCANCELCODE' => ['e-Invoice Cancellation – Remarks', 'e-Invoice Cancellation – IRN Cancel Code'],
            'GSTOVRDNSTOREDNATURE' => ['GST Nature of Transaction', 'GST Ovrdn Stored Nature'],
            'GSTOVRDNCLASSIFICATION' => ['GST Classification', 'GST Ovrdn Classification'],
            'GSTRATEINFERAPPLICABILITY' => ['GST Rate Details', 'GST Rate Infer Applicability'],
            'GSTOVRDNASSESSABLEVALUE' => ['Taxable Value', 'GST Ovrdn Assessable Value'],
            'GSTITEMSOURCE' => ['GST Source of Details', 'GST Item Source'],
            'GSTOVRDNISREVCHARGEAPPL' => ['Applicable for Reverse Charge', 'GST Ovrdn Is Rev Charge Appl'],
            'GSTOVRDNINELIGIBLEITC' => ['Eligible for Input Tax Credit', 'GST Ovrdn Ineligible ITC'],
            'GSTOVRDNTAXABILITY' => ['GST Taxability Type', 'GST Ovrdn Taxability'],
            'GSTHSNNAME' => ['HSN/SAC', 'GST HSN Name'],
            'GSTHSNDESCRIPTION' => ['HSN Description', 'GST HSN Description'],
            'HSNITEMSOURCE' => ['HSN/SAC Source of Details', 'HSN Item Source'],
            'HSNOVRDNCLASSIFICATION' => ['HSN/SAC Classification', 'HSN Ovrdn Classification'],
            'BUYERGSTIN' => ['Buyer/Supplier – GSTIN/UIN', 'Buyer/Supplier – GST Number'],
            'ISBOENOTAPPLICABLE' => ['Buyer/Supplier – Is Bill of Entry available', 'Buyer/Supplier – Is BOE Not Applicable'],
            'ISGSTSECSEVENAPPLICABLE' => ['Buyer/Supplier – Supplies under section 7 of IGST Act', 'Buyer/Supplier – Is GST Sec Seven Applicable'],
            'BUYERPLACEOFSUPPLYCOUNTRY' => ['Buyer/Supplier – Country (POS)', 'Buyer/Supplier – Place Of Supply Country'],
            'BUYERGSTISOTHTERRITORYASSESSEE' => ['Buyer/Supplier – Assessee of Other Territory', 'Buyer/Supplier – Party GST Is Other Territory Assessee'],
            'BASICBUYERNAME' => ['Consignee (ship to)', 'Basic Buyer Name'],
            'CONSIGNEEADDRESSTYPE' => ['Consignee – Address Type', 'Consignee – Buyer Address Type'],
            'BASICBUYERADDRESS' => ['Consignee – Mailing Name', 'Consignee – Basic Buyer Address'],
            'CONSIGNEEGSTIN' => ['Consignee – GSTIN/UIN', 'Consignee – GST Number'],
            'ADDITIONALNARRATION' => ['Nature of Processing', 'Additional Narration'],
            'STATPAYMENTTYPE' => ['Stat Adjustment (GST) – Type of Duty/Tax', 'Stat Payment Type'],
            'TAXADJUSTMENT' => ['Stat Adjustment (GST) – Nature of Adjustment', 'Tax Adjustment'],
            'GSTADDITIONALDETAILS' => ['Stat Adjustment (GST) – Additional Nature of Adjustment', 'GST Additional Details'],
            'STATADJUSTMENT.GSTTAXRATE' => ['Stat Adjustment (GST) – Rate', 'Stat Adjustment – GST Tax Rate'],
            'STATADJUSTMENT.GSTASSESSABLEVALUE' => ['Stat Adjustment (GST) – Taxable Value', 'Stat Adjustment – GST Assessable Value'],
            'STATADJUSTMENT.ISDDOCUMENTNUMBER' => ['Stat Adjustment (GST) – ISD Invoice/Debit/Credit Note No.', 'Stat Adjustment – ISD Document Number'],
            'STATADJUSTMENT.ISDDOCUMENTDATE' => ['Stat Adjustment (GST) – ISD Invoice/Debit/Credit Note Date', 'Stat Adjustment – ISD Document Date'],
            'STATADJUSTMENT.ISELIGIBLEFORITC' => ['Stat Adjustment (GST) – Eligible for Input Tax Credit', 'Stat Adjustment – Is Eligible for ITC'],
            'STATADJUSTMENT.GSTITCDOCUMENTTYPE' => ['Stat Adjustment (GST) – Type of Supply', 'Stat Adjustment – GSTITCDocumentType'],
            'GSTTYPEOFSUPPLY' => ['Type of Supply', 'GST Type of Supply'],
            'VCHENTRYMODE' => ['Change Mode', 'Vch Entry Mode', 'Voucher Mode'],
            'ISOPTIONAL' => ['Optional', 'Is Optional'],
            'ISPOSTDATED' => ['Post-Dated', 'Is Post Dated'],
            'ISCANCELLED' => ['Cancelled', 'Is Cancelled'],
            'BANKALLOCATIONS.TRANSACTIONNAME' => ['Bank Allocations – Ref ID', 'Bank Allocations – Transaction Name'],
            'BANKALLOCATIONS.ACCOUNTNUMBER' => ['Bank Allocations – A/c No.', 'Bank Allocations – Account Number', 'Bank Allocations – Account No.'],
            'BANKALLOCATIONS.INSTRUMENTNUMBER' => ['Bank Allocations – Inst No.', 'Bank Allocations – Instrument Number'],
            'BANKALLOCATIONS.PAYMENTFAVOURING' => ['Bank Allocations – Favouring Name', 'Bank Allocations – Payment Favouring'],
            'BANKALLOCATIONS.INSTRUMENTDATE' => ['Bank Allocations – Inst Date', 'Bank Allocations – Instrument Date'],
            'BANKALLOCATIONS.CHEQUECROSSCOMMENT' => ['Bank Allocations – Cross using', 'Bank Allocations – Cheque Cross Comment'],
            'BANKALLOCATIONS.NARRATION' => ['Bank Allocations – Remarks', 'Bank Allocations – Narration'],
            'BANKALLOCATIONS.BANKPARTYNAME' => ['Bank Allocations – Ledger Name', 'Bank Allocations – Bank Party Name'],
            'BANKALLOCATIONS.PDCACTUALDATE' => ['Bank Allocations – PDC Issue Date', 'Bank Allocations – Post-Dated Cheque Issue Date', 'PDC Actual Date'],
            'BANKALLOCATIONS.PDCREMARKS' => ['Bank Allocations – PDC Note', 'Bank Allocations – Post-Dated Cheque Note', 'PDC Remarks'],
            'POSDETAILS.POSCARDLEDGER' => ['POS Details – Credit/Debit Card', 'POS Details – POS Card Ledger'],
            'POSDETAILS.POSCARDLEDGERAMOUNT' => ['POS Details – Credit/Debit Card Amount', 'POS Details – POS Card Ledger Amount'],
            'POSDETAILS.POSCASHRECEIVED' => ['POS Details – Cash tendered', 'POS Details – POS Cash Received'],
            'ADVANCEREFUND.AMOUNT' => ['Advance Payment/Receipt/Refund Details – Taxable Value', 'Advance Payment/Receipt/Refund Details – Amount'],
            'TAXTYPEALLOCATIONS.SUBTYPE' => ['Tax Type Allocations Additional Details – Nature of Payment', 'Tax Type Allocations Additional Details – Subtype'],
            'TAXTYPEALLOCATIONS.GSTCESSLIABILITY' => ['Tax Type Allocations – Cess Liability', 'Tax Type Allocations – GST Cess Liability'],
            'GSTADVANCEDATE' => ['GST Advance Details – Month Year', 'GST Advance Details – GST Advance Date'],
            'TDSPARTY.CASHPARTYDEDTYPE' => ['TDS Party Details – Deductee Type', 'TDS Party Details – Cash Party Ded Type'],
            'TDSPARTY.CASHPARTYPAN' => ['TDS Party Details – PAN Number', 'TDS Party Details – Cash Party PAN'],
            'TDSBILL.CATEGORY' => ['TDS Bill Allocations – TDS Nature of Payment', 'TDS Bill Allocations – Category'],
            'TDSBILL.TDSTAXOBJASSBVALUE' => ['TDS Bill Allocations – Assessable Amount', 'TDS Bill Allocations – TDS Tax Obj Assb Value'],
            'TDSBILL.TDSDEDUCTAMT' => ['TDS Bill Allocations – Paid Amount', 'TDS Bill Allocations – TDS Deduct Amt'],
            'TCSPARTY.CASHPARTYDEDTYPE' => ['TCS Party Details – Collectee Type', 'TCS Party Details – Cash Party Ded Type'],
            'TCSPARTY.CASHPARTYPAN' => ['TCS Party Details – PAN Number', 'TCS Party Details – Cash Party PAN'],
            'STATPAYMENTGST.TAXPAYMENTTYPE' => ['Stat Payment (GST) – Type of Payment', 'Stat Payment (GST) – Tax Payment Type'],
            'STATPAYMENTGST.TAXPAYPERIODFROMDATE' => ['Stat Payment (GST) – Period From', 'Stat Payment (GST) – Tax Pay Period From Date'],
            'STATPAYMENTGST.TAXPAYPERIODTODATE' => ['Stat Payment (GST) – Period To', 'Stat Payment (GST) – Tax Pay Period To Date'],
            'STATPAYMENTTDS.TAXPAYPERIODFROMDATE' => ['Stat Payment (TDS) – Period From', 'Stat Payment (TDS) – Tax Pay Period From Date'],
            'STATPAYMENTTDS.TAXPAYPERIODTODATE' => ['Stat Payment (TDS) – Period To', 'Stat Payment (TDS) – Tax Pay Period To Date'],
            'STATPAYMENTTCS.TAXPAYPERIODFROMDATE' => ['Stat Payment (TCS) – Period From', 'Stat Payment (TCS) – Tax Pay Period From Date'],
            'STATPAYMENTTCS.TAXPAYPERIODTODATE' => ['Stat Payment (TCS) – Period To', 'Stat Payment (TCS) – Tax Pay Period To Date'],
            'LEDGERGSTHSNINFERAPPLICABILITY' => ['Ledger – HSN/SAC Details', 'Ledger – GST HSN Infer Applicability'],
            'ITEMGSTHSNINFERAPPLICABILITY' => ['Item – HSN/SAC Details', 'Item – GST HSN Infer Applicability'],
            'INVENTORYAPPROVALNUMBER' => ['Withheld Certificate No.', 'Inventory Approval Number'],
        ],
    ];

    /**
     * Normalise an input payload to canonical XML tags.
     *
     * Keys that are already canonical pass through untouched. Aliases are
     * translated case-insensitively and whitespace-insensitively. Unknown keys
     * pass through as-is so callers can still send custom UDF / rare fields we
     * haven't mapped yet.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function canonicalize(string $entity, array $data): array
    {
        $lookup = self::aliasLookup($entity);
        if (empty($lookup)) {
            return $data;
        }

        $out = [];
        foreach ($data as $key => $value) {
            $canon = $lookup[self::normaliseKey((string) $key)] ?? $key;
            $out[$canon] = $value;
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    public static function canonicalFields(string $entity): array
    {
        return array_keys(self::MAP[$entity] ?? []);
    }

    /**
     * @return array<int, string>
     */
    public static function aliasesFor(string $entity, string $canonical): array
    {
        return self::MAP[$entity][$canonical] ?? [];
    }

    /**
     * Build a lookup table of normalised-alias → canonical-field for one entity.
     * Cached per-call via memoization.
     *
     * @return array<string, string>
     */
    private static function aliasLookup(string $entity): array
    {
        static $cache = [];
        if (isset($cache[$entity])) {
            return $cache[$entity];
        }

        $out = [];
        foreach (self::MAP[$entity] ?? [] as $canonical => $aliases) {
            $out[self::normaliseKey($canonical)] = $canonical;
            foreach ($aliases as $alias) {
                $out[self::normaliseKey($alias)] = $canonical;
            }
        }

        return $cache[$entity] = $out;
    }

    /**
     * Normalise a key for lookup: lowercase, strip all non-alphanumeric.
     * Makes `Parent Name`, `parent_name`, `PARENTNAME`, `Parent-Name` all match.
     */
    private static function normaliseKey(string $key): string
    {
        return strtolower(preg_replace('/[^a-z0-9]/i', '', $key) ?? '');
    }
}
