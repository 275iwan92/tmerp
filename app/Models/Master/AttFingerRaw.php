<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttFingerRaw extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'att_finger_raw';

    protected $primaryKey = 'id_finger_raw';

    public $timestamps = false;

    protected $fillable = [
        'id_import',
        'employee_id',
        'scan_datetime',
        'scan_type',
        'raw_device_code',
    ];

    protected $casts = [
        'scan_datetime' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmpEmployee::class, 'employee_id', 'employee_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(AttFingerImport::class, 'id_import', 'id_import');
    }
}
