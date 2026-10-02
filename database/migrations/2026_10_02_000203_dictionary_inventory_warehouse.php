<?php

use App\Support\Localization\Dictionary;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Terjemahan Indonesia untuk teks English layar Inventory > Warehouse (Warehouse Map, Stock Transfer, Buildings, Storage
// Areas, Bins, Stock by Location, label, lacak). Nama dokumen / menu, tombol dokumen, status, dan istilah "dead stock"
// tetap English. Hanya mengisi yang belum punya terjemahan.
return new class extends Migration
{
    private const UI = [
        // peta gudang (x-dl::wh-map)
        'Search bin, item, lot...' => 'Cari bin, item, lot...', 'Click a bin on the map to see what is inside.' => 'Klik sebuah bin di peta untuk melihat isinya.',
        'Floor' => 'Lantai', 'Edit Layout' => 'Atur Tata Letak', 'In use' => 'Terisi', 'Full' => 'Penuh', 'Over capacity' => 'Lebih dari kapasitas', 'Dead stock' => 'Dead stock',
        'Bin' => 'Bin', 'Capacity' => 'Kapasitas', 'Idle' => 'Diam', 'Level' => 'Tingkat', 'No stock in this bin.' => 'Tidak ada stok di bin ini.',
        'Layout saved.' => 'Tata letak tersimpan.', 'Could not save the layout.' => 'Tata letak gagal disimpan.', 'No bin matches the search.' => 'Tidak ada bin yang cocok.',
        'bins match' => 'bin cocok', 'Never moved' => 'Tidak pernah bergerak', 'pallets' => 'pallet', 'Max' => 'Maks', 'free :free kg of :cap' => 'sisa :free kg dari :cap',
        'no capacity set' => 'kapasitas belum diisi', 'empty' => 'kosong', 'DEAD STOCK' => 'DEAD STOCK', 'Holds dead stock' => 'Berisi dead stock',
        ':n items have no weight data and are not counted in the capacity.' => ':n item tanpa data berat tidak dihitung dalam kapasitas.',
        // layar Warehouse Map
        'No warehouse yet' => 'Belum ada gudang', 'Add the first building, then its storage areas and bins.' => 'Tambahkan gedung pertama, lalu area penyimpanan dan bin-nya.',
        'Building' => 'Gedung', 'Show' => 'Tampilkan', 'Buildings' => 'Gedung', 'Storage Areas' => 'Area Penyimpanan', 'Bins' => 'Bin', 'Generate Bins' => 'Buat Bin', 'Generate' => 'Buat',
        'This building has no size yet. Fill in its length and width (metres) in Buildings to draw the map.' => 'Gedung ini belum punya ukuran. Isi panjang dan lebarnya (meter) di Buildings supaya petanya bisa digambar.',
        'Block' => 'Blokir', 'Unblock' => 'Buka Blokir', 'Block this bin? It can no longer receive stock until it is unblocked.' => 'Blokir bin ini? Bin tidak bisa menerima stok sampai blokirnya dibuka.',
        'Floor area :area m2 on :floors floor(s), :bins bins, :used of :cap kg in use. Red = dead stock: the item has not moved for more than :months months (moving it to another bin does not count).' => 'Luas lantai :area m2 di :floors lantai, :bins bin, terpakai :used dari :cap kg. Merah = dead stock: item tidak bergerak lebih dari :months bulan (dipindah ke bin lain tidak dihitung).',
        ':n bins of this building have no storage area yet, so they are not on the map. Set their area in Bins.' => ':n bin gedung ini belum punya area penyimpanan, jadi tidak tampil di peta. Isi areanya di Bins.',
        'Storage area' => 'Area penyimpanan', 'Floor area: lanes and slots. Rack area: racks, columns and levels. Open area: one bin, the numbers are ignored.' => 'Area lantai: lajur dan slot. Area rak: rak, kolom, dan tingkat. Area terbuka: satu bin, angkanya diabaikan.',
        'Lane / rack from' => 'Lajur / rak dari', 'Lane / rack to' => 'Lajur / rak sampai', 'Slot / column from' => 'Slot / kolom dari', 'Slot / column to' => 'Slot / kolom sampai',
        'Levels (rack)' => 'Tingkat (rak)', 'Capacity per bin (kg)' => 'Kapasitas per bin (kg)', 'Pallets per bin' => 'Pallet per bin',
        'Bin codes are made from the building, the area and the position, for example A10-PPR-L03-05. Bins that already exist are skipped, so you can run it again to add lanes.' => 'Kode bin dibuat dari gedung, area, dan posisinya, misalnya A10-PPR-L03-05. Bin yang sudah ada dilewati, jadi bisa dijalankan lagi untuk menambah lajur.',
        'Fill in the reason for blocking the bin.' => 'Isi alasan memblokir bin.', ':n bins created in :zone (:s already existed).' => ':n bin dibuat di :zone (:s sudah ada).',
        'Draft created with everything in :bin. Choose the destination bins, then Confirm.' => 'Draft dibuat berisi semua isi :bin. Pilih bin tujuannya, lalu Confirm.',
        // Stock Transfer
        'Search transfer no., warehouse, item, bin...' => 'Cari no transfer, gudang, item, bin...', 'Transfer No. / Route' => 'No Transfer / Rute', 'From Warehouse' => 'Gudang Asal',
        'To Warehouse' => 'Gudang Tujuan', 'Carried By' => 'Dibawa oleh', 'Dead Stock' => 'Dead Stock', 'From Bin' => 'Bin Asal', 'To Bin' => 'Bin Tujuan',
        'Choose where the stock is now and where it goes. Same warehouse = a move between bins. The items are added on the next screen.' => 'Pilih tempat stok sekarang dan tujuannya. Gudang yang sama = pindah antar bin. Item ditambahkan di layar berikutnya.',
        'From warehouse' => 'Gudang asal', 'To warehouse' => 'Gudang tujuan', 'Replenish a storage area' => 'Mengisi area penyimpanan', 'Consolidate / tidy up' => 'Merapikan / menggabungkan',
        'Bring to production' => 'Dibawa ke produksi', 'Move dead stock aside' => 'Menyingkirkan dead stock', 'Other' => 'Lainnya', 'Transfer No.' => 'No Transfer',
        'Changing it: Save first, then choose the destination bins again.' => 'Bila diubah: Save dulu, lalu pilih lagi bin tujuannya.', 'Name of the person who moves the goods' => 'Nama orang yang memindahkan barang',
        '+ Item' => '+ Item', 'Item | Source Bin | On Hand' => 'Item | Bin Asal | Stok', 'Destination Bin | Free Capacity' => 'Bin Tujuan | Sisa Kapasitas', 'Qty to Move' => 'Qty Dipindah',
        'Pick the item in its source bin, the destination bin and the quantity, then Save: the check below shows the destination capacity after the move. Confirm moves the stock.' => 'Pilih item di bin asalnya, bin tujuan, dan qty-nya, lalu Save: pemeriksaan di bawah menampilkan kapasitas bin tujuan sesudah pindah. Confirm memindahkan stoknya.',
        ':n line(s) carry DEAD STOCK: the item has not moved for months. Moving it does not make it alive again.' => ':n baris berisi DEAD STOCK: itemnya sudah berbulan-bulan tidak bergerak. Memindahkannya tidak membuatnya hidup lagi.',
        ':n line(s) bring the destination bin over its capacity. You may still Confirm; consider another bin.' => ':n baris membuat bin tujuan melebihi kapasitasnya. Tetap boleh Confirm; pertimbangkan bin lain.',
        'Bins & Capacity' => 'Bin & Kapasitas', 'One row per line: where it comes from, where it goes, and how full the destination bin is after the move.' => 'Satu baris per baris transfer: dari mana, ke mana, dan seberapa penuh bin tujuan sesudah pindah.',
        'Search item, bin...' => 'Cari item, bin...', 'Line' => 'Baris', 'Weight (kg)' => 'Berat (kg)', 'Destination After Move' => 'Tujuan Sesudah Pindah', 'Destination Now' => 'Tujuan Sekarang',
        'Check' => 'Periksa', 'Bin Labels' => 'Label Bin', 'Print Labels' => 'Cetak Label', 'Destination' => 'Tujuan',
        'Capacity label of every bin on this transfer, with its QR code and barcode. Scan the QR code with a phone to see what is in the bin.' => 'Label kapasitas tiap bin di transfer ini, beserta QR code dan barcode-nya. Pindai QR code dengan HP untuk melihat isi bin.',
        'Draft created. Add the items to move, then Confirm.' => 'Draft dibuat. Tambahkan item yang dipindah, lalu Confirm.', 'Draft deleted.' => 'Draft dihapus.',
        'Transfer cancelled. The stock is back in the source bins.' => 'Transfer dibatalkan. Stok sudah kembali ke bin asal.',
        'Confirmed. The stock has moved; the transfer is ready to print.' => 'Sudah Confirm. Stok sudah pindah; transfer siap dicetak.',
        'Add at least one line.' => 'Tambahkan minimal satu baris.', 'Line :n: fill in the quantity.' => 'Baris :n: isi qty-nya.', 'Line :n: choose the destination bin.' => 'Baris :n: pilih bin tujuan.',
        'Line :n: the destination bin is not in the destination warehouse.' => 'Baris :n: bin tujuan bukan milik gudang tujuan.', 'Line :n: the destination bin :bin is blocked.' => 'Baris :n: bin tujuan :bin sedang diblokir.',
        'Line :n: the destination bin is the same as the source bin.' => 'Baris :n: bin tujuan sama dengan bin asal.', ':item: only :qty on hand in the source bin.' => ':item: hanya ada :qty di bin asal.',
        'Only a Confirmed transfer can be cancelled.' => 'Hanya transfer Confirmed yang bisa di-Cancel.', ':item: not enough stock in :bin (:qty on hand).' => ':item: stok di :bin tidak cukup (ada :qty).',
        // label + lacak
        'Print Location Label' => 'Cetak Label Lokasi', 'Print Item Label' => 'Cetak Label Item', 'Label size' => 'Ukuran label', 'Copies per label' => 'Lembar per label', 'Print History' => 'Riwayat Cetak',
        'A label for every bin: its code, storage area, capacity, a QR code for the phone camera and a barcode for the scanner.' => 'Label untuk tiap bin: kode, area penyimpanan, kapasitas, QR code untuk kamera HP, dan barcode untuk scanner.',
        'Storage area (ticks all its bins)' => 'Area penyimpanan (mencentang semua bin-nya)', 'Add a building and its bins first.' => 'Tambahkan gedung dan bin-nya lebih dulu.',
        'A label for stock that is in a bin now: item code and name, quantity, bin, last movement, a QR code and a barcode. Dead stock gets a red stripe.' => 'Label untuk stok yang sekarang ada di bin: kode dan nama item, qty, bin, pergerakan terakhir, QR code, dan barcode. Dead stock diberi pita merah.',
        'This building holds no stock, so there is nothing to label.' => 'Gedung ini tidak berisi stok, jadi tidak ada yang diberi label.', 'Stock (item | bin | on hand)' => 'Stok (item | bin | jumlah)',
        'Choose at least one label to print.' => 'Pilih minimal satu label untuk dicetak.', 'Pallets' => 'Pallet', 'Weight' => 'Berat', 'Last move' => 'Gerak terakhir',
        'Labels' => 'Label', 'labels' => 'label', 'Item Labels' => 'Label Item', 'Location Labels' => 'Label Lokasi', 'Nothing to print' => 'Tidak ada yang dicetak',
        'The chosen bins or stock were not found.' => 'Bin atau stok yang dipilih tidak ditemukan.',
        'Track via QR / Barcode' => 'Lacak lewat QR / Barcode', 'Scan a location label, an item label, or type a bin code, an item code or a transfer number.' => 'Pindai label lokasi, label item, atau ketik kode bin, kode item, atau nomor transfer.',
        'Scan or type a code, then Enter' => 'Pindai atau ketik kode, lalu Enter', 'Scan with the camera' => 'Pindai dengan kamera', 'Find' => 'Cari', 'Close camera' => 'Tutup kamera',
        'A USB or Bluetooth scanner types the code and presses Enter by itself. On a phone, the camera button scans the QR code.' => 'Scanner USB atau Bluetooth mengetik kodenya dan menekan Enter sendiri. Di HP, tombol kamera memindai QR code.',
        'The camera cannot be opened. Type the code instead.' => 'Kamera tidak bisa dibuka. Ketik kodenya saja.',
        'Nothing found for ":code". Check the code, or look it up in the Warehouse Map.' => 'Tidak ada yang cocok dengan ":code". Periksa kodenya, atau cari di Warehouse Map.',
        'Show on Map' => 'Lihat di Peta', 'Blocked because' => 'Diblokir karena', 'Search item...' => 'Cari item...', 'Class' => 'Kelas', 'Search bin...' => 'Cari bin...',
        ':class · in :n bin(s) · :qty on hand in total' => ':class · di :n bin · total :qty', 'Last Movements' => 'Pergerakan Terakhir', 'Search doc no, bin...' => 'Cari no dokumen, bin...',
        'The 15 latest stock card rows of this item, newest first. TF = stock transfer between bins.' => '15 baris kartu stok terakhir item ini, yang terbaru di atas. TF = stock transfer antar bin.',
        'Balance in Bin' => 'Saldo di Bin',
        // Buildings / Storage Areas / Bins / laporan
        'Building Name' => 'Nama Gedung', 'Stock Kept' => 'Stok yang Disimpan', 'Length (m)' => 'Panjang (m)', 'Width (m)' => 'Lebar (m)', 'Floors' => 'Lantai', 'Shape' => 'Bentuk',
        'Cut-out Length (m)' => 'Panjang Potongan (m)', 'Cut-out Width (m)' => 'Lebar Potongan (m)', 'Cut-out Corner' => 'Sudut Potongan', 'Custom Outline (m)' => 'Garis Luar Sendiri (m)',
        'One row per warehouse building. Length and Width are in metres. L shape: also fill the cut-out corner (length, width, which corner). Custom outline: type the corner points in metres, clockwise, like 0,0; 30,0; 30,12; 0,12.' => 'Satu baris per gedung gudang. Length dan Width dalam meter. Huruf L: isi juga sudut potongannya (panjang, lebar, sudut mana). Garis luar sendiri: ketik titik sudut dalam meter, searah jarum jam, seperti 0,0; 30,0; 30,12; 0,12.',
        'Area Name' => 'Nama Area', 'Use' => 'Kegunaan', 'How Stored' => 'Cara Simpan', 'Stock Class' => 'Kelas Stok', 'X (m)' => 'X (m)', 'Y (m)' => 'Y (m)', 'Depth (m)' => 'Kedalaman (m)',
        'One row per storage area on one floor of a building. X and Y are the top-left corner on the floor plan, Width and Depth its size, all in metres. A saved code cannot be changed because bin codes are made from it.' => 'Satu baris per area penyimpanan di satu lantai gedung. X dan Y = sudut kiri-atas di denah, Width dan Depth = ukurannya, semua dalam meter. Kode yang sudah tersimpan tidak bisa diubah karena kode bin dibuat darinya.',
        'Storage Area' => 'Area Penyimpanan', 'Bin Code' => 'Kode Bin', 'Short Name' => 'Nama Singkat', 'Row' => 'Baris', 'Column' => 'Kolom', 'Capacity (kg)' => 'Kapasitas (kg)',
        'Usable' => 'Bisa Dipakai', 'Blocked Because' => 'Diblokir Karena',
        'Bins are usually made with Generate Bins on the Warehouse Map. Here you set the capacity, the storage area and Usable (untick to block a bin, with the reason). Row, Column and Level together must be unique within a building.' => 'Bin biasanya dibuat lewat Generate Bins di Warehouse Map. Di sini diatur kapasitas, area penyimpanan, dan Usable (lepas centang untuk memblokir bin, dengan alasannya). Row, Column, dan Level bersama-sama harus unik di satu gedung.',
        'Search item, bin, area...' => 'Cari item, bin, area...',
        'Stock on hand now, one row per item and bin, in every building. Red rows are dead stock: the item has not moved for longer than the dead stock limit (Settings, Constants), and moving it to another bin does not count as a movement.' => 'Stok yang ada sekarang, satu baris per item per bin, di semua gedung. Baris merah = dead stock: item diam lebih lama dari batas dead stock (Settings, Constants), dan dipindah ke bin lain tidak dihitung sebagai pergerakan.',
        'Search code, name, printed by...' => 'Cari kode, nama, pencetak...', 'Printed At' => 'Dicetak Pada', 'Qty on Label' => 'Qty di Label', 'Copies' => 'Lembar', 'Size' => 'Ukuran', 'Printed By' => 'Dicetak Oleh',
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
