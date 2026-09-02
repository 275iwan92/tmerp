<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Hasil ENGINE imposition (Tool #1, §4.3a) -- 1 baris = 1 Master Card imposition,
// REUSABLE lintas Inquiry, bisa menaungi >1 prod_part (§4.2b, gabung/pisah =
// keputusan engine). Menggantikan pola lama iqp_layout (1:1 ke 1 iqp_part, tm2sdi).
class ProdLayoutGroup extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_layout_group';

    protected $primaryKey = 'id_layout_group';

    protected $fillable = [
        'product_id',
        'mastercard_code',
        'plano_size_id',
        'plano_w_mm',
        'plano_h_mm',
        'method',
        'up_count',
        'efficiency_pct',
        'waste_pct',
        'layout_detail',
        'status',
        'confirmed_at',
        'confirmed_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'layout_detail' => 'array',
        'confirmed_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProdProduct::class, 'product_id', 'id_product');
    }

    public function planoSize(): BelongsTo
    {
        return $this->belongsTo(MatPlanoSize::class, 'plano_size_id', 'id_plano_size');
    }

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(ProdPart::class, 'prod_layout_group_part', 'id_layout_group', 'part_id')
            ->withPivot('qty_up_in_group');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ProdPartAttachment::class, 'id_layout_group', 'id_layout_group');
    }
}
