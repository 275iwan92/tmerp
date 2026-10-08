<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Tooling master (plate sets, cutting / emboss / foil dies): status feeds the tooling gate of scheduling, first / repeat setup minutes feed SetupTimeEngine.
class ProdToolingAsset extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'prod_tooling_asset';

    protected $primaryKey = 'id_tooling_asset';

    public const TYPES = ['PLATE_SET' => 'Plate set', 'CUTTING_DIE' => 'Cutting die', 'EMBOSS_DIE' => 'Emboss die', 'FOIL_DIE' => 'Foil die'];

    public const STATUSES = ['NOT_MADE' => 'Not made', 'IN_MAKING' => 'In making', 'READY' => 'Ready', 'IN_USE' => 'In use', 'NEEDS_REPAIR' => 'Needs repair', 'RETIRED' => 'Retired'];

    protected $fillable = ['tooling_code', 'tooling_type', 'product_id', 'dieline_ref', 'size_note', 'location', 'status', 'impressions_planned', 'impressions_used',
        'setup_first_min', 'setup_repeat_min', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean'];
}
