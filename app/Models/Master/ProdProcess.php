<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class ProdProcess extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_process';

    protected $primaryKey = 'id_process';

    protected $fillable = [
        'process_name',
        'gantt_color',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Tidak ada relasi balik ke iqp_part_process (tm2sdi, process_id) — sengaja,
    // master tetap netral. Lihat IqpPartProcess::process().
}
