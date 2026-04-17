<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Tally\Exceptions\TallyConnectionException;
use Modules\Tally\Exceptions\TallyImportException;
use Modules\Tally\Exceptions\TallyResponseException;
use Modules\Tally\Exceptions\TallyValidationException;
use Modules\Tally\Exceptions\TallyXmlParseException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->renderable(function (TallyConnectionException $e) {
            return response()->json([
                'success' => false,
                'data' => ['host' => $e->host, 'port' => $e->port, 'connection' => $e->connectionCode],
                'message' => $e->getMessage(),
            ], 503);
        });

        $exceptions->renderable(function (TallyResponseException $e) {
            return response()->json([
                'success' => false,
                'data' => ['http_status' => $e->httpStatus],
                'message' => $e->getMessage(),
            ], 502);
        });

        $exceptions->renderable(function (TallyImportException $e) {
            return response()->json([
                'success' => false,
                'data' => ['import_result' => $e->importResult, 'line_errors' => $e->lineErrors],
                'message' => $e->getMessage(),
            ], 422);
        });

        $exceptions->renderable(function (TallyXmlParseException $e) {
            return response()->json([
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ], 502);
        });

        $exceptions->renderable(function (TallyValidationException $e) {
            return response()->json([
                'success' => false,
                'data' => ['errors' => $e->errors],
                'message' => $e->getMessage(),
            ], 400);
        });
    })->create();
