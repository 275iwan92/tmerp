<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

// Pandangan sempit atas mat_category untuk layar Purchase Lead Time: hanya lead_time_days yang boleh ditulis.
class MatCategoryLeadTime extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'mat_category';

    protected $primaryKey = 'id_category';

    protected $fillable = ['lead_time_days', 'updated_by'];
}
