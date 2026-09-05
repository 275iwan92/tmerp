<?php

namespace App\Models\Pyr;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PyrPayslipDetail extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_payslip_detail';

    protected $primaryKey = 'id_detail';

    public $timestamps = false;

    protected $fillable = [
        'id_payslip',
        'attendance_date',
        'status_code',
        'component_code',
        'amount',
        'note',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(PyrPayslip::class, 'id_payslip', 'id_payslip');
    }
}
