<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Perbaikan data (bukan fitur) -- sesi Claude Code ini tanpa sengaja
// membatalkan IQ2600001 saat menguji fitur upload artwork REVISE (memanggil
// revise.blade.php::persist() dgn source_id=5, yang otomatis menandai
// SOURCE-nya CANCELLED/superseded -- efek samping alur REVISE sungguhan,
// child revision hasil test SUDAH dihapus tapi status SOURCE lupa
// dikembalikan). Nilai asli (SUBMITTED, tidak dibatalkan) direkonstruksi
// dari migration seed aslinya:
// 2026_08_22_000001_confirm_test_inquiries_for_worklist_demo.php.
return new class extends Migration
{
    public function up(): void
    {
        DB::connection('tm2sdi')->table('iqp_inquiry')
            ->where('inquiry_no', 'IQ2600001')
            ->where('stat_cancel', true)
            ->update([
                'stat_cancel' => false,
                'sub_state' => 'SUBMITTED',
                'cancel_reason' => null,
                'updated_by' => 'system',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Tidak ada "down" yang aman/berarti -- ini perbaikan data korup,
        // bukan perubahan skema. Sengaja no-op.
    }
};
