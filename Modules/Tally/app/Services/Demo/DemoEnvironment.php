<?php

namespace Modules\Tally\Services\Demo;

use Modules\Tally\Models\TallyConnection;
use Modules\Tally\Services\TallyHttpClient;

/**
 * Scopes a closure to the demo environment:
 *   - overrides config('tally.company') = 'SwatTech Demo'
 *   - rebinds TallyHttpClient to a DemoHttpClient pointing at the DEMO connection
 *   - rebinds TallyHttpClient::class in the container so existing services
 *     (LedgerService, GroupService, etc.) get the guarded client
 *
 * All three changes are process-local and do not persist past the command run.
 */
final class DemoEnvironment
{
    /**
     * Run the given closure with demo-scoped bindings.
     *
     * @template T
     *
     * @param  \Closure(DemoHttpClient, TallyConnection): T  $fn
     * @return T
     */
    public static function run(\Closure $fn): mixed
    {
        $connection = self::demoConnection();
        $client = new DemoHttpClient(
            $connection->host,
            $connection->port,
            DemoConstants::COMPANY,
            $connection->timeout,
        );

        $previousCompany = config('tally.company');
        config(['tally.company' => DemoConstants::COMPANY]);
        app()->instance(TallyHttpClient::class, $client);

        try {
            return $fn($client, $connection);
        } finally {
            config(['tally.company' => $previousCompany]);
            app()->forgetInstance(TallyHttpClient::class);
        }
    }

    /**
     * Return the DEMO connection or throw.
     */
    public static function demoConnection(): TallyConnection
    {
        $connection = TallyConnection::where('code', DemoConstants::CONNECTION_CODE)->first();

        if (! $connection) {
            throw new DemoSafetyException(
                "DEMO connection row not found. Run 'php artisan tally:demo seed' first.",
            );
        }

        if ($connection->company_name !== DemoConstants::COMPANY) {
            throw new DemoSafetyException(
                "DEMO connection is pointing at '{$connection->company_name}', not '".DemoConstants::COMPANY."'. "
                .'Refusing to proceed — this would mutate a non-demo company.',
            );
        }

        return $connection;
    }
}
