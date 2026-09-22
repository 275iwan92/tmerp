<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Urutan operasi standar per tipe/kategori produk. Titik awal BOP ProDev
// supaya routing tidak disusun ulang dari nol setiap part.
class ProdRoutingTemplate extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_routing_template';

    protected $primaryKey = 'template_id';

    protected $fillable = [
        'template_code',
        'template_name',
        'prod_type_code',
        'category_id',
        'substrate_hint',
        'is_default',
        'description',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'activate' => 'boolean',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(ProdRoutingStep::class, 'template_id', 'template_id')
            ->orderBy('branch_code')->orderBy('step_seq');
    }
}
