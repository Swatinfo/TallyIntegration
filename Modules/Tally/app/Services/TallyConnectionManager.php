<?php

namespace Modules\Tally\Services;

use Modules\Tally\Models\TallyConnection;
use RuntimeException;

class TallyConnectionManager
{
    /** @var array<string, TallyHttpClient> */
    private array $clients = [];

    /**
     * Resolve a connection code to a TallyHttpClient.
     */
    public function resolve(string $code): TallyHttpClient
    {
        if (isset($this->clients[$code])) {
            return $this->clients[$code];
        }

        $connection = TallyConnection::where('code', $code)
            ->where('is_active', true)
            ->first();

        if (! $connection) {
            throw new RuntimeException("Tally connection '{$code}' not found or inactive.");
        }

        $this->clients[$code] = new TallyHttpClient(
            $connection->host,
            $connection->port,
            $connection->company_name,
            $connection->timeout,
            $code,
        );

        return $this->clients[$code];
    }

    /**
     * Create a TallyHttpClient from a TallyConnection model instance.
     */
    public function fromConnection(TallyConnection $connection): TallyHttpClient
    {
        $code = $connection->code;

        if (! isset($this->clients[$code])) {
            $this->clients[$code] = new TallyHttpClient(
                $connection->host,
                $connection->port,
                $connection->company_name,
                $connection->timeout,
                $code,
            );
        }

        return $this->clients[$code];
    }

    /**
     * Flush cached client instances (useful after connection config changes).
     */
    public function flush(?string $code = null): void
    {
        if ($code) {
            unset($this->clients[$code]);
        } else {
            $this->clients = [];
        }
    }
}
