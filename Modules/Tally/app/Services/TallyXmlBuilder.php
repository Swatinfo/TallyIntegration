<?php

namespace Modules\Tally\Services;

class TallyXmlBuilder
{
    /**
     * Build a report export request (Balance Sheet, Trial Balance, P&L, Day Book, etc.)
     * Uses TYPE=Data with report ID in header.
     */
    public static function buildExportRequest(
        string $reportName,
        array $fetchFields = [],
        array $filters = [],
        ?string $company = null,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Export</TALLYREQUEST>';
        $xml .= '<TYPE>Data</TYPE>';
        $xml .= '<ID>'.self::escapeXml($reportName).'</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';
        $xml .= '<EXPLODEFLAG>Yes</EXPLODEFLAG>';
        $xml .= '<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        foreach ($filters as $key => $value) {
            $xml .= "<{$key}>".self::escapeXml((string) $value)."</{$key}>";
        }

        $xml .= '</STATICVARIABLES>';

        if (! empty($fetchFields)) {
            $xml .= '<FETCHLIST>';
            foreach ($fetchFields as $field) {
                $xml .= '<FETCH>'.self::escapeXml($field).'</FETCH>';
            }
            $xml .= '</FETCHLIST>';
        }

        $xml .= '</DESC>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build a collection export request (list of ledgers, stock items, groups, etc.)
     * Uses TYPE=Collection with collection name as ID.
     *
     * Pass $explode=false for collections that reference other rows recursively
     * (e.g. `List of Units` with compound units) — otherwise TallyPrime can
     * segfault while inlining the referenced objects.
     */
    public static function buildCollectionExportRequest(
        string $collectionType,
        array $fetchFields = [],
        array $filters = [],
        ?string $company = null,
        bool $explode = true,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Export</TALLYREQUEST>';
        $xml .= '<TYPE>Collection</TYPE>';
        $xml .= '<ID>'.self::escapeXml($collectionType).'</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';
        $xml .= '<EXPLODEFLAG>'.($explode ? 'Yes' : 'No').'</EXPLODEFLAG>';
        $xml .= '<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        foreach ($filters as $key => $value) {
            $xml .= "<{$key}>".self::escapeXml((string) $value)."</{$key}>";
        }

        $xml .= '</STATICVARIABLES>';

        if (! empty($fetchFields)) {
            $xml .= '<FETCHLIST>';
            foreach ($fetchFields as $field) {
                $xml .= '<FETCH>'.self::escapeXml($field).'</FETCH>';
            }
            $xml .= '</FETCHLIST>';
        }

