<?php

namespace Modules\Tally\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\Tally\Exceptions\TallyConnectionException;
use Modules\Tally\Exceptions\TallyResponseException;
use Modules\Tally\Logging\TallyLogChannel;

class TallyHttpClient
{
    private string $url;

    private int $timeout;

    private string $company;

    private string $connectionCode;

    public function __construct(
        string $host,
        int $port,
        string $company = '',
        int $timeout = 30,
        string $connectionCode = 'default',
    ) {
        $this->url = "http://{$host}:{$port}";
        $this->company = $company;
        $this->timeout = $timeout;
        $this->connectionCode = $connectionCode;
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

    public function getConnectionCode(): string
    {
        return $this->connectionCode;
    }

    /**
     * Send an XML request to TallyPrime and return the raw XML response.
     *
     * @throws TallyConnectionException
     * @throws TallyResponseException
     */
    public function sendXml(string $xml): string
    {
        TallyLogChannel::ensureTodayLogFile();

        $breaker = app(CircuitBreaker::class);
        $breaker->assertAvailable($this->connectionCode);

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
                $breaker->recordFailure($this->connectionCode);

                throw new TallyResponseException(
                    "TallyPrime returned HTTP {$response->status()}",
                    $response->status(),
                    $response->body(),
                );
            }

            $this->logRequest($xml, $response->body(), $durationMs);
            $breaker->recordSuccess($this->connectionCode);

            return $response->body();
        } catch (ConnectionException $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;
            $this->logError($xml, $e->getMessage(), $durationMs);
            $breaker->recordFailure($this->connectionCode);

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
            // Empty $company suppresses <SVCURRENTCOMPANY>: the List-of-Companies
            // report is a global Tally collection; pinning it to one company
            // scopes the response away from the thing we're asking about.
            $xml = TallyXmlBuilder::buildExportRequest('List of Companies', company: '');
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
        // See isConnected() for why $company is empty.
        $xml = TallyXmlBuilder::buildExportRequest('List of Companies', company: '');
        $response = $this->sendXml($xml);

        return TallyXmlParser::extractCompanyList($response);
    }

    public function getUrl(): string
    {
        return $this->url;
    }
}
