<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// UI-20 rollout (backlog01): lookup Master Data -> Grid Lookup v2 (diisi di sel, simpan ke database):
// Satuan, Konversi Satuan (baris menu baru; di v1 panel di dalam Master Satuan), Currency, Kurs, Kategori Supplier.
// down() = target lama (MenuRoutes v1) + baris Konversi Satuan dihapus.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    // kode => [slug v2, target lama]
    private const MAP = [
        'MD_UOM' => ['md-uom', 'form_uom.php'],
        'MD_CURRENCY' => ['md-currency', null],
        'MD_EXCHRATE' => ['md-exchange-rate', null],
        'MD_SUPCAT' => ['md-supplier-category', null],
    ];

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        foreach (self::MAP as $code => [$slug]) {
            $menu()->where('code', $code)->where(fn ($q) => $q->whereNull('target')->orWhere('target', 'not like', 'v2:%'))
                ->update(['target' => 'v2:'.$slug, 'object_type' => 'grid', 'status_code' => 'READY_FOR_QC',
                    'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }

        $uom = $menu()->where('code', 'MD_UOM')->first();
        if ($uom && ! $menu()->where('code', 'MD_UOM_CONV')->exists()) {
            $menu()->insert([
                'parent_id' => $uom->parent_id, 'code' => 'MD_UOM_CONV', 'label' => 'Unit Conversion', 'icon' => 'fa-solid fa-right-left',
                'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:md-uom-conversion', 'sort_order' => $uom->sort_order + 1,
                'activate' => 1, 'required_object' => $uom->required_object, 'status_code' => 'READY_FOR_QC',
                'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        foreach (self::MAP as $code => [$slug, $old]) {
            $menu()->where('code', $code)->where('target', 'v2:'.$slug)->update(['target' => $old, 'object_type' => 'form', 'status_code' => 'NOT_BUILT']);
        }
        $menu()->where('code', 'MD_UOM_CONV')->delete();
    }
};
