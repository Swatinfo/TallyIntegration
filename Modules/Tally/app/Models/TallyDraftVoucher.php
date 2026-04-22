<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyDraftVoucher extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PUSHED = 'pushed';

    protected $fillable = [
        'tally_connection_id',
        'voucher_type',
        'voucher_data',
        'narration',
        'amount',
        'status',
        'created_by',
        'submitted_at', 'submitted_by',
        'approved_at', 'approved_by',
        'rejected_at', 'rejected_by',
        'rejection_reason',
        'pushed_at', 'push_result',
        'tally_master_id',
        'is_locked',
    ];

    protected $casts = [
        'voucher_data' => 'array',
        'push_result' => 'array',
        'amount' => 'decimal:2',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'pushed_at' => 'datetime',
        'is_locked' => 'bool',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT && ! $this->is_locked;
    }

    public function isSubmittable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActionable(): bool
    {
        // Can an approver act on this (approve/reject)?
        return $this->status === self::STATUS_SUBMITTED;
    }
}
