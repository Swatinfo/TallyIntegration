<?php

use App\Http\Controllers\Api\Tally\GroupController;
use App\Http\Controllers\Api\Tally\HealthController;
use App\Http\Controllers\Api\Tally\LedgerController;
use App\Http\Controllers\Api\Tally\ReportController;
use App\Http\Controllers\Api\Tally\StockItemController;
use App\Http\Controllers\Api\Tally\TallyConnectionController;
use App\Http\Controllers\Api\Tally\VoucherController;
use App\Http\Middleware\ResolveTallyConnection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
| Tally Integration Routes
|--------------------------------------------------------------------------
*/

Route::prefix('tally')->group(function () {
    // Connection management (no Tally middleware needed)
    Route::apiResource('connections', TallyConnectionController::class)
        ->parameters(['connections' => 'connection']);
    Route::get('connections/{connection}/health', [TallyConnectionController::class, 'health'])
        ->name('tally.connections.health');

    // Default health check (uses config-based connection)
    Route::get('health', HealthController::class)->name('tally.health');

    // Connection-specific routes: /api/tally/{connection}/ledgers, etc.
    Route::prefix('{connection}')->middleware(ResolveTallyConnection::class)->group(function () {
        Route::get('health', HealthController::class)->name('tally.connection.health');

        Route::apiResource('ledgers', LedgerController::class)
            ->parameters(['ledgers' => 'name']);

        Route::apiResource('groups', GroupController::class)
            ->parameters(['groups' => 'name']);

        Route::apiResource('stock-items', StockItemController::class)
            ->parameters(['stock-items' => 'name']);

        Route::apiResource('vouchers', VoucherController::class)
            ->parameters(['vouchers' => 'masterID']);

        Route::get('reports/{type}', [ReportController::class, 'show'])
            ->name('tally.connection.reports.show');
    });
});
