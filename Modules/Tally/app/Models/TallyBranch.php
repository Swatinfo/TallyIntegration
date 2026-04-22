<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyBranch extends Model
{
    protected $fillable = [
        'tally_company_id', 'name', 'code', 'city', 'state', 'gstin', 'is_active',
    ];

    protected $casts = ['is_active' => 'bool'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(TallyCompany::class, 'tally_company_id');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(TallyConnection::class, 'tally_branch_id');
    }
}
