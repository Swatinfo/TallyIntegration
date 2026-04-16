<?php

namespace App\Services\Tally;

use RuntimeException;
use SimpleXMLElement;

class TallyXmlParser
{
    /**
     * Parse a Tally XML response into a PHP array.
     */
    public static function parse(string $xml): array
    {
        $xml = self::sanitizeXml($xml);

        $element = @simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

        if ($element === false) {
            throw new RuntimeException('Failed to parse TallyPrime XML response: '.self::getXmlErrors());
        }

        return self::xmlToArray($element);
    }

    /**
     * Extract the import result (CREATED, ALTERED, ERRORS) from a Tally import response.
     *
     * Response format (from official samples):
     * <ENVELOPE><HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER>
     *   <BODY><DATA><IMPORTRESULT><CREATED>2</CREATED>...</IMPORTRESULT></DATA></BODY>
     * </ENVELOPE>
     */
    public static function parseImportResult(string $xml): array
    {
        $data = self::parse($xml);

        // Official response path: BODY > DATA > IMPORTRESULT
        $result = $data['BODY']['DATA']['IMPORTRESULT'] ?? [];

        return [
            'created' => (int) ($result['CREATED'] ?? 0),
            'altered' => (int) ($result['ALTERED'] ?? 0),
            'deleted' => (int) ($result['DELETED'] ?? 0),
            'lastvchid' => $result['LASTVCHID'] ?? null,
            'lastmid' => $result['LASTMID'] ?? null,
            'combined' => (int) ($result['COMBINED'] ?? 0),
            'ignored' => (int) ($result['IGNORED'] ?? 0),
            'errors' => (int) ($result['ERRORS'] ?? 0),
            'cancelled' => (int) ($result['CANCELLED'] ?? 0),
        ];
    }

    /**
     * Check if an import was successful (no errors).
     */
    public static function isImportSuccessful(string $xml): bool
    {
        $result = self::parseImportResult($xml);

        return $result['errors'] === 0
            && ($result['created'] > 0 || $result['altered'] > 0 || $result['deleted'] > 0 || $result['combined'] > 0);
    }

    /**
     * Extract a collection of objects from a Tally collection export response.
     *
     * Response format (from official samples):
     * <ENVELOPE><HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER>
     *   <BODY><DESC></DESC><DATA><COLLECTION>
     *     <LEDGER NAME="...">...</LEDGER>
     *     <LEDGER NAME="...">...</LEDGER>
     *   </COLLECTION></DATA></BODY>
     * </ENVELOPE>
     */
    public static function extractCollection(string $xml, string $objectType): array
    {
        $data = self::parse($xml);

        // Path 1: BODY > DATA > COLLECTION > TYPE (collection export)
        $objects = $data['BODY']['DATA']['COLLECTION'][$objectType] ?? null;

        // Path 2: BODY > DATA > TALLYMESSAGE > TYPE (some report exports)
        if ($objects === null) {
            $objects = $data['BODY']['DATA']['TALLYMESSAGE'][$objectType] ?? null;
        }

        // Path 3: Direct data without COLLECTION wrapper
        if ($objects === null) {
            $objects = $data['BODY']['DATA'][$objectType] ?? [];
        }

        if (empty($objects)) {
            return [];
        }

        // If single object, wrap in array for consistent return
        if (isset($objects['@attributes']) || isset($objects['NAME'])) {
            $objects = [$objects];
        }

        return $objects;
    }

    /**
     * Extract a single object from a Tally OBJECT export response.
     *
     * Response format (from official samples):
     * <ENVELOPE><HEADER>...</HEADER><BODY><DESC></DESC><DATA>
     *   <TALLYMESSAGE><LEDGER NAME="Cash">...</LEDGER></TALLYMESSAGE>
     * </DATA></BODY></ENVELOPE>
     */
    public static function extractObject(string $xml, string $objectType): ?array
    {
        $data = self::parse($xml);

        // BODY > DATA > TALLYMESSAGE > TYPE
        $object = $data['BODY']['DATA']['TALLYMESSAGE'][$objectType] ?? null;

        if ($object === null) {
            return null;
        }

        // If multiple objects returned, take first
        if (isset($object[0])) {
            return $object[0];
        }

        return $object;
    }

    /**
     * Extract error messages from a Tally response.
     */
    public static function extractErrors(string $xml): array
    {
        $data = self::parse($xml);
        $errors = [];

        $result = $data['BODY']['DATA']['IMPORTRESULT'] ?? [];

        // Check LINEERROR in import results
        $lineErrors = $result['LINEERROR'] ?? null;
        if ($lineErrors) {
            $errors[] = is_array($lineErrors) ? implode('; ', $lineErrors) : (string) $lineErrors;
        }

        return $errors;
    }

    /**
     * Extract the list of companies from a "List of Companies" response.
     */
    public static function extractCompanyList(string $xml): array
    {
        $data = self::parse($xml);
        $companies = [];

        // Try collection path first
        $companyList = $data['BODY']['DATA']['COLLECTION']['COMPANY'] ?? [];

        if (empty($companyList)) {
            $companyList = $data['BODY']['DATA']['TALLYMESSAGE']['COMPANY'] ?? [];
        }

        if (empty($companyList)) {
            return [];
        }

        if (! isset($companyList[0])) {
            $companyList = [$companyList];
        }

        foreach ($companyList as $company) {
            $name = is_array($company)
                ? ($company['NAME'] ?? ($company['@attributes']['NAME'] ?? ''))
                : (string) $company;
            if ($name) {
                $companies[] = $name;
            }
        }

        return $companies;
    }

    /**
     * Extract report data from a Tally export response.
     * Report responses have varied structures — returns raw parsed data.
     */
    public static function extractReport(string $xml): array
    {
        $data = self::parse($xml);

        return $data['BODY']['DATA'] ?? $data['BODY'] ?? $data;
    }

    /**
     * Check the STATUS header in a Tally response (1 = success, 0 = failure).
     */
    public static function isSuccessResponse(string $xml): bool
    {
        $data = self::parse($xml);

        return ($data['HEADER']['STATUS'] ?? '0') === '1';
    }

    /**
     * Convert a SimpleXMLElement to a PHP array recursively.
     */
    private static function xmlToArray(SimpleXMLElement $element): array
    {
        $result = [];

        // Handle attributes
        foreach ($element->attributes() as $attrName => $attrValue) {
            $result['@attributes'][$attrName] = (string) $attrValue;
        }

        // Handle child elements
        foreach ($element->children() as $childName => $child) {
            $childArray = self::xmlToArray($child);
            $value = empty($childArray) ? (string) $child : $childArray;

            if (isset($result[$childName])) {
                // Multiple elements with same name — make it a sequential array
                if (! is_array($result[$childName]) || ! isset($result[$childName][0])) {
                    $result[$childName] = [$result[$childName]];
                }
                $result[$childName][] = $value;
            } else {
                $result[$childName] = $value;
            }
        }

        // If element has no children and no attributes, return its text
        if (empty($result)) {
            return [(string) $element];
        }

        return $result;
    }

    /**
     * Remove BOM and invalid characters from XML string.
     */
    private static function sanitizeXml(string $xml): string
    {
        // Remove UTF-8 BOM
        $xml = ltrim($xml, "\xEF\xBB\xBF");

        // Remove null bytes
        return str_replace("\0", '', $xml);
    }

    private static function getXmlErrors(): string
    {
        $errors = libxml_get_errors();
        libxml_clear_errors();

        $messages = [];
        foreach ($errors as $error) {
            $messages[] = trim($error->message);
        }

        return implode('; ', $messages) ?: 'Unknown XML error';
    }
}
