<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English putaran 2 Inventory & Warehouse (2026-10-02): label palet / item / lokasi,
// panduan ukuran label, kedaluwarsa, kapasitas luas x tinggi (m3), dan approval Stock Transfer. Nama dokumen / menu,
// tombol dokumen dan status tetap English. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        // label (x-dl::label, x-dl::label-sheet, x-dl::label-guide)
        'Pallet' => 'Palet', 'Location' => 'Lokasi', 'Expired' => 'Kedaluwarsa', 'Expires soon' => 'Segera kedaluwarsa', 'Blocked' => 'Diblokir', 'Closed' => 'Ditutup',
        'Max weight' => 'Berat maks', 'Volume' => 'Volume', 'Floor area' => 'Luas lantai', 'Pallets' => 'Palet', 'Arrived' => 'Tiba', 'Lot' => 'Lot', 'Expiry' => 'Kedaluwarsa',
        'Weight' => 'Berat', 'Supplier pallet' => 'Palet supplier', 'Receipt' => 'Tanda Terima', 'At arrival' => 'Saat tiba', 'Label no.' => 'Label ke',
        'After every use: print a new label with the remaining quantity.' => 'Setiap habis dipakai: cetak label baru dengan qty sisa.',
        'Label roll (label printer)' => 'Gulungan label (printer label)', 'A4 sheet (office printer)' => 'Kertas A4 (printer kantor)', 'Label Roll' => 'Gulungan Label', 'A4 Sheet' => 'Kertas A4',
        'Pallet Labels' => 'Label Palet', 'Item Labels' => 'Label Item', 'Location Labels' => 'Label Lokasi',
        'The chosen bins, stock or pallets were not found.' => 'Bin, stok, atau palet yang dipilih tidak ditemukan.',
        'Label sizes and how to print' => 'Ukuran label dan cara mencetak', 'Used for' => 'Dipakai untuk', 'Labels per A4 sheet' => 'Label per lembar A4', 'Colour band' => 'Pita warna',
        'Item label for small packs: boxes, ink cans, rolls.' => 'Label item untuk kemasan kecil: dus, kaleng tinta, rol.',
        'Bin / rack label and item label on a stack.' => 'Label bin / rak dan label item di tumpukan.',
        'Pallet label, readable from a few metres away.' => 'Label palet, terbaca dari jarak beberapa meter.',
        'Label roll (label printer): in the print dialog choose the paper size of the roll, margins None, scale 100%. One label = one page.' => 'Gulungan label (printer label): di jendela cetak pilih ukuran kertas sesuai gulungan, margin None, skala 100%. Satu label = satu halaman.',
        'A4 sheet (office printer): paper A4, margins None or Minimum, scale 100%, turn on Background graphics so the colour band prints. Cut along the label borders.' => 'Kertas A4 (printer kantor): kertas A4, margin None atau Minimum, skala 100%, nyalakan Background graphics supaya pita warna ikut tercetak. Potong mengikuti garis tepi label.',
        'No colour printer: the band still prints the label type and the mark as text, so the label stays readable.' => 'Tanpa printer warna: pita tetap mencetak jenis label dan tandanya sebagai tulisan, jadi label tetap terbaca.',
        'Print On' => 'Dicetak di',
        // peta gudang
        'Expired / expires soon' => 'Kedaluwarsa / segera kedaluwarsa', 'Floor area × height' => 'Luas tapak × tinggi', 'Expires' => 'Kedaluwarsa', 'free :free m³' => 'sisa :free m³',
        ':n items have no size data and are not counted in the volume.' => ':n item tanpa data ukuran tidak dihitung dalam volume.',
        'Capacity per Floor' => 'Kapasitas per Lantai',
        'Floor area and clear height give the room volume. Storage volume = area of the storage areas x their stacking height. In use = estimated volume and weight of the stock (paper: sheets x size x thickness).' => 'Luas lantai dan tinggi bersih memberi volume ruang. Volume simpan = luas area penyimpanan x tinggi tumpuknya. Terpakai = perkiraan volume dan berat stok (kertas: lembar x ukuran x tebal).',
        'Building Size & Height' => 'Ukuran & Tinggi Gedung', 'Stacking Height per Area' => 'Tinggi Tumpuk per Area',
        'Floor Area (m²)' => 'Luas Lantai (m²)', 'Clear Height (m)' => 'Tinggi Bersih (m)', 'Room Volume (m³)' => 'Volume Ruang (m³)', 'Storage Areas (m²)' => 'Area Penyimpanan (m²)',
        'Storage % of Floor' => 'Area Simpan % Lantai', 'Storage Volume (m³)' => 'Volume Simpan (m³)', 'In Use (m³)' => 'Terpakai (m³)', 'Volume Used %' => 'Volume Terpakai %',
        'In Use (kg)' => 'Terpakai (kg)', 'Weight Used %' => 'Berat Terpakai %',
        'A floor without a clear height has no volume. Fill in the height of every floor in Buildings (and the stacking height per area in Storage Areas if it is lower than the ceiling).' => 'Lantai tanpa tinggi bersih tidak punya volume. Isi tinggi tiap lantai di Buildings (dan tinggi tumpuk per area di Storage Areas bila lebih rendah dari plafon).',
        // Buildings / Storage Areas / Stock by Location
        'Clear Height Floor 1 (m)' => 'Tinggi Bersih Lantai 1 (m)', 'Clear Height Floor 2 (m)' => 'Tinggi Bersih Lantai 2 (m)', 'Clear Height Floor 3 (m)' => 'Tinggi Bersih Lantai 3 (m)',
        'Stack Height (m)' => 'Tinggi Tumpuk (m)',
        'The stack height is higher than the clear height of that floor.' => 'Tinggi tumpuk lebih tinggi dari tinggi bersih lantai itu.',
        'Volume (m³)' => 'Volume (m³)', 'Expiry Date' => 'Tgl Kedaluwarsa',
        // Print Pallet Label
        'Pallet labels for goods receipt :no, received :date. Every pallet that arrived needs a label before it is put away.' => 'Label palet untuk penerimaan barang :no, diterima :date. Setiap palet yang datang wajib diberi label sebelum disimpan.',
        'Back to Receipt' => 'Kembali ke Penerimaan', 'Item Labels of this Receipt' => 'Label Item Penerimaan Ini', 'All Pallet Label Work' => 'Semua Pekerjaan Label Palet',
        'Pallet Labels of this Receipt' => 'Label Palet Penerimaan Ini',
        'Bin :bin · :onhand on hand · :labelled on :n pallet label(s)' => 'Bin :bin · stok :onhand · :labelled di :n label palet',
        'The pallet labels show :qty more than the stock in this bin: stock was used. Relabel the pallet that was opened with what is left on it.' => 'Label palet menunjukkan :qty lebih banyak dari stok di bin ini: stok sudah dipakai. Label ulang palet yang dibuka dengan sisa isinya.',
        ':qty in this bin is not on a pallet label yet. One line = one pallet: split the quantity with + Pallet, and copy the pallet code printed on the supplier label.' => ':qty di bin ini belum punya label palet. Satu baris = satu palet: pecah qty-nya dengan + Pallet, dan salin kode palet yang tercetak di label supplier.',
        'Arrival date' => 'Tanggal tiba', 'Receipt No.' => 'No. Penerimaan', 'New Pallets' => 'Palet Baru', '+ Pallet' => '+ Palet', 'Qty on the Pallet' => 'Qty di Palet',
        'Supplier Pallet Code' => 'Kode Palet Supplier', 'as printed on the supplier label' => 'seperti tercetak di label supplier', 'Create & Print' => 'Buat & Cetak',
        'Every unit of this stock is on a pallet label. Relabel a pallet after its contents are used.' => 'Semua stok ini sudah berlabel palet. Label ulang palet setiap isinya dipakai.',
        'Pallets of this Stock' => 'Palet Stok Ini',
        'Oldest arrival first: use the oldest pallet first. After using from a pallet, relabel it with what is left; 0 closes the pallet.' => 'Yang datang paling lama di atas: pakai palet tertua dulu. Sesudah mengambil dari satu palet, label ulang dengan sisanya; 0 menutup palet.',
        'Search pallet no., lot, supplier code...' => 'Cari no palet, lot, kode supplier...', 'Pallet No.' => 'No Palet', 'Qty Now' => 'Qty Sekarang', 'Qty at Arrival' => 'Qty Saat Tiba',
        'Labels Printed' => 'Label Dicetak', 'Relabel after Use' => 'Label Ulang Sesudah Dipakai',
        'Choose the pallet that was opened and type what is left on it. A new label is printed with the same pallet number and arrival date; 0 closes the pallet.' => 'Pilih palet yang dibuka dan ketik sisa isinya. Label baru dicetak dengan nomor palet dan tanggal tiba yang sama; 0 menutup palet.',
        'Remaining quantity' => 'Qty sisa', 'Note' => 'Catatan', 'e.g. taken for a work order' => 'mis. diambil untuk work order', 'Relabel & Print' => 'Label Ulang & Cetak',
        'Print Again' => 'Cetak Ulang', 'For a label that is damaged or lost. The quantity on the label does not change.' => 'Untuk label yang rusak atau hilang. Qty di label tidak berubah.',
        'Receipt Lines' => 'Baris Penerimaan', 'Pallet Label Work' => 'Pekerjaan Label Palet',
        'One row per item and bin of this receipt. Open a row to make its pallet labels.' => 'Satu baris per item dan bin penerimaan ini. Buka satu baris untuk membuat label paletnya.',
        'Stock whose pallet labels do not match what is in the bin. Arrival label: goods came in without a pallet label. Relabel after use: stock was taken and the pallet label still shows the old quantity.' => 'Stok yang label paletnya tidak cocok dengan isi bin. Arrival label: barang datang tanpa label palet. Relabel after use: stok sudah diambil dan label palet masih menunjukkan qty lama.',
        'This receipt put no stock into a bin.' => 'Penerimaan ini tidak memasukkan stok ke bin.',
        'All stock in this building is on an up-to-date pallet label.' => 'Semua stok di gedung ini sudah berlabel palet yang benar.',
        'Search item, bin...' => 'Cari item, bin...', 'Received' => 'Diterima', 'On Hand in Bin' => 'Stok di Bin', 'On Pallet Labels' => 'Di Label Palet', 'Difference' => 'Selisih', 'To Do' => 'Yang Harus Dikerjakan',
        'Arrival label' => 'Label kedatangan', 'Relabel after use' => 'Label ulang sesudah dipakai', 'Labelled' => 'Sudah berlabel', 'No expiry date' => 'Tanpa tgl kedaluwarsa',
        'Pallets in this Building' => 'Palet di Gedung Ini',
        'Every pallet with a label that still holds stock. Open a pallet to relabel it or print its label again.' => 'Semua palet berlabel yang masih berisi stok. Buka satu palet untuk label ulang atau cetak ulang labelnya.',
        'No pallet has a label yet in this building.' => 'Belum ada palet berlabel di gedung ini.',
        'Search pallet no., item, bin, lot, supplier code...' => 'Cari no palet, item, bin, lot, kode supplier...', 'Pallet No. / Item' => 'No Palet / Item',
        'Fill in the quantity of at least one pallet.' => 'Isi qty paling sedikit satu palet.', 'This stock is no longer in that bin.' => 'Stok ini sudah tidak ada di bin itu.',
        ':item: only :qty in this bin is not on a pallet label yet.' => ':item: hanya :qty di bin ini yang belum berlabel palet.',
        'Pallet :no is already closed.' => 'Palet :no sudah ditutup.', 'Pallet :no: the remaining quantity must be between 0 and :max.' => 'Palet :no: qty sisa harus antara 0 dan :max.',
        'Pallet :no is empty and closed.' => 'Palet :no kosong dan ditutup.',
        'Draft created for pallet :no. Choose the destination bin, then Confirm.' => 'Draft dibuat untuk palet :no. Pilih bin tujuannya, lalu Confirm.',
        // Print Item Label / Print Location Label
        'A label for stock that is in a bin now: item code and name, quantity, bin, arrival date, lot and expiry date, a QR code and a barcode. The colour band shows the stock class; a red stripe means dead stock or expired.' => 'Label untuk stok yang sekarang ada di bin: kode dan nama item, qty, bin, tanggal tiba, lot dan tanggal kedaluwarsa, QR code dan barcode. Pita warna menunjukkan kelas stok; garis merah = dead stock atau kedaluwarsa.',
        'Item labels for goods receipt :no, received :date. The items of this receipt are already ticked.' => 'Label item untuk penerimaan barang :no, diterima :date. Item penerimaan ini sudah tercentang.',
        'This receipt put no stock into a bin, so there is nothing to label.' => 'Penerimaan ini tidak memasukkan stok ke bin, jadi tidak ada yang dilabeli.',
        'One label per pack or stack: type how many packs carry the same label.' => 'Satu label per kemasan atau tumpukan: ketik berapa kemasan yang memakai label yang sama.',
        'A label for every bin: its code, storage area, capacity (weight, volume, floor area), a QR code for the phone camera and a barcode for the scanner. The colour band shows the kind of storage area.' => 'Label untuk tiap bin: kode, area penyimpanan, kapasitas (berat, volume, luas tapak), QR code untuk kamera HP dan barcode untuk scanner. Pita warna menunjukkan jenis area penyimpanan.',
        // Goods receipt
        'Item Labels' => 'Label Item',
        // lacak
        'Scan a location label, an item label or a pallet label, or type a bin code, an item code, a pallet number or a transfer number.' => 'Pindai label lokasi, label item, atau label palet, atau ketik kode bin, kode item, nomor palet, atau nomor transfer.',
        'Move this Pallet' => 'Pindahkan Palet Ini', 'Relabel / Print' => 'Label Ulang / Cetak', 'Quantity now / at arrival' => 'Qty sekarang / saat tiba',
        'Labelled pallets of this item, oldest arrival first: use the oldest first.' => 'Palet berlabel item ini, yang datang paling lama di atas: pakai yang tertua dulu.',
        'Search pallet no., bin, lot...' => 'Cari no palet, bin, lot...', 'When' => 'Kapan', 'What' => 'Apa', 'Qty Before' => 'Qty Sebelum', 'Qty After' => 'Qty Sesudah', 'Document' => 'Dokumen', 'By' => 'Oleh',
        'Relabelled' => 'Dilabel ulang', 'Moved' => 'Dipindah',
        // Stock Transfer: approval + volume + palet
        'Waiting for approval' => 'Menunggu approval',
        'Pick the item in its source bin, the destination bin and the quantity, then Save: the check below shows the destination capacity after the move. Confirm sends the transfer for approval; the stock moves after the last approval.' => 'Pilih item di bin asalnya, bin tujuan, dan qty-nya, lalu Save: pemeriksaan di bawah menunjukkan kapasitas tujuan sesudah pindah. Confirm mengajukan transfer untuk approval; stok pindah sesudah approval terakhir.',
        'Waiting for approval: the stock has not moved yet. It moves when the last level approves.' => 'Menunggu approval: stok belum pindah. Stok pindah saat level terakhir menyetujui.',
        'Rejected: no stock moved. Use Duplicate to make a new Draft with the same lines.' => 'Ditolak: tidak ada stok yang pindah. Pakai Duplicate untuk membuat Draft baru dengan baris yang sama.',
        'Destination Weight After Move' => 'Berat Tujuan Sesudah Pindah', 'Destination Weight Now' => 'Berat Tujuan Sekarang', 'Destination Volume After Move' => 'Volume Tujuan Sesudah Pindah',
        'Destination Volume Now' => 'Volume Tujuan Sekarang',
        'Draft created as a copy. Check the lines, then Confirm.' => 'Draft dibuat sebagai salinan. Periksa barisnya, lalu Confirm.',
        'Transfer cancelled. No stock had moved yet.' => 'Transfer dibatalkan. Belum ada stok yang pindah.', 'Fill in the reason.' => 'Isi alasannya.',
        'Approved.' => 'Disetujui.', 'Rejected.' => 'Ditolak.', 'On hold.' => 'Ditahan.',
        'Confirmed and sent for approval. The stock moves after the last approval.' => 'Di-Confirm dan diajukan untuk approval. Stok pindah sesudah approval terakhir.',
        'This document is not waiting for your approval.' => 'Dokumen ini tidak sedang menunggu approval Anda.', 'This transfer is not waiting for approval.' => 'Transfer ini tidak sedang menunggu approval.',
        'Approval for Stock Transfer is switched off: Confirm moves the stock at once. Switch it on in Setup > Setting Approval (module TF) to make every transfer wait for approval.' => 'Approval Stock Transfer sedang dimatikan: Confirm langsung memindahkan stok. Nyalakan di Setup > Setting Approval (modul TF) supaya setiap transfer menunggu approval.',
        'Route' => 'Rute', 'Stock value' => 'Nilai stok', 'Lines' => 'Baris', 'No destination' => 'Belum ada tujuan',
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
