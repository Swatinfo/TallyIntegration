# 03 — Inventory Setup (Units, Stock Groups, Stock Items, Godowns, Cost Centers)

Everything needed to set up inventory tracking before creating sales/purchase with stock.

---

## Units

```php
use Modules\Tally\Services\Masters\UnitService;
$units = app(UnitService::class);

// Simple units
$units->create(['NAME' => 'Nos', 'ISSIMPLEUNIT' => 'Yes']);
$units->create(['NAME' => 'Kgs', 'ISSIMPLEUNIT' => 'Yes']);
$units->create(['NAME' => 'Ltrs', 'ISSIMPLEUNIT' => 'Yes']);
$units->create(['NAME' => 'Pcs', 'ISSIMPLEUNIT' => 'Yes']);
$units->create(['NAME' => 'Boxes', 'ISSIMPLEUNIT' => 'Yes']);
$units->create(['NAME' => 'Meters', 'ISSIMPLEUNIT' => 'Yes']);

// Compound unit (1 Box = 12 Pcs)
$units->create([
    'NAME' => 'Box of 12',
    'ISSIMPLEUNIT' => 'No',
    'BASEUNITS' => 'Boxes',
    'ADDITIONALUNITS' => 'Pcs',
    'CONVERSION' => '12',
]);
```

## Stock Groups

```php
use Modules\Tally\Services\Masters\StockGroupService;
$sg = app(StockGroupService::class);

$sg->create(['NAME' => 'Electronics', 'PARENT' => 'Primary']);
$sg->create(['NAME' => 'Mobile Phones', 'PARENT' => 'Electronics']);
$sg->create(['NAME' => 'Laptops', 'PARENT' => 'Electronics']);
$sg->create(['NAME' => 'Raw Materials', 'PARENT' => 'Primary']);
$sg->create(['NAME' => 'Finished Goods', 'PARENT' => 'Primary']);
$sg->create(['NAME' => 'Packing Materials', 'PARENT' => 'Primary']);
```

## Stock Items

### Create via API

```bash
# Product with opening stock
curl -X POST http://localhost:8000/api/tally/MUM/stock-items \
  -H "Content-Type: application/json" \
  -d '{
    "NAME": "iPhone 15 Pro",
    "PARENT": "Mobile Phones",
    "BASEUNITS": "Nos",
    "OPENINGBALANCE": "50 Nos",
    "OPENINGRATE": "120000/Nos",
    "OPENINGVALUE": "6000000"
  }'

# Raw material by weight
curl -X POST http://localhost:8000/api/tally/MUM/stock-items \
  -H "Content-Type: application/json" \
  -d '{
    "NAME": "Steel Rods - 12mm",
    "PARENT": "Raw Materials",
    "BASEUNITS": "Kgs",
    "OPENINGBALANCE": "500 Kgs",
    "OPENINGRATE": "65/Kgs",
    "OPENINGVALUE": "32500"
  }'

# Item with no opening stock
curl -X POST http://localhost:8000/api/tally/MUM/stock-items \
  -H "Content-Type: application/json" \
  -d '{ "NAME": "Widget A", "PARENT": "Finished Goods", "BASEUNITS": "Nos" }'

# Item with batch tracking (pharma/food)
curl -X POST http://localhost:8000/api/tally/MUM/stock-items \
  -H "Content-Type: application/json" \
  -d '{ "NAME": "Paracetamol 500mg", "PARENT": "Finished Goods", "BASEUNITS": "Nos", "HASBATCHES": "Yes" }'
```

### List / Get / Update / Delete

```bash
curl http://localhost:8000/api/tally/MUM/stock-items
curl http://localhost:8000/api/tally/MUM/stock-items/iPhone%2015%20Pro

curl -X PUT http://localhost:8000/api/tally/MUM/stock-items/Widget%20A \
  -H "Content-Type: application/json" \
  -d '{ "PARENT": "Electronics" }'

curl -X DELETE http://localhost:8000/api/tally/MUM/stock-items/Old%20Product
```

## Godowns (Warehouses)

Godowns are created via raw XML (separate Tally object type):

```php
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

$client = app(TallyHttpClient::class);

$godowns = [
    ['NAME' => 'Main Warehouse - Mumbai', 'PARENT' => ''],
    ['NAME' => 'Factory Store', 'PARENT' => 'Main Warehouse - Mumbai'],
    ['NAME' => 'Showroom', 'PARENT' => 'Main Warehouse - Mumbai'],
    ['NAME' => 'Warehouse - Delhi', 'PARENT' => ''],
    ['NAME' => 'Dispatch Bay', 'PARENT' => ''],
];

foreach ($godowns as $g) {
    $xml = TallyXmlBuilder::buildImportMasterRequest('GODOWN', $g, 'Create');
    $response = $client->sendXml($xml);
    $result = TallyXmlParser::parseImportResult($response);
}
```

## Cost Centers

```php
use Modules\Tally\Services\Masters\CostCenterService;
$cc = app(CostCenterService::class);

// Departments
$cc->create(['NAME' => 'Sales Department', 'PARENT' => '']);
$cc->create(['NAME' => 'Production Department', 'PARENT' => '']);
$cc->create(['NAME' => 'Admin Department', 'PARENT' => '']);

// Projects
$cc->create(['NAME' => 'Project Alpha', 'PARENT' => '']);

// Locations
$cc->create(['NAME' => 'Mumbai Office', 'PARENT' => '']);
$cc->create(['NAME' => 'Delhi Office', 'PARENT' => '']);
```

## Currency Masters (for Forex)

```php
$client = app(TallyHttpClient::class);

$currencies = [
    ['NAME' => 'USD', 'MAILINGNAME' => 'US Dollar', 'EXPANDEDSYMBOL' => 'US Dollars', 'DECIMALPLACES' => '2'],
    ['NAME' => 'EUR', 'MAILINGNAME' => 'Euro', 'EXPANDEDSYMBOL' => 'Euros', 'DECIMALPLACES' => '2'],
    ['NAME' => 'GBP', 'MAILINGNAME' => 'British Pound', 'EXPANDEDSYMBOL' => 'Pounds', 'DECIMALPLACES' => '2'],
];

foreach ($currencies as $c) {
    $xml = TallyXmlBuilder::buildImportMasterRequest('CURRENCY', $c, 'Create');
    $client->sendXml($xml);
}
```
