<?php

namespace Modules\Tally\Tests\Concerns;

use Mockery;
use Modules\Tally\Services\TallyHttpClient;

trait MocksTallyClient
{
    /** @var array<int, string> Captured request XML payloads in call order. */
    protected array $capturedTallyRequests = [];

    /**
     * Mock TallyHttpClient to return a fixed XML response.
     *
     * Each XML payload passed to sendXml() is captured into
     * $this->capturedTallyRequests so tests can assert on outbound requests.
     */
    protected function mockTallyClient(string $responseXml): TallyHttpClient
    {
        $this->capturedTallyRequests = [];

        $mock = Mockery::mock(TallyHttpClient::class);
        $mock->shouldReceive('sendXml')->andReturnUsing(function (string $xml) use ($responseXml) {
            $this->capturedTallyRequests[] = $xml;

            return $responseXml;
        });
        $mock->shouldReceive('isConnected')->andReturn(true);
        $mock->shouldReceive('getCompanies')->andReturn(['Test Company']);
        $mock->shouldReceive('getUrl')->andReturn('http://localhost:9000');
        $mock->shouldReceive('getCompany')->andReturn('Test Company');
        $mock->shouldReceive('getConnectionCode')->andReturn('default');

        $this->app->instance(TallyHttpClient::class, $mock);

        return $mock;
    }

    /**
     * Return the most recent XML payload sent to TallyHttpClient::sendXml(),
     * or null if none have been captured yet.
     */
    protected function lastTallyRequestXml(): ?string
    {
        return $this->capturedTallyRequests === []
            ? null
            : $this->capturedTallyRequests[count($this->capturedTallyRequests) - 1];
    }

    /**
     * Mock TallyHttpClient to return sequential responses.
     */
    protected function mockTallyClientSequence(array $responses): TallyHttpClient
    {
        $this->capturedTallyRequests = [];
        $index = 0;

        $mock = Mockery::mock(TallyHttpClient::class);
        $mock->shouldReceive('sendXml')->andReturnUsing(function (string $xml) use ($responses, &$index) {
            $this->capturedTallyRequests[] = $xml;
            $response = $responses[$index] ?? $responses[count($responses) - 1];
            $index++;

            return $response;
        });
        $mock->shouldReceive('isConnected')->andReturn(true);
        $mock->shouldReceive('getCompanies')->andReturn(['Test Company']);
        $mock->shouldReceive('getUrl')->andReturn('http://localhost:9000');
        $mock->shouldReceive('getCompany')->andReturn('Test Company');
        $mock->shouldReceive('getConnectionCode')->andReturn('default');

        $this->app->instance(TallyHttpClient::class, $mock);

        return $mock;
    }

    /**
     * Load a test fixture XML file.
     */
    protected function fixture(string $name): string
    {
        $path = __DIR__.'/../Fixtures/Xml/'.$name;

        if (! file_exists($path)) {
            throw new \RuntimeException("Test fixture not found: {$path}");
        }

        return file_get_contents($path);
    }
}
