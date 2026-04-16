<?php

namespace App\Services\Tally\Masters;

use App\Services\Tally\TallyHttpClient;
use App\Services\Tally\TallyXmlBuilder;
use App\Services\Tally\TallyXmlParser;

class CostCenterService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Cost Centres');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractCollection($response, 'COSTCENTRE');
    }

    public function get(string $name): ?array
    {
        $xml = TallyXmlBuilder::buildObjectExportRequest('Cost Centre', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractObject($response, 'COSTCENTRE');
    }

    /**
     * @param  array  $data  Keys: NAME, PARENT, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('COSTCENTRE', $data, 'Create');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('COSTCENTRE', $data, 'Alter');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('COSTCENTRE', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }
}
