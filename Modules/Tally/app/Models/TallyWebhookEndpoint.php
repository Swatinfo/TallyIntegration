<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyWebhookEndpoint extends Model
{
    protected $fillable = [
        'tally_connection_id', 'name', 'url', 'secret', 'events', 'headers', 'is_active',
        'failure_count', 'last_failure_at',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'bool',
        'last_failure_at' => 'datetime',
    ];

    protected $hidden = ['secret'];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(TallyWebhookDelivery::class, 'tally_webhook_endpoint_id');
    }

    public function subscribesTo(string $event): bool
    {
        return in_array('*', $this->events ?? [], true)
            || in_array($event, $this->events ?? [], true);
    }
}
