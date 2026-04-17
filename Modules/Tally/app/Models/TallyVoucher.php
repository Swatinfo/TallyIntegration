<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyVoucher extends Model
{
    protected $fillable = [
        'tally_connection_id',
        'voucher_number',
        'tally_master_id',
        'voucher_type',
        'date',
        'party_name',
        'narration',
        'amount',
        'is_cancelled',
        'ledger_entries',
        'inventory_entries',
        'bill_allocations',
        'tally_raw_data',
        'data_hash',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'is_cancelled' => 'boolean',
            'ledger_entries' => 'array',
            'inventory_entries' => 'array',
            'bill_allocations' => 'array',
            'tally_raw_data' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    public function computeDataHash(): string
    {
        $data = collect($this->only([
            'voucher_number', 'voucher_type', 'date', 'party_name',
            'narration', 'amount', 'is_cancelled', 'ledger_entries',
        ]))->toJson();

        return md5($data);
    }
}
