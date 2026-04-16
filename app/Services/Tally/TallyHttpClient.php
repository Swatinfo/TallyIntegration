<?php

namespace App\Services\Tally;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TallyHttpClient
{
    private string $url;

    private int $timeout;

    public function __construct()
    {
        $host = config('tally.host');
        $port = config('tally.port');
        $this->url = "http://{$host}:{$port}";
        $this->timeout = config('tally.timeout', 30);
    }

    /**
     * Send an XML request to TallyPrime and return the raw XML response.
     *
     * @throws RuntimeException
     */
    public function sendXml(string $xml): string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                ])
                ->withBody($xml, 'text/xml')
                ->post($this->url);

            if ($response->failed()) {
                throw new RuntimeException(
                    "TallyPrime returned HTTP {$response->status()}: {$response->body()}"
                );
            }

            return $response->body();
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "Cannot connect to TallyPrime at {$this->url}. Ensure Tally is running in server mode. Error: {$e->getMessage()}"
            );
        }
    }

    /**
     * Check if TallyPrime is reachable.
     */
    public function isConnected(): bool
    {
        try {
            $xml = TallyXmlBuilder::buildExportRequest('List of Companies');
            $this->sendXml($xml);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * Get the list of companies loaded in TallyPrime.
     *
     * @return array<string>
     */
    public function getCompanies(): array
    {
        $xml = TallyXmlBuilder::buildExportRequest('List of Companies');
        $response = $this->sendXml($xml);

        return TallyXmlParser::extractCompanyList($response);
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
