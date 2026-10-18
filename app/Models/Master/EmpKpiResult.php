<?php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Nilai aktual satu KPI jabatan untuk satu pegawai pada satu bulan; target, bobot, dan arah dibekukan saat entri pertama.
class EmpKpiResult extends Model
{
    protected $connection = 'tm2mst';

    protected $table = 'emp_kpi_result';

    protected $primaryKey = 'id_kpi_result';

    protected $fillable = ['employee_id', 'position_kpi_id', 'work_center_id', 'period_month', 'actual_value', 'target_snapshot', 'weight_snapshot', 'direction_snapshot', 'note', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['period_month' => 'date', 'actual_value' => 'float', 'target_snapshot' => 'float', 'weight_snapshot' => 'float', 'activate' => 'boolean'];

    public function kpi(): BelongsTo
    {
        return $this->belongsTo(OrgPositionKpi::class, 'position_kpi_id', 'id_position_kpi');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(EmpWorkCenter::class, 'work_center_id', 'work_center_id');
    }
}
