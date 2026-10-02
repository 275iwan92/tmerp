<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Dashboard opening screen (user 2026-10-02): OVERALL = papan yang disusun pemiliknya dengan tanda centang; selain itu
// dashboard per bidang (Sales, Purchasing, AR & AP, Cashflow, Payroll, Human Resource) yang isinya template
// (sys_board_template.profile_code = dashboard_code). Widget di dashboard bidang itulah yang bisa dicentang masuk ke OVERALL.
class SysDashboard extends Model
{
    public const OVERALL = 'OVERALL';

    protected $connection = 'tm2sys';

    protected $table = 'sys_dashboard';

    protected $primaryKey = 'id_dashboard';

    protected $fillable = ['dashboard_code', 'title', 'icon', 'module_code', 'min_level', 'sort_order', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean', 'min_level' => 'integer', 'sort_order' => 'integer'];
}