        $xml .= '</DESC>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build a Collection export that defines the collection inline via TDL injection.
     *
     * Use this when Tally has no built-in collection for a master type — e.g.
     * `List of Units` is NOT a built-in TallyPrime collection (TallyPrime returns
     * `Error in TDL, 'Collection: List of Units' Could not find description` and
     * blocks the HTTP responder behind a UI dialog). The inline `<TDL><TDLMESSAGE>`
     * block defines an ad-hoc collection of the given Tally type, then the request
     * exports it.
     *
     * @param  string  $collectionName  Arbitrary unique name; the request uses this as <ID>
     * @param  string  $tallyType  Tally TDL object type — use the **concatenated**
     *                             form for multi-word masters per production TDL
     *                             integrations (laxmantandon/tally_migration_tdl
     *                             `send/*.txt` uses `Type : StockItem`, `Type :
     *                             StockGroup`). Object SUBTYPEs use spaces; TDL
     *                             `<TYPE>` uses concatenated. Canonical values:
     *                             `Unit`, `Currency`, `Godown`, `Ledger`, `Group`,
     *                             `CostCentre`, `VoucherType`, `StockCategory`,
     *                             `PriceLevel`, `StockItem`, `StockGroup`.
     * @param  array<string>  $fetchFields  Fields to include (omit for all)
     */
    public static function buildAdHocCollectionExportRequest(
        string $collectionName,
        string $tallyType,
        array $fetchFields = [],
        ?string $company = null,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();

        // Per Tally docs Sample 16, inline <COLLECTION> definitions declare
        // fields via one <NATIVEMETHOD> per field. The single comma-separated
        // <FETCH> form crashes TallyPrime when one of the fields isn't a valid
        // TDL method on the type (reproduced 2026-04-19 on Price Level with
        // <FETCH>NAME, USEFORGROUPS</FETCH> — connection reset after 6s).
        // NATIVEMETHOD is silently tolerated for unknown field names.
        $methodTags = '';
        foreach ($fetchFields as $field) {
            $methodTags .= '<NATIVEMETHOD>'.self::escapeXml($field).'</NATIVEMETHOD>';
        }

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Export</TALLYREQUEST>';
        $xml .= '<TYPE>Collection</TYPE>';
        $xml .= '<ID>'.self::escapeXml($collectionName).'</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';
        $xml .= '<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        $xml .= '</STATICVARIABLES>';
        $xml .= '<TDL>';
        $xml .= '<TDLMESSAGE>';
        $xml .= '<COLLECTION NAME="'.self::escapeXml($collectionName).'" ISMODIFY="No">';
        $xml .= '<TYPE>'.self::escapeXml($tallyType).'</TYPE>';
        $xml .= $methodTags;
        $xml .= '</COLLECTION>';
        $xml .= '</TDLMESSAGE>';
        $xml .= '</TDL>';
        $xml .= '</DESC>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build a single-object export request (e.g., get one Ledger by name).
     * Uses TYPE=Object with SUBTYPE. Per official samples, uses BinaryXML format.
     */
    public static function buildObjectExportRequest(
        string $objectType,
        string $objectName,
        array $fetchFields = [],
        ?string $company = null,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Export</TALLYREQUEST>';
        $xml .= '<TYPE>Object</TYPE>';
        $xml .= '<SUBTYPE>'.self::escapeXml($objectType).'</SUBTYPE>';
        $xml .= '<ID TYPE="Name">'.self::escapeXml($objectName).'</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';
        $xml .= '<SVEXPORTFORMAT>'.self::resolveObjectExportFormat().'</SVEXPORTFORMAT>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        $xml .= '</STATICVARIABLES>';

        if (! empty($fetchFields)) {
            $xml .= '<FETCHLIST>';
            foreach ($fetchFields as $field) {
                $xml .= '<FETCH>'.self::escapeXml($field).'</FETCH>';
            }
            $xml .= '</FETCHLIST>';
        }

        $xml .= '</DESC>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Resolve the export format for Object-type exports. Defaults to the
     * BinaryXML per Demo Samples; admins can flip to $$SysName:XML via
     * TALLY_OBJECT_EXPORT_FORMAT=SysName when BinaryXML crashes Tally
     * mid-response on specific subtypes.
     */
    /**
     * Resolve which company to pin via <SVCURRENTCOMPANY>.
     *
     * Per-connection requests have a request-scoped TallyHttpClient rebound
     * in the container by ResolveTallyConnection middleware — its company is
     * the correct pin (one Tally can host several). Out-of-request callers
     * and unit tests fall back to the module config default.
     *
     * Callers that intentionally want to suppress the pin (e.g. the global
     * List-of-Companies export) should pass company: '' — that short-circuits
     * this resolver entirely because '' !== null.
     */
    private static function resolveDefaultCompany(): string
    {
        try {
            $client = app(TallyHttpClient::class);
            $company = $client->getCompany();
            if ($company !== '') {
                return $company;
            }
        } catch (\Throwable) {
            // No app container (unit test) or no client bound — fall through.
        }

        try {
            return (string) config('tally.company', '');
        } catch (\Throwable) {
            return '';
        }
    }

    private static function resolveObjectExportFormat(): string
    {
        try {
            $format = config('tally.object_export_format', 'BinaryXML');
        } catch (\Throwable) {
            $format = 'BinaryXML';
        }

        return strcasecmp((string) $format, 'SysName') === 0 ? '$$SysName:XML' : 'BinaryXML';
    }

    /**
     * Build an import request for creating/altering master objects.
     * Matches official format: Import > Data > ID in header.
     */
    public static function buildImportMasterRequest(
        string $objectType,
        array $objectData,
        string $action = 'Create',
        ?string $company = null,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Import</TALLYREQUEST>';
        $xml .= '<TYPE>Data</TYPE>';
        $xml .= '<ID>All Masters</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        $xml .= '</STATICVARIABLES>';
        $xml .= '</DESC>';
        $xml .= '<DATA>';
        $xml .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">';
        $xml .= "<{$objectType} NAME=\"".self::escapeXml($objectData['NAME'] ?? '').'" ACTION="'.$action.'">';
        $xml .= self::arrayToXml($objectData);
        $xml .= "</{$objectType}>";
        $xml .= '</TALLYMESSAGE>';
        $xml .= '</DATA>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Inject WebStatus UDF markers into master/voucher data.
     *
     * Pattern borrowed from laxmantandon/tally_migration_tdl — masters/vouchers
     * synced from an external system carry three UDFs (`WebStatus`, `WebStatus_Message`,
     * `WebStatus_DocName`) so the accountant can see sync state inside Tally itself,
     * and an Exception Report TDL can surface everything that didn't sync cleanly.
     *
     * The companion TDL file `Modules/Tally/scripts/tdl/TallyModuleIntegration.txt`
     * declares these UDFs in Tally; without it the UDF tags are silently ignored,
     * so sending them is safe even without the TDL installed.
     *
     * @param  array<string, mixed>  $data  Master/voucher payload to augment.
     * @return array<string, mixed> The same array with UDF children appended.
     */
    public static function withWebStatus(array $data, string $status, ?string $message = null, ?string $docName = null): array
    {
        $data['UDF:WEBSTATUS.LIST'] = ['UDF:WEBSTATUS' => $status];
        if ($message !== null) {
            $data['UDF:WEBSTATUS_MESSAGE.LIST'] = ['UDF:WEBSTATUS_MESSAGE' => $message];
        }
        if ($docName !== null) {
            $data['UDF:WEBSTATUS_DOCNAME.LIST'] = ['UDF:WEBSTATUS_DOCNAME' => $docName];
        }

        return $data;
    }

    /**
     * Build an ALTER request against the Company master.
     *
     * Used for data that lives on the Company object rather than as a standalone
     * master — e.g. Price Level names (Company.PRICELEVELLIST), which Tally does
     * not expose as a separately-importable PRICELEVEL master. Two independent
     * reference integrations (laxmantandon/express_tally, aadil-sengupta/Tally.Py)
     * both skip price levels for this reason.
     *
     * @param  array<string, mixed>  $companyData  The sub-fields to alter; NAME is injected.
     */
    public static function buildCompanyAlterRequest(
        string $companyName,
        array $companyData,
        ?string $company = null,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();
        $companyData = array_merge(['NAME' => $companyName], $companyData);

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Import</TALLYREQUEST>';
        $xml .= '<TYPE>Data</TYPE>';
        $xml .= '<ID>All Masters</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        $xml .= '</STATICVARIABLES>';
        $xml .= '</DESC>';
        $xml .= '<DATA>';
        $xml .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">';
        $xml .= '<COMPANY NAME="'.self::escapeXml($companyName).'" ACTION="Alter">';
        $xml .= self::arrayToXml($companyData);
        $xml .= '</COMPANY>';
        $xml .= '</TALLYMESSAGE>';
        $xml .= '</DATA>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build an import request for creating/altering a single voucher.
     */
    public static function buildImportVoucherRequest(
        array $voucherData,
        string $action = 'Create',
        ?string $company = null,
    ): string {
        return self::buildBatchImportVoucherRequest([$voucherData], $action, $company);
    }

    /**
     * Build a batch import request for multiple vouchers in one request.
     *
     * @param  array<array>  $vouchers  Array of voucher data arrays
     */
    public static function buildBatchImportVoucherRequest(
        array $vouchers,
        string $action = 'Create',
        ?string $company = null,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Import</TALLYREQUEST>';
        $xml .= '<TYPE>Data</TYPE>';
        $xml .= '<ID>Vouchers</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        $xml .= '<IMPORTDUPS>@@DUPCOMBINE</IMPORTDUPS>';
        $xml .= '</STATICVARIABLES>';
        $xml .= '</DESC>';
        $xml .= '<DATA>';
        $xml .= '<TALLYMESSAGE>';

        foreach ($vouchers as $voucherData) {
            // Per official samples: bare <VOUCHER> for Create, ACTION attribute only for Alter
            if ($action === 'Create') {
                $xml .= '<VOUCHER>';
            } else {
                $xml .= '<VOUCHER ACTION="'.$action.'">';
            }
            $xml .= self::arrayToXml($voucherData);
            $xml .= '</VOUCHER>';
        }

        $xml .= '</TALLYMESSAGE>';
        $xml .= '</DATA>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build a voucher cancellation request.
     * Uses attribute-based format per official samples.
     */
    public static function buildCancelVoucherRequest(
        string $date,
        string $voucherNumber,
        string $voucherType,
        ?string $narration = null,
        ?string $company = null,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Import</TALLYREQUEST>';
        $xml .= '<TYPE>Data</TYPE>';
        $xml .= '<ID>Vouchers</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        $xml .= '</STATICVARIABLES>';
        $xml .= '</DESC>';
        $xml .= '<DATA>';
        $xml .= '<TALLYMESSAGE>';
        $xml .= '<VOUCHER DATE="'.self::escapeXml($date).'"';
        // Cancel uses compressed `VoucherNumber` per the canonical Tally sample-xml
        // page (Sample 13). Alter/Delete keep the spaced `Voucher Number` form.
        $xml .= ' TAGNAME="VoucherNumber"';
        $xml .= ' TAGVALUE="'.self::escapeXml($voucherNumber).'"';
        $xml .= ' VCHTYPE="'.self::escapeXml($voucherType).'"';
        $xml .= ' ACTION="Cancel">';

        if ($narration) {
            $xml .= '<NARRATION>'.self::escapeXml($narration).'</NARRATION>';
        }

        $xml .= '</VOUCHER>';
        $xml .= '</TALLYMESSAGE>';
        $xml .= '</DATA>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build a voucher deletion request.
     * Uses attribute-based format per official samples.
     */
    public static function buildDeleteVoucherRequest(
        string $date,
        string $voucherNumber,
        string $voucherType,
        ?string $company = null,
    ): string {
        $company = $company ?? self::resolveDefaultCompany();

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Import</TALLYREQUEST>';
        $xml .= '<TYPE>Data</TYPE>';
        $xml .= '<ID>Vouchers</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';

        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }

        $xml .= '</STATICVARIABLES>';
        $xml .= '</DESC>';
        $xml .= '<DATA>';
        $xml .= '<TALLYMESSAGE>';
        $xml .= '<VOUCHER DATE="'.self::escapeXml($date).'"';
        $xml .= ' TAGNAME="Voucher Number"';
        $xml .= ' TAGVALUE="'.self::escapeXml($voucherNumber).'"';
        $xml .= ' VCHTYPE="'.self::escapeXml($voucherType).'"';
        $xml .= ' ACTION="Delete">';
        $xml .= '</VOUCHER>';
        $xml .= '</TALLYMESSAGE>';
        $xml .= '</DATA>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build a Function export request to invoke Tally built-in functions.
     * Uses TYPE=Function. Examples: $$SystemPeriodFrom, $$SystemPeriodTo, $$NumStockItems
     */
    public static function buildFunctionExportRequest(
        string $functionName,
        array $params = [],
    ): string {
        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Export</TALLYREQUEST>';
        $xml .= '<TYPE>Function</TYPE>';
        $xml .= '<ID>'.self::escapeXml($functionName).'</ID>';
        $xml .= '</HEADER>';

        if (! empty($params)) {
            $xml .= '<BODY>';
            $xml .= '<DESC>';
            $xml .= '<FUNCPARAMLIST>';
            foreach ($params as $param) {
                $xml .= '<PARAM>'.self::escapeXml((string) $param).'</PARAM>';
            }
            $xml .= '</FUNCPARAMLIST>';
            $xml .= '</DESC>';
            $xml .= '</BODY>';
        }

        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build a TDL-based report to query company AlterIDs for incremental sync.
     * Returns AltMstId (last master alter ID) and AltVchId (last voucher alter ID).
     */
    public static function buildAlterIdQueryRequest(?string $company = null): string
    {
        $company = $company ?? self::resolveDefaultCompany();

        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<VERSION>1</VERSION>';
        $xml .= '<TALLYREQUEST>Export</TALLYREQUEST>';
        $xml .= '<TYPE>Data</TYPE>';
        $xml .= '<ID>TallySyncReport</ID>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<DESC>';
        $xml .= '<STATICVARIABLES>';
        $xml .= '<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>';
        if ($company) {
            $xml .= '<SVCURRENTCOMPANY>'.self::escapeXml($company).'</SVCURRENTCOMPANY>';
        }
        $xml .= '</STATICVARIABLES>';
        $xml .= '<TDL>';
        $xml .= '<TDLMESSAGE>';
        $xml .= '<REPORT NAME="TallySyncReport"><FORMS>TallySyncForm</FORMS></REPORT>';
        $xml .= '<FORM NAME="TallySyncForm"><PARTS>TallySyncPart</PARTS></FORM>';
        $xml .= '<PART NAME="TallySyncPart"><LINES>TallySyncLine</LINES>';
        $xml .= '<REPEAT>TallySyncLine : TallySyncCollection</REPEAT>';
        $xml .= '<SCROLLED>Vertical</SCROLLED></PART>';
        $xml .= '<LINE NAME="TallySyncLine"><FIELDS>FldAltMstId,FldAltVchId</FIELDS></LINE>';
        $xml .= '<FIELD NAME="FldAltMstId"><SET>$AltMstId</SET><XMLTAG>ALTMSTID</XMLTAG></FIELD>';
        $xml .= '<FIELD NAME="FldAltVchId"><SET>$AltVchId</SET><XMLTAG>ALTVCHID</XMLTAG></FIELD>';
        $xml .= '<COLLECTION NAME="TallySyncCollection"><TYPE>Company</TYPE>';
        $xml .= '<FILTER>TallySyncFilter</FILTER></COLLECTION>';
        $xml .= '<SYSTEM TYPE="Formulae" NAME="TallySyncFilter">$$IsEqual:##SVCurrentCompany:$Name</SYSTEM>';
        $xml .= '</TDLMESSAGE>';
        $xml .= '</TDL>';
        $xml .= '</DESC>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build a Delete request for a master object.
     */
    public static function buildDeleteMasterRequest(
        string $objectType,
        string $name,
        ?string $company = null,
    ): string {
        return self::buildImportMasterRequest(
            $objectType,
            ['NAME' => $name],
            'Delete',
            $company,
        );
    }

    /**
     * Convert a PHP array to XML tags. Handles nested arrays for line items.
     */
    public static function arrayToXml(array $data): string
    {
        $xml = '';

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (self::isAssociativeArray($value)) {
                    $xml .= "<{$key}>";
                    $xml .= self::arrayToXml($value);
                    $xml .= "</{$key}>";
                } else {
                    // Sequential array — repeated elements (e.g., ALLLEDGERENTRIES.LIST)
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $xml .= "<{$key}>";
                            $xml .= self::arrayToXml($item);
                            $xml .= "</{$key}>";
                        } else {
                            $xml .= "<{$key}>".self::escapeXml((string) $item)."</{$key}>";
                        }
                    }
                }
            } else {
                $xml .= "<{$key}>".self::escapeXml((string) $value)."</{$key}>";
            }
        }

        return $xml;
    }

    public static function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function isAssociativeArray(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
