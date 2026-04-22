<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallyCompany extends Model
{
    protected $fillable = [
        'tally_organization_id', 'name', 'code', 'country', 'base_currency', 'gstin', 'is_active',
    ];

    protected $casts = ['is_active' => 'bool'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(TallyOrganization::class, 'tally_organization_id');
    }

    public function branches(): HasMany
    {
        return $this->hasMany(TallyBranch::class, 'tally_company_id');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(TallyConnection::class, 'tally_company_id');
    }
}
