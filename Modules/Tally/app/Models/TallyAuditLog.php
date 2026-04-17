<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyAuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'tally_connection_id',
        'action',
        'object_type',
        'object_name',
        'request_data',
        'response_data',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'request_data' => 'array',
            'response_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }
}
