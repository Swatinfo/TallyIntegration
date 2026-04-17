<?php

namespace Modules\Tally\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Tally\Models\TallySync;

class SyncTracker
{
    /**
     * Create or update a sync record for an entity.
     */
    public function track(
        int $connectionId,
        string $entityType,
        int $entityId,
        string $direction = 'bidirectional',
        string $priority = 'normal',
        ?string $tallyName = null,
    ): TallySync {
        return TallySync::updateOrCreate(
            [
                'tally_connection_id' => $connectionId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
            ],
            [
                'sync_direction' => $direction,
                'priority' => $priority,
                'tally_name' => $tallyName,
                'sync_status' => 'pending',
            ],
        );
    }

    /**
     * Mark an entity as needing sync (changed locally).
     */
    public function markDirty(int $connectionId, string $entityType, int $entityId, string $localDataHash): void
    {
        $sync = TallySync::where([
            'tally_connection_id' => $connectionId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
        ])->first();

        if ($sync) {
            if ($sync->local_data_hash !== $localDataHash) {
                $sync->update([
                    'local_data_hash' => $localDataHash,
                    'sync_status' => 'pending',
                ]);
            }
        }
    }

    /**
     * Detect changes between local and Tally data.
     * Returns: 'none', 'local_changed', 'tally_changed', 'conflict'
     */
    public function detectChange(TallySync $sync, string $currentLocalHash, string $currentTallyHash): string
    {
        $localChanged = $sync->local_data_hash !== $currentLocalHash;
        $tallyChanged = $sync->tally_data_hash !== $currentTallyHash;

        if (! $localChanged && ! $tallyChanged) {
            return 'none';
        }

        if ($localChanged && ! $tallyChanged) {
            return 'local_changed';
        }

        if (! $localChanged && $tallyChanged) {
            return 'tally_changed';
        }

        return 'conflict';
    }

    /**
     * Get pending sync records for processing, ordered by priority.
     */
    public function getPending(int $connectionId, int $limit = 50): Collection
    {
        return TallySync::pendingForConnection($connectionId)
            ->limit($limit)
            ->get()
            ->filter(fn (TallySync $sync) => $sync->sync_status === 'pending' || $sync->isDueForRetry());
    }

    /**
     * Get all conflicts for a connection.
     */
    public function getConflicts(int $connectionId): Collection
    {
        return TallySync::conflictsForConnection($connectionId)->get();
    }

    /**
     * Resolve a conflict with a chosen strategy.
     */
    public function resolveConflict(TallySync $sync, string $strategy, ?int $resolvedBy = null): void
    {
        $sync->update([
            'resolution_strategy' => $strategy,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
            'sync_status' => 'pending', // Re-queue for sync with chosen strategy
        ]);
    }

    /**
     * Get sync statistics for a connection.
     */
    public function stats(int $connectionId): array
    {
        return TallySync::statsForConnection($connectionId);
    }

    /**
     * Determine sync priority based on entity type.
     */
    public static function priorityForType(string $entityType, ?string $voucherType = null): string
    {
        if ($entityType === 'voucher' && $voucherType) {
            return match ($voucherType) {
                'Payment', 'Receipt' => 'critical',
                'Sales', 'Purchase' => 'high',
                'Journal', 'Contra' => 'normal',
                default => 'normal',
            };
        }

        return match ($entityType) {
            'ledger' => 'normal',
            'group' => 'low',
            'stock_item' => 'low',
            default => 'normal',
        };
    }
}
