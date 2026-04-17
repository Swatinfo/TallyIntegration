# 08 — Inventory Operations

Stock journals for manufacturing, godown transfers, wastage, and physical stock adjustments. All stock movement operations that aren't sales/purchase.

---

## Godown-to-Godown Transfer

Transfer 100 Widget A from Main Warehouse to Showroom (no financial impact):

```php
$client = app(TallyHttpClient::class);

$data = [
    'DATE' => '20260416', 'VOUCHERTYPENAME' => 'Stock Journal',
    'NARRATION' => 'Transfer: Main Warehouse → Showroom', 'VOUCHERNUMBER' => 'STJ-001',
    'INVENTORYENTRIESIN.LIST' => [[
        'STOCKITEMNAME' => 'Widget A', 'RATE' => '500/Nos', 'ACTUALQTY' => '100 Nos', 'AMOUNT' => '50000',
        'BATCHALLOCATIONS.LIST' => ['GODOWNNAME' => 'Showroom', 'BATCHNAME' => 'Primary Batch', 'AMOUNT' => '50000', 'ACTUALQTY' => '100 Nos'],
    ]],
    'INVENTORYENTRIESOUT.LIST' => [[
        'STOCKITEMNAME' => 'Widget A', 'RATE' => '500/Nos', 'ACTUALQTY' => '100 Nos', 'AMOUNT' => '50000',
        'BATCHALLOCATIONS.LIST' => ['GODOWNNAME' => 'Main Warehouse - Mumbai', 'BATCHNAME' => 'Primary Batch', 'AMOUNT' => '50000', 'ACTUALQTY' => '100 Nos'],
    ]],
];

$xml = TallyXmlBuilder::buildImportVoucherRequest($data, 'Create');
$response = $client->sendXml($xml);
$result = TallyXmlParser::parseImportResult($response);
```

## Manufacturing / Production (BOM Consumption)

Manufacture 50 "Finished Product X". BOM: 2 Kgs Steel + 1 Widget A per unit.

```php
$data = [
    'DATE' => '20260420', 'VOUCHERTYPENAME' => 'Stock Journal',
    'NARRATION' => 'Production batch #PB-042', 'VOUCHERNUMBER' => 'MFG-001',
    // Finished goods IN
    'INVENTORYENTRIESIN.LIST' => [[
        'STOCKITEMNAME' => 'Finished Product X', 'RATE' => '1300/Nos',
        'ACTUALQTY' => '50 Nos', 'AMOUNT' => '65000',
        'BATCHALLOCATIONS.LIST' => ['GODOWNNAME' => 'Main Warehouse - Mumbai', 'BATCHNAME' => 'Primary Batch', 'AMOUNT' => '65000', 'ACTUALQTY' => '50 Nos'],
    ]],
    // Raw materials OUT
    'INVENTORYENTRIESOUT.LIST' => [
        [
            'STOCKITEMNAME' => 'Steel Rods - 12mm', 'RATE' => '65/Kgs',
            'ACTUALQTY' => '100 Kgs', 'AMOUNT' => '6500',
            'BATCHALLOCATIONS.LIST' => ['GODOWNNAME' => 'Factory Store', 'BATCHNAME' => 'Primary Batch', 'AMOUNT' => '6500', 'ACTUALQTY' => '100 Kgs'],
        ],
        [
            'STOCKITEMNAME' => 'Widget A', 'RATE' => '500/Nos',
            'ACTUALQTY' => '50 Nos', 'AMOUNT' => '25000',
            'BATCHALLOCATIONS.LIST' => ['GODOWNNAME' => 'Main Warehouse - Mumbai', 'BATCHNAME' => 'Primary Batch', 'AMOUNT' => '25000', 'ACTUALQTY' => '50 Nos'],
        ],
    ],
];
```

## Wastage / Scrap Write-Off

10 Kgs of damaged raw material:

```php
$data = [
    'DATE' => '20260425', 'VOUCHERTYPENAME' => 'Stock Journal',
    'NARRATION' => 'Material wastage - damaged', 'VOUCHERNUMBER' => 'WST-001',
    'INVENTORYENTRIESOUT.LIST' => [[
        'STOCKITEMNAME' => 'Steel Rods - 12mm', 'RATE' => '65/Kgs',
        'ACTUALQTY' => '10 Kgs', 'AMOUNT' => '650',
        'BATCHALLOCATIONS.LIST' => ['GODOWNNAME' => 'Factory Store', 'BATCHNAME' => 'Primary Batch', 'AMOUNT' => '650', 'ACTUALQTY' => '10 Kgs'],
    ]],
    // No INVENTORYENTRIESIN.LIST — pure reduction
];
```

## Physical Stock Adjustment

System shows 100 Widget A, physical count shows 95. Adjust -5:

```php
$data = [
    'DATE' => '20260430', 'VOUCHERTYPENAME' => 'Stock Journal',
    'NARRATION' => 'Physical stock adjustment', 'VOUCHERNUMBER' => 'ADJ-001',
    'INVENTORYENTRIESOUT.LIST' => [[
        'STOCKITEMNAME' => 'Widget A', 'RATE' => '500/Nos',
        'ACTUALQTY' => '5 Nos', 'AMOUNT' => '2500',
        'BATCHALLOCATIONS.LIST' => ['GODOWNNAME' => 'Main Warehouse - Mumbai', 'BATCHNAME' => 'Primary Batch', 'AMOUNT' => '2500', 'ACTUALQTY' => '5 Nos'],
    ]],
];
```

## Stock Reports

```bash
curl "http://localhost:8000/api/tally/MUM/reports/stock-summary"
```

```php
// Godown-wise stock
$xml = TallyXmlBuilder::buildExportRequest('Godown Summary');
$response = $client->sendXml($xml);
$report = TallyXmlParser::extractReport($response);
```
