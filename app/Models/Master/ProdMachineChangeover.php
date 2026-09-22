<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Tambahan waktu & waste akibat PERGANTIAN kondisi di mesin, bukan milik
// mesinnya sendiri. Tanpa ini jadwal mesin cetak selalu optimistis.
class ProdMachineChangeover extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_machine_changeover';

    protected $primaryKey = 'changeover_id';

    public const KINDS = [
        'COLOR' => 'Ganti Warna',
        'SUBSTRATE' => 'Ganti Bahan',
        'PLATE' => 'Ganti Plate',
        'DIE' => 'Ganti Pisau / Plong',
        'SIZE' => 'Ganti Ukuran',
        'INK_TYPE' => 'Ganti Jenis Tinta',
    ];

    protected $fillable = [
        'machine_id',
        'change_kind',
        'from_value',
        'to_value',
        'extra_minutes',
        'extra_waste_qty',
        'note',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'activate' => 'boolean',
        'extra_minutes' => 'integer',
        'extra_waste_qty' => 'integer',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(ProdMachine::class, 'machine_id', 'machine_id');
    }
}
