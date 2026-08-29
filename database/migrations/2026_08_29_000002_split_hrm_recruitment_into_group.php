<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Recruitment sekarang punya spec lengkap Tier 1-4 (docs/recruitment_tier1-4.md,
// belum diimplementasi -- lihat docs/recruitment_state.yaml). Item flat
// HRM_RECRUITMENT (id 975, dibuat 2026-08-26 sbg placeholder tunggal) dipecah
// jadi GRUP berisi 8 layar internal (L1-L9 dari recruitment_tier4.md SECTION 1.2,
// L8 digabung sebagai satu item "Rekap Sync Log"). Semua tetap NOT_BUILT --
// belum ada 1 pun yang digarap Claude Code, ini murni penataan sidebar sesuai
// docs/sidebarmenustandard.md (grup begitu isinya > 1-2 layar).
// Portal kandidat (P1-P4, Flutter, host terpisah) TIDAK masuk sini -- itu bukan
// layar internal Livewire, di luar sys_app_menu.
return new class extends Migration
{
    public function up(): void
    {
        $sys = DB::connection('tm2sys');

        $groupId = $sys->table('sys_app_menu')->where('code', 'HRM_RECRUITMENT')->value('id');

        if (! $groupId || $sys->table('sys_app_menu')->where('code', 'HRM_RCR_REQUISITION')->exists()) {
            return;
        }

        $sys->table('sys_app_menu')->where('id', $groupId)->update([
            'menu_type' => 'group',
            'object_type' => null,
        ]);

        $nextId = (int) $sys->table('sys_app_menu')->max('id') + 1;

        $items = [
            ['code' => 'HRM_RCR_REQUISITION', 'label' => 'Permohonan Tenaga Kerja', 'sort_order' => 10],
            ['code' => 'HRM_RCR_VACANCY', 'label' => 'Posting Lowongan & Monitoring Channel', 'sort_order' => 20],
            ['code' => 'HRM_RCR_TALENTPOOL', 'label' => 'Talent Pool', 'sort_order' => 30],
            ['code' => 'HRM_RCR_PIPELINE', 'label' => 'Pipeline Board', 'sort_order' => 40],
            ['code' => 'HRM_RCR_INTERVIEW', 'label' => 'Jadwal Interview', 'sort_order' => 50],
            ['code' => 'HRM_RCR_ASSESSMENT', 'label' => 'Input Hasil Psikotes/Interview', 'sort_order' => 60],
            ['code' => 'HRM_RCR_OFFER', 'label' => 'Offering', 'sort_order' => 70],
            ['code' => 'HRM_RCR_SYNCLOG', 'label' => 'Rekap Sync Log', 'sort_order' => 80],
            ['code' => 'HRM_RCR_PROVISION', 'label' => 'Provisioning ke ERP', 'sort_order' => 90],
        ];

        foreach ($items as $item) {
            $sys->table('sys_app_menu')->insert([
                'id' => $nextId++,
                'parent_id' => $groupId,
                'code' => $item['code'],
                'label' => $item['label'],
                'menu_type' => 'item',
                'object_type' => 'form',
                'target_kind' => 'app',
                'sort_order' => $item['sort_order'],
                'activate' => true,
                'status_code' => 'NOT_BUILT',
                'status_updated_by' => 'claude_code',
                'status_updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $sys = DB::connection('tm2sys');

        $sys->table('sys_app_menu')->whereIn('code', [
            'HRM_RCR_REQUISITION', 'HRM_RCR_VACANCY', 'HRM_RCR_TALENTPOOL',
            'HRM_RCR_PIPELINE', 'HRM_RCR_INTERVIEW', 'HRM_RCR_ASSESSMENT',
            'HRM_RCR_OFFER', 'HRM_RCR_SYNCLOG', 'HRM_RCR_PROVISION',
        ])->delete();

        $sys->table('sys_app_menu')->where('code', 'HRM_RECRUITMENT')->update([
            'menu_type' => 'item',
            'object_type' => 'form',
        ]);
    }
};
