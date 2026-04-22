<?php

namespace Modules\Tally\Services;

use Modules\Tally\Models\TallyDraftVoucher;
use Modules\Tally\Services\Vouchers\VoucherService;
use Modules\Tally\Services\Vouchers\VoucherType;

/**
 * State machine for draft vouchers + approval threshold enforcement.
 *
 * Flow: draft → submitted → approved → pushed
 *                        └→ rejected (terminal)
 *
 * Approval thresholds come from config('tally.workflow.approval_thresholds').
 * A draft below every matching threshold auto-approves on submit; otherwise
 * an approver with ApproveVouchers permission must act.
 */
class WorkflowService
{
    public function __construct(
        private VoucherService $vouchers,
    ) {}

    /**
     * Does this draft need an explicit approver's action (vs auto-approve)?
     */
    public function requiresApproval(TallyDraftVoucher $draft): bool
    {
        $thresholds = config('tally.workflow.approval_thresholds', []);

        if (empty($thresholds)) {
            // Policy: no thresholds configured ⇒ all drafts require approval.
            return true;
        }

        foreach ($thresholds as $rule) {
            if ($rule['type'] !== $draft->voucher_type) {
                continue;
            }
            if ((float) $draft->amount >= (float) $rule['amount']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Maker action: move draft → submitted. If below all thresholds, auto-approve + push.
     */
    public function submit(TallyDraftVoucher $draft, ?int $userId = null): TallyDraftVoucher
    {
        if (! $draft->isSubmittable()) {
            throw new \RuntimeException("Cannot submit — current status is {$draft->status}");
        }

        $draft->update([
            'status' => TallyDraftVoucher::STATUS_SUBMITTED,
            'submitted_at' => now(),
            'submitted_by' => $userId,
        ]);

        if (! $this->requiresApproval($draft)) {
            // Auto-approve + auto-push since amount is below the threshold.
            return $this->approve($draft->fresh(), $userId, autoApproved: true);
        }

        return $draft->fresh();
    }

    /**
     * Checker action: approve a submitted draft and push it to Tally.
     */
    public function approve(TallyDraftVoucher $draft, ?int $approverId = null, bool $autoApproved = false): TallyDraftVoucher
    {
        if (! $draft->isActionable()) {
            throw new \RuntimeException("Cannot approve — current status is {$draft->status}");
        }

        if (! $autoApproved
            && config('tally.workflow.require_distinct_approver', true)
            && $approverId !== null
            && $approverId === $draft->submitted_by) {
            throw new \RuntimeException('Self-approval is not allowed (maker ≠ checker).');
        }

        $draft->update([
            'status' => TallyDraftVoucher::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approverId,
            'is_locked' => true,
        ]);

        // Push to Tally.
        $result = $this->vouchers->create(
            VoucherType::from($draft->voucher_type),
            $draft->voucher_data,
        );

        $draft->update([
            'status' => TallyDraftVoucher::STATUS_PUSHED,
            'pushed_at' => now(),
            'push_result' => $result,
            'tally_master_id' => $result['lastvchid'] ?? null,
        ]);

        return $draft->fresh();
    }

    /**
     * Checker action: reject with a reason. Terminal state — draft cannot re-submit.
     */
    public function reject(TallyDraftVoucher $draft, string $reason, ?int $rejectorId = null): TallyDraftVoucher
    {
        if (! $draft->isActionable()) {
            throw new \RuntimeException("Cannot reject — current status is {$draft->status}");
        }

        $draft->update([
            'status' => TallyDraftVoucher::STATUS_REJECTED,
            'rejected_at' => now(),
            'rejected_by' => $rejectorId,
            'rejection_reason' => $reason,
            'is_locked' => true,
        ]);

        return $draft->fresh();
    }
}
