<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Model;

// Target per bulan. sales_person_id 0 = target perusahaan; selain itu target satu sales person (tm2mst.sales_person, relasi logis).
// OVERDUE_RECEIVABLES = batas atas (makin kecil makin baik); ONTIME_DELIVERY dalam persen.
class SysKpiTarget extends Model
{
    public const KEYS = ['REVENUE' => 'Order value (confirmed Customer Orders)', 'NEW_ORDERS' => 'New Customer Orders (documents)', 'NEW_CUSTOMERS' => 'New customers (first order)',
        'ONTIME_DELIVERY' => 'On-time delivery (percent)', 'OVERDUE_RECEIVABLES' => 'Overdue receivables (upper limit)'];

    protected $connection = 'tm2sys';

    protected $table = 'sys_kpi_target';

    protected $primaryKey = 'id_kpi_target';

    protected $fillable = ['target_key', 'period_month', 'sales_person_id', 'target_value', 'notes', 'activate', 'created_by', 'updated_by'];

    protected $casts = ['activate' => 'boolean', 'period_month' => 'date:Y-m-d', 'target_value' => 'float'];
}
