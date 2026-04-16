<?php

namespace App\Services\Tally\Masters;

use App\Services\Tally\TallyHttpClient;
use App\Services\Tally\TallyXmlBuilder;
use App\Services\Tally\TallyXmlParser;

class GroupService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Groups');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractCollection($response, 'GROUP');
    }

    public function get(string $name): ?array
    {
        $xml = TallyXmlBuilder::buildObjectExportRequest('Group', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractObject($response, 'GROUP');
    }

    /**
     * @param  array  $data  Keys: NAME, PARENT, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('GROUP', $data, 'Create');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('GROUP', $data, 'Alter');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('GROUP', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }
}
