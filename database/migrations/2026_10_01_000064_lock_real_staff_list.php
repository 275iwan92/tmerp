<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Daftar STAFF nyata dari user (docs/attachments/KEBUTUHAN PAK IWAN.xlsx, 2026-10-01): 38 orang, semuanya sudah ada di
// emp_employee. User: "Ini DATA REAL, pastikan ada terus sampai saya update terbaru" -> dikunci sebagai data nyata:
// data_origin LIVE + data_origin_manual = 1 (keputusan admin, tidak ditarik balik aturan DataOriginService dan tidak ikut
// tersembunyi oleh setting sembunyikan LEGACY / DUMMY) + aktif. Tidak ada kolom lain yang diubah.
return new class extends Migration
{
    private const STAFF = ['TM-00146', 'TM-00118', 'TM-00139', 'TM-00112', 'TM-00113', 'TM-00114', 'TM-00115', 'TM-00116', 'TM-00121', 'TM-00122',
        'TM-00145', 'TM-00127', 'TM-00129', 'TM-00132', 'TM-00135', 'TM-00137', 'TM-00140', 'TM-00147', 'TM-00142', 'TM-00143', 'TM-00144', 'TM-00149',
        'TM-00150', 'TM-00520', 'TM-00522', 'TM-00523', 'TM-00527', 'TM-00528', 'TM-00529', 'TM-00530', 'TM-00531', 'TM-00533', 'TM-00534', 'TM-00535',
        'TM-00539', 'TM-00540', 'TM-00541', 'TM-00542'];

    public function up(): void
    {
        DB::connection('tm2mst')->table('emp_employee')->whereIn('employee_code', self::STAFF)
            ->update(['data_origin' => 'LIVE', 'data_origin_manual' => 1, 'activate' => 1, 'updated_at' => now(), 'updated_by' => 'staff-list-2026-10-01']);
    }

    public function down(): void
    {
        // tanda data nyata dibiarkan
    }
};
