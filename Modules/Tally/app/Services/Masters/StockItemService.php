<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class StockItemService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('stockitem:list', function () {
            $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Stock Items');
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'STOCKITEM');
        });
    }

    public function get(string $name): ?array
    {
        return $this->cachedGet("stockitem:{$name}", function () use ($name) {
            $xml = TallyXmlBuilder::buildObjectExportRequest('Stock Item', $name);
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractObject($response, 'STOCKITEM');
        });
    }

    /**
     * @param  array  $data  Keys: NAME, PARENT (stock group), BASEUNITS, OPENINGBALANCE, OPENINGRATE, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKITEM', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockitem:list');
            app(AuditLogger::class)->log('create', 'STOCKITEM', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKITEM', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockitem:list', "stockitem:{$name}");
            app(AuditLogger::class)->log('alter', 'STOCKITEM', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('STOCKITEM', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockitem:list', "stockitem:{$name}");
            app(AuditLogger::class)->log('delete', 'STOCKITEM', $name, [], $result);
        }

        return $result;
    }
}
