<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class CostCenterService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('costcentre:list', function () {
            $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Cost Centres');
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'COSTCENTRE');
        });
    }

    public function get(string $name): ?array
    {
        return $this->cachedGet("costcentre:{$name}", function () use ($name) {
            $xml = TallyXmlBuilder::buildObjectExportRequest('Cost Centre', $name);
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractObject($response, 'COSTCENTRE');
        });
    }

    /**
     * @param  array  $data  Keys: NAME, PARENT, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('COSTCENTRE', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('costcentre:list');
            app(AuditLogger::class)->log('create', 'COSTCENTRE', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('COSTCENTRE', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('costcentre:list', "costcentre:{$name}");
            app(AuditLogger::class)->log('alter', 'COSTCENTRE', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('COSTCENTRE', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('costcentre:list', "costcentre:{$name}");
            app(AuditLogger::class)->log('delete', 'COSTCENTRE', $name, [], $result);
        }

        return $result;
    }
}
