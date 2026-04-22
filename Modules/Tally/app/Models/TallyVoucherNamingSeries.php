<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Tally voucher type can drive multiple numbering streams (e.g. "SI/2026/",
 * "SINV/"). Pattern borrowed from laxmantandon/tally_migration_tdl's
 * NamingSeriesConfig.txt — real-world Tally setups assign different series per
 * branch / financial year / counter.
 */
class TallyVoucherNamingSeries extends Model
{
    protected $table = 'tally_voucher_naming_series';

    protected $fillable = [
        'tally_connection_id',
        'voucher_type',
        'series_name',
        'prefix',
        'suffix',
        'last_number',
        'is_active',
    ];

    protected $casts = [
        'last_number' => 'integer',
        'is_active' => 'bool',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    /**
     * Reserve and return the next voucher number for this series.
     * Increments last_number atomically.
     */
    public function nextNumber(): string
    {
        $this->increment('last_number');

        return ($this->prefix ?? '').((string) $this->last_number).($this->suffix ?? '');
    }
}
