<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\Fields\TallyFieldRegistry;
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
            // Inline TDL collection definition — see UnitService::list() for the
            // rationale (built-in `List of X` collections aren't guaranteed for
            // every master type and silently hang Tally when missing).
            // TDL TYPE uses the concatenated form (per production Tally TDL
            // integrations — e.g. laxmantandon/tally_migration_tdl `send/*.txt`
            // uses `Type : StockItem` / `StockGroup`). Object SUBTYPEs take the
            // spaced form; TDL `<TYPE>` takes the concatenated form.
            // FETCH limited to NAME + PARENT (CATEGORY can be invalid TDL method).
            $xml = TallyXmlBuilder::buildAdHocCollectionExportRequest(
                collectionName: 'TallyModuleCostCentres',
                tallyType: 'CostCentre',
                fetchFields: ['NAME', 'PARENT'],
            );
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'COSTCENTRE');
        });
    }

    public function get(string $name): ?array
    {
        // Filter from cached list — Object export reliability has been a recurring
        // issue across master types in TallyPrime; the list is small enough that
        // filter-from-list is the safer default.
        return $this->cachedGet("costcentre:{$name}", function () use ($name) {
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
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::COST_CENTRE, $data);
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
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::COST_CENTRE, $data);
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
