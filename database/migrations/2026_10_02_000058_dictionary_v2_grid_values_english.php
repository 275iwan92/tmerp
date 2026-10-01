<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Nilai tampilan grid v2 yang dulu ditulis Indonesia (Ya, Mingguan, Menang, ...) kini English lewat t(); terjemahan Indonesianya di sini.
// Nilai, kunci warna (tones) dan saringan kotak statistik memakai t() yang sama, jadi tetap cocok di bahasa apa pun. Hanya mengisi yang kosong.
return new class extends Migration
{
    private const UI = [
        'Yes' => 'Ya', 'No' => 'Tidak', 'Cash' => 'Tunai', 'Working' => 'Bekerja', 'Furloughed' => 'Dirumahkan', 'On Leave' => 'Cuti', 'Weekly' => 'Mingguan',
        'Monthly' => 'Bulanan', 'Clock In' => 'Masuk', 'Clock Out' => 'Pulang', 'Outside radius' => 'Di luar radius', 'Inside radius' => 'Dalam radius',
        'Printed' => 'Sudah', 'Not printed' => 'Belum', 'Sent' => 'Terkirim', 'Not sent' => 'Belum', 'Active' => 'Aktif', 'Set' => 'Sudah', 'Not set' => 'Belum diatur',
        'Local' => 'Lokal', 'Prospect' => 'Prospek', 'Production' => 'Produksi', 'Supporting' => 'Penunjang', 'Subcontract' => 'Subkon', 'Won' => 'Menang', 'Lost' => 'Kalah',
        '(no PIC)' => '(tanpa PIC)', 'Late' => 'Terlambat', 'Absent' => 'Tidak masuk', 'Late < 5 min' => 'Telat < 5 mnt', 'Late 6-15 min' => 'Telat 6-15 mnt',
        'Late > 15 min' => 'Telat > 15 mnt', 'Early leave < 5 min' => 'Pulang awal < 5 mnt', 'Early leave > 5 min' => 'Pulang awal > 5 mnt',
        'Absent without notice' => 'Alpha', 'Sick' => 'Sakit', 'Permission' => 'Izin', 'Approved permission' => 'Izin disetujui', 'Leave' => 'Cuti',
        'Maternity leave' => 'Cuti melahirkan',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        $db = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $db->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if (! $db->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $db->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void
    {
        // isi kamus dibiarkan (bisa sudah diubah user)
    }
};
