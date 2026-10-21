<?php

/*
 * REMARK
 * Tujuan       : Terjemahan Indonesia untuk saran part Inquiry v2 (warna/ukuran dari artwork, bahan/finishing dari histori), isian Prospek lama, dan tombol Remove artwork.
 * Sumber hulu  : app/Services/Sales/InquirySuggestionService.php, resources/views/v2/screens/inquiry-new.blade.php, inquiry-form.js.
 * Input        : -
 * Output       : Baris sys_dictionary (ui, grup form) + terjemahan Indonesia; terjemahan yang sudah terisi tidak ditimpa.
 * Dipakai oleh : Layar Inquiry Baru v2 (x-mp::sales.inquiry-part).
 * Status       : Jalankan di ddt* dan tm2*: php artisan migrate --path=database/migrations/2026_10_21_000200_inquiry_suggestions_dictionary.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UI = [
        '💡 Material suggestion from history: :name (used in :pct% of the earlier Inquiries of this product type)' => '💡 Saran bahan dari histori: :name (dipakai :pct% Inquiry Tipe Produk ini sebelumnya)',
        '💡 Colour notation suggestion from the artwork analysis: :code (:n colours detected:bw) — rough estimate, check before using.' => '💡 Saran notasi warna dari analisis artwork: :code (:n warna terdeteksi:bw) — perkiraan kasar, cek ulang sebelum dipakai.',
        'black and white' => 'hitam-putih',
        '📋 Finishing suggestion from history: :name (used in :pct% of the earlier Inquiries of this product type).' => '📋 Saran finishing dari histori: :name (dipakai :pct% Inquiry Tipe Produk ini sebelumnya).',
        '📐 Size read from the artwork DPI metadata (:x×:y dpi): :w × :h mm — assuming this is the printed flat blank, not the outer box dimensions.' => '📐 Ukuran terbaca dari metadata DPI artwork (:x×:y dpi): :w × :h mm — asumsi ini bentangan/flat yang dicetak, bukan dimensi luar box.',
        ':dpi dpi is usually just a screen or software default, NOT the real print scale — most likely inaccurate, do not use it right away.' => '⚠️ :dpi dpi biasanya cuma default layar/software, BUKAN skala cetak asli — kemungkinan besar tidak akurat, jangan langsung dipakai.',
        'Still check it manually before confirming to the customer.' => 'Tetap cek ulang manual sebelum konfirmasi ke customer.',
        'Use suggestion' => 'Pakai saran',
        'Use as Flat W/H' => 'Pakai ke Flat W/H',
        'Add to Finishing' => 'Tambah ke Finishing',
        'Prospect Name (legacy data)' => 'Nama Calon Customer (data lama)',
        'Prospect name…' => 'Nama prospek…',
        'Prospect Name' => 'Nama Prospek',
        'Prospect Name is required.' => 'Nama prospek wajib diisi.',
        'Remove artwork' => 'Hapus artwork',
        'Remove the saved artwork of this part?' => 'Hapus artwork tersimpan milik part ini?',
        'Artwork of :part removed.' => 'Artwork :part dihapus.',
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $sys->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'form', 'origin' => 'app',
                'activate' => 1, 'created_at' => now(), 'created_by' => 'migration']);
            $key = $sys->table('sys_dictionary')->where('context', 'ui')->where('source_en', $en)->value('id_dictionary');
            if ($key && ! $sys->table('sys_dictionary_lang')->where('id_dictionary', $key)->where('lang_code', 'id')->where('text', '<>', '')->exists()) {
                $sys->table('sys_dictionary_lang')->updateOrInsert(['id_dictionary' => $key, 'lang_code' => 'id'], ['text' => $id]);
            }
        }
        Dictionary::flush();
    }

    public function down(): void {}
};
