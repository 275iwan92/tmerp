<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// backlog07 Grup A: layar isi Master Kategori Produk (Grid Lookup v2, md-product-category) -- baris menu baru di Master Data,
// tepat setelah Master Product.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    public function up(): void
    {
        $menu = fn () => DB::connection('tm2sys')->table('sys_app_menu');
        $product = $menu()->where('code', 'MD_PROD')->first();
        if (! $product || $menu()->where('code', 'MD_PRODCAT')->exists()) {
            return;
        }
        $menu()->insert([
            'parent_id' => $product->parent_id, 'code' => 'MD_PRODCAT', 'label' => 'Product Category', 'icon' => 'fa-solid fa-boxes-stacked',
            'menu_type' => 'item', 'object_type' => 'grid', 'target' => 'v2:md-product-category', 'sort_order' => $product->sort_order + 1,
            'activate' => 1, 'required_object' => $product->required_object, 'status_code' => 'READY_FOR_QC',
            'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->where('code', 'MD_PRODCAT')->delete();
    }
};
