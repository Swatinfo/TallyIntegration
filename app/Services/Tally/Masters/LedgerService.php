<?php

namespace App\Services\Tally\Masters;

use App\Services\Tally\TallyHttpClient;
use App\Services\Tally\TallyXmlBuilder;
use App\Services\Tally\TallyXmlParser;

class LedgerService
{
    public function __construct(
        private TallyHttpClient $client,
    ) {}

    /**
     * List all ledgers from TallyPrime.
     */
    public function list(): array
    {
        $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Accounts');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractCollection($response, 'LEDGER');
    }

    /**
     * Get a single ledger by name using OBJECT export.
     */
    public function get(string $name): ?array
    {
        $xml = TallyXmlBuilder::buildObjectExportRequest('Ledger', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::extractObject($response, 'LEDGER');
    }

    /**
     * Create a new ledger in TallyPrime.
     *
     * @param  array  $data  Keys: NAME, PARENT (group), OPENINGBALANCE, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('LEDGER', $data, 'Create');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    /**
     * Update (alter) an existing ledger.
     *
     * @param  string  $name  Current ledger name
     * @param  array  $data  Fields to update (must include NAME as old name, NEWNAME for rename)
     */
    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('LEDGER', $data, 'Alter');
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }

    /**
     * Delete a ledger from TallyPrime.
     */
    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('LEDGER', $name);
        $response = $this->client->sendXml($xml);

        return TallyXmlParser::parseImportResult($response);
    }
}
