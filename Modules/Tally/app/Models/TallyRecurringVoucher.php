<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyRecurringVoucher extends Model
{
    protected $fillable = [
        'tally_connection_id',
        'name',
        'voucher_type',
        'frequency',
        'day_of_month',
        'day_of_week',
        'start_date',
        'end_date',
        'next_run_at',
        'last_run_at',
        'last_run_result',
        'voucher_template',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_at' => 'date',
        'last_run_at' => 'datetime',
        'last_run_result' => 'array',
        'voucher_template' => 'array',
        'is_active' => 'bool',
        'day_of_month' => 'int',
        'day_of_week' => 'int',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    public function isDue(?\DateTimeInterface $asOf = null): bool
    {
        $asOf = $asOf ?? now();
        if (! $this->is_active) {
            return false;
        }
        if ($this->end_date && $this->end_date->lt($asOf)) {
            return false;
        }

        return $this->next_run_at && $this->next_run_at->lte($asOf);
    }
}
