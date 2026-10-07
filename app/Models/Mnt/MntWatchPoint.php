<?php

namespace App\Models\Mnt;

use Illuminate\Database\Eloquent\Model;

// Satu hal yang dicek pada aset: apa, cara, batas normal (opsional, diisi dari manual), frekuensi, pemeriksa.
class MntWatchPoint extends Model
{
    protected $connection = 'tm2mnt';

    protected $table = 'mnt_watch_point';

    protected $primaryKey = 'id_watch_point';

    protected $guarded = ['id_watch_point'];

    protected $casts = ['activate' => 'boolean', 'normal_min' => 'float', 'normal_max' => 'float'];

    public const FREQUENCIES = ['SHIFT' => 'Every shift', 'DAILY' => 'Daily', 'WEEKLY' => 'Weekly', 'MONTHLY' => 'Monthly'];

    public const ROLES = ['OPERATOR' => 'Operator', 'TECHNICIAN' => 'Technician'];
}
