<?php

/*
 * REMARK
 * Tujuan       : Menu Manufacture > Production menjadi satu pintu "Entry OUTPUT" (Production Output); menu produksi lain yang tak dipakai dihapus.
 * Sumber hulu  : Perintah Jo 13 Okt 07:28Z-07:34Z (hapus bukan hide; Scrap Types ke QA; "Process Entry" diganti "Entry OUTPUT").
 * Input        : -
 * Output       : sys_app_menu: PROD_OUT.label = "Entry OUTPUT"; PROD_OPERATOR tampil; PROD_SCRAP_TYPE pindah ke PROD_QA;
 *                baris PROD_OUTPUT (daftar WO lama), PROD_QC (grup kosong), PROD_GRP_RPT + PROD_RPT_NO_OUTPUT dihapus.
 * Dipakai oleh : App\Support\V2\AppMenu (sidebar). down() memulihkan label, hide, induk, dan baris yang dihapus.
 * Status       : Jalankan di ddt* (--env=mockup); di tm2* saat rilis (idempoten).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Baris yang dihapus (snapshot untuk down()): kode => [parent code, label, type, target, sort].
    private const REMOVED = [
        'PROD_OUTPUT' => ['PROD_OUT', 'Production Output', 'item', 'v2:prod-work-order', 10],
        'PROD_QC' => ['PROD_PRODUCTION', 'QC', 'group', null, 20],
        'PROD_GRP_RPT' => ['PROD_PRODUCTION', 'Reports', 'group', null, 30],
        'PROD_RPT_NO_OUTPUT' => ['PROD_GRP_RPT', 'Shipped Without Production Output', 'item', 'v2:prod-no-output', 10],
    ];

    private function menu()
    {
        return DB::connection('tm2sys')->table('sys_app_menu');
    }

    public function up(): void
    {
        $id = fn (string $code) => $this->menu()->where('code', $code)->value('id');
        $this->menu()->where('code', 'PROD_OUT')->update(['label' => 'Entry OUTPUT']);
        $this->menu()->where('code', 'PROD_OPERATOR')->update(['hide_in_menu' => 0, 'label' => 'Production Output', 'sort_order' => 5]);
        if ($qa = $id('PROD_QA')) {
            $this->menu()->where('code', 'PROD_SCRAP_TYPE')->update(['parent_id' => $qa]);
        }
        foreach (['PROD_RPT_NO_OUTPUT', 'PROD_GRP_RPT', 'PROD_QC', 'PROD_OUTPUT'] as $code) {
            $this->menu()->where('code', $code)->delete();
        }
    }

    public function down(): void
    {
        $id = fn (string $code) => $this->menu()->where('code', $code)->value('id');
        $this->menu()->where('code', 'PROD_OUT')->update(['label' => 'Process Entry']);
        $this->menu()->where('code', 'PROD_OPERATOR')->update(['hide_in_menu' => 1]);
        if ($out = $id('PROD_OUT')) {
            $this->menu()->where('code', 'PROD_SCRAP_TYPE')->update(['parent_id' => $out]);
        }
        foreach (['PROD_QC', 'PROD_GRP_RPT', 'PROD_OUTPUT', 'PROD_RPT_NO_OUTPUT'] as $code) {
            [$parent, $label, $type, $target, $sort] = self::REMOVED[$code];
            if ($this->menu()->where('code', $code)->exists() || ! ($pid = $id($parent))) {
                continue;
            }
            $this->menu()->insert(['parent_id' => $pid, 'code' => $code, 'label' => $label, 'menu_type' => $type, 'object_type' => $type === 'group' ? 'group' : 'grid',
                'target' => $target, 'sort_order' => $sort, 'activate' => 1, 'hide_in_menu' => 0, 'status_code' => 'READY_FOR_QC', 'created_at' => now()]);
        }
    }
};
