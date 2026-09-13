<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Register dokumen SOP (Standard Operating Procedure). Input HANYA oleh HR
// Officer (SecUser::isHrOfficer()), dibaca read-only oleh semua pegawai lewat
// Self-Service (keputusan user 2026-09-13). department_id NULL = berlaku
// semua departemen. Versi pertama -- lihat docs/sop_module_blueprint_reference.md
// utk materi mentah yang lebih detail (workflow steps/QC rules/dst) yang
// belum diproses ke skema ini.
class SopDocument extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sop_document';

    protected $primaryKey = 'sop_id';

    protected $fillable = [
        'sop_code',
        'title',
        'revision',
        'effective_date',
        'department_id',
        'purpose',
        'content',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'activate' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }
}
