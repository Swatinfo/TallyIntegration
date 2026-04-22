<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\Fields\TallyFieldRegistry;
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
            // TallyPrime has NO built-in `List of Units` collection — sending that ID
            // returns `Error in TDL, 'Collection: List of Units' Could not find description`
            // and blocks Tally's HTTP responder behind a UI dialog (reproduced 2026-04-19,
            // crashes Tally entirely after timeout). Define the collection inline via TDL
            // injection — Tally's canonical pattern for ad-hoc master collections.
            // FETCH limited to NAME — extra fields can crash Tally if the
            // method name doesn't match a TDL accessor on the type. The smoke
            // test only needs NAME for filter-from-list; richer responses are a
            // separate API concern.
            $xml = TallyXmlBuilder::buildAdHocCollectionExportRequest(
                collectionName: 'TallyModuleUnits',
                tallyType: 'Unit',
                fetchFields: ['NAME'],
            );
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'UNIT');
        });
    }

    public function get(string $name): ?array
    {
        // Object export of <SUBTYPE>Unit</SUBTYPE> can crash TallyPrime
        // (compound units recursively reference BASEUNITS / ADDITIONALUNITS).
        // Filter from the safe explode=false list instead. Reproduced 2026-04-19:
        // GET /units/Nos returned 503 then Tally became unreachable.
        return $this->cachedGet("unit:{$name}", function () use ($name) {
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
     * @param  array  $data  Keys: NAME, ISSIMPLEUNIT (Yes/No), BASEUNITS, ADDITIONALUNITS, CONVERSION, etc.
     */
    public function create(array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::UNIT, $data);
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
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::UNIT, $data);
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
