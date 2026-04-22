<?php

namespace Modules\Tally\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallyImportJob extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tally_connection_id', 'entity_type', 'file_disk', 'file_path',
        'total_rows', 'processed_rows', 'failed_rows', 'status',
        'result_summary', 'uploaded_by',
    ];

    protected $casts = [
        'total_rows' => 'int',
        'processed_rows' => 'int',
        'failed_rows' => 'int',
        'result_summary' => 'array',
    ];

    public function connection(): BelongsTo
    {
        return $this->belongsTo(TallyConnection::class, 'tally_connection_id');
    }
}
