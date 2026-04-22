<?php

use Illuminate\Support\Facades\Route;
use Modules\Tally\Http\Controllers\AttachmentController;
use Modules\Tally\Http\Controllers\AttendanceTypeController;
use Modules\Tally\Http\Controllers\AuditLogController;
use Modules\Tally\Http\Controllers\BankingController;
use Modules\Tally\Http\Controllers\BranchController;
use Modules\Tally\Http\Controllers\CompanyController;
use Modules\Tally\Http\Controllers\ConsolidatedReportController;
use Modules\Tally\Http\Controllers\CostCategoryController;
use Modules\Tally\Http\Controllers\CostCenterController;
use Modules\Tally\Http\Controllers\CurrencyController;
use Modules\Tally\Http\Controllers\DraftVoucherController;
use Modules\Tally\Http\Controllers\EmployeeCategoryController;
use Modules\Tally\Http\Controllers\EmployeeController;
use Modules\Tally\Http\Controllers\EmployeeGroupController;
use Modules\Tally\Http\Controllers\GodownController;
use Modules\Tally\Http\Controllers\GroupController;
use Modules\Tally\Http\Controllers\HealthController;
use Modules\Tally\Http\Controllers\ImportController;
use Modules\Tally\Http\Controllers\IntegrationController;
use Modules\Tally\Http\Controllers\InventoryController;
use Modules\Tally\Http\Controllers\LedgerController;
use Modules\Tally\Http\Controllers\ManufacturingController;
use Modules\Tally\Http\Controllers\MasterMappingController;
use Modules\Tally\Http\Controllers\OperationsController;
use Modules\Tally\Http\Controllers\OrganizationController;
use Modules\Tally\Http\Controllers\PriceListController;
use Modules\Tally\Http\Controllers\RecurringVoucherController;
use Modules\Tally\Http\Controllers\ReportController;
use Modules\Tally\Http\Controllers\StockCategoryController;
use Modules\Tally\Http\Controllers\StockGroupController;
use Modules\Tally\Http\Controllers\StockItemController;
use Modules\Tally\Http\Controllers\SyncController;
use Modules\Tally\Http\Controllers\TallyConnectionController;
use Modules\Tally\Http\Controllers\UnitController;
use Modules\Tally\Http\Controllers\VoucherController;
use Modules\Tally\Http\Controllers\VoucherNamingSeriesController;
use Modules\Tally\Http\Controllers\VoucherTypeController;
use Modules\Tally\Http\Controllers\WebhookController;
use Modules\Tally\Http\Middleware\CheckTallyPermission;
use Modules\Tally\Http\Middleware\GuardTallyPathParams;
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
        // Phase 9C
        Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
        Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit-logs.show');
    });

    // Connection management
    Route::middleware(CheckTallyPermission::class.':manage_connections')->group(function () {
        Route::apiResource('connections', TallyConnectionController::class)
            ->parameters(['connections' => 'connection']);
        Route::get('connections/{connection}/sync-stats', [SyncController::class, 'stats'])->name('sync.stats');
        Route::get('connections/{connection}/sync-pending', [SyncController::class, 'pending'])->name('sync.pending');
        Route::get('connections/{connection}/sync-conflicts', [SyncController::class, 'conflicts'])->name('sync.conflicts');
        Route::post('sync/{sync}/resolve', [SyncController::class, 'resolveConflict'])->name('sync.resolve');
        // Phase 9C
        Route::get('sync/{sync}', [SyncController::class, 'show'])->name('sync.show');
        Route::post('sync/{sync}/cancel', [SyncController::class, 'cancel'])->name('sync.cancel');
        Route::get('connections/{connection}/sync-history', [SyncController::class, 'history'])->name('sync.history');
        Route::post('connections/{connection}/sync/resolve-all', [SyncController::class, 'resolveAll'])->name('sync.resolve-all');
        Route::get('connections/{connection}/circuit-state', [TallyConnectionController::class, 'circuitState'])->name('connections.circuit-state');

        // Exception report + reset — mirrors tally_migration_tdl's EXCEPTION_Reports.txt
        // and its "Reset Status" button. Surfaces every failed/conflict sync for the
        // operator and lets them retry in bulk.
        Route::get('connections/{connection}/exceptions', [SyncController::class, 'exceptions'])->name('sync.exceptions');
        Route::post('connections/{connection}/sync/reset-status', [SyncController::class, 'resetStatus'])->name('sync.reset-status');

        // Phase 9L — recurring voucher scheduling (DB-only config + manual run trigger)
        Route::get('connections/{connection}/recurring-vouchers', [RecurringVoucherController::class, 'index'])->name('recurring-vouchers.index');
        Route::post('connections/{connection}/recurring-vouchers', [RecurringVoucherController::class, 'store'])->name('recurring-vouchers.store');
        Route::get('connections/{connection}/recurring-vouchers/{recurringVoucher}', [RecurringVoucherController::class, 'show'])->name('recurring-vouchers.show');
        Route::put('connections/{connection}/recurring-vouchers/{recurringVoucher}', [RecurringVoucherController::class, 'update'])->name('recurring-vouchers.update');
        Route::patch('connections/{connection}/recurring-vouchers/{recurringVoucher}', [RecurringVoucherController::class, 'update']);
        Route::delete('connections/{connection}/recurring-vouchers/{recurringVoucher}', [RecurringVoucherController::class, 'destroy'])->name('recurring-vouchers.destroy');
        Route::post('connections/{connection}/recurring-vouchers/{recurringVoucher}/run', [RecurringVoucherController::class, 'run'])->name('recurring-vouchers.run');

        // Master mappings — per-connection Tally-name ↔ ERP-name aliases.
        // Pattern borrowed from laxmantandon/tally_migration_tdl (CustomerMappingTool,
        // ItemMappingTool). Used by sync jobs to keep links stable across renames.
        Route::get('connections/{connection}/master-mappings', [MasterMappingController::class, 'index'])->name('master-mappings.index');
        Route::post('connections/{connection}/master-mappings', [MasterMappingController::class, 'store'])->name('master-mappings.store');
        Route::delete('connections/{connection}/master-mappings/{mapping}', [MasterMappingController::class, 'destroy'])->name('master-mappings.destroy');

        // Voucher naming series — one voucher type can drive multiple numbering streams.
        // Pattern borrowed from laxmantandon/tally_migration_tdl's NamingSeriesConfig.txt.
        Route::get('connections/{connection}/naming-series', [VoucherNamingSeriesController::class, 'index'])->name('naming-series.index');
        Route::post('connections/{connection}/naming-series', [VoucherNamingSeriesController::class, 'store'])->name('naming-series.store');
        Route::put('connections/{connection}/naming-series/{series}', [VoucherNamingSeriesController::class, 'update'])->name('naming-series.update');
        Route::patch('connections/{connection}/naming-series/{series}', [VoucherNamingSeriesController::class, 'update']);
        Route::delete('connections/{connection}/naming-series/{series}', [VoucherNamingSeriesController::class, 'destroy'])->name('naming-series.destroy');
    });

    // Phase 9J — Workflow / draft vouchers with maker-checker.
    // CRUD + submit are maker-side (manage_vouchers); approve/reject are checker-side (approve_vouchers).
    Route::middleware(CheckTallyPermission::class.':manage_vouchers')->group(function () {
        Route::get('connections/{connection}/draft-vouchers', [DraftVoucherController::class, 'index'])->name('draft-vouchers.index');
        Route::post('connections/{connection}/draft-vouchers', [DraftVoucherController::class, 'store'])->name('draft-vouchers.store');
        Route::get('connections/{connection}/draft-vouchers/{draftVoucher}', [DraftVoucherController::class, 'show'])->name('draft-vouchers.show');
        Route::put('connections/{connection}/draft-vouchers/{draftVoucher}', [DraftVoucherController::class, 'update'])->name('draft-vouchers.update');
        Route::patch('connections/{connection}/draft-vouchers/{draftVoucher}', [DraftVoucherController::class, 'update']);
        Route::delete('connections/{connection}/draft-vouchers/{draftVoucher}', [DraftVoucherController::class, 'destroy'])->name('draft-vouchers.destroy');
        Route::post('connections/{connection}/draft-vouchers/{draftVoucher}/submit', [DraftVoucherController::class, 'submit'])->name('draft-vouchers.submit');
    });

    Route::middleware(CheckTallyPermission::class.':approve_vouchers')->group(function () {
        Route::post('connections/{connection}/draft-vouchers/{draftVoucher}/approve', [DraftVoucherController::class, 'approve'])->name('draft-vouchers.approve');
        Route::post('connections/{connection}/draft-vouchers/{draftVoucher}/reject', [DraftVoucherController::class, 'reject'])->name('draft-vouchers.reject');
    });

    // Phase 9Z — MNC hierarchy (Organizations / Companies / Branches)
    // Phase 9K — Consolidated reports across an organization's connections
    Route::middleware(CheckTallyPermission::class.':manage_connections')->group(function () {
        Route::apiResource('organizations', OrganizationController::class)
            ->parameters(['organizations' => 'organization']);

        Route::apiResource('companies', CompanyController::class)
            ->parameters(['companies' => 'company']);

        Route::apiResource('branches', BranchController::class)
            ->parameters(['branches' => 'branch']);

        // 9K — consolidated reports per organization
        Route::get('organizations/{organization}/consolidated/balance-sheet', [ConsolidatedReportController::class, 'balanceSheet'])
            ->name('organizations.consolidated.balance-sheet');
        Route::get('organizations/{organization}/consolidated/profit-and-loss', [ConsolidatedReportController::class, 'profitAndLoss'])
            ->name('organizations.consolidated.profit-and-loss');
        Route::get('organizations/{organization}/consolidated/trial-balance', [ConsolidatedReportController::class, 'trialBalance'])
            ->name('organizations.consolidated.trial-balance');
    });

    // Phase 9I — Webhooks (integration management)
    Route::middleware(CheckTallyPermission::class.':manage_integrations')->group(function () {
        Route::apiResource('webhooks', WebhookController::class)
            ->parameter('webhooks', 'webhook');
        Route::get('webhooks/{webhook}/deliveries', [WebhookController::class, 'deliveries'])->name('webhooks.deliveries');
        Route::post('webhooks/{webhook}/test', [WebhookController::class, 'test'])->name('webhooks.test');

        // Import jobs — status readable anytime; upload below.
        Route::get('import-jobs/{importJob}', [ImportController::class, 'status'])->name('import-jobs.show');
        Route::post('connections/{connection}/import/{entity}', [ImportController::class, 'start'])->name('import.start');

        // Attachments — per-voucher under a specific connection.
        Route::get('connections/{connection}/vouchers/{masterID}/attachments', [AttachmentController::class, 'index'])->name('vouchers.attachments.index');
        Route::post('connections/{connection}/vouchers/{masterID}/attachments', [AttachmentController::class, 'store'])->name('vouchers.attachments.store');
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download'])->name('attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::post('connections/{connection}/sync-from-tally', [SyncController::class, 'triggerInbound'])->name('sync.inbound');
        Route::post('connections/{connection}/sync-to-tally', [SyncController::class, 'triggerOutbound'])->name('sync.outbound');
        Route::post('connections/{connection}/sync-full', [SyncController::class, 'triggerFull'])->name('sync.full');
        Route::get('connections/{connection}/health', [TallyConnectionController::class, 'health'])
            ->name('connections.health');
        Route::get('connections/{connection}/metrics', [TallyConnectionController::class, 'metrics'])
            ->name('connections.metrics');
        Route::post('connections/{connection}/discover', [TallyConnectionController::class, 'discover'])
            ->name('connections.discover');
        Route::get('connections/{connection}/companies', [TallyConnectionController::class, 'companies'])
            ->name('connections.companies');
        Route::post('connections/test', [TallyConnectionController::class, 'test'])
            ->name('connections.test');
    });

    // Default health check
    Route::get('health', HealthController::class)->name('health');

    // Connection-specific routes
    Route::prefix('{connection}')->middleware([ResolveTallyConnection::class, GuardTallyPathParams::class])->group(function () {
        Route::get('health', HealthController::class)->name('connection.health');

        // Read-only master routes
        Route::middleware(CheckTallyPermission::class.':view_masters')->group(function () {
            Route::get('ledgers', [LedgerController::class, 'index'])->name('ledgers.index');
            Route::get('ledgers/{name}', [LedgerController::class, 'show'])->name('ledgers.show');
            Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
            Route::get('groups/{name}', [GroupController::class, 'show'])->name('groups.show');
            Route::get('stock-items', [StockItemController::class, 'index'])->name('stock-items.index');
            Route::get('stock-items/{name}', [StockItemController::class, 'show'])->name('stock-items.show');
            // Phase 9G — BOM is a read-side convenience on top of the stock item
            Route::get('stock-items/{name}/bom', [ManufacturingController::class, 'getBom'])->name('stock-items.bom.show');
            Route::get('stock-groups', [StockGroupController::class, 'index'])->name('stock-groups.index');
            Route::get('stock-groups/{name}', [StockGroupController::class, 'show'])->name('stock-groups.show');
            Route::get('units', [UnitController::class, 'index'])->name('units.index');
            Route::get('units/{name}', [UnitController::class, 'show'])->name('units.show');
            Route::get('cost-centres', [CostCenterController::class, 'index'])->name('cost-centres.index');
            Route::get('cost-centres/{name}', [CostCenterController::class, 'show'])->name('cost-centres.show');
            Route::get('currencies', [CurrencyController::class, 'index'])->name('currencies.index');
            Route::get('currencies/{name}', [CurrencyController::class, 'show'])->name('currencies.show');
            Route::get('godowns', [GodownController::class, 'index'])->name('godowns.index');
            Route::get('godowns/{name}', [GodownController::class, 'show'])->name('godowns.show');
            Route::get('voucher-types', [VoucherTypeController::class, 'index'])->name('voucher-types.index');
            Route::get('voucher-types/{name}', [VoucherTypeController::class, 'show'])->name('voucher-types.show');
            Route::get('stock-categories', [StockCategoryController::class, 'index'])->name('stock-categories.index');
            Route::get('stock-categories/{name}', [StockCategoryController::class, 'show'])->name('stock-categories.show');
            Route::get('price-lists', [PriceListController::class, 'index'])->name('price-lists.index');
            Route::get('price-lists/{name}', [PriceListController::class, 'show'])->name('price-lists.show');

            // Phase 9N — 5 new masters (Employee trio, CostCategory, AttendanceType)
            Route::get('cost-categories', [CostCategoryController::class, 'index'])->name('cost-categories.index');
            Route::get('cost-categories/{name}', [CostCategoryController::class, 'show'])->name('cost-categories.show');
            Route::get('employee-groups', [EmployeeGroupController::class, 'index'])->name('employee-groups.index');
            Route::get('employee-groups/{name}', [EmployeeGroupController::class, 'show'])->name('employee-groups.show');
            Route::get('employee-categories', [EmployeeCategoryController::class, 'index'])->name('employee-categories.index');
            Route::get('employee-categories/{name}', [EmployeeCategoryController::class, 'show'])->name('employee-categories.show');
            Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
            Route::get('employees/{name}', [EmployeeController::class, 'show'])->name('employees.show');
            Route::get('attendance-types', [AttendanceTypeController::class, 'index'])->name('attendance-types.index');
            Route::get('attendance-types/{name}', [AttendanceTypeController::class, 'show'])->name('attendance-types.show');
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
            // Phase 9G — BOM write
            Route::put('stock-items/{name}/bom', [ManufacturingController::class, 'setBom'])->name('stock-items.bom.update');

            Route::post('stock-groups', [StockGroupController::class, 'store'])->name('stock-groups.store');
            Route::put('stock-groups/{name}', [StockGroupController::class, 'update'])->name('stock-groups.update');
            Route::patch('stock-groups/{name}', [StockGroupController::class, 'update']);
            Route::delete('stock-groups/{name}', [StockGroupController::class, 'destroy'])->name('stock-groups.destroy');

            Route::post('units', [UnitController::class, 'store'])->name('units.store');
            Route::put('units/{name}', [UnitController::class, 'update'])->name('units.update');
            Route::patch('units/{name}', [UnitController::class, 'update']);
            Route::delete('units/{name}', [UnitController::class, 'destroy'])->name('units.destroy');

            Route::post('cost-centres', [CostCenterController::class, 'store'])->name('cost-centres.store');
            Route::put('cost-centres/{name}', [CostCenterController::class, 'update'])->name('cost-centres.update');
            Route::patch('cost-centres/{name}', [CostCenterController::class, 'update']);
            Route::delete('cost-centres/{name}', [CostCenterController::class, 'destroy'])->name('cost-centres.destroy');

            Route::post('currencies', [CurrencyController::class, 'store'])->name('currencies.store');
            Route::put('currencies/{name}', [CurrencyController::class, 'update'])->name('currencies.update');
            Route::patch('currencies/{name}', [CurrencyController::class, 'update']);
            Route::delete('currencies/{name}', [CurrencyController::class, 'destroy'])->name('currencies.destroy');

            Route::post('godowns', [GodownController::class, 'store'])->name('godowns.store');
            Route::put('godowns/{name}', [GodownController::class, 'update'])->name('godowns.update');
            Route::patch('godowns/{name}', [GodownController::class, 'update']);
            Route::delete('godowns/{name}', [GodownController::class, 'destroy'])->name('godowns.destroy');

            Route::post('voucher-types', [VoucherTypeController::class, 'store'])->name('voucher-types.store');
            Route::put('voucher-types/{name}', [VoucherTypeController::class, 'update'])->name('voucher-types.update');
            Route::patch('voucher-types/{name}', [VoucherTypeController::class, 'update']);
            Route::delete('voucher-types/{name}', [VoucherTypeController::class, 'destroy'])->name('voucher-types.destroy');

            Route::post('stock-categories', [StockCategoryController::class, 'store'])->name('stock-categories.store');
            Route::put('stock-categories/{name}', [StockCategoryController::class, 'update'])->name('stock-categories.update');
            Route::patch('stock-categories/{name}', [StockCategoryController::class, 'update']);
            Route::delete('stock-categories/{name}', [StockCategoryController::class, 'destroy'])->name('stock-categories.destroy');

            Route::post('price-lists', [PriceListController::class, 'store'])->name('price-lists.store');
            Route::put('price-lists/{name}', [PriceListController::class, 'update'])->name('price-lists.update');
            Route::patch('price-lists/{name}', [PriceListController::class, 'update']);
            Route::delete('price-lists/{name}', [PriceListController::class, 'destroy'])->name('price-lists.destroy');

            // Phase 9N — 5 new master write routes
            Route::post('cost-categories', [CostCategoryController::class, 'store'])->name('cost-categories.store');
            Route::put('cost-categories/{name}', [CostCategoryController::class, 'update'])->name('cost-categories.update');
            Route::patch('cost-categories/{name}', [CostCategoryController::class, 'update']);
            Route::delete('cost-categories/{name}', [CostCategoryController::class, 'destroy'])->name('cost-categories.destroy');

            Route::post('employee-groups', [EmployeeGroupController::class, 'store'])->name('employee-groups.store');
            Route::put('employee-groups/{name}', [EmployeeGroupController::class, 'update'])->name('employee-groups.update');
            Route::patch('employee-groups/{name}', [EmployeeGroupController::class, 'update']);
            Route::delete('employee-groups/{name}', [EmployeeGroupController::class, 'destroy'])->name('employee-groups.destroy');

            Route::post('employee-categories', [EmployeeCategoryController::class, 'store'])->name('employee-categories.store');
            Route::put('employee-categories/{name}', [EmployeeCategoryController::class, 'update'])->name('employee-categories.update');
            Route::patch('employee-categories/{name}', [EmployeeCategoryController::class, 'update']);
            Route::delete('employee-categories/{name}', [EmployeeCategoryController::class, 'destroy'])->name('employee-categories.destroy');

            Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
            Route::put('employees/{name}', [EmployeeController::class, 'update'])->name('employees.update');
            Route::patch('employees/{name}', [EmployeeController::class, 'update']);
            Route::delete('employees/{name}', [EmployeeController::class, 'destroy'])->name('employees.destroy');

            Route::post('attendance-types', [AttendanceTypeController::class, 'store'])->name('attendance-types.store');
            Route::put('attendance-types/{name}', [AttendanceTypeController::class, 'update'])->name('attendance-types.update');
            Route::patch('attendance-types/{name}', [AttendanceTypeController::class, 'update']);
            Route::delete('attendance-types/{name}', [AttendanceTypeController::class, 'destroy'])->name('attendance-types.destroy');
        });

        // Read vouchers
        Route::middleware(CheckTallyPermission::class.':view_vouchers')->group(function () {
            Route::get('vouchers', [VoucherController::class, 'index'])->name('vouchers.index');
            Route::get('vouchers/{masterID}', [VoucherController::class, 'show'])->name('vouchers.show');
        });

        // Write vouchers
        Route::middleware([CheckTallyPermission::class.':manage_vouchers', 'throttle:tally-write'])->group(function () {
            Route::post('vouchers', [VoucherController::class, 'store'])->name('vouchers.store');
            Route::post('vouchers/batch', [VoucherController::class, 'batch'])->name('vouchers.batch');
            Route::put('vouchers/{masterID}', [VoucherController::class, 'update'])->name('vouchers.update');
            Route::patch('vouchers/{masterID}', [VoucherController::class, 'update']);
            Route::delete('vouchers/{masterID}', [VoucherController::class, 'destroy'])->name('vouchers.destroy');

            // Phase 9D — banking operations (reconciliation + bank feed)
            Route::post('bank/reconcile', [BankingController::class, 'reconcile'])->name('bank.reconcile');
            Route::post('bank/unreconcile', [BankingController::class, 'unreconcile'])->name('bank.unreconcile');
            Route::post('bank/import-statement', [BankingController::class, 'importStatement'])->name('bank.import-statement');
            Route::post('bank/auto-match', [BankingController::class, 'autoMatch'])->name('bank.auto-match');
            Route::post('bank/batch-reconcile', [BankingController::class, 'batchReconcile'])->name('bank.batch-reconcile');

            // Phase 9F — inventory convenience endpoints
            Route::post('stock-transfers', [InventoryController::class, 'stockTransfer'])->name('inventory.stock-transfer');
            Route::post('physical-stock', [InventoryController::class, 'physicalStock'])->name('inventory.physical-stock');

            // Phase 9G — manufacturing + job work
            Route::post('manufacturing', [ManufacturingController::class, 'manufacture'])->name('manufacturing.create');
            Route::post('job-work-out', [ManufacturingController::class, 'jobWorkOut'])->name('manufacturing.job-work-out');
            Route::post('job-work-in', [ManufacturingController::class, 'jobWorkIn'])->name('manufacturing.job-work-in');

        });

        // Phase 9I — voucher PDF (read-only, needs view_vouchers). Uses per-conn middleware for client binding.
        Route::middleware(CheckTallyPermission::class.':view_vouchers')->group(function () {
            Route::get('vouchers/{masterID}/pdf', [IntegrationController::class, 'voucherPdf'])->name('vouchers.pdf');
        });

        // Phase 9I — email invoice (separate permission: send_invoices)
        Route::middleware(CheckTallyPermission::class.':send_invoices')->group(function () {
            Route::post('vouchers/{masterID}/email', [IntegrationController::class, 'emailVoucher'])->name('vouchers.email');
        });

        // Reports (read-only, separate rate limit)
        Route::middleware([CheckTallyPermission::class.':view_reports', 'throttle:tally-reports'])->group(function () {
            Route::get('reports/{type}', [ReportController::class, 'show'])->name('connection.reports.show');
        });

        // Phase 9C — observability endpoints
        Route::middleware(CheckTallyPermission::class.':view_masters')->group(function () {
            Route::get('stats', [OperationsController::class, 'stats'])->name('operations.stats');
            Route::get('search', [OperationsController::class, 'search'])->name('operations.search');
        });
        Route::middleware(CheckTallyPermission::class.':manage_masters')->group(function () {
            Route::post('cache/flush', [OperationsController::class, 'flushCache'])->name('operations.cache.flush');
        });
    });
});
