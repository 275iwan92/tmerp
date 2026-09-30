<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Standar pemakaian bahan non-kertas (tinta/varnish/coating/film/lem) untuk Material Planning.
// trigger_keyword dicocokkan ke nama proses part (prod_part_process.process_name) / prod_part.finishing.
class MatUsageStandard extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_usage_standard';

    protected $primaryKey = 'id_usage_standard';

    protected $guarded = ['id_usage_standard'];

    protected $casts = ['activate' => 'boolean', 'qty_per_basis' => 'float'];
}
