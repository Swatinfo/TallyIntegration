<?php

namespace App\Services\Tally\Masters;

use App\Services\Tally\TallyHttpClient;
use App\Services\Tally\TallyXmlBuilder;
use App\Services\Tally\TallyXmlParser;

class UnitService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Units');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractCollection($response, 'UNIT');
    }

    public function get(string $name): ?array
    {
        $xml = TallyXmlBuilder::buildObjectExportRequest('Unit', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractObject($response, 'UNIT');
    }

    /**
     * @param  array  $data  Keys: NAME, ISSIMPLEUNIT (Yes/No), BASEUNITS, ADDITIONALUNITS, CONVERSION, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('UNIT', $data, 'Create');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('UNIT', $data, 'Alter');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('UNIT', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }
}
