<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TallyOrganization extends Model
{
    protected $fillable = ['name', 'code', 'country', 'base_currency', 'is_active'];

    protected $casts = ['is_active' => 'bool'];

    public function companies(): HasMany
    {
        return $this->hasMany(TallyCompany::class, 'tally_organization_id');
    }

    public function connections(): HasMany
    {
        return $this->hasMany(TallyConnection::class, 'tally_organization_id');
    }

    public function branches(): HasManyThrough
    {
        return $this->hasManyThrough(TallyBranch::class, TallyCompany::class, 'tally_organization_id', 'tally_company_id');
    }
}
