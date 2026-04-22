<?php

use Modules\Tally\Exceptions\TallyXmlParseException;
use Modules\Tally\Services\TallyXmlParser;

function tallyFixture(string $name): string
{
    return file_get_contents(__DIR__.'/../Fixtures/Xml/'.$name);
}

it('parses valid XML into array', function () {
    $result = TallyXmlParser::parse('<ENVELOPE><HEADER><VERSION>1</VERSION></HEADER></ENVELOPE>');

    expect($result)->toBeArray();
    expect($result)->toHaveKey('HEADER');
});

it('throws TallyXmlParseException on invalid XML', function () {
    TallyXmlParser::parse('not valid xml at all');
})->throws(TallyXmlParseException::class);

it('parses import success result', function () {
    $result = TallyXmlParser::parseImportResult(tallyFixture('import-success.xml'));

    expect($result['created'])->toBe(1);
    expect($result['errors'])->toBe(0);
    expect($result['combined'])->toBe(0);
});

it('parses import error result', function () {
    $result = TallyXmlParser::parseImportResult(tallyFixture('import-error.xml'));

    expect($result['created'])->toBe(0);
    expect($result['errors'])->toBe(1);
});

it('detects successful import', function () {
    expect(TallyXmlParser::isImportSuccessful(tallyFixture('import-success.xml')))->toBeTrue();
    expect(TallyXmlParser::isImportSuccessful(tallyFixture('import-error.xml')))->toBeFalse();
});

it('detects cancel success via combined field', function () {
    $result = TallyXmlParser::parseImportResult(tallyFixture('cancel-success.xml'));

    expect($result['combined'])->toBe(1);
    expect($result['errors'])->toBe(0);
    expect(TallyXmlParser::isImportSuccessful(tallyFixture('cancel-success.xml')))->toBeTrue();
});

it('extracts collection of ledgers', function () {
    $ledgers = TallyXmlParser::extractCollection(tallyFixture('collection-ledgers.xml'), 'LEDGER');

    expect($ledgers)->toHaveCount(2);
    expect($ledgers[0]['@attributes']['NAME'])->toBe('Cash');
    expect($ledgers[1]['@attributes']['NAME'])->toBe('HDFC Bank');
});

it('extracts single object from object export', function () {
    $ledger = TallyXmlParser::extractObject(tallyFixture('object-ledger.xml'), 'LEDGER');

    expect($ledger)->not->toBeNull();
    expect($ledger['@attributes']['NAME'])->toBe('Cash');
    // PARENT has @attributes due to TYPE="String" attribute on XML element
    expect($ledger)->toHaveKey('PARENT');
});

it('returns null for non-existent object', function () {
    $result = TallyXmlParser::extractObject(tallyFixture('collection-ledgers.xml'), 'NONEXISTENT');

    expect($result)->toBeNull();
});

it('extracts company list', function () {
    $companies = TallyXmlParser::extractCompanyList(tallyFixture('company-list.xml'));

    expect($companies)->not->toBeEmpty();
    expect($companies)->toContain('ABC Enterprises Pvt Ltd');
    expect($companies)->toContain('XYZ Trading Co');
});

it('returns empty array for empty collection', function () {
    $xml = '<ENVELOPE><HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER><BODY><DESC></DESC><DATA><COLLECTION></COLLECTION></DATA></BODY></ENVELOPE>';

    expect(TallyXmlParser::extractCollection($xml, 'LEDGER'))->toBeEmpty();
});

it('checks success response status from header', function () {
    $xml = '<ENVELOPE><HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER><BODY></BODY></ENVELOPE>';
    expect(TallyXmlParser::isSuccessResponse($xml))->toBeTrue();

    $xml2 = '<ENVELOPE><HEADER><VERSION>1</VERSION><STATUS>0</STATUS></HEADER><BODY></BODY></ENVELOPE>';
    expect(TallyXmlParser::isSuccessResponse($xml2))->toBeFalse();
});

it('handles BOM in XML', function () {
    $xmlWithBom = "\xEF\xBB\xBF<ENVELOPE><HEADER><VERSION>1</VERSION></HEADER></ENVELOPE>";
    $result = TallyXmlParser::parse($xmlWithBom);

    expect($result)->toHaveKey('HEADER');
});

