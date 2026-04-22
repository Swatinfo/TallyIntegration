<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class VoucherTypeService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('vouchertype:list', function () {
            // EXPLODEFLAG=No + explicit FETCHLIST: voucher types inherit from a
            // parent base type — keep the list lean and avoid recursive expansion.
            // Inline TDL — see UnitService::list() for rationale.
            // TDL TYPE uses concatenated form (production Tally TDL convention —
            // see CostCenterService::list for rationale).
            // FETCH limited to NAME + PARENT (others can be invalid TDL methods).
            $xml = TallyXmlBuilder::buildAdHocCollectionExportRequest(
                collectionName: 'TallyModuleVoucherTypes',
                tallyType: 'VoucherType',
                fetchFields: ['NAME', 'PARENT'],
            );
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'VOUCHERTYPE');
        });
    }

    public function get(string $name): ?array
    {
        // Object export of <SUBTYPE>Voucher Type</SUBTYPE> can hang or crash TallyPrime
        // (voucher types reference parent base type). Filter from the safe list.
        return $this->cachedGet("vouchertype:{$name}", function () use ($name) {
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
     * @param  array  $data  Keys: NAME, PARENT (base type e.g. "Sales"), ABBR, NUMBERINGMETHOD, ISDEEMEDPOSITIVE, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('VOUCHERTYPE', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('vouchertype:list');
            app(AuditLogger::class)->log('create', 'VOUCHERTYPE', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('VOUCHERTYPE', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('vouchertype:list', "vouchertype:{$name}");
            app(AuditLogger::class)->log('alter', 'VOUCHERTYPE', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('VOUCHERTYPE', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('vouchertype:list', "vouchertype:{$name}");
            app(AuditLogger::class)->log('delete', 'VOUCHERTYPE', $name, [], $result);
        }

        return $result;
    }
}
