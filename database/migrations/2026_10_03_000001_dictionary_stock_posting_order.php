<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk pesan baru StockPostingService (urutan kartu stok, rulebook A.24): saldo minus di baris
// sesudahnya, tanggal di periode inventory tertutup, peringatan nilai baris keluar. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        'Item #:item in location #:loc would go below zero on :date (:qty) because of movements dated after this document -- posting refused.' => 'Saldo item #:item di lokasi #:loc akan minus pada :date (:qty) karena ada pergerakan bertanggal sesudah dokumen ini -- posting ditolak.',
        'The document date is in a closed inventory period (closed through :date). Enter a Stock Adjustment dated in an open period instead.' => 'Tanggal dokumen ada di periode inventory yang sudah ditutup (ditutup sampai :date). Input Stock Adjustment bertanggal di periode yang masih terbuka.',
        'The average price of :n later stock-out row(s) changed; their value changed by :value. Journals already posted for them were not changed.' => 'Harga rata-rata :n baris stok keluar sesudahnya berubah; nilainya berubah :value. Jurnal yang sudah diposting untuk baris itu tidak diubah.',
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
