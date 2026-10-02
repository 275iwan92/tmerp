<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Isi tiap dashboard (profile_code = sys_dashboard.dashboard_code): dashboard bidang tampil apa adanya; baris OVERALL = titik awal
// papan user dan tujuan Reset to Template.
class SysBoardTemplate extends Model
{
    protected $connection = 'tm2sys';

    protected $table = 'sys_board_template';

    protected $primaryKey = 'id_board_template';

    protected $fillable = ['profile_code', 'widget_key', 'sort_order', 'size', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean', 'size' => 'integer'];
}
