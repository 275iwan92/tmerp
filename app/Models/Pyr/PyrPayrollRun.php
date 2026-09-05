<?php

namespace App\Models\Pyr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Siklus status (Tier 3 §5.2, direvisi 2026-09-05 sesuai alur bisnis
// lengkap): DRAFT -> CALCULATED (bisa dijalankan ulang, estimasi H-1 &
// proses ulang H) -> VERIFIED (cek jam masuk/keluar semua/random sample)
// -> CONFIRMED (kunci presensi) -> PAID (transfer bank/cash + slip
// terkirim) -> CLOSED (masuk jurnal, next_run_id otomatis dibuat).
class PyrPayrollRun extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_payroll_run';

    protected $primaryKey = 'id_run';

    public $timestamps = false;

    protected $fillable = [
        'period_type',
        'period_start',
        'period_end',
        'run_status',
        'triggered_by',
        'triggered_at',
        'verification_method',
        'verified_sample_employee_ids',
        'verified_by',
        'verified_at',
        'confirmed_by',
        'confirmed_at',
        'paid_by',
        'paid_at',
        'payslip_sent_at',
        'closed_by',
        'closed_at',
        'next_run_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'triggered_at' => 'datetime',
        'verified_sample_employee_ids' => 'array',
        'verified_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'paid_at' => 'datetime',
        'payslip_sent_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(PyrPayslip::class, 'id_run', 'id_run');
    }

    public function nextPeriodStart(): \Carbon\Carbon
    {
        return $this->period_type === 'WEEKLY'
            ? $this->period_end->copy()->addDay()
            : $this->period_start->copy()->addMonthNoOverflow();
    }

    public function nextPeriodEnd(): \Carbon\Carbon
    {
        return $this->period_type === 'WEEKLY'
            ? $this->nextPeriodStart()->copy()->addDays(6)
            : $this->nextPeriodStart()->copy()->endOfMonth();
    }
}
