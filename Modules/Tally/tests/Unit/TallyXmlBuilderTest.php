<?php

use Modules\Tally\Services\TallyXmlBuilder;

it('builds export request with correct header structure and EXPLODEFLAG', function () {
    $xml = TallyXmlBuilder::buildExportRequest('Balance Sheet', [], [], 'TestCompany');

    expect($xml)
        ->toContain('<VERSION>1</VERSION>')
        ->toContain('<TALLYREQUEST>Export</TALLYREQUEST>')
        ->toContain('<TYPE>Data</TYPE>')
        ->toContain('<ID>Balance Sheet</ID>')
        ->toContain('<SVCURRENTCOMPANY>TestCompany</SVCURRENTCOMPANY>')
        ->toContain('<EXPLODEFLAG>Yes</EXPLODEFLAG>')
        ->toContain('<SVEXPORTFORMAT>$$SysName:XML</SVEXPORTFORMAT>');
});

it('builds export request with filters', function () {
    $xml = TallyXmlBuilder::buildExportRequest('Ledger Vouchers', [], [
        'LEDGERNAME' => 'Cash',
        'SVFROMDATE' => '20260401',
    ], 'TestCompany');

    expect($xml)
        ->toContain('<LEDGERNAME>Cash</LEDGERNAME>')
        ->toContain('<SVFROMDATE>20260401</SVFROMDATE>');
});

it('builds export request with fetch fields using FETCHLIST', function () {
    $xml = TallyXmlBuilder::buildExportRequest('Balance Sheet', ['Name', 'Parent', 'Closing Balance'], [], 'TestCompany');

    expect($xml)
        ->toContain('<FETCHLIST>')
        ->toContain('<FETCH>Name</FETCH>')
        ->toContain('<FETCH>Parent</FETCH>')
        ->toContain('<FETCH>Closing Balance</FETCH>')
        ->toContain('</FETCHLIST>');
});

it('builds collection export request with TYPE=Collection and EXPLODEFLAG', function () {
    $xml = TallyXmlBuilder::buildCollectionExportRequest('Ledger', [], [], 'TestCompany');

    expect($xml)
        ->toContain('<TYPE>Collection</TYPE>')
        ->toContain('<ID>Ledger</ID>')
        ->toContain('<EXPLODEFLAG>Yes</EXPLODEFLAG>');
});

it('builds object export request with TYPE=Object, SUBTYPE, and BinaryXML', function () {
    $xml = TallyXmlBuilder::buildObjectExportRequest('Ledger', 'Cash', [], 'TestCompany');

    expect($xml)
        ->toContain('<TYPE>Object</TYPE>')
        ->toContain('<SUBTYPE>Ledger</SUBTYPE>')
        ->toContain('<ID TYPE="Name">Cash</ID>')
        ->toContain('<SVEXPORTFORMAT>BinaryXML</SVEXPORTFORMAT>');
});

it('builds import master request with correct structure', function () {
    $xml = TallyXmlBuilder::buildImportMasterRequest('LEDGER', [
        'NAME' => 'Test Ledger',
        'PARENT' => 'Sundry Debtors',
    ], 'Create', 'TestCompany');

    expect($xml)
        ->toContain('<TALLYREQUEST>Import</TALLYREQUEST>')
        ->toContain('<TYPE>Data</TYPE>')
        ->toContain('<ID>All Masters</ID>')
        ->toContain('<LEDGER NAME="Test Ledger" ACTION="Create">')
        ->toContain('<NAME>Test Ledger</NAME>')
        ->toContain('<PARENT>Sundry Debtors</PARENT>')
        ->toContain('<SVCURRENTCOMPANY>TestCompany</SVCURRENTCOMPANY>');
});

