<?php

namespace App\Models\Sdi;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IqpPart extends Model
{
    protected $connection = 'tm2sdi';

    protected $table = 'iqp_part';

    protected $primaryKey = 'id_part';

    protected $fillable = [
        'inquiry_id',
        'prod_part_id',
        'part_seq',
        'part_code',
        'part_name',
        'part_type',
        'qty_per_sku',
        'pack_qty',
        'cfg_color',
        'cfg_ink',
        'dieline_spec',
        'flat_w',
        'flat_h',
        'flat_area',
        'artwork_path',
        'artwork_original_filename',
        'artwork_colors',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dieline_spec' => 'array',
        'artwork_colors' => 'array',
        'qty_per_sku' => 'decimal:4',
        'flat_w' => 'decimal:4',
        'flat_h' => 'decimal:4',
        'flat_area' => 'decimal:4',
    ];

    public function inquiry(): BelongsTo
    {
        return $this->belongsTo(IqpInquiry::class, 'inquiry_id', 'id_inquiry');
    }

    // Bahan yang DIPILIH Sales (input). Baris source=ENGINE (bahan finishing
    // hasil engine BOM ProDev) sengaja tidak ikut, supaya tidak muncul/terhapus
    // di form Inquiry -- pakai bomMaterials() untuk BOM lengkap.
    public function materials(): HasMany
    {
        return $this->hasMany(IqpPartMaterial::class, 'part_id', 'id_part')->where('source', 'MANUAL');
    }

    public function bomMaterials(): HasMany
    {
        return $this->hasMany(IqpPartMaterial::class, 'part_id', 'id_part')->orderBy('layer_seq');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(IqpPartProcess::class, 'part_id', 'id_part');
    }

    public function finishings(): HasMany
    {
        return $this->hasMany(IqpPartFinishing::class, 'part_id', 'id_part')->orderBy('seq');
    }

    public function inks(): HasMany
    {
        return $this->hasMany(IqpPartInk::class, 'part_id', 'id_part')->orderBy('pass_seq')->orderBy('unit_no');
    }

    public function layout(): HasOne
    {
        return $this->hasOne(IqpLayout::class, 'part_id', 'id_part');
    }
}
