<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Jabatan tambahan seorang pegawai (posisi utama = emp_employee.position_id). Satu pegawai boleh memegang beberapa jabatan.
class EmpEmployeePosition extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_employee_position';

    protected $primaryKey = 'id_employee_position';

    protected $fillable = ['employee_id', 'position_id', 'since_date', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['since_date' => 'date', 'activate' => 'boolean'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(OrgPosition::class, 'position_id', 'position_id');
    }
}
