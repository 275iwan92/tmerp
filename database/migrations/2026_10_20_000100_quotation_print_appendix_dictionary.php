<?php

/*
 * REMARK
 * Tujuan       : Terjemahan Indonesia untuk lembar lampiran cetak Quotation v2 (Terms & Alternatives, Product Mockup) dan pesan gate cetak Quotation / Customer Order.
 * Sumber hulu  : app/Support/V2/Print/QuotationPrint.php, CustomerOrderPrint.php, resources/views/v2/partials/print-quotation-mockup.blade.php, DevLib print-doc-appendix.
 * Input        : -
 * Output       : Baris sys_dictionary (ui) + terjemahan Indonesia (sys_dictionary_lang).
 * Dipakai oleh : Cetak Quotation dan Customer Order v2.
 * Status       : Jalankan di ddt* dan tm2* (paritas Jo 2026-10-16): php artisan migrate --path=database/migrations/2026_10_20_000100_quotation_print_appendix_dictionary.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UI = [
        'Print is available once the Quotation is confirmed (issued).' => 'Cetak tersedia setelah Quotation dikonfirmasi (diterbitkan).',
        'Print is available once the Customer Order is confirmed.' => 'Cetak tersedia setelah Customer Order dikonfirmasi.',
        'Revision' => 'Revisi',
        'Payment' => 'Pembayaran',
        'Production lead time' => 'Lead time produksi',
        'Follows capacity' => 'Menyesuaikan kapasitas',
        'Counted from' => 'Mulai dihitung',
        'After the proof is approved' => 'Setelah proof disetujui',
        'Transfer to' => 'Transfer ke',
        'Account no.' => 'No. Rek.',
        'The payment instruction (bank account) is sent separately by Finance after the PO is issued.' => 'Instruksi pembayaran (rekening tujuan) akan dikirim terpisah oleh tim Finance setelah PO diterbitkan.',
        'Terms & Conditions' => 'Syarat & Ketentuan',
        'This offer is valid until the date shown in the header.' => 'Penawaran berlaku sampai tanggal yang tertera pada kop dokumen.',
        'Prices are :scheme of VAT :pct%.' => 'Harga :scheme PPN :pct%.',
        'Lead time is counted after the final artwork and the colour digital proof are approved.' => 'Lead time produksi terhitung setelah artwork final & digital proof (warna) disetujui.',
        'Delivery tolerance is ±5% of the order qty, invoiced by the actual amount delivered.' => 'Toleransi pengiriman ±5% dari qty order, ditagih sesuai jumlah aktual.',
        'This offer becomes binding once the customer purchase order is issued.' => 'Penawaran ini menjadi mengikat setelah PO customer diterbitkan.',
        'Quantity alternatives (unit price excl. VAT, excluding tooling, main product)' => 'Alternatif kuantitas (harga satuan excl. PPN, di luar tooling, produk utama)',
        'Qty option' => 'Opsi Qty',
        'Qty (pcs)' => 'Qty (pcs)',
        'Unit price' => 'Harga Satuan',
        'Product subtotal' => 'Subtotal Produk',
        'Other quantity options for the main product of this offer. Tooling is charged once and does not change between options. To choose another option, contact the Sales PIC before the offer expires.' => 'Opsi kuantitas lain untuk produk utama penawaran ini. Biaya tooling tetap dibebankan satu kali, tidak berubah antar opsi. Untuk memilih opsi lain, hubungi PIC Sales sebelum masa berlaku berakhir.',
        'Terms & Alternatives' => 'Syarat & Alternatif',
        'Product Mockup' => 'Mockup Produk',
        'Appendix' => 'Lampiran',
        'Dieline' => 'Dieline',
        'The dieline is not in the Inquiry yet.' => 'Dieline belum ada di Inquiry.',
        'The artwork is not uploaded in the Inquiry yet — only the dieline lines are shown.' => 'Artwork belum di-upload di Inquiry — tampil garis dieline saja.',
        'Illustration from the Inquiry Product, not a colour proof.' => 'Ilustrasi dari Inquiry Product, bukan proof warna.',
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
