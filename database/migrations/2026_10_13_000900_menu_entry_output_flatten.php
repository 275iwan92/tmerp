<?php

/*
 * REMARK
 * Tujuan       : Menu "Production Output" naik satu level: item langsung di bawah Production, tanpa grup perantara "Entry OUTPUT" (permintaan Jo 13 Okt 14:57Z).
 * Sumber hulu  : Migrasi 2026_10_13_000700 (grup Entry OUTPUT hanya berisi satu menu).
 * Input        : -
 * Output       : sys_app_menu: PROD_OPERATOR.parent_id = induk PROD_OUT, sort_order = milik PROD_OUT; baris grup PROD_OUT dihapus.
 * Dipakai oleh : App\Support\V2\AppMenu (sidebar). down() menyisipkan lagi grup PROD_OUT dan memindahkan PROD_OPERATOR ke dalamnya.
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis (idempoten).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function menu()
    {
        return DB::connection('tm2sys')->table('sys_app_menu');
    }

    public function up(): void
    {
        $group = $this->menu()->where('code', 'PROD_OUT')->first();
        if (! $group) {
            return;
        }
        $this->menu()->where('code', 'PROD_OPERATOR')->update(['parent_id' => $group->parent_id, 'sort_order' => $group->sort_order]);
        if (! $this->menu()->where('parent_id', $group->id)->exists()) {
            $this->menu()->where('id', $group->id)->delete();
        }
    }

    public function down(): void
    {
        $item = $this->menu()->where('code', 'PROD_OPERATOR')->first();
        if (! $item || $this->menu()->where('code', 'PROD_OUT')->exists()) {
            return;
        }
        $id = $this->menu()->insertGetId(['parent_id' => $item->parent_id, 'code' => 'PROD_OUT', 'label' => 'Entry OUTPUT', 'icon' => 'fa-solid fa-boxes-packing',
            'menu_type' => 'group', 'sort_order' => $item->sort_order, 'activate' => 1, 'hide_in_menu' => 0, 'status_code' => 'NOT_BUILT', 'created_at' => now()]);
        $this->menu()->where('code', 'PROD_OPERATOR')->update(['parent_id' => $id, 'sort_order' => 5]);
    }
};
