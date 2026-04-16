<?php

namespace App\Services\Tally\Masters;

use App\Services\Tally\TallyHttpClient;
use App\Services\Tally\TallyXmlBuilder;
use App\Services\Tally\TallyXmlParser;

class StockItemService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Stock Items');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractCollection($response, 'STOCKITEM');
    }

    public function get(string $name): ?array
    {
        $xml = TallyXmlBuilder::buildObjectExportRequest('Stock Item', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractObject($response, 'STOCKITEM');
    }

    /**
     * @param  array  $data  Keys: NAME, PARENT (stock group), BASEUNITS, OPENINGBALANCE, OPENINGRATE, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKITEM', $data, 'Create');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKITEM', $data, 'Alter');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('STOCKITEM', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }
}
