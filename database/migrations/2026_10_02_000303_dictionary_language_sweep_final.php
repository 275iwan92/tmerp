<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Isi akhir sapuan multi-bahasa 2026-10-02: muat ulang database/data/dictionary_ui_2026_10_02_sweep.json setelah semua
// kelompok selesai (hanya mengisi terjemahan yang masih kosong), lalu samakan beberapa istilah yang sempat termuat dengan
// dua versi berbeda saat pemuatan bertahap (000300 / 000301).
return new class extends Migration
{
    // English => Indonesia yang dipakai (satu versi untuk semua layar)
    private const SETTLED = [
        'Details' => 'Details', 'Disc %' => 'Disc %', 'Net Receivable' => 'Piutang Bersih', '(running)' => '(berjalan)', 'Top Up' => 'Isi Ulang',
        'Petty Cash' => 'Kas Kecil', 'Recalculate' => 'Hitung Ulang', 'Engine Catalog' => 'Engine Catalog', 'Purchase Lead Time' => 'Purchase Lead Time',
        'Material Usage Standard' => 'Material Usage Standard', 'UoM' => 'UoM', 'Preventive' => 'Preventif', 'Shipper' => 'Pengirim',
        'Shipment Method' => 'Cara Kirim', 'Inquiry No. / Customer' => 'No. Inquiry / Customer', 'Ready to Quote' => 'Ready to Quote',
        'Spare Parts' => 'Spare Part', 'First Needed' => 'Pertama Dibutuhkan', 'Posted to Production' => 'Posted to Production',
        'Ordered' => 'Sudah Dipesan', 'Channel' => 'Saluran', 'Part Type' => 'Tipe Part', 'Action' => 'Aksi',
        'Short Qty' => 'Qty Kurang', 'Petty Cash Name' => 'Nama Kas Kecil', 'Custodian' => 'Pemegang Kas', 'Import' => 'Import',
    ];

    public function up(): void
    {
        if (! Schema::connection('tm2sys')->hasTable('sys_dictionary')) {
            return;
        }
        (require __DIR__.'/2026_10_02_000300_dictionary_language_sweep.php')->up();

        $db = DB::connection('tm2sys');
        foreach (self::SETTLED as $en => $id) {
            $key = $db->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            // hanya baris yang diisi sapuan ini (2026-10-02 / 03), bukan terjemahan lama atau hasil ubahan user
            if ($key && $db->table('sys_dictionary')->where('id_dictionary', $key)->where('created_at', '>=', '2026-10-02 20:00:00')->exists()) {
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
