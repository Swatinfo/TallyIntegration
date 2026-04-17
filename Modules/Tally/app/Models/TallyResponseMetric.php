<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;

class TallyResponseMetric extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tally_connection_id',
        'endpoint',
        'response_time_ms',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'response_time_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
