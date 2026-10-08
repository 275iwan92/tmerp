<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

// Register alat cetak (v2.01): die, plat, klise. Master sederhana dengan status dan lokasi (bin).
class InvTool extends Model
{
    public const TYPES = ['DIE' => 'Die', 'PLATE' => 'Plate', 'BLOCK' => 'Block'];

    public const STATUSES = ['AVAILABLE' => 'Available', 'IN_USE' => 'In use', 'AT_VENDOR' => 'At vendor', 'REPAIR' => 'In repair', 'RETIRED' => 'Retired'];

    protected $connection = 'tm2inv';

    protected $table = 'inv_tool';

    protected $primaryKey = 'id_tool';

    protected $guarded = ['id_tool'];

    protected $casts = ['activate' => 'boolean', 'impressions' => 'integer'];
}
