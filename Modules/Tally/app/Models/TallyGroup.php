<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyGroup extends Model
{
    protected $fillable = [
        'tally_connection_id',
        'name',
        'parent',
        'nature',
        'is_primary',
        'tally_raw_data',
        'data_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'tally_raw_data' => 'array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    public function computeDataHash(): string
    {
        $data = collect($this->only(['name', 'parent', 'nature', 'is_primary']))->toJson();

        return md5($data);
    }
}
