<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttFingerImport extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'att_finger_import';

    protected $primaryKey = 'id_import';

    public $timestamps = false;

    protected $fillable = [
        'import_date',
        'file_name',
        'imported_by',
        'row_count',
        'status',
        'error_note',
        'processed_at',
    ];

    protected $casts = [
        'import_date' => 'date',
        'processed_at' => 'datetime',
    ];

    public function rawScans(): HasMany
    {
        return $this->hasMany(AttFingerRaw::class, 'id_import', 'id_import');
    }
}
