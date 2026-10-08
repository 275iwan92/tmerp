<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

// Laporan masalah (dibuat siapa saja atau oleh Daily Check). Request tidak sama dengan order: yang ditolak atau dobel tidak
// mengotori data order. Alur Accept / Reject = App\Services\Maintenance\RequestService.
class MntRequest extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_request';

    protected $primaryKey = 'id_request';

    protected $guarded = ['id_request'];

    protected $casts = ['request_date' => 'datetime', 'stopped_at' => 'datetime', 'asset_stopped' => 'boolean'];

    public const KINDS = ['BREAKDOWN' => 'Breakdown', 'CORRECTIVE' => 'Corrective', 'SAFETY' => 'Safety', 'IMPROVEMENT' => 'Improvement'];

    public const STATUS = ['OPEN' => 'Open', 'ACCEPTED' => 'Accepted', 'REJECTED' => 'Rejected'];

    public const STATUS_TONES = ['Open' => 'warn', 'Accepted' => 'success', 'Rejected' => 'danger'];

    public const KIND_TONES = ['Breakdown' => 'danger', 'Corrective' => 'warn', 'Safety' => 'danger', 'Improvement' => 'info'];
}
