<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\Fields\TallyFieldRegistry;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class CostCategoryService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('costcategory:list', function () {
            $xml = TallyXmlBuilder::buildAdHocCollectionExportRequest(
                collectionName: 'TallyModuleCostCategories',
                tallyType: 'CostCategory',
                fetchFields: ['NAME'],
            );
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'COSTCATEGORY');
        });
    }

    public function get(string $name): ?array
    {
        return $this->cachedGet("costcategory:{$name}", function () use ($name) {
            foreach ($this->list() as $row) {
                $rowName = $row['@attributes']['NAME'] ?? ($row['NAME']['#text'] ?? $row['NAME'] ?? null);
                if ($rowName !== null && strcasecmp((string) $rowName, $name) === 0) {
                    return $row;
                }
            }

            return null;
        });
    }

    public function create(array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::COST_CATEGORY, $data);
        $xml = TallyXmlBuilder::buildImportMasterRequest('COSTCATEGORY', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('costcategory:list');
            app(AuditLogger::class)->log('create', 'COSTCATEGORY', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::COST_CATEGORY, $data);
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('COSTCATEGORY', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('costcategory:list', "costcategory:{$name}");
            app(AuditLogger::class)->log('alter', 'COSTCATEGORY', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('COSTCATEGORY', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('costcategory:list', "costcategory:{$name}");
            app(AuditLogger::class)->log('delete', 'COSTCATEGORY', $name, [], $result);
        }

        return $result;
    }
}
