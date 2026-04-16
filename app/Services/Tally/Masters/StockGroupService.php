<?php

namespace App\Services\Tally\Masters;

use App\Services\Tally\TallyHttpClient;
use App\Services\Tally\TallyXmlBuilder;
use App\Services\Tally\TallyXmlParser;

class StockGroupService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Stock Groups');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractCollection($response, 'STOCKGROUP');
    }

    public function get(string $name): ?array
    {
        $xml = TallyXmlBuilder::buildObjectExportRequest('Stock Group', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractObject($response, 'STOCKGROUP');
    }

    /**
     * @param  array  $data  Keys: NAME, PARENT, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKGROUP', $data, 'Create');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKGROUP', $data, 'Alter');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('STOCKGROUP', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }
}
