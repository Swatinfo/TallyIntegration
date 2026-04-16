<?php

namespace App\Models;

use Database\Factories\TallyConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TallyConnection extends Model
{
    /** @use HasFactory<TallyConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'host',
        'port',
        'company_name',
        'timeout',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'timeout' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
