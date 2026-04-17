<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class LedgerService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('ledger:list', function () {
            $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Accounts');
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'LEDGER');
        });
    }

    public function get(string $name): ?array
    {
        return $this->cachedGet("ledger:{$name}", function () use ($name) {
            $xml = TallyXmlBuilder::buildObjectExportRequest('Ledger', $name);
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractObject($response, 'LEDGER');
        });
    }

    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('LEDGER', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('ledger:list');
            app(AuditLogger::class)->log('create', 'LEDGER', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('LEDGER', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('ledger:list', "ledger:{$name}");
            app(AuditLogger::class)->log('alter', 'LEDGER', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('LEDGER', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('ledger:list', "ledger:{$name}");
            app(AuditLogger::class)->log('delete', 'LEDGER', $name, [], $result);
        }

        return $result;
    }
}
