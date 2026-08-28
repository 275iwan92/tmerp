<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProdType extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_type';

    protected $primaryKey = 'type_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'type_code',
        'type_name',
        'category',
        'sub_category',
        'box_style',
        'photo_path',
        'svg_generator',
        'dieline_sample',
        'dieline_spec',
        'dieline_template_id',
        'ecma_code',
        'part_type_default',
        'material_filter',
        'has_multi_part',
        'sort_order',
        'spec_version',
        'notes',
        'activate',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dieline_spec' => 'array',
        'has_multi_part' => 'boolean',
        'activate' => 'boolean',
    ];

    public function params(): HasMany
    {
        return $this->hasMany(ProdTypeParam::class, 'type_code', 'type_code')->orderBy('seq');
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProdTypeSize::class, 'type_code', 'type_code')->orderBy('sort_order');
    }
}
