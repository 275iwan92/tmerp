<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Jam kantor CV. Telasih Makmur per hari (ISO 1 = Monday .. 7 = Sunday): jam perusahaan menerima/mengirim barang,
// dipakai slot kirim CPDEL. BUKAN jam produksi -- produksi bisa 24/7 menurut shift (emp_shift).
class SysOfficeHour extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'sys_office_hour';

    protected $primaryKey = 'id_office_hour';

    protected $fillable = ['day_of_week', 'day_name', 'is_workday', 'start_time', 'end_time', 'notes', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['is_workday' => 'boolean', 'activate' => 'boolean'];
}
