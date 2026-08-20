<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmpLeaveRequest extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_leave_request';

    protected $primaryKey = 'request_id';

    public $timestamps = false;

    protected $fillable = [
        'employee_id',
        'leave_type',
        'khusus_event_type',
        'izin_category',
        'izin_relasi_pemohon',
        'izin_instansi_asal',
        'date_from',
        'date_to',
        'total_days',
        'reason',
        'attachment_surat_cuti',
        'attachment_surat_dokter',
        'attachment_kwitansi_resep',
        'attachment_jkn_screenshot',
        'attachment_path',
        'sakit_lapor_jam',
        'converted_to_izin',
        'is_paid',
        'deducts_leave_balance',
        'status',
        'requested_at',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'total_days' => 'integer',
        'converted_to_izin' => 'boolean',
        'is_paid' => 'boolean',
        'deducts_leave_balance' => 'boolean',
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
