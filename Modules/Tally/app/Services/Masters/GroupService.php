<?php

namespace Modules\Tally\Services\Masters;

use Modules\Tally\Services\AuditLogger;
use Modules\Tally\Services\Concerns\CachesMasterData;
use Modules\Tally\Services\TallyHttpClient;
use Modules\Tally\Services\TallyXmlBuilder;
use Modules\Tally\Services\TallyXmlParser;

class GroupService
{
    use CachesMasterData;

    public function __construct(
        private TallyHttpClient $client,
    ) {}

    public function list(): array
    {
        return $this->cachedList('group:list', function () {
            $xml = TallyXmlBuilder::buildCollectionExportRequest('List of Groups');
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractCollection($response, 'GROUP');
        });
    }

    public function get(string $name): ?array
    {
        return $this->cachedGet("group:{$name}", function () use ($name) {
            $xml = TallyXmlBuilder::buildObjectExportRequest('Group', $name);
            $response = $this->client->sendXml($xml);

            return TallyXmlParser::extractObject($response, 'GROUP');
        });
    }

    /**
     * @param  array  $data  Keys: NAME, PARENT, etc.
     */
    public function create(array $data): array
    {
        $xml = TallyXmlBuilder::buildImportMasterRequest('GROUP', $data, 'Create');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('group:list');
            app(AuditLogger::class)->log('create', 'GROUP', $data['NAME'] ?? null, $data, $result);
        }

        return $result;
    }

    public function update(string $name, array $data): array
    {
        $data['NAME'] = $name;
        $xml = TallyXmlBuilder::buildImportMasterRequest('GROUP', $data, 'Alter');
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('group:list', "group:{$name}");
            app(AuditLogger::class)->log('alter', 'GROUP', $name, $data, $result);
        }

        return $result;
    }

    public function delete(string $name): array
    {
        $xml = TallyXmlBuilder::buildDeleteMasterRequest('GROUP', $name);
        $response = $this->client->sendXml($xml);
        $result = TallyXmlParser::parseImportResult($response);

        if ($result['errors'] === 0) {
            $this->invalidateCache('group:list', "group:{$name}");
            app(AuditLogger::class)->log('delete', 'GROUP', $name, [], $result);
        }

        return $result;
    }
}
