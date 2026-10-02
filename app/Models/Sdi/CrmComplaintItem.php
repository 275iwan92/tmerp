<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmComplaintItem extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'crm_complaint_item';

    protected $primaryKey = 'id_complaint_item';

    protected $guarded = ['id_complaint_item'];

    protected $casts = ['qty_complaint' => 'decimal:2', 'qty_return' => 'decimal:2'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(CrmComplaint::class, 'complaint_id', 'id_complaint');
    }
}
