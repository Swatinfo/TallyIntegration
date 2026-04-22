<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

/**
 * Price Levels live on the Company master's PRICELEVELLIST — NOT as a standalone
 * PRICELEVEL master. Tally's own export/import FAQ and two independent reference
 * integrations (laxmantandon/express_tally — "Planned Feature"; aadil-sengupta/Tally.Py —
 * no support) both confirm this architectural constraint.
 *
 * This service therefore routes every call through Company Object export + Company ALTER:
 *  - list():    <TYPE>Object</TYPE><SUBTYPE>Company</SUBTYPE> with FETCH PRICELEVELLIST
 *  - create():  COMPANY ACTION="Alter" with a new PRICELEVELLIST.LIST entry
 *  - update():  rename is not supported by Tally's XML API; returns a not-supported result
 *  - delete():  removal of a sub-list entry is not supported by Tally's XML API
 *
 * Item rates per price level live on Stock Items (PRICELEVELLIST on STOCKITEM); manage those
 * via StockItemService::update with the PRICELEVELLIST payload.
 */
class PriceListService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('pricelevel:list', function () {
            $companyName = $this->client->getCompany();
            if ($companyName === '') {
                return [];
            }

            $xml = TallyXmlBuilder::buildObjectExportRequest(
                objectType: 'Company',
                objectName: $companyName,
                fetchFields: ['PRICELEVELLIST'],
            );
            $response = $this->client->sendXml($xml);
            $company = TallyXmlParser::extractObject($response, 'COMPANY');

            return $this->extractPriceLevels($company);
        });
    }

    public function get(string $name): ?array
    {
        return $this->cachedGet("pricelevel:{$name}", function () use ($name) {
            foreach ($this->list() as $row) {
                $rowName = $row['NAME'] ?? null;
                if ($rowName !== null && strcasecmp((string) $rowName, $name) === 0) {
                    return $row;
                }
            }

            return null;
        });
    }

    /**
     * Add a new price level name to the Company's PRICELEVELLIST.
     *
     * @param  array  $data  Key: NAME.
     */
    public function create(array $data): array
    {
        $name = (string) ($data['NAME'] ?? '');
        if ($name === '') {
            return $this->failureResult('NAME is required for price level create');
        }

        $companyName = $this->client->getCompany();
        if ($companyName === '') {
            return $this->failureResult('No active company — cannot alter price levels');
        }

        $xml = TallyXmlBuilder::buildCompanyAlterRequest(
            companyName: $companyName,
            companyData: [
                'PRICELEVELLIST.LIST' => [
                    'NAME' => $name,
                ],
            ],
        );
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0 && ($result['line_error'] ?? null) === null) {
            $this->invalidateCache('pricelevel:list');
            app(AuditLogger::class)->log('create', 'PRICELEVEL', $name, $data, $result);
        }

        return $result;
    }

    /**
     * Tally's XML API does not support renaming a price level.
     * Callers should remove+create via the Tally UI (F11 → Price Levels).
     */
    public function update(string $name, array $data): array
    {
        return $this->failureResult(
            'Price level rename is not supported via Tally XML. Manage via F11 → Price Levels in TallyPrime.'
        );
    }

    /**
     * Tally's XML API does not support removing an entry from Company.PRICELEVELLIST.
     * Callers should remove via the Tally UI (F11 → Price Levels).
     */
    public function delete(string $name): array
    {
        return $this->failureResult(
            'Price level delete is not supported via Tally XML. Manage via F11 → Price Levels in TallyPrime.'
        );
    }

    /**
     * @return array<int, array{NAME: string}>
     */
    private function extractPriceLevels(?array $company): array
    {
        if ($company === null) {
            return [];
        }

        $list = $company['PRICELEVELLIST.LIST'] ?? $company['PRICELEVELLIST'] ?? null;
        if ($list === null) {
            return [];
        }

        if (isset($list['NAME']) || isset($list['#text'])) {
            $list = [$list];
        }

        $levels = [];
        foreach ($list as $entry) {
            $entryName = $entry['NAME']['#text'] ?? $entry['NAME'] ?? $entry['#text'] ?? null;
            if (is_string($entryName) && $entryName !== '') {
                $levels[] = ['NAME' => $entryName];
            }
        }

        return $levels;
    }

    /**
     * @return array{created: int, altered: int, deleted: int, errors: int, exceptions: int, line_error: string|null, lastvchid: string, lastmid: string, combined: int, ignored: int, cancelled: int}
     */
    private function failureResult(string $message): array
    {
        return [
            'created' => 0,
            'altered' => 0,
            'deleted' => 0,
            'lastvchid' => '0',
            'lastmid' => '0',
            'combined' => 0,
            'ignored' => 0,
            'errors' => 1,
            'exceptions' => 0,
            'cancelled' => 0,
            'line_error' => $message,
        ];
    }
}
