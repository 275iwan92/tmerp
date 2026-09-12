<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// "Jadwal" di mockup roster: wadah bernama yang mencakup 1..n departemen,
// mis. "Jadwal Produksi Offset". Cakupan departemen disimpan di tabel pivot
// emp_roster_schedule_department (departemennya sendiri tetap master
// org_department, tidak diduplikasi di sini).
class EmpRosterSchedule extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_roster_schedule';

    protected $primaryKey = 'schedule_id';

    protected $fillable = [
        'schedule_name',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function departmentLinks(): HasMany
    {
        return $this->hasMany(EmpRosterScheduleDepartment::class, 'schedule_id', 'schedule_id');
    }

    public function departmentIds(): array
    {
        return $this->departmentLinks->pluck('department_id')->all();
    }
}
