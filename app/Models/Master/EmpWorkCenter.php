<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Unit kerja tempat pegawai ditempatkan di roster. Menyatukan 2 hal yang dulu
// terpisah: mesin Offset (machine_id terisi, 1:1 ke prod_machine) dan unit
// non-mesin seperti QC/PPIC/Gudang (machine_id NULL) yang dulu tidak punya
// wadah sama sekali -- itu sebabnya layar roster lama terpaksa punya 2 papan.
class EmpWorkCenter extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_work_center';

    protected $primaryKey = 'work_center_id';

    protected $fillable = [
        'department_id',
        'machine_id',
        'work_center_code',
        'work_center_name',
        'sort_order',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrgDepartment::class, 'department_id', 'department_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(EmpEmployeeWorkCenterSkill::class, 'work_center_id', 'work_center_id');
    }
}
