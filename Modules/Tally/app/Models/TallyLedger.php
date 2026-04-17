<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyLedger extends Model
{
    protected $fillable = [
        'tally_connection_id',
        'name',
        'parent',
        'gstin',
        'gst_registration_type',
        'state',
        'email',
        'phone',
        'contact_person',
        'opening_balance',
        'closing_balance',
        'credit_period',
        'credit_limit',
        'currency',
        'address',
        'tally_raw_data',
        'data_hash',
    ];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'address' => 'array',
            'tally_raw_data' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    /**
     * Generate a hash of the syncable data for change detection.
     */
    public function computeDataHash(): string
    {
        $data = collect($this->only([
            'name', 'parent', 'gstin', 'gst_registration_type', 'state',
            'email', 'phone', 'contact_person', 'opening_balance',
            'credit_period', 'credit_limit', 'currency',
        ]))->toJson();

        return md5($data);
    }
}