it('strips raw ASCII control bytes that Tally emits inside reserved masters', function () {
    // TallyPrime wraps PARENTSTRUCTURE values with raw 0x03 (ETX) delimiters.
    // Without sanitisation this throws TallyXmlParseException ("Unknown XML error").
    $xml = '<ENVELOPE><BODY><DATA><COLLECTION>'.
        '<GROUP NAME="Indirect Incomes">'.
        "<PARENTSTRUCTURE>\x03Indirect Incomes\x03</PARENTSTRUCTURE>".
        '</GROUP></COLLECTION></DATA></BODY></ENVELOPE>';

    $groups = TallyXmlParser::extractCollection($xml, 'GROUP');

    expect($groups)->toHaveCount(1);
    expect($groups[0]['PARENTSTRUCTURE'])->toBe('Indirect Incomes');
});

it('strips numeric character references to forbidden XML 1.0 code points', function () {
    // TallyPrime emits &#4; inside <PARENT> for default Primary-based groups.
    // Raw libxml rejects these references with "xmlParseCharRef: invalid xmlChar value".
    $xml = '<ENVELOPE><BODY><DATA><COLLECTION>'.
        '<GROUP NAME="Capital Account">'.
        '<PARENT>&#4; Primary</PARENT>'.
        '</GROUP></COLLECTION></DATA></BODY></ENVELOPE>';

    $groups = TallyXmlParser::extractCollection($xml, 'GROUP');

    expect($groups)->toHaveCount(1);
    expect($groups[0]['PARENT'])->toBe(' Primary');
});

it('preserves text content under #text when the element also carries attributes', function () {
    // Tally stamps TYPE="String" / TYPE="Logical" on nearly every leaf element.
    // Without this, PARENT="Primary" and ISREVENUE="Yes" came back empty.
    $xml = '<ENVELOPE><BODY><DATA><COLLECTION>'.
        '<GROUP NAME="Sales Accounts">'.
        '<PARENT TYPE="String">Primary</PARENT>'.
        '<ISREVENUE TYPE="Logical">Yes</ISREVENUE>'.
        '</GROUP></COLLECTION></DATA></BODY></ENVELOPE>';

    $groups = TallyXmlParser::extractCollection($xml, 'GROUP');

    expect($groups)->toHaveCount(1);
    expect($groups[0]['PARENT']['@attributes']['TYPE'])->toBe('String');
    expect($groups[0]['PARENT']['#text'])->toBe('Primary');
    expect($groups[0]['ISREVENUE']['@attributes']['TYPE'])->toBe('Logical');
    expect($groups[0]['ISREVENUE']['#text'])->toBe('Yes');
});

it('returns a plain string when a leaf element has text but no attributes', function () {
    $xml = '<ENVELOPE><HEADER><VERSION>1</VERSION><STATUS>1</STATUS></HEADER></ENVELOPE>';

    $result = TallyXmlParser::parse($xml);

    expect($result['HEADER']['VERSION'])->toBe('1');
    expect($result['HEADER']['STATUS'])->toBe('1');
});

it('preserves valid XML whitespace while stripping control bytes', function () {
    // \t, \n, \r are legal in XML 1.0 and must survive sanitization.
    $xml = "<ENVELOPE>\r\n\t<HEADER><VERSION>1</VERSION></HEADER>\r\n</ENVELOPE>";

    $result = TallyXmlParser::parse($xml);

    expect($result)->toHaveKey('HEADER');
});

it('extracts errors from import result', function () {
    $errors = TallyXmlParser::extractErrors(tallyFixture('import-error.xml'));

    expect($errors)->not->toBeEmpty();
});

it('returns empty import result for non-import XML', function () {
    $xml = '<ENVELOPE><HEADER><VERSION>1</VERSION></HEADER><BODY></BODY></ENVELOPE>';
    $result = TallyXmlParser::parseImportResult($xml);

    expect($result['created'])->toBe(0);
    expect($result['errors'])->toBe(0);
});
