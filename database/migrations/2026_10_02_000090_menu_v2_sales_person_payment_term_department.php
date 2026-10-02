<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Audit 2026-10-02 (docs/audit_input_report_lookup_2026_10_02.md D.2): tiga lookup yang dibaca banyak layar tetapi tidak punya
// layar isi -- Sales Person, Payment Terms (Master Data) dan Department (Human Resources > Organization). Grid Lookup v2.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // kode baru => [kode baris tetangga (induk + hak akses ikut dia), urutan, label, ikon, slug]
    private const ROWS = [
        'MD_SALES_PERSON' => ['MD_CUST', 13, 'Sales Person', 'fa-solid fa-user-tie', 'md-sales-person'],
        'MD_PAYMENT_TERM' => ['MD_CURRENCY', 89, 'Payment Terms', 'fa-solid fa-file-invoice-dollar', 'md-payment-term'],
        'HRM_ORG_DEPT' => ['HRM_ORG_STRUCTURE', 5, 'Department', 'fa-solid fa-people-group', 'hrm-department'],
    ];

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        foreach (self::ROWS as $code => [$sibling, $sort, $label, $icon, $slug]) {
            $near = $menu()->where('code', $sibling)->first();
            if (! $near || $menu()->where('code', $code)->exists()) {
                continue;
            }
            $menu()->insert([
                'parent_id' => $near->parent_id, 'code' => $code, 'label' => $label, 'icon' => $icon,
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:'.$slug, 'sort_order' => $sort,
                'activate' => 1, 'required_object' => $near->required_object, 'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_keys(self::ROWS))->delete();
    }
};
