<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class CurrencyService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('currency:list', function () {
            // Inline TDL — see UnitService::list() for rationale.
            // FETCH limited to NAME (see PriceListService for crash rationale).
            $xml = TallyXmlBuilder::buildAdHocCollectionExportRequest(
                collectionName: 'TallyModuleCurrencies',
                tallyType: 'Currency',
                fetchFields: ['NAME'],
            );
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'CURRENCY');
        });
    }

    public function get(string $name): ?array
    {
        // Filter from cached list — see CostCenterService::get() for rationale.
        return $this->cachedGet("currency:{$name}", function () use ($name) {
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
     * @param  array  $data  Keys: NAME (symbol e.g. "$"), MAILINGNAME, ISSUFFIX, HASSYMBOLSPACE, DECIMALPLACES, FORMALNAME, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('CURRENCY', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('currency:list');
            app(AuditLogger::class)->log('create', 'CURRENCY', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('CURRENCY', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('currency:list', "currency:{$name}");
            app(AuditLogger::class)->log('alter', 'CURRENCY', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('CURRENCY', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('currency:list', "currency:{$name}");
            app(AuditLogger::class)->log('delete', 'CURRENCY', $name, [], $result);
        }

        return $result;
    }
}
