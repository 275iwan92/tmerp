<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// User 2026-08-31: jangan pisah grup "Reports -- <kelas>" sendiri -- gabung
// ke grup kelasnya masing-masing (Raw Material/Finished Goods/Semi Finished
// Goods), item AED (Stock In/Out/dst) vs Report dibedakan LEWAT IKON saja,
// bukan lewat grup terpisah. 3 tingkat report dibedakan ikon juga:
// - Stock Balance   = lv1 operasional (staff gudang, "berapa stok sekarang")
// - Stock Card      = lv2 manager (riwayat mutasi, dipakai audit/supervisor)
// - Slow Moving Item = lv3 top management (implikasi strategis working capital)
// Pemetaan lv1/2/3 ke 3 laporan yang ada ini interpretasi Claude Code (user
// cuma minta "beda ikon" tanpa merinci laporan mana level berapa) -- gampang
// diubah tinggal update kolom `icon` kalau user mau pemetaan lain.
// AED items sengaja TIDAK diisi kolom icon manual -- fallback object_type
// 'form' -> fa-pen-to-square (lihat iconFor() main-menu.blade.php) sudah
// pas mewakili "add/edit/delete".
return new class extends Migration
{
    private const MOVES = [
        // itemCode => [newParentCode, newSortOrder]
        'INV_RAW_RM' => ['INV_TRX_RM', 60],
        'INV_CARD_RM' => ['INV_TRX_RM', 70],
        'INV_SLOW_RM' => ['INV_TRX_RM', 80],
        'INV_RAW_FG' => ['INV_TRX_FG', 60],
        'INV_CARD_FG' => ['INV_TRX_FG', 70],
        'INV_SLOW_FG' => ['INV_TRX_FG', 80],
        'INV_RAW_SFG' => ['INV_TRX_SFG', 60],
        'INV_CARD_SFG' => ['INV_TRX_SFG', 70],
        'INV_SLOW_SFG' => ['INV_TRX_SFG', 80],
    ];

    private const ICONS = [
        'INV_RAW_RM' => 'fa-solid fa-list-check',
        'INV_RAW_FG' => 'fa-solid fa-list-check',
        'INV_RAW_SFG' => 'fa-solid fa-list-check',
        'INV_CARD_RM' => 'fa-solid fa-chart-column',
        'INV_CARD_FG' => 'fa-solid fa-chart-column',
        'INV_CARD_SFG' => 'fa-solid fa-chart-column',
        'INV_SLOW_RM' => 'fa-solid fa-chart-pie',
        'INV_SLOW_FG' => 'fa-solid fa-chart-pie',
        'INV_SLOW_SFG' => 'fa-solid fa-chart-pie',
    ];

    private const OLD_REPORT_GROUPS = ['INV_RPT_RM', 'INV_RPT_FG', 'INV_RPT_SFG'];

    public function up(): void
    {
        $db = DB::connection('tm2sys');

        foreach (self::MOVES as $itemCode => [$parentCode, $sort]) {
            $parentId = $db->table('sys_app_menu')->where('code', $parentCode)->value('id');
            $db->table('sys_app_menu')->where('code', $itemCode)->update([
                'parent_id' => $parentId,
                'sort_order' => $sort,
                'icon' => self::ICONS[$itemCode],
            ]);
        }

        $db->table('sys_app_menu')->whereIn('code', self::OLD_REPORT_GROUPS)->update(['activate' => false]);
    }

    public function down(): void
    {
        $db = DB::connection('tm2sys');

        $oldSort = ['INV_RAW_RM' => 10, 'INV_CARD_RM' => 20, 'INV_SLOW_RM' => 30, 'INV_RAW_FG' => 10, 'INV_CARD_FG' => 20, 'INV_SLOW_FG' => 30, 'INV_RAW_SFG' => 10, 'INV_CARD_SFG' => 20, 'INV_SLOW_SFG' => 30];
        $oldParent = ['INV_RAW_RM' => 'INV_RPT_RM', 'INV_CARD_RM' => 'INV_RPT_RM', 'INV_SLOW_RM' => 'INV_RPT_RM', 'INV_RAW_FG' => 'INV_RPT_FG', 'INV_CARD_FG' => 'INV_RPT_FG', 'INV_SLOW_FG' => 'INV_RPT_FG', 'INV_RAW_SFG' => 'INV_RPT_SFG', 'INV_CARD_SFG' => 'INV_RPT_SFG', 'INV_SLOW_SFG' => 'INV_RPT_SFG'];

        $db->table('sys_app_menu')->whereIn('code', self::OLD_REPORT_GROUPS)->update(['activate' => true]);

        foreach ($oldParent as $itemCode => $parentCode) {
            $parentId = $db->table('sys_app_menu')->where('code', $parentCode)->value('id');
            $db->table('sys_app_menu')->where('code', $itemCode)->update([
                'parent_id' => $parentId,
                'sort_order' => $oldSort[$itemCode],
                'icon' => null,
            ]);
        }
    }
};
