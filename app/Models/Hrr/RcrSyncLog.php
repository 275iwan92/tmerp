<?php

namespace App\Models\Hrr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RcrSyncLog extends Model
{
    public $timestamps = false;

    protected $connection = 'tm2hrr';

    protected $table = 'rcr_sync_log';

    protected $primaryKey = 'id_log';

    protected $fillable = ['submission_uuid', 'backend_used', 'fetched_at', 'result', 'error_note', 'id_application'];

    protected $casts = ['fetched_at' => 'datetime'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(AppApplication::class, 'id_application', 'id_application');
    }
}