it('builds import voucher request without ACTION on creation per demo samples', function () {
    $xml = TallyXmlBuilder::buildImportVoucherRequest([
        'DATE' => '20260416',
        'VOUCHERTYPENAME' => 'Sales',
        'PARTYLEDGERNAME' => 'Customer A',
    ], 'Create', 'TestCompany');

    expect($xml)
        ->toContain('<ID>Vouchers</ID>')
        ->toContain('<IMPORTDUPS>@@DUPCOMBINE</IMPORTDUPS>')
        ->toContain('<TALLYMESSAGE>')
        ->not->toContain('xmlns:UDF')
        ->toContain('<VOUCHER>')
        ->not->toContain('ACTION="Create"')
        ->toContain('<VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>')
        ->toContain('<DATE>20260416</DATE>');
});

it('builds voucher alter request WITH ACTION attribute', function () {
    $xml = TallyXmlBuilder::buildImportVoucherRequest([
        'DATE' => '20260416',
        'VOUCHERTYPENAME' => 'Sales',
        'MASTERID' => '12345',
    ], 'Alter', 'TestCompany');

    expect($xml)->toContain('<VOUCHER ACTION="Alter">');
});

it('builds batch import with multiple vouchers without ACTION', function () {
    $xml = TallyXmlBuilder::buildBatchImportVoucherRequest([
        ['DATE' => '20260401', 'VOUCHERTYPENAME' => 'Payment'],
        ['DATE' => '20260402', 'VOUCHERTYPENAME' => 'Payment'],
    ], 'Create', 'TestCompany');

    expect(substr_count($xml, '<VOUCHER>'))->toBe(2);
    expect($xml)->not->toContain('ACTION="Create"');
    expect($xml)->toContain('<DATE>20260401</DATE>');
    expect($xml)->toContain('<DATE>20260402</DATE>');
});

it('builds cancel voucher request with attribute format', function () {
    $xml = TallyXmlBuilder::buildCancelVoucherRequest(
        '16-Apr-2026', '1', 'Sales', 'Wrong amount', 'TestCompany'
    );

    expect($xml)
        ->toContain('DATE="16-Apr-2026"')
        ->toContain('TAGNAME="Voucher Number"')
        ->toContain('TAGVALUE="1"')
        ->toContain('VCHTYPE="Sales"')
        ->toContain('ACTION="Cancel"')
        ->toContain('<NARRATION>Wrong amount</NARRATION>');
});

it('builds delete voucher request with attribute format', function () {
    $xml = TallyXmlBuilder::buildDeleteVoucherRequest(
        '16-Apr-2026', '1', 'Payment', 'TestCompany'
    );

    expect($xml)
        ->toContain('ACTION="Delete"')
        ->toContain('TAGVALUE="1"')
        ->toContain('VCHTYPE="Payment"');
});

it('builds delete master request', function () {
    $xml = TallyXmlBuilder::buildDeleteMasterRequest('LEDGER', 'Old Ledger', 'TestCompany');

    expect($xml)
        ->toContain('ACTION="Delete"')
        ->toContain('<NAME>Old Ledger</NAME>');
});

it('escapes XML special characters', function () {
    expect(TallyXmlBuilder::escapeXml('Duties & Taxes'))->toBe('Duties &amp; Taxes');
    expect(TallyXmlBuilder::escapeXml('<script>'))->toBe('&lt;script&gt;');
    expect(TallyXmlBuilder::escapeXml('"quoted"'))->toBe('&quot;quoted&quot;');
});

it('converts nested arrays to XML', function () {
    $xml = TallyXmlBuilder::arrayToXml([
        'NAME' => 'Test',
        'ALLLEDGERENTRIES.LIST' => [
            ['LEDGERNAME' => 'A', 'AMOUNT' => '100'],
            ['LEDGERNAME' => 'B', 'AMOUNT' => '-100'],
        ],
    ]);

    expect($xml)
        ->toContain('<NAME>Test</NAME>')
        ->toContain('<ALLLEDGERENTRIES.LIST><LEDGERNAME>A</LEDGERNAME><AMOUNT>100</AMOUNT></ALLLEDGERENTRIES.LIST>')
        ->toContain('<ALLLEDGERENTRIES.LIST><LEDGERNAME>B</LEDGERNAME><AMOUNT>-100</AMOUNT></ALLLEDGERENTRIES.LIST>');
});
