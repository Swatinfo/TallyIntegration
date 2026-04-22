<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\Fields\TallyFieldRegistry;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class EmployeeGroupService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('employeegroup:list', function () {
            $xml = TallyXmlBuilder::buildAdHocCollectionExportRequest(
                collectionName: 'TallyModuleEmployeeGroups',
                tallyType: 'CostCentre',
                fetchFields: ['NAME', 'PARENT', 'CATEGORY'],
            );
            $response = $this->client->sendXml($xml);

            // Employee Groups are stored as Cost Centres with CATEGORY = "Employees"
            // (or user-defined employee category). Filter on the list after fetch.
            return array_values(array_filter(
                TallyXmlParser::extractCollection($response, 'COSTCENTRE'),
                fn ($row) => ! empty($row['CATEGORY']),
            ));
        });
    }

    public function get(string $name): ?array
    {
        return $this->cachedGet("employeegroup:{$name}", function () use ($name) {
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
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::EMPLOYEE_GROUP, $data);
        $xml = TallyXmlBuilder::buildImportMasterRequest('COSTCENTRE', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('employeegroup:list');
            app(AuditLogger::class)->log('create', 'EMPLOYEEGROUP', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data = TallyFieldRegistry::canonicalize(TallyFieldRegistry::EMPLOYEE_GROUP, $data);
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('COSTCENTRE', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('employeegroup:list', "employeegroup:{$name}");
            app(AuditLogger::class)->log('alter', 'EMPLOYEEGROUP', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('COSTCENTRE', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('employeegroup:list', "employeegroup:{$name}");
            app(AuditLogger::class)->log('delete', 'EMPLOYEEGROUP', $name, [], $result);
        }

        return $result;
    }
}
