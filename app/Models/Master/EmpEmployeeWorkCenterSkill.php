<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Sertifikasi pegawai per work center. Dipakai papan roster utk filter
// "Hanya yang punya skill unit ini" + penanda chip kuning kalau pegawai
// ditempatkan di unit yang belum jadi keahliannya (peringatan, bukan blokir).
class EmpEmployeeWorkCenterSkill extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_employee_work_center_skill';

    protected $fillable = [
        'employee_id',
        'work_center_id',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(EmpWorkCenter::class, 'work_center_id', 'work_center_id');
    }
}
