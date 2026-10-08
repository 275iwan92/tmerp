<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

// Laporan masalah (dibuat siapa saja atau oleh Daily Check). Request tidak sama dengan order: yang ditolak atau dobel tidak
// mengotori data order. Layar Request dan alur Accept / Reject menyusul di Fase 3.
class MntRequest extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_request';

    protected $primaryKey = 'id_request';

    protected $guarded = ['id_request'];

    protected $casts = ['request_date' => 'datetime', 'stopped_at' => 'datetime', 'asset_stopped' => 'boolean'];

    public const KINDS = ['BREAKDOWN' => 'Breakdown', 'CORRECTIVE' => 'Corrective', 'SAFETY' => 'Safety', 'IMPROVEMENT' => 'Improvement'];
}
