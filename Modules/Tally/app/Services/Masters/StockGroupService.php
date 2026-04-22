<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\Fields\TallyFieldRegistry;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class StockGroupService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('stockgroup:list', function () {
            // EXPLODEFLAG=No + explicit FETCHLIST: stock groups reference a default
            // BASEUNITS (unit name) for their items, same crash class as Units.
            // CLOSINGBALANCE included so the controller's zero_balance filter works.
            $xml = TallyXmlBuilder::buildCollectionExportRequest(
                'List of Stock Groups',
                fetchFields: ['NAME', 'PARENT', 'BASEUNITS', 'CLOSINGBALANCE'],
                explode: false,
            );
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'STOCKGROUP');
        });
    }

    public function get(string $name): ?array
    {
        // Same Object-export hang as Group — filter from the cached collection list instead.
        return $this->cachedGet("stockgroup:{$name}", function () use ($name) {
            foreach ($this->list() as $row) {
                $rowName = $row['@attributes']['NAME'] ?? ($row['NAME']['#text'] ?? $row['NAME'] ?? null);
                if ($rowName !== null && strcasecmp((string) $rowName, $name) === 0) {
                    return $row;
                }
            }

            return null;
        });
    }

    /**
     * @param  array  $data  Keys: NAME, PARENT, etc.
     */
    public function create(array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::STOCK_GROUP, $data);
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKGROUP', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockgroup:list');
            app(AuditLogger::class)->log('create', 'STOCKGROUP', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::STOCK_GROUP, $data);
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKGROUP', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockgroup:list', "stockgroup:{$name}");
            app(AuditLogger::class)->log('alter', 'STOCKGROUP', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('STOCKGROUP', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockgroup:list', "stockgroup:{$name}");
            app(AuditLogger::class)->log('delete', 'STOCKGROUP', $name, [], $result);
        }

        return $result;
    }
}
