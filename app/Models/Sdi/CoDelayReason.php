<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;

// Master alasan jadwal kirim bergeser (CPDEL): tiap alasan menunjuk departemen penanggung jawab (KPI janji kirim).
class CoDelayReason extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'co_delay_reason';

    protected $primaryKey = 'id_delay_reason';

    protected $fillable = ['reason_code', 'reason_name', 'department', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
