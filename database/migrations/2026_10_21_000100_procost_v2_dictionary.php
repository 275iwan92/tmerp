<?php

/*
 * REMARK
 * Tujuan       : Terjemahan Indonesia untuk layar ProCost v2 (Costing Hub procost dan Instant procost-instant): label field, kelompok, tombol, pesan, judul panel dan laporan. Padanan Indonesia memakai istilah v1 yang sudah dipakai officer.
 * Sumber hulu  : app/Support/V2/Costing/ProCostFields.php, resources/views/v2/screens/procost.blade.php, procost-instant.blade.php, routes/v2_procost.php.
 * Input        : -
 * Output       : Baris sys_dictionary (ui, grup form) + terjemahan Indonesia; terjemahan yang sudah terisi tidak ditimpa.
 * Dipakai oleh : Layar Pro-Cost v2.
 * Status       : Jalankan di ddt* dan tm2* (paritas Jo 2026-10-16): php artisan migrate --path=database/migrations/2026_10_21_000100_procost_v2_dictionary.php [--env=mockup].
 */

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const UI = [
        // Kelompok dan tab
        'Part' => 'Part', 'Paper & geometry' => 'Kertas & geometri', 'Print & process rates' => 'Ongkos cetak & proses', 'Sheet / single face' => 'Sheet / single face',
        'Lamination' => 'Laminasi', 'Common costs' => 'Ongkos umum', 'Tooling' => 'Tooling', 'Cost parameters' => 'Parameter ongkos', 'ProDev & BOM' => 'ProDev & BOM',
        'Result' => 'Hasil', 'Parts details' => 'Detail part', 'Info' => 'Info', 'Parts' => 'Part', 'Coating layers' => 'Layer coating', 'Coating' => 'Coating', 'Output' => 'Output',
        'Parameters' => 'Parameter', 'Dimensions' => 'Dimensi',
        // Field part
        'Part name' => 'Nama part', 'Part type' => 'Tipe part', 'Qty per set' => 'Qty per set', 'Part no.' => 'No urut part', 'Grammage / material' => 'Gramatur / bahan',
        'DC %  (override)' => '% DC (override)', 'DC % (override)' => '% DC (override)', 'Plano length (cm)' => 'Panjang plano (cm)', 'Plano width (cm)' => 'Lebar plano (cm)',
        '1 plano cut into' => '1 plano dipotong jadi', 'Per print sheet (LB)' => 'Isi dalam 1 LB', 'Per print LB' => 'Isi dalam 1 LB cetak', 'Per working LB' => 'Isi/LB kerja',
        'Print size L (cm)' => 'Ukuran cetak P (cm)', 'Print size W (cm)' => 'Ukuran cetak L (cm)', 'Colours' => 'Jumlah warna', 'Print rate / ream' => 'Ongkos cetak / rim',
        'Die-cut rate / LB' => 'Ongkos plong / LB', 'Glue rate / pc' => 'Ongkos lem / pc', 'Hot print rate / pc' => 'Ongkos hot print / pc', 'Film rate' => 'Film rate',
        'Film factor' => 'Film faktor', 'Plate (per colour)' => 'Plate (Rp/warna)', 'Knife factor' => 'Pisau faktor', 'Knife rate (per order)' => 'Pisau rate (Rp/order)',
        'Substance / SF type' => 'Substansi / tipe SF', 'Sheet supplier' => 'Supplier sheet', 'Piece length SF/SH (cm)' => 'Panjang piece SF/SH (cm)', 'Piece width SF (cm)' => 'Lebar piece SF (cm)',
        'Trim width (cm)' => 'Lebar trim (cm)', 'Per trim' => 'Isi / trim', 'Discount / surcharge' => 'Diskon / surcharge', 'Flute type' => 'Jenis flute', 'Flute type (geometry automatic)' => 'Jenis flute (geometri otomatis)',
        'Print rate / pc' => 'Ongkos print / pc', 'Die cut rate / pc' => 'Ongkos die cut / pc', 'Slitter rate / pc' => 'Ongkos slitter / pc', 'Slotter rate / pc' => 'Ongkos slotter / pc',
        'Join type' => 'Jenis join', 'Join rate / pc' => 'Ongkos join / pc', 'Partition rate / pc' => 'Ongkos partisi / pc', 'Layer rate / pc' => 'Ongkos layer / pc', 'Die knife rate / pc' => 'Ongkos pisau diecut / pc',
        'Use lamination' => 'Pakai laminasi', 'Lam sheet flat (per pc)' => 'Lam sheet flat (Rp/pc)', 'SF EF rate (E/F)' => 'Rate SF EF (E/F)', 'SF EF price (override)' => 'Harga SF EF (override)',
        'Use SF size?' => 'Pakai ukuran SF?', 'Lam SH rate (per cm²)' => 'Ongkos lam SH (Rp/cm²)', 'Lam SF rate (per cm²)' => 'Ongkos lam SF (Rp/cm²)', 'Manual lamination rate' => 'Ongkos laminasi manual',
        'Peel rate / pc' => 'Ongkos kupas / pc', 'QC rate / pc' => 'Ongkos QC / pc', 'Delivery rate / pc' => 'Ongkos kirim / pc', 'Packing type' => 'Jenis packing', 'Packing rate / pc' => 'Ongkos packing / pc',
        'Length (mm)' => 'Panjang (mm)', 'Width (mm)' => 'Lebar (mm)', 'Height (mm)' => 'Tinggi (mm)', 'Glue flap (mm)' => 'Lidah lem (mm)', 'Paper price (override)' => 'Harga kertas (override)',
        'Yes' => 'Ya', 'No' => 'Tidak', 'None' => 'Tanpa', 'Nearest' => 'Nearest', 'Ceiling' => 'Ceiling', 'Floor' => 'Floor',
        // Header / parameter
        'Margin (%)' => 'Margin (%)', 'Tolerance (%)' => 'Toleransi (%)', 'Rounding' => 'Pembulatan', 'VAT (%)' => 'PPN (%)', 'Paper index (IDR / ream)' => 'Paper index (Rp/rim)', 'Document' => 'Dokumen',
        'Qty' => 'Qty', 'Add a part from the Inquiry' => 'Tambah part dari Inquiry', '+ Part' => '+ Part', '+ Manual part' => '+ Part manual', 'Delete this part' => 'Hapus part ini',
        'Dimensions L × W × H (mm)' => 'Dimensi P × L × T (mm)', 'Material' => 'Material', 'Colour' => 'Warna', 'Ink' => 'Tinta', 'Finishing' => 'Finishing', 'Source' => 'Sumber', 'Artwork' => 'Artwork',
        'No artwork' => 'Tanpa artwork', 'Inquiry' => 'Inquiry', 'Doc No.' => 'No. dokumen', 'Doc date' => 'Tanggal dokumen', 'Calculation type' => 'Tipe perhitungan',
        'Calculate' => 'Hitung', 'Submit to Direksi' => 'Submit ke Direksi', 'Publish (Direksi)' => 'Publish (Direksi)', '+ Quotation' => '+ Quotation', 'Final selling price / pcs' => 'Harga jual akhir / pcs',
        'Total COGS (HPP)' => 'Total COGS (HPP)', 'Profit contribution' => 'Kontribusi laba', 'Tax' => 'Pajak', 'New product' => 'Produk baru', 'Recalculation' => 'Hitung ulang', 'Revision' => 'Revisi', 'Product revision' => 'Revisi produk',
        // Pesan
        'Engine suggestion' => 'Saran engine', 'you changed it' => 'sudah Anda ubah', 'Really not used' => 'Memang tidak dipakai',
        ':label was emptied and restored to :value.' => ':label dikosongkan, dikembalikan ke :value.',
        '✦ The engine filled :n boxes from Inquiry, ProDev, templates, constants and masters.' => '✦ Engine mengisi :n kotak dari Inquiry, ProDev, template, konstanta, dan master.',
        ':n estimated (blue) because the ProDev layout does not exist yet.' => ':n perkiraan (garis biru) karena layout ProDev belum ada.',
        ':n already changed by you.' => ':n sudah Anda ubah.',
        'Hover over the hint under a box to see its source; any value can be changed.' => 'Arahkan kursor ke keterangan di bawah kotak untuk melihat sumbernya; nilai mana pun boleh diubah.',
        'Not filled yet and worth checking' => 'Belum terisi dan perlu dicek',
        'Publish this price to Sales? After publish the selling price is open for Quotation.' => 'Publish harga ini ke Sales? Setelah publish, harga jual terbuka untuk Quotation.',
        'Delete this part from the costing?' => 'Hapus part ini dari costing?', 'Delete this part?' => 'Hapus part ini?', 'Cancel this document?' => 'Batalkan dokumen ini?',
        'The inquiry has no qty tier yet — default 1,000' => 'Inquiry belum punya tier qty — default 1.000',
        'There is no costing part yet. Inquiry parts that already have a ProDev layout appear here automatically; otherwise add one with + Part.' => 'Belum ada part costing. Part Inquiry yang sudah punya layout ProDev otomatis muncul di sini; selain itu tambah lewat "+ Part".',
        'Add a part first (General tab).' => 'Tambah part dulu (tab General).',
        'Saved.' => 'Tersimpan.', 'Saved and recalculated.' => 'Tersimpan & dihitung ulang.', 'Costing deleted.' => 'Dokumen costing dihapus.', 'This document is already locked.' => 'Dokumen ini sudah terkunci.',
        'Reversed: a new Inquiry and a draft ProDev were created; this document is now Integrated.' => 'Reverse: Inquiry baru dan ProDev draft dibuat; dokumen ini kini Integrated.',
        // Panel ProDev
        'Visual Imposition Layout (ProDev)' => 'Visual Imposition Layout (ProDev)', 'Take data (ProDev)' => 'Ambil Data (ProDev)', 'Manual' => 'Manual', 'Switch to manual' => 'Ganti ke manual', 'Take data from ProDev' => 'Ambil data dari ProDev',
        'Change ProDev layout (CONFIRMED)' => 'Ganti layout ProDev (CONFIRMED)', 'Apply layout' => 'Pakai layout', 'Plano size' => 'Ukuran plano', 'Print area' => 'Area cetak', 'Imposition' => 'Imposition',
        'Method · UP' => 'Metode · UP', 'Efficiency / waste' => 'Efisiensi / waste', 'Knife' => 'Pisau', 'Layout status' => 'Status layout', 'Item' => 'Item', 'Value' => 'Nilai',
        'This part has no ProDev layout yet — the illustration and print size appear after the layout is made in ProDev. Meanwhile fill the geometry in Cost parameters.' => 'Part ini belum punya layout ProDev — ilustrasi & print size muncul setelah layout dibuat di ProDev. Sementara isi geometri di Parameter ongkos.',
        'BOM and availability' => 'BOM dan ketersediaan', 'Book' => 'Book', 'Release hold' => 'Release hold', 'Recalculate' => 'Hitung ulang', 'Book is available after Publish' => 'Book tersedia setelah Publish',
        'Role' => 'Role', 'Need' => 'Kebutuhan', 'Free stock' => 'Stok bebas', 'Incoming PO/PR' => 'PO/PR masuk', 'Status' => 'Status',
        'BOM — Bill of Material (InqProd)' => 'BOM — Bill of Material (InqProd)', 'Layer' => 'Layer', 'Brand' => 'Merk', 'Grammage / flute' => 'Gramatur/Flute', 'Size (cm)' => 'Ukuran (cm)', 'Qty / sheet' => 'Qty/Sheet',
        'There is no BOM in the Inquiry for this part yet.' => 'Belum ada BOM di Inquiry untuk part ini.', 'customer material' => 'bahan customer',
        'Process' => 'Proses', 'Machine' => 'Mesin', 'In-house / Subcon' => 'In-house / Subkon', 'Target / hour' => 'Target/jam', 'Factor' => 'Faktor', 'Waste' => 'Waste', 'Subcon' => 'Subkon', 'In-house' => 'In-house',
        'There is no routing in ProDev / Inquiry for this part yet.' => 'Belum ada routing di ProDev/Inquiry untuk part ini.', 'Pass' => 'Pass', 'Unit' => 'Unit', 'Coverage' => 'Coverage',
        'There is no ink data yet.' => 'Belum ada data tinta.', 'Finishing & ProDev part detail' => 'Finishing & detail part ProDev', 'Attribute' => 'Atribut', 'Notes' => 'Catatan', 'Cost' => 'Biaya', 'Origin' => 'Asal',
        'There is no finishing yet.' => 'Belum ada finishing.', 'Knife code' => 'Kode pisau', 'Knife size' => 'Ukuran pisau', 'Raw material' => 'Bahan mentah', 'Joint' => 'Sambungan', 'ECMA' => 'ECMA', 'Flap' => 'Lidah',
        // Hasil
        'Granular Cost Breakdown' => 'Granular Cost Breakdown', 'Calculation report' => 'Laporan Hitung', 'notes' => 'catatan', 'Materials' => 'Material', 'Total material cost' => 'Total biaya material', 'Cost per plano' => 'Biaya per plano',
        'Plano sheets needed' => 'Kebutuhan lembar plano', 'Sheet (trim × length)' => 'Sheet (trim × panjang)', 'Running meter / discount' => 'Running meter / diskon', 'Process & finishing' => 'Proses & finishing',
        'Rate' => 'Rate', 'Basis' => 'Basis', 'Subtotal' => 'Subtotal', 'Total process' => 'Total proses', 'Tooling (film · plate · knife)' => 'Tooling (film · plate · pisau)', 'Total tooling' => 'Total tooling',
        'Calculation description' => 'Deskripsi hitung', 'Selling price :p per pcs (:o per order)' => 'Harga jual :p per pcs (:o per order)', 'above' => 'di atas', 'below' => 'di bawah', 'the median of similar costings' => 'median costing serupa',
        '1. Price breakdown, per pcs' => '1. Rincian harga, per pcs', '2. Worth your attention' => '2. Yang perlu diperhatikan', 'No notes: every core box is filled and nothing is estimated.' => 'Tidak ada catatan: semua isian inti terisi dan tidak ada perkiraan.',
        'The step breakdown is not shown: the trace differs from the stored engine result. Recalculate or report it.' => 'Rincian langkah tidak ditampilkan: hasil penelusuran tidak sama dengan hasil engine tersimpan. Hitung ulang atau laporkan.',
        '3. Compared with similar costings (:n tiers): median :m, lowest :lo, highest :hi.' => '3. Dibanding costing serupa (:n tier): median :m, terendah :lo, tertinggi :hi.',
        '3. Only :n comparisons so far (minimum 3). The comparison appears after more costings are calculated.' => '3. Baru :n pembanding (minimal 3). Perbandingan muncul setelah lebih banyak costing selesai dihitung.',
        '4. Source of the filled boxes: :s. Boxes you changed: :o. Statistics are used from 5 similar costings and light machine learning from 30 (COSTING constants). Templates only fill rates; product geometry stays from Inquiry and ProDev.' => '4. Asal isian yang diisi engine: :s. Isian yang Anda ubah: :o. Statistik dipakai mulai 5 costing serupa dan machine learning ringan mulai 30 (konstanta COSTING). Template hanya mengisi tarif, geometri produk tetap dari Inquiry dan ProDev.',
        '5. Quick simulation' => '5. Simulasi cepat', 'Price' => 'Harga', 'Change' => 'Perubahan',
        'This calculation is recorded as learning material for the engine: :n boxes with their context (part type, qty).' => 'Hitung ini dicatat sebagai bahan belajar engine: :n isian beserta konteksnya (tipe part, qty).',
        'The cost breakdown is only for the designated logins (constant PROCOST_FORMULA_LOGINS). The final selling price stays visible above.' => 'Breakdown biaya hanya untuk login yang ditunjuk (konstanta PROCOST_FORMULA_LOGINS). Harga jual akhir tetap tampil di atas.',
        'Revision: what changed from the parent product (must be recalculated)' => 'Revisi: yang berubah dari product induk (wajib dihitung ulang)', 'Part' => 'Part', 'Changed' => 'Yang berubah', 'Before' => 'Sebelum', 'After' => 'Sesudah', 'Repeated in' => 'Diulang di',
        'Product Costing (view only)' => 'Product Costing (lihat saja)', 'Not processed yet — the Costing team has not opened or calculated this inquiry.' => 'Belum diproses — Tim Costing belum membuka/menghitung costing untuk Inquiry ini.',
        'Not processed yet (:status) — the price shows after Direksi publishes.' => 'Belum diproses (:status) — harga baru kelihatan setelah publish Direksi.', 'Price / pcs' => 'Harga / pcs',
        // Instant
        'Update HPP — Instant mode' => 'Update HPP — Mode Instant', 'Calculate HPP' => 'Hitung HPP', 'Cancel document' => 'Batalkan dokumen', 'Reverse' => 'Reverse',
        'Reverse: create a NEW Inquiry Product + a DRAFT ProDev + a draft Master Product from this data; this document becomes Integrated. Continue?' => 'Reverse: bikin Inquiry Product BARU + ProDev DRAFT + Master Product draft dari data ini, lalu dokumen ini jadi mode Integrated. Lanjut?',
        'Customer' => 'Customer', 'Prospect / lead name' => 'Nama calon customer / lead', 'Product name' => 'Nama produk', 'Order qty (pcs)' => 'Qty order (pcs)', 'VAT' => 'PPN', 'Include VAT' => 'Include PPN', 'Exclude VAT' => 'Exclude PPN',
        'VAT percent' => 'Persen PPN', 'Margin percent' => 'Persen margin', 'Tolerance percent' => 'Persen toleransi', 'Rounding mode' => 'Mode pembulatan', 'Currency' => 'Mata uang',
        'No exchange-rate conversion yet — all master rates are IDR' => 'Belum ada konversi kurs — semua rate master IDR', 'Price per pcs' => 'Harga per pcs', 'Total HPP' => 'Total HPP', 'Total material cost ' => 'Total biaya material',
        'Total process cost' => 'Total biaya proses', 'Total tooling cost' => 'Total biaya tooling', 'Total after margin' => 'Total setelah margin', 'Total after VAT' => 'Total setelah PPN',
        'Part qty' => 'Qty part', 'Cost price (HPP) / pc' => 'Harga pokok (HPP) / pc', 'Print quantity (LC)' => 'Jumlah cetak (LC)', 'Detailed breakdown per pc' => 'Rincian per pc', 'Supplier price info' => 'Info harga supplier',
        'Detail per order (× qty :n)' => 'Detail per order (× qty :n)', 'Item / order' => 'Item / order', 'Coating type' => 'Jenis coating', 'Index coating' => 'Index coating', 'Coating notes' => 'Catatan coating',
        'Cost per print sheet / per pcs' => 'Biaya per lembar cetak / per pcs', 'Remove' => 'Hapus', '+ Coating' => '+ Coating', 'No records found' => 'Tidak ada data', 'Calculation notes' => 'Catatan kalkulasi',
        'No part yet. Click + Part to add one.' => 'Belum ada part. Klik "+ Part" untuk menambah part.', 'Part :n of :t' => 'Part :n dari :t',
        'The calculation detail (breakdown per pc / order) is only for the designated logins (constant PROCOST_FORMULA_LOGINS).' => 'Detail perhitungan (breakdown per PC/Order) hanya untuk login yang ditunjuk (konstanta PROCOST_FORMULA_LOGINS).',
        'HPP ID' => 'HPP ID', 'Create log' => 'Create log', 'Created by' => 'Dibuat oleh', 'Update log' => 'Update log', 'Updated by' => 'Diubah oleh', 'Mode' => 'Mode', 'Reversed at' => 'Reversed at', 'Reversed by' => 'Reversed by',
    ];

    public function up(): void
    {
        $sys = DB::connection('tm2sys');
        foreach (self::UI as $en => $id) {
            $en = trim($en);
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
