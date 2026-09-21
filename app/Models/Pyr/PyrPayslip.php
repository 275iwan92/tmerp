<?php

namespace App\Models\Pyr;

use App\Casts\PayrollEncrypted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PyrPayslip extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_payslip';

    protected $primaryKey = 'id_payslip';

    public $timestamps = false;

    protected $fillable = [
        'id_run',
        'employee_id',
        'total_pokok',
        'total_tj_keahlian',
        'total_tj_loyalitas',
        'total_tj_prestasi',
        'total_bonus_pagi',
        'total_bonus_hadir',
        'total_uang_roti',
        'total_overtime_pay',
        'total_denda',
        'total_bpjs_employee',
        'total_pph21',
        'total_gaji_bersih',
        'has_anomaly',
        'anomaly_note',
        'print_flag',
        'printed_at',
        'wa_flag',
        'wa_sent_at',
    ];

    protected $casts = [
        'total_pokok' => PayrollEncrypted::class,
        'total_tj_keahlian' => 'decimal:2',
        'total_tj_loyalitas' => 'decimal:2',
        'total_tj_prestasi' => 'decimal:2',
        'total_bonus_pagi' => 'decimal:2',
        'total_bonus_hadir' => 'decimal:2',
        'total_uang_roti' => 'decimal:2',
        'total_overtime_pay' => 'decimal:2',
        'total_denda' => 'decimal:2',
        'total_bpjs_employee' => 'decimal:2',
        'total_pph21' => 'decimal:2',
        'total_gaji_bersih' => PayrollEncrypted::class,
        'has_anomaly' => 'boolean',
        'print_flag' => 'boolean',
        'printed_at' => 'datetime',
        'wa_flag' => 'boolean',
        'wa_sent_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(PyrPayrollRun::class, 'id_run', 'id_run');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PyrPayslipDetail::class, 'id_payslip', 'id_payslip');
    }
}
