<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tabel hari libur (docs/BACKLOG.md, item "mudah" #1, 2026-09-02) --
// ditemukan tidak ada sama sekali di database manapun (TIER_1_ATTENDANCE.md
// DELTA-02, blocker nyata utk aturan lembur H-7/H-1 modul Attendance yang
// belum dibangun). Ditaruh tm2mst (prefix sys_ = "sistem/parameter" master
// data, CLAUDE.md §3) krn sifatnya referensi lintas-domain (bukan cuma
// dipakai Attendance -- Production Scheduling/Delivery bisa butuh juga),
// bukan tm2sys (itu utk user/role/notifikasi/audit).
return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tm2mst')->create('sys_holiday', function (Blueprint $table) {
            $table->id('id_holiday');
            $table->date('holiday_date')->unique();
            $table->string('holiday_name', 150);
            $table->enum('holiday_type', ['NATIONAL', 'COMPANY'])->default('NATIONAL');
            $table->boolean('activate')->default(true);
            $table->timestamps();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });

        // Seed HANYA hari libur nasional tanggal TETAP (kalender Masehi,
        // tidak butuh penetapan pemerintah) -- SENGAJA TIDAK isi hari libur
        // penanggalan bulan/lunar (Idul Fitri/Idul Adha/Nyepi/Imlek/Waisak/
        // Isra Mikraj/Maulid Nabi/Tahun Baru Islam/Wafat & Kenaikan Isa
        // Almasih) krn tanggal pastinya beda tiap tahun & baru resmi lewat
        // SKB 3 Menteri -- isi manual lewat layar Master Hari Libur begitu
        // kalender resmi tahun berjalan terbit, jangan ditebak di sini.
        $now = now();
        $fixedDates = [
            ['holiday_date' => '2026-01-01', 'holiday_name' => 'Tahun Baru Masehi'],
            ['holiday_date' => '2026-05-01', 'holiday_name' => 'Hari Buruh Internasional'],
            ['holiday_date' => '2026-06-01', 'holiday_name' => 'Hari Lahir Pancasila'],
            ['holiday_date' => '2026-08-17', 'holiday_name' => 'Hari Kemerdekaan RI'],
            ['holiday_date' => '2026-12-25', 'holiday_name' => 'Hari Raya Natal'],
        ];

        foreach ($fixedDates as $row) {
            DB::connection('tm2mst')->table('sys_holiday')->insert($row + [
                'holiday_type' => 'NATIONAL',
                'activate' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => 'claude_code',
            ]);
        }
    }

    public function down(): void
    {
        Schema::connection('tm2mst')->dropIfExists('sys_holiday');
    }
};
