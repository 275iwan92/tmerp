<?php

namespace App\Models\Sdi;

use App\Models\Master\MatCategory;
use App\Models\Master\MatRaw;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IqpPartMaterial extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_part_material';

    protected $primaryKey = 'id_part_material';

    protected $fillable = [
        'part_id',
        'layer_seq',
        'rawmat_id',
        'category_id',
        'rawmat_name',
        'gram_weight',
        'flute_type',
        'size_p',
        'size_l',
        'qty_per_sheet',
        'uom',
        'source',
        'is_primary',
        'material_source',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'gram_weight' => 'decimal:4',
        'size_p' => 'decimal:4',
        'size_l' => 'decimal:4',
    ];

    public function part(): BelongsTo
    {
        return $this->belongsTo(IqpPart::class, 'part_id', 'id_part');
    }

    // FK lintas-DB (tm2sdi -> tm2mst): rawmat_id = id snapshot saat inquiry dibuat.
    // Master boleh berubah setelahnya; rawmat_name di kolom ini adalah snapshot beku.
    public function rawMaterial(): BelongsTo
    {
        return $this->belongsTo(MatRaw::class, 'rawmat_id', 'material_id');
    }

    // FK lintas-DB (tm2sdi -> tm2mst): kategori bahan (Jenis atau Gramatur/Spec)
    // yang dipilih sales di Inquiry New -- bukan mat_raw spesifik (sales tidak
    // pilih brand/ukuran, lihat migration 2026_09_29_000002).
    public function category(): BelongsTo
    {
        return $this->belongsTo(MatCategory::class, 'category_id', 'id_category');
    }
}
