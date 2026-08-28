<?php

namespace App\Models\Pur;

use App\Models\Master\OrgDepartment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrRequisition extends Model
{
    protected $connection = 'tm2pur';

    protected $table = 'pr_requisition';

    protected $primaryKey = 'pr_id';

    protected $fillable = [
        'doc_no', 'i_doc_date', 'i_source', 'mrp_run_id', 'i_department_id', 'i_need_date', 'i_notes',
        'c_est_total', 'status', 'revision_no', 'parent_pr_id', 'stat_cancel', 'cancel_reason',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'i_doc_date' => 'date',
        'i_need_date' => 'date',
        'stat_cancel' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PrLine::class, 'pr_id', 'pr_id')->orderBy('line_no');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'i_department_id', 'department_id');
    }

    public static function nextDocNo(): string
    {
        $prefix = 'PR'.now()->format('y');

        $last = static::where('doc_no', 'like', "{$prefix}%")
            ->lockForUpdate()
            ->orderByDesc('doc_no')
            ->value('doc_no');

        $seq = $last ? ((int) substr($last, 4)) + 1 : 1;

        return $prefix.str_pad((string) $seq, 5, '0', STR_PAD_LEFT);
    }

    public function recalcEstTotal(): void
    {
        $this->c_est_total = $this->lines()->sum('c_est_subtotal');
    }
}
