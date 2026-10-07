<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// C8: HRM > Organization Chart, Recruitment > Pipeline Board -> layar v2; Employee Self-Service = Edit Profile v2 (/v2/profile, sudah penuh: data diri,
// keluarga, dokumen, cuti / sakit / izin, izin keluar, tukar shift, jadwal, kartu pengenal, akun) jadi menunya cukup menunjuk ke sana.
// HRM_RCR_PORTAL (portal kandidat publik, di luar login) sengaja TIDAK diubah.
return new class extends Migration
{
    protected $connection = 'tm2sys';

    private const MAP = ['HRM_ORG_CHART' => ['v2:hrm-org-chart', 'report'], 'HRM_RCR_PIPELINE' => ['v2:hrm-pipeline-board', 'grid'], 'HRM_SELFSERVICE' => ['/v2/profile', 'form']];

    public function up(): void
    {
        foreach (self::MAP as $code => [$target, $type]) {
            DB::connection('tm2sys')->table('sys_app_menu')->where('code', $code)->update(['target' => $target, 'object_type' => $type,
                'status_code' => 'READY_FOR_QC', 'status_updated_by' => 'claude (v2 build)', 'status_updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::connection('tm2sys')->table('sys_app_menu')->whereIn('code', array_keys(self::MAP))->update(['target' => null]);
    }
};
