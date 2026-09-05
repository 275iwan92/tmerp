<?php

namespace App\Models\Pyr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PyrPayrollRun extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_payroll_run';

    protected $primaryKey = 'id_run';

    public $timestamps = false;

    protected $fillable = [
        'period_year',
        'period_month',
        'run_status',
        'triggered_by',
        'triggered_at',
        'finalized_by',
        'finalized_at',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'finalized_at' => 'datetime',
    ];

    public function payslips(): HasMany
    {
        return $this->hasMany(PyrPayslip::class, 'id_run', 'id_run');
    }

    public function periodStart(): \Carbon\Carbon
    {
        return \Carbon\Carbon::create($this->period_year, $this->period_month, 1)->startOfDay();
    }

    public function periodEnd(): \Carbon\Carbon
    {
        return $this->periodStart()->endOfMonth();
    }
}
