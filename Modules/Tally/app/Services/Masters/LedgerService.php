<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\Fields\TallyFieldRegistry;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class LedgerService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('ledger:list', function () {
            // Tally's built-in collection is "List of Ledgers". An older name
            // ("List of Accounts") yields TDL error: 'Could not find description'.
            $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Ledgers');
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'LEDGER');
        });
    }

    public function get(string $name): ?array
    {
        // Filter from cached list — Object exports have proven unreliable across
        // master types in TallyPrime (hangs / crashes). The list is cached and
        // shared with the index endpoint, so per-name lookups are O(n) on the
        // first call then O(1) until invalidation. For very large ledger sets
        // (10K+) consider switching to Object export with a connection-level
        // health probe and short timeout.
        return $this->cachedGet("ledger:{$name}", function () use ($name) {
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
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::LEDGER, $data);
        $xml = TallyXmlBuilder::buildImportMasterRequest('LEDGER', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('ledger:list');
            app(AuditLogger::class)->log('create', 'LEDGER', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::LEDGER, $data);
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('LEDGER', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('ledger:list', "ledger:{$name}");
            app(AuditLogger::class)->log('alter', 'LEDGER', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('LEDGER', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('ledger:list', "ledger:{$name}");
            app(AuditLogger::class)->log('delete', 'LEDGER', $name, [], $result);
        }

        return $result;
    }
}
