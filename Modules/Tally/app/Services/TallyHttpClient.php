<?php

namespace Modules\Tally\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Tally\Exceptions\TallyConnectionException;
use Modules\Tally\Exceptions\TallyResponseException;

class TallyHttpClient
{
    private string $url;

    private int $timeout;

    private string $company;

    public function __construct(string $host, int $port, string $company = '', int $timeout = 30)
    {
        $this->url = "http://{$host}:{$port}";
        $this->company = $company;
        $this->timeout = $timeout;
    }

    /**
     * Create from config values (fallback for default connection).
     */
    public static function fromConfig(): static
    {
        return new static(
            config('tally.host', 'localhost'),
            (int) config('tally.port', 9000),
            config('tally.company', ''),
            (int) config('tally.timeout', 30),
        );
    }

    public function getCompany(): string
    {
        return $this->company;
    }

    /**
     * Send an XML request to TallyPrime and return the raw XML response.
     *
     * @throws TallyConnectionException
     * @throws TallyResponseException
     */
    public function sendXml(string $xml): string
    {
        $startTime = microtime(true);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Content-Type' => 'text/xml; charset=utf-8',
                ])
                ->withBody($xml, 'text/xml')
                ->post($this->url);

            $durationMs = (microtime(true) - $startTime) * 1000;

            if ($response->failed()) {
                $this->logError($xml, "HTTP {$response->status()}", $durationMs);

                throw new TallyResponseException(
                    "TallyPrime returned HTTP {$response->status()}",
                    $response->status(),
                    $response->body(),
                );
            }

            $this->logRequest($xml, $response->body(), $durationMs);

            return $response->body();
        } catch (ConnectionException $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;
            $this->logError($xml, $e->getMessage(), $durationMs);

            throw new TallyConnectionException(
                "Cannot connect to TallyPrime at {$this->url}. Ensure Tally is running in server mode.",
                parse_url($this->url, PHP_URL_HOST) ?: '',
                (int) (parse_url($this->url, PHP_URL_PORT) ?: 0),
            );
        }
    }

    private function logRequest(string $requestXml, string $responseXml, float $durationMs): void
    {
        try {
            app(TallyRequestLogger::class)->log($requestXml, $responseXml, $durationMs);
        } catch (\Throwable) {
            // Don't let logging failures break the main flow
        }
    }

    private function logError(string $requestXml, string $error, float $durationMs): void
    {
        try {
            app(TallyRequestLogger::class)->logError($requestXml, $error, $durationMs);
        } catch (\Throwable) {
            // Don't let logging failures break the main flow
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
        } catch (TallyConnectionException|TallyResponseException) {
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
