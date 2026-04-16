<?php

namespace App\Services\Tally;

class TallyXmlBuilder
{
    /**
     * Build an Export Data request envelope.
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
        $xml .= '<TALLYREQUEST>Export Data</TALLYREQUEST>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<EXPORTDATA>';
        $xml .= '<REQUESTDESC>';
        $xml .= "<REPORTNAME>{$reportName}</REPORTNAME>";

        if ($company) {
            $xml .= '<STATICVARIABLES>';
            $xml .= "<SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY>";

            foreach ($filters as $key => $value) {
                $xml .= "<{$key}>{$value}</{$key}>";
            }

            $xml .= '</STATICVARIABLES>';
        } elseif (! empty($filters)) {
            $xml .= '<STATICVARIABLES>';

            foreach ($filters as $key => $value) {
                $xml .= "<{$key}>{$value}</{$key}>";
            }

            $xml .= '</STATICVARIABLES>';
        }

        if (! empty($fetchFields)) {
            $xml .= '<DESC>';

            foreach ($fetchFields as $field) {
                $xml .= "<FIELD>{$field}</FIELD>";
            }

            $xml .= '</DESC>';
        }

        $xml .= '</REQUESTDESC>';
        $xml .= '</EXPORTDATA>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build an Import Data request envelope for creating/altering masters.
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
        $xml .= '<TALLYREQUEST>Import Data</TALLYREQUEST>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<IMPORTDATA>';
        $xml .= '<REQUESTDESC>';

        if ($company) {
            $xml .= '<STATICVARIABLES>';
            $xml .= "<SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY>";
            $xml .= '</STATICVARIABLES>';
        }

        $xml .= '<REPORTNAME>All Masters</REPORTNAME>';
        $xml .= '</REQUESTDESC>';
        $xml .= '<REQUESTDATA>';
        $xml .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">';
        $xml .= "<{$objectType} NAME=\"".self::escapeXml($objectData['NAME'] ?? '').'" ACTION="'.$action.'">';
        $xml .= self::arrayToXml($objectData);
        $xml .= "</{$objectType}>";
        $xml .= '</TALLYMESSAGE>';
        $xml .= '</REQUESTDATA>';
        $xml .= '</IMPORTDATA>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Build an Import Data request envelope for creating/altering vouchers.
     */
    public static function buildImportVoucherRequest(
        array $voucherData,
        string $action = 'Create',
        ?string $company = null,
    ): string {
        $company = $company ?? config('tally.company');

        $xml = '<ENVELOPE>';
        $xml .= '<HEADER>';
        $xml .= '<TALLYREQUEST>Import Data</TALLYREQUEST>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<IMPORTDATA>';
        $xml .= '<REQUESTDESC>';

        if ($company) {
            $xml .= '<STATICVARIABLES>';
            $xml .= "<SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY>";
            $xml .= '</STATICVARIABLES>';
        }

        $xml .= '<REPORTNAME>Vouchers</REPORTNAME>';
        $xml .= '</REQUESTDESC>';
        $xml .= '<REQUESTDATA>';
        $xml .= '<TALLYMESSAGE xmlns:UDF="TallyUDF">';

        $voucherType = $voucherData['VCHTYPE'] ?? '';
        $xml .= '<VOUCHER VCHTYPE="'.self::escapeXml($voucherType).'" ACTION="'.$action.'">';
        $xml .= self::arrayToXml($voucherData);
        $xml .= '</VOUCHER>';

        $xml .= '</TALLYMESSAGE>';
        $xml .= '</REQUESTDATA>';
        $xml .= '</IMPORTDATA>';
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
     * Build a Delete request for a voucher.
     */
    public static function buildDeleteVoucherRequest(
        string $masterID,
        string $voucherType,
        ?string $company = null,
    ): string {
        return self::buildImportVoucherRequest(
            [
                'VCHTYPE' => $voucherType,
                'MASTERID' => $masterID,
            ],
            'Delete',
            $company,
        );
    }

    /**
     * Build a collection export request (e.g., list of ledgers, stock items).
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
        $xml .= '<TALLYREQUEST>Export Data</TALLYREQUEST>';
        $xml .= '</HEADER>';
        $xml .= '<BODY>';
        $xml .= '<EXPORTDATA>';
        $xml .= '<REQUESTDESC>';
        $xml .= '<STATICVARIABLES>';

        if ($company) {
            $xml .= "<SVCURRENTCOMPANY>{$company}</SVCURRENTCOMPANY>";
        }

        $xml .= '<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>';

        foreach ($filters as $key => $value) {
            $xml .= "<{$key}>{$value}</{$key}>";
        }

        $xml .= '</STATICVARIABLES>';
        $xml .= "<REPORTNAME>{$collectionType}</REPORTNAME>";
        $xml .= '</REQUESTDESC>';
        $xml .= '</EXPORTDATA>';
        $xml .= '</BODY>';
        $xml .= '</ENVELOPE>';

        return $xml;
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
