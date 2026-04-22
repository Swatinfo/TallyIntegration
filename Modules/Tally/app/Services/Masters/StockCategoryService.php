<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\Fields\TallyFieldRegistry;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class StockCategoryService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('stockcategory:list', function () {
            // Inline TDL — see UnitService::list() for rationale.
            // TDL TYPE uses concatenated form (production Tally TDL convention —
            // see CostCenterService::list for rationale).
            $xml = TallyXmlBuilder::buildAdHocCollectionExportRequest(
                collectionName: 'TallyModuleStockCategories',
                tallyType: 'StockCategory',
                fetchFields: ['NAME', 'PARENT'],
            );
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'STOCKCATEGORY');
        });
    }

    public function get(string $name): ?array
    {
        // Filter from cached list — see CostCenterService::get() for rationale.
        return $this->cachedGet("stockcategory:{$name}", function () use ($name) {
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
     * @param  array  $data  Keys: NAME, PARENT.
     */
    public function create(array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::STOCK_CATEGORY, $data);
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKCATEGORY', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockcategory:list');
            app(AuditLogger::class)->log('create', 'STOCKCATEGORY', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::STOCK_CATEGORY, $data);
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('STOCKCATEGORY', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockcategory:list', "stockcategory:{$name}");
            app(AuditLogger::class)->log('alter', 'STOCKCATEGORY', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('STOCKCATEGORY', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('stockcategory:list', "stockcategory:{$name}");
            app(AuditLogger::class)->log('delete', 'STOCKCATEGORY', $name, [], $result);
        }

        return $result;
    }
}
