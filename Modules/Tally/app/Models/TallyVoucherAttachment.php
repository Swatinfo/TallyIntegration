<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyVoucherAttachment extends Model
{
    protected $fillable = [
        'tally_connection_id', 'voucher_master_id', 'file_disk', 'file_path',
        'original_name', 'mime_type', 'size_bytes', 'uploaded_by',
    ];

    protected $casts = [
        'size_bytes' => 'int',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }
}
