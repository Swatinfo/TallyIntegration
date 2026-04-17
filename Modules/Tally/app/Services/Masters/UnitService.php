<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class UnitService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('unit:list', function () {
            $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Units');
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'UNIT');
        });
    }

    public function get(string $name): ?array
    {
        return $this->cachedGet("unit:{$name}", function () use ($name) {
            $xml = TallyXmlBuilder::buildObjectExportRequest('Unit', $name);
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractObject($response, 'UNIT');
        });
    }

    /**
     * @param  array  $data  Keys: NAME, ISSIMPLEUNIT (Yes/No), BASEUNITS, ADDITIONALUNITS, CONVERSION, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('UNIT', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('unit:list');
            app(AuditLogger::class)->log('create', 'UNIT', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('UNIT', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('unit:list', "unit:{$name}");
            app(AuditLogger::class)->log('alter', 'UNIT', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('UNIT', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('unit:list', "unit:{$name}");
            app(AuditLogger::class)->log('delete', 'UNIT', $name, [], $result);
        }

        return $result;
    }
}
