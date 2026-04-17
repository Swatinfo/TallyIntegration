<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Tally\Database\Factories\TallyConnectionFactory;

class TallyConnection extends Model
{
    /** @use HasFactory<TallyConnectionFactory> */
    use HasFactory;

    protected static function newFactory(): TallyConnectionFactory
    {
        return TallyConnectionFactory::new();
    }

    protected $fillable = [
        'name',
        'code',
        'host',
        'port',
        'company_name',
        'timeout',
        'is_active',
        'last_alter_master_id',
        'last_alter_voucher_id',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'timeout' => 'integer',
            'is_active' => 'boolean',
            'last_alter_master_id' => 'integer',
            'last_alter_voucher_id' => 'integer',
            'last_synced_at' => 'datetime',
        ];
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(TallyLedger::class, 'tally_connection_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(TallyVoucher::class, 'tally_connection_id');
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(TallyStockItem::class, 'tally_connection_id');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TallyGroup::class, 'tally_connection_id');
    }
}
