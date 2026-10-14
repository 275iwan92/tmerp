<?php

/*
 * REMARK
 * Tujuan       : Satu anggota crew pada satu periode (karyawan produksi + peran).
 * Sumber hulu  : tm2mst.prod_machine_crew_member.
 * Input        : id_crew_period, employee_id, role.
 * Output       : Relasi employee().
 * Dipakai oleh : MachineCrewService, layar v2 crew-form.
 * Status       : Aktif.
 */

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProdMachineCrewMember extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_machine_crew_member';

    protected $primaryKey = 'id_crew_member';

    protected $fillable = ['id_crew_period', 'employee_id', 'role'];

    public function period(): BelongsTo
    {
        return $this->belongsTo(ProdMachineCrewPeriod::class, 'id_crew_period', 'id_crew_period');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }
}
