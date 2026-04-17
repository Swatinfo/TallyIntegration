<?php

use Illuminate\Support\Facades\Route;
use Modules\Tally\Http\Controllers\AuditLogController;
use Modules\Tally\Http\Controllers\GroupController;
use Modules\Tally\Http\Controllers\HealthController;
use Modules\Tally\Http\Controllers\LedgerController;
use Modules\Tally\Http\Controllers\ReportController;
use Modules\Tally\Http\Controllers\StockItemController;
use Modules\Tally\Http\Controllers\SyncController;
use Modules\Tally\Http\Controllers\TallyConnectionController;
use Modules\Tally\Http\Controllers\VoucherController;
use Modules\Tally\Http\Middleware\CheckTallyPermission;
use Modules\Tally\Http\Middleware\ResolveTallyConnection;

/*
|--------------------------------------------------------------------------
| Tally Module API Routes
|--------------------------------------------------------------------------
|
| All routes prefixed /api/tally/ by RouteServiceProvider.
| Auth: Sanctum token required. Permissions checked per route group.
|
*/

Route::middleware(['auth:sanctum', 'throttle:tally-api'])->group(function () {
    // Audit logs
    Route::middleware(CheckTallyPermission::class.':manage_connections')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });

    // Connection management
    Route::middleware(CheckTallyPermission::class.':manage_connections')->group(function () {
        Route::apiResource('connections', TallyConnectionController::class)
            ->parameters(['connections' => 'connection']);
        Route::get('connections/{connection}/sync-stats', [SyncController::class, 'stats'])->name('sync.stats');
        Route::get('connections/{connection}/sync-pending', [SyncController::class, 'pending'])->name('sync.pending');
        Route::get('connections/{connection}/sync-conflicts', [SyncController::class, 'conflicts'])->name('sync.conflicts');
        Route::post('sync/{sync}/resolve', [SyncController::class, 'resolveConflict'])->name('sync.resolve');
        Route::post('connections/{connection}/sync-from-tally', [SyncController::class, 'triggerInbound'])->name('sync.inbound');
        Route::post('connections/{connection}/sync-to-tally', [SyncController::class, 'triggerOutbound'])->name('sync.outbound');
        Route::post('connections/{connection}/sync-full', [SyncController::class, 'triggerFull'])->name('sync.full');
        Route::get('connections/{connection}/health', [TallyConnectionController::class, 'health'])
            ->name('connections.health');
        Route::get('connections/{connection}/metrics', [TallyConnectionController::class, 'metrics'])
            ->name('connections.metrics');
        Route::post('connections/{connection}/discover', [TallyConnectionController::class, 'discover'])
            ->name('connections.discover');
        Route::post('connections/test', [TallyConnectionController::class, 'test'])
            ->name('connections.test');
    });

    // Default health check
    Route::get('health', HealthController::class)->name('health');

    // Connection-specific routes
    Route::prefix('{connection}')->middleware(ResolveTallyConnection::class)->group(function () {
        Route::get('health', HealthController::class)->name('connection.health');

        // Read-only master routes
        Route::middleware(CheckTallyPermission::class.':view_masters')->group(function () {
            Route::get('ledgers', [LedgerController::class, 'index'])->name('ledgers.index');
            Route::get('ledgers/{name}', [LedgerController::class, 'show'])->name('ledgers.show');
            Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
            Route::get('groups/{name}', [GroupController::class, 'show'])->name('groups.show');
            Route::get('stock-items', [StockItemController::class, 'index'])->name('stock-items.index');
            Route::get('stock-items/{name}', [StockItemController::class, 'show'])->name('stock-items.show');
        });

        // Write master routes
        Route::middleware([CheckTallyPermission::class.':manage_masters', 'throttle:tally-write'])->group(function () {
            Route::post('ledgers', [LedgerController::class, 'store'])->name('ledgers.store');
            Route::put('ledgers/{name}', [LedgerController::class, 'update'])->name('ledgers.update');
            Route::patch('ledgers/{name}', [LedgerController::class, 'update']);
            Route::delete('ledgers/{name}', [LedgerController::class, 'destroy'])->name('ledgers.destroy');

            Route::post('groups', [GroupController::class, 'store'])->name('groups.store');
            Route::put('groups/{name}', [GroupController::class, 'update'])->name('groups.update');
            Route::patch('groups/{name}', [GroupController::class, 'update']);
            Route::delete('groups/{name}', [GroupController::class, 'destroy'])->name('groups.destroy');

            Route::post('stock-items', [StockItemController::class, 'store'])->name('stock-items.store');
            Route::put('stock-items/{name}', [StockItemController::class, 'update'])->name('stock-items.update');
            Route::patch('stock-items/{name}', [StockItemController::class, 'update']);
            Route::delete('stock-items/{name}', [StockItemController::class, 'destroy'])->name('stock-items.destroy');
        });

        // Read vouchers
        Route::middleware(CheckTallyPermission::class.':view_vouchers')->group(function () {
            Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
            Route::get('vouchers/{masterID}', [VoucherController::class, 'show'])->name('vouchers.show');
        });

        // Write vouchers
        Route::middleware([CheckTallyPermission::class.':manage_vouchers', 'throttle:tally-write'])->group(function () {
            Route::post('vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
            Route::put('vouchers/{masterID}', [VoucherController::class, 'update'])->name('vouchers.update');
            Route::patch('vouchers/{masterID}', [VoucherController::class, 'update']);
            Route::delete('vouchers/{masterID}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');
        });

        // Reports (read-only, separate rate limit)
        Route::middleware([CheckTallyPermission::class.':view_reports', 'throttle:tally-reports'])->group(function () {
            Route::get('reports/{type}', [ReportController::class, 'show'])->name('connection.reports.show');
        });
    });
});
