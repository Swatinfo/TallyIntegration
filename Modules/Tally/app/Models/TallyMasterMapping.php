<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps names between Tally masters and the host ERP. Consulted during sync so a
 * rename on either side doesn't break the link. Entity types: ledger, group,
 * stock_item, stock_group, cost_centre, godown, voucher_type, etc.
 *
 * Pattern borrowed from laxmantandon/tally_migration_tdl's CustomerMappingTool /
 * ItemMappingTool — real-world Tally and ERP names rarely match 1:1.
 */
class TallyMasterMapping extends Model
{
    protected $fillable = [
        'tally_connection_id',
        'entity_type',
        'tally_name',
        'erp_name',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }

    public static function resolveTallyName(int $connectionId, string $entityType, string $erpName): ?string
    {
        return static::query()
            ->where('tally_connection_id', $connectionId)
            ->where('entity_type', $entityType)
            ->where('erp_name', $erpName)
            ->value('tally_name');
    }

    public static function resolveErpName(int $connectionId, string $entityType, string $tallyName): ?string
    {
        return static::query()
            ->where('tally_connection_id', $connectionId)
            ->where('entity_type', $entityType)
            ->where('tally_name', $tallyName)
            ->value('erp_name');
    }
}
