<?php

namespace App\Services\Tally;

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
        $company = $company ?? config('tally.company');

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
     */
    public static function buildCollectionExportRequest(
        string $collectionType,
        array $fetchFields = [],
        array $filters = [],
        ?string $company = null,
    ): string {
        $company = $company ?? config('tally.company');

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
     * Build a single-object export request (e.g., get one Ledger by name).
     * Uses TYPE=OBJECT with SUBTYPE for the object type.
     */
    public static function buildObjectExportRequest(
        string $objectType,
        string $objectName,
        array $fetchFields = [],
        ?string $company = null,
    ): string {
        $company = $company ?? config('tally.company');

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
        $xml .= '<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>';

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
     * Build an import request for creating/altering master objects.
     * Matches official format: Import > Data > ID in header.
     */
    public static function buildImportMasterRequest(
        string $objectType,
        array $objectData,
        string $action = 'Create',
        ?string $company = null,
    ): string {
        $company = $company ?? config('tally.company');

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
        $company = $company ?? config('tally.company');

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
        $xml .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">';

        foreach ($vouchers as $voucherData) {
            $xml .= '<VOUCHER ACTION="'.$action.'">';
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
        $company = $company ?? config('tally.company');

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
        $company = $company ?? config('tally.company');

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
