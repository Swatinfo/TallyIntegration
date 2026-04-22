<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyWebhookDelivery extends Model
{
    protected $fillable = [
        'tally_webhook_endpoint_id', 'event', 'payload', 'attempt_number',
        'status', 'response_code', 'response_body', 'delivered_at', 'next_retry_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(TallyWebhookEndpoint::class, 'tally_webhook_endpoint_id');
    }
}
