<?php

namespace Modules\Tally\Tests\Concerns;

use Mockery;
use Modules\Tally\Services\TallyHttpClient;

trait MocksTallyClient
{
    /**
     * Mock TallyHttpClient to return a fixed XML response.
     */
    protected function mockTallyClient(string $responseXml): TallyHttpClient
    {
        $mock = Mockery::mock(TallyHttpClient::class);
        $mock->shouldReceive('sendXml')->andReturn($responseXml);
        $mock->shouldReceive('isConnected')->andReturn(true);
        $mock->shouldReceive('getCompanies')->andReturn(['Test Company']);
        $mock->shouldReceive('getUrl')->andReturn('http://localhost:9000');
        $mock->shouldReceive('getCompany')->andReturn('Test Company');

        $this->app->instance(TallyHttpClient::class, $mock);

        return $mock;
    }

    /**
     * Mock TallyHttpClient to return sequential responses.
     */
    protected function mockTallyClientSequence(array $responses): TallyHttpClient
    {
        $mock = Mockery::mock(TallyHttpClient::class);
        $mock->shouldReceive('sendXml')->andReturnValues($responses);
        $mock->shouldReceive('isConnected')->andReturn(true);
        $mock->shouldReceive('getCompanies')->andReturn(['Test Company']);
        $mock->shouldReceive('getUrl')->andReturn('http://localhost:9000');
        $mock->shouldReceive('getCompany')->andReturn('Test Company');

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
