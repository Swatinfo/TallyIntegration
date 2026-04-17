<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyStockItem extends Model
{
    protected $fillable = [
        'tally_connection_id',
        'name',
        'parent',
        'base_unit',
        'opening_balance_qty',
        'opening_balance_value',
        'opening_rate',
        'closing_balance_qty',
        'closing_balance_value',
        'has_batches',
        'hsn_code',
        'tally_raw_data',
        'data_hash',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance_qty' => 'decimal:4',
            'opening_balance_value' => 'decimal:2',
            'opening_rate' => 'decimal:2',
            'closing_balance_qty' => 'decimal:4',
            'closing_balance_value' => 'decimal:2',
            'has_batches' => 'boolean',
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
            'name', 'parent', 'base_unit', 'opening_balance_qty',
            'opening_balance_value', 'opening_rate', 'has_batches', 'hsn_code',
        ]))->toJson();

        return md5($data);
    }
}
