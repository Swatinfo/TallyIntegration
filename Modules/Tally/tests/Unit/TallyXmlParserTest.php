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
