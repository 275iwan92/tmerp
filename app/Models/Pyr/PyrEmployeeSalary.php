<?php

namespace App\Models\Pyr;

use App\Casts\PayrollEncrypted;
use Illuminate\Database\Eloquent\Model;

// employee_id LINTAS-DB ke tm2mst.emp_employee -- tidak ada relasi
// Eloquent belongsTo (beda koneksi), resolve manual via EmpEmployee::find()
// kalau perlu tampilkan nama (CLAUDE.md §2 pola 1: query terpisah).
class PyrEmployeeSalary extends Model
{
    protected $connection = 'tm2pyr';

    protected $table = 'pyr_employee_salary';

    protected $primaryKey = 'id_salary';

    protected $fillable = [
        'employee_id',
        'basic_salary_monthly',
        'tj_keahlian_monthly',
        'tj_loyalitas_monthly',
        'tj_prestasi_monthly',
        'bonus_pagi_amount',
        'bonus_hadir_amount',
        'effective_from',
        'effective_to',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'basic_salary_monthly' => PayrollEncrypted::class,
        'tj_keahlian_monthly' => 'decimal:2',
        'tj_loyalitas_monthly' => 'decimal:2',
        'tj_prestasi_monthly' => 'decimal:2',
        'bonus_pagi_amount' => 'decimal:2',
        'bonus_hadir_amount' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'activate' => 'boolean',
    ];

    public static function activeFor(int $employeeId): ?self
    {
        return static::query()
            ->where('employee_id', $employeeId)
            ->where('activate', true)
            ->latest('effective_from')
            ->first();
    }

    // gaji_pokok_harian / tj_keahlian_harian utk formula prorata (Tier 2 §3) --
    // 1 bulan diasumsikan 30 hari kalender (konsisten dgn prorata gaji bulanan
    // ke harian yang lazim dipakai; bukan hari kerja efektif).
    public function dailyBasicSalary(): float
    {
        return round(((float) $this->basic_salary_monthly) / 30, 2);
    }

    public function dailyTjKeahlian(): float
    {
        return round(((float) $this->tj_keahlian_monthly) / 30, 2);
    }
}
