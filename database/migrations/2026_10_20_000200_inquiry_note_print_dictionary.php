<?php

/*
 * REMARK
 * Tujuan       : Terjemahan Indonesia untuk cetak Nota Inquiry v2 (Inquiry Note): judul, kolom, lampiran spesifikasi / dieline / syarat dan konfirmasi customer.
 * Sumber hulu  : app/Support/V2/Print/InquiryNotePrint.php, resources/views/v2/partials/print-inquiry-note-dieline.blade.php.
 * Input        : -
 * Output       : Baris sys_dictionary (ui, grup print) + terjemahan Indonesia; terjemahan yang sudah terisi tidak ditimpa.
 * Dipakai oleh : Cetak Inquiry Note v2.
 * Status       : Jalankan di ddt* dan tm2* (paritas Jo 2026-10-16): php artisan migrate --path=database/migrations/2026_10_20_000200_inquiry_note_print_dictionary.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UI = [
        'Inquiry Note' => 'Nota Inquiry',
        'Price Offer · Product Specification' => 'Penawaran Harga · Spesifikasi Produk',
        'Price Tier' => 'Jenjang Harga',
        'Confirms the note' => 'Mengonfirmasi nota',
        'Order value, primary tier' => 'Nilai order, tier utama',
        'Tier' => 'Jenjang',
        'Product' => 'Produk',
        'Sales Notes' => 'Catatan Sales',
        'Dimensions L × W × H' => 'Dimensi P × L × T',
        'Glue flap' => 'Lidah lem',
        'Flat spread' => 'Bentangan flat',
        'Flat area' => 'Luas flat',
        'Material' => 'Material',
        'Colour' => 'Warna',
        'Process / Finishing' => 'Proses / Finishing',
        'Design No.' => 'No. Desain',
        'Material source' => 'Sumber material',
        'Job nature' => 'Sifat pekerjaan',
        'Additional Info' => 'Info Tambahan',
        'Product Specification' => 'Spesifikasi Produk',
        'There is no part in this inquiry yet.' => 'Belum ada part di inquiry ini.',
        'Full-Size Dieline' => 'Dieline Ukuran Penuh',
        'Customer Confirmation' => 'Konfirmasi Customer',
        'Terms & Customer Confirmation' => 'Syarat & Konfirmasi Customer',
        'The price is valid for 30 days from the issue date of this note.' => 'Harga berlaku 30 hari sejak tanggal nota ini diterbitkan.',
        'Delivery tolerance is ±5% of the order qty (printing industry standard).' => 'Toleransi jumlah pengiriman ±5% dari qty order (standar industri percetakan).',
        'Lead time follows the production capacity after the final artwork and the digital proof are approved.' => 'Lead time produksi menyesuaikan kapasitas produksi setelah artwork final & digital proof disetujui.',
        'Tooling cost (knife / die / plate) of :amount is charged once on the first order.' => 'Biaya tooling (pisau/matras/plate) sebesar :amount dibebankan satu kali di order pertama.',
        'Payment follows the payment term of the registered customer / down payment for the first order.' => 'Pembayaran sesuai payment term yang berlaku untuk customer terdaftar / DP untuk order pertama.',
        'Prices exclude shipping cost unless stated otherwise.' => 'Harga belum termasuk biaya pengiriman kecuali disebutkan lain.',
        'A change of specification after the note is approved can change the price and lead time.' => 'Perubahan spesifikasi setelah nota disetujui dapat mengubah harga dan lead time.',
        'Please check the price and the specification. If they are correct, sign below and return this note to the Sales PIC. If there is a correction, write it on this document — we will issue a revision.' => 'Mohon periksa harga dan spesifikasi. Bila telah sesuai, tandatangani kolom di bawah dan kirim kembali nota ini ke PIC Sales. Bila ada koreksi, tuliskan langsung pada dokumen ini — kami akan terbitkan revisi.',
        'AGREED — continue to the official quotation, qty tier:' => 'SESUAI — lanjutkan ke penawaran resmi (quotation), qty tier:',
        'Needs REVISION — notes:' => 'Perlu REVISI — catatan:',
        'Name, position & stamp' => 'Nama jelas, jabatan & stempel',
        'Sales Manager' => 'Sales Manager',
        'Price per pcs, :cur · VAT :pct%. A change of material, gram weight, colours or finishing changes the price.' => 'Harga per pcs, :cur · PPN :pct%. Perubahan material, gramatur, jumlah warna, atau finishing akan mengubah harga.',
        'Price not available yet (costing not published): technical specification only. Ask Costing for the official offer.' => 'Harga belum tersedia (costing belum dipublish): hanya spesifikasi teknis. Hubungi Costing untuk penawaran resmi.',
        'Schematic, not to scale. Solid line = cut, dashed line = crease / fold.' => 'Skematik, tidak berskala. Garis penuh = potong, garis putus = rill / lipat.',
        'Target' => 'Target',
        'Incl. VAT' => 'Incl. PPN',
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $sys->table('sys_dictionary')->insertOrIgnore(['context' => 'ui', 'source_en' => $en, 'group_code' => 'print', 'origin' => 'app',
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
