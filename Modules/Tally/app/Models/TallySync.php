<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallySync extends Model
{
    protected $fillable = [
        'tally_connection_id',
        'entity_type',
        'entity_id',
        'tally_name',
        'tally_master_id',
        'sync_direction',
        'sync_status',
        'priority',
        'local_data_hash',
        'tally_data_hash',
        'last_synced_at',
        'last_sync_attempt',
        'sync_attempts',
        'error_message',
        'conflict_data',
        'resolution_strategy',
        'resolved_at',
        'resolved_by',
    ];

    protected function casts(): array
    {
        return [
            'sync_attempts' => 'integer',
            'conflict_data' => 'array',
            'last_synced_at' => 'datetime',
            'last_sync_attempt' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    /**
     * Get the entity model (TallyLedger, TallyVoucher, etc.) based on entity_type + entity_id.
     */
    public function entity(): ?Model
    {
        $modelClass = match ($this->entity_type) {
            'ledger' => TallyLedger::class,
            'voucher' => TallyVoucher::class,
            'stock_item' => TallyStockItem::class,
            'group' => TallyGroup::class,
            default => null,
        };

        if (! $modelClass) {
            return null;
        }

        return $modelClass::find($this->entity_id);
    }

    /**
     * Check if this sync record is due for retry (exponential backoff).
     */
    public function isDueForRetry(): bool
    {
        if ($this->sync_status !== 'failed' || $this->sync_attempts >= 3) {
            return false;
        }

        if (! $this->last_sync_attempt) {
            return true;
        }

        // Exponential backoff: 1 min, 5 min, 15 min
        $delays = [60, 300, 900];
        $delay = $delays[$this->sync_attempts - 1] ?? 900;

        return $this->last_sync_attempt->addSeconds($delay)->isPast();
    }

    public function markInProgress(): void
    {
        $this->update([
            'sync_status' => 'in_progress',
            'last_sync_attempt' => now(),
            'sync_attempts' => $this->sync_attempts + 1,
        ]);
    }

    public function markCompleted(?string $tallyDataHash = null): void
    {
        $this->update([
            'sync_status' => 'completed',
            'last_synced_at' => now(),
            'error_message' => null,
            'tally_data_hash' => $tallyDataHash ?? $this->tally_data_hash,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'sync_status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function markConflict(array $localData, array $tallyData, array $conflictFields): void
    {
        $this->update([
            'sync_status' => 'conflict',
            'conflict_data' => [
                'local' => $localData,
                'tally' => $tallyData,
                'fields' => $conflictFields,
            ],
        ]);
    }

    /**
     * Scope: get pending syncs for a connection, ordered by priority.
     */
    public function scopePendingForConnection($query, int $connectionId)
    {
        return $query->where('tally_connection_id', $connectionId)
            ->whereIn('sync_status', ['pending', 'failed'])
            ->orderByRaw("FIELD(priority, 'critical', 'high', 'normal', 'low')")
            ->orderBy('created_at');
    }

    /**
     * Scope: get conflicts for a connection.
     */
    public function scopeConflictsForConnection($query, int $connectionId)
    {
        return $query->where('tally_connection_id', $connectionId)
            ->where('sync_status', 'conflict');
    }

    /**
     * Get sync statistics for a connection.
     */
    public static function statsForConnection(int $connectionId): array
    {
        $stats = self::where('tally_connection_id', $connectionId)
            ->selectRaw('sync_status, count(*) as count')
            ->groupBy('sync_status')
            ->pluck('count', 'sync_status')
            ->toArray();

        return [
            'pending' => $stats['pending'] ?? 0,
            'in_progress' => $stats['in_progress'] ?? 0,
            'completed' => $stats['completed'] ?? 0,
            'failed' => $stats['failed'] ?? 0,
            'conflict' => $stats['conflict'] ?? 0,
            'total' => array_sum($stats),
        ];
    }
}
