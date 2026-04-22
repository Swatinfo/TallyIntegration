<?php

namespace Modules\Tally\Services;

use Modules\Tally\Exceptions\TallyXmlParseException;
use SimpleXMLElement;

class TallyXmlParser
{
    /**
     * Parse a Tally XML response into a PHP array.
     */
    public static function parse(string $xml): array
    {
        $xml = self::sanitizeXml($xml);

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            $element = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

            if ($element === false) {
                $capturedErrors = libxml_get_errors();
                libxml_clear_errors();

                throw new TallyXmlParseException(
                    'Failed to parse TallyPrime XML response: '.self::formatXmlErrors($capturedErrors),
                    self::excerptAroundError($xml, $capturedErrors),
                    $capturedErrors,
                );
            }
        } finally {
            libxml_use_internal_errors($previous);
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

        $errors = (int) ($result['ERRORS'] ?? 0);
        $exceptions = (int) ($result['EXCEPTIONS'] ?? 0);

        // Tally returns EXCEPTIONS>0 with ERRORS=0 when an import silently
        // skips a row because of a missing reference master (e.g. PARENT
        // doesn't exist). Roll exceptions into errors so callers don't treat
        // a no-op import as a successful create.
        return [
            'created' => (int) ($result['CREATED'] ?? 0),
            'altered' => (int) ($result['ALTERED'] ?? 0),
            'deleted' => (int) ($result['DELETED'] ?? 0),
            'lastvchid' => $result['LASTVCHID'] ?? null,
            'lastmid' => $result['LASTMID'] ?? null,
            'combined' => (int) ($result['COMBINED'] ?? 0),
            'ignored' => (int) ($result['IGNORED'] ?? 0),
            'errors' => $errors + $exceptions,
            'exceptions' => $exceptions,
            'cancelled' => (int) ($result['CANCELLED'] ?? 0),
            'line_error' => isset($result['LINEERROR']) ? (is_array($result['LINEERROR']) ? ($result['LINEERROR']['#text'] ?? null) : $result['LINEERROR']) : null,
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

        $report = $data['BODY']['DATA'] ?? $data['BODY'] ?? $data;

        return is_array($report) ? $report : [];
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
     *
     * When an element has attributes but no child elements, the text content is
     * preserved under the '#text' key (Tally stamps TYPE="String" / TYPE="Logical"
     * on most leaf fields — without this, their values silently disappear).
     */
    private static function xmlToArray(SimpleXMLElement $element): array|string
    {
        $result = [];

        // Handle attributes
        foreach ($element->attributes() as $attrName => $attrValue) {
            $result['@attributes'][$attrName] = (string) $attrValue;
        }

        $hasChildren = false;

        // Handle child elements
        foreach ($element->children() as $childName => $child) {
            $hasChildren = true;
            $value = self::xmlToArray($child);

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

        // If element has no children and no attributes, it's a leaf text node
        if (empty($result)) {
            return (string) $element;
        }

        // Attributes + text but no children — preserve the text content.
        if (! $hasChildren) {
            $text = trim((string) $element);
            if ($text !== '') {
                $result['#text'] = $text;
            }
        }

        return $result;
    }

    /**
     * Remove BOM and invalid XML 1.0 characters from a Tally response.
     *
     * Tally uses ASCII control bytes (0x03 ETX, 0x04 EOT) as internal delimiters
     * inside PARENTSTRUCTURE / PARENT for reserved masters. XML 1.0 forbids
     * 0x00–0x08, 0x0B, 0x0C and 0x0E–0x1F both as raw bytes and as numeric
     * character references, so both forms must be stripped before parsing.
     */
    private static function sanitizeXml(string $xml): string
    {
        // Remove UTF-8 BOM
        $xml = ltrim($xml, "\xEF\xBB\xBF");

        // Strip raw control bytes that are invalid in XML 1.0.
        $xml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xml);

        // Strip numeric character references to the same forbidden range
        // (decimal 1-8, 11, 12, 14-31; hex 0x1-0x8, 0xB, 0xC, 0xE-0x1F).
        return preg_replace(
            '/&#(?:0*(?:[1-8]|1[12]|1[4-9]|2[0-9]|3[01])|[xX]0*(?:[1-8bBcCeEfF]|1[0-9a-fA-F]));/',
            '',
            $xml
        );
    }

    /**
     * Return ~500 chars around the first libxml error line so exception
     * excerpts point at the neighbourhood of the failure instead of the
     * first 1 KB of a 20 KB response.
     *
     * @param  array<int, \LibXMLError>  $errors
     */
    private static function excerptAroundError(string $xml, array $errors): string
    {
        $line = $errors[0]->line ?? 0;

        if ($line <= 0) {
            return mb_substr($xml, 0, 1000);
        }

        $lines = preg_split('/\r\n|\r|\n/', $xml);
        if (! is_array($lines)) {
            return mb_substr($xml, 0, 1000);
        }

        $start = max(0, $line - 3);
        $end = min(count($lines), $line + 3);
        $window = array_slice($lines, $start, $end - $start);
        $prefix = '[libxml line '.$line.', showing '.($start + 1).'-'.$end.']'."\n";

        return $prefix.mb_substr(implode("\n", $window), 0, 1000);
    }

    /**
     * @param  array<int, \LibXMLError>  $errors
     */
    private static function formatXmlErrors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $error) {
            $messages[] = sprintf(
                '%s (line %d, column %d)',
                trim($error->message),
                $error->line,
                $error->column,
            );
        }

        return implode('; ', $messages) ?: 'Unknown XML error';
    }
}
