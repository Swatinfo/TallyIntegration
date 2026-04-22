<?php

namespace Modules\Tally\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rules\Enum;
use Modules\Tally\Http\Requests\ImportBankStatementRequest;
use Modules\Tally\Http\Requests\ReconcileVoucherRequest;
use Modules\Tally\Rules\SafeXmlString;
use Modules\Tally\Services\Banking\BankingService;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

class BankingController extends Controller
{
    public function __construct(
        private BankingService $service,
        private VoucherService $voucherService,
    ) {}

    /**
     * Mark a voucher as reconciled with the bank statement.
     */
    public function reconcile(ReconcileVoucherRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->service->reconcile(
            $validated['voucher_number'],
            $validated['voucher_date'],
            VoucherType::from($validated['voucher_type']),
            $validated['statement_date'],
            $validated['bank_ledger'],
        );

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Voucher reconciled successfully' : 'Failed to reconcile voucher',
        ], $result['errors'] === 0 ? 200 : 422);
    }

    /**
     * Clear the reconciliation flag on a voucher.
     */
    public function unreconcile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'voucher_number' => ['required', 'string', 'max:100', new SafeXmlString],
            'voucher_date' => ['required', 'string', 'size:8'],
            'voucher_type' => ['required', new Enum(VoucherType::class)],
            'bank_ledger' => ['required', 'string', 'max:255', new SafeXmlString],
        ]);

        $result = $this->service->unreconcile(
            $validated['voucher_number'],
            $validated['voucher_date'],
            VoucherType::from($validated['voucher_type']),
            $validated['bank_ledger'],
        );

        return response()->json([
            'success' => $result['errors'] === 0,
            'data' => $result,
            'message' => $result['errors'] === 0 ? 'Voucher unreconciled successfully' : 'Failed to unreconcile voucher',
        ], $result['errors'] === 0 ? 200 : 422);
    }

    /**
     * Upload a bank statement (CSV file or raw string) and parse it into rows.
     * Returns parsed rows — the client then calls auto-match or batch-reconcile.
     */
    public function importStatement(ImportBankStatementRequest $request): JsonResponse
    {
        $csv = $request->filled('csv')
            ? (string) $request->input('csv')
            : (string) file_get_contents($request->file('statement_file')->getRealPath());

        $rows = $this->service->parseStatement($csv);

        return response()->json([
            'success' => true,
            'data' => [
                'row_count' => count($rows),
                'rows' => $rows,
            ],
            'message' => 'Statement parsed successfully',
        ]);
    }

    /**
     * Auto-match statement rows against Tally vouchers (Payment/Receipt/Contra).
     * Returns candidate matches with confidence rating.
     */
    public function autoMatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bank_ledger' => ['required', 'string', 'max:255', new SafeXmlString],
            'from_date' => ['required', 'string', 'size:8'],
            'to_date' => ['required', 'string', 'size:8'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*' => ['array'],
            'date_tolerance_days' => ['sometimes', 'integer', 'min:0', 'max:30'],
        ]);

        // Pull payments + receipts + contra vouchers for the period — most bank
        // ledger activity comes through these three.
        $vouchers = array_merge(
            $this->voucherService->list(VoucherType::Payment, $validated['from_date'], $validated['to_date']),
            $this->voucherService->list(VoucherType::Receipt, $validated['from_date'], $validated['to_date']),
            $this->voucherService->list(VoucherType::Contra, $validated['from_date'], $validated['to_date']),
        );

        $matches = $this->service->findMatches(
            $validated['rows'],
            $vouchers,
            $validated['date_tolerance_days'] ?? 3,
        );

        return response()->json([
            'success' => true,
            'data' => [
                'total_candidates' => count($matches),
                'matches' => $matches,
            ],
            'message' => 'Auto-match completed',
        ]);
    }

    /**
     * Apply a list of reconciliations as a batch.
     */
    public function batchReconcile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.voucher_number' => ['required', 'string', 'max:100'],
            'entries.*.voucher_date' => ['required', 'string', 'size:8'],
            'entries.*.voucher_type' => ['required', new Enum(VoucherType::class)],
            'entries.*.statement_date' => ['required', 'string', 'max:20'],
            'entries.*.bank_ledger' => ['required', 'string', 'max:255'],
        ]);

        $summary = $this->service->batchReconcile($validated['entries']);

        return response()->json([
            'success' => $summary['failed'] === 0,
            'data' => $summary,
            'message' => "Reconciled {$summary['reconciled']}, failed {$summary['failed']}",
        ], $summary['failed'] === 0 ? 200 : 207);
    }
}
