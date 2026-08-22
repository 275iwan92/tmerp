<?php

namespace App\Models\Inventory;

use App\Models\Master\OrgDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SfgStock extends Model
{
    protected $connection = 'tm2inv';

    protected $table = 'sfg_stock';

    protected $primaryKey = 'doc_id';

    const CREATED_AT = 'create_log';

    const UPDATED_AT = 'update_log';

    protected $fillable = [
        'doc_no', 'doc_date', 'doc_type', 'doc_cat',
        'ref_doc_type', 'ref_doc_id', 'ref_doc_no', 'product_id', 'wo_id',
        'warehouse_id', 'issued_to_dept', 'process_code', 'reason_code', 'attachment_path', 'notes',
        'stat_confirm', 'stat_cancel', 'stat_closed', 'post_date', 'period_id',
        'flow_status', 'posted_by', 'posted_at',
        'opn_start', 'opn_stop', 'opn_officer', 'opn_witness', 'full_scope',
        'create_by', 'update_by',
    ];

    protected $casts = [
        'doc_date' => 'datetime',
        'post_date' => 'date',
        'posted_at' => 'datetime',
        'opn_start' => 'datetime',
        'opn_stop' => 'datetime',
        'stat_confirm' => 'boolean',
        'stat_cancel' => 'boolean',
        'stat_closed' => 'boolean',
        'full_scope' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(SfgStockD::class, 'doc_id', 'doc_id')->orderBy('seq_no');
    }

    public function issuedToDepartment(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'issued_to_dept', 'department_id');
    }
}
