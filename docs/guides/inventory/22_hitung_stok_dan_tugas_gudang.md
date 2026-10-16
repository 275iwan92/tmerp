# Gudang: Hitung stok (Count Tasks) dan Task Board

*Panduan pengguna untuk Officer Gudang dan Supervisor Gudang. Ditulis 16 Oktober 2026 dari membaca kode dan data mockup.test. Layar tidak dibuka di browser dan tes tidak dijalankan; hal yang belum terbukti ada di bagian 6.*

Aturan teknis: `docs/tg/inventory/warehouse/16_card_count-task.md` dan `17_card_warehouse-task-and-board.md`.

## 1. Gambaran: hitung per area

Hitung stok dikerjakan **per area penyimpanan**, bukan per dokumen. Satu area dihitung sekali setiap sekian hari (diatur di Storage Areas, kolom **Count Every (days)**) atau kapan pun Supervisor menekan **Start Count**. Hasilnya adalah dokumen **Stock Opname** bernomor yang memperbaiki saldo.

| Langkah | Siapa | Layar | Hasilnya |
|---|---|---|---|
| 1 | Supervisor | Warehouse > Count > **Count Tasks**: pilih area, **Start Count** | Satu tugas hitung per item di tiap bin area itu; bin-nya dikunci |
| 2 | Officer | Count Tasks: **Open** pada tugas, isi **Counted Quantity**, **Confirm** | Selisih menjadi Stock Opname terposting |
| 3 | Otomatis | Jadwal harian pukul 05:00 | Area yang jatuh tempo dimulai sendiri |

## 2. Memulai hitung

1. Buka **Warehouse > Count > Count Tasks**. Kartu **Start Counting** berisi daftar **Area**. Area yang sudah lewat jadwalnya diberi tanda "due" dan tertulis "(every N days)".
2. Pilih area, tekan **Start Count**. Pesan: ":n count task(s) made. The bins are locked until they are counted." Bila area tidak berisi stok: "There is no stock in that area, so there is nothing to count."
3. **Start Due Areas** memulai semua area yang jatuh tempo sekaligus ("No area is due, or the due areas have no stock." bila tidak ada).
4. Area yang masih punya tugas hitung terbuka tidak bisa dimulai lagi: "Area :zone is already being counted."

Area jatuh tempo bila aktif, bukan virtual, **Count Every (days)** terisi, belum punya tugas terbuka, dan tugas hitung terakhirnya sudah lebih tua dari selang itu (atau belum pernah dihitung). Di data contoh belum ada area dengan jadwal, jadi tanda "due" tidak akan muncul sebelum Anda mengisinya.

### Bin dikunci: apa artinya

Bin yang berisi stok dan masuk daftar hitung **dikunci**. Sistem menolak: transfer dari atau ke bin itu ("Line :n: the bin :bin is locked for counting."), putaway ke bin itu, dan alokasi/Confirm Pick dari bin itu. Bin yang terkunci tidak diusulkan sebagai tujuan putaway.

**Hati-hati:** kunci ini **tidak** menghalangi dokumen stok lain (penerimaan barang, Stock In/Out, retur, penyesuaian, pemakaian produksi). Jadi selama hitung berjalan, jangan memposting dokumen lain ke bin yang sedang dihitung, karena saldo berubah dan hasil hitung jadi sulit dibandingkan. Bin kosong di area itu juga tidak dikunci.

## 3. Menghitung dan Confirm

1. Buka daftar. Kolom: Task, Area, Bin, Item, **System Qty**, **Counted Qty**, **Difference**, Unit, **Stock Opname**, Status. Kotak statistik: All, Open, Done, Cancelled. Daftar menampilkan tugas terbuka dan yang diubah 7 hari terakhir.
2. **Open** pada tugas. Layar menampilkan "System quantity" (saldo saat tugas dibuat). Hitung fisik barang di bin itu.
3. Isi **Counted Quantity** (tidak boleh minus: "The counted quantity cannot be negative.") lalu **Confirm**.
4. Sistem membandingkan jumlah hitung dengan **saldo kartu stok saat Anda menekan Confirm** (bukan angka System Qty lama):
   - Cocok: "Counted. No difference." Tidak ada dokumen.
   - Selisih: dokumen **Stock Opname** terbit dan terposting seperti penyesuaian lain. Pesan: "Counted. Stock Opname :no corrected the stock." Nomornya tampil di kolom **Stock Opname**.
   - Posting ditolak (mis. periode sudah ditutup): pesan galat tampil dan tugas **tetap Open**.
5. Bin dibuka lagi otomatis setelah tugas terbuka terakhir di bin itu selesai.

**Cancel** pada tugas (dengan alasan; kosong dicatat "Cancelled") membatalkan tanpa penyesuaian dan membuka kunci bila itu tugas terakhir bin tersebut. Pesan: "Count task cancelled."

Tidak ada persetujuan atasan untuk selisih: Stock Opname langsung terposting. Pastikan hitung ulang bila selisihnya besar sebelum menekan Confirm.

## 4. Perintah terjadwal

Server menjalankan `inv:count-due` setiap hari pukul **05:00** (hanya bila tabel tugas ada). Perintah ini membuat tugas dan mengunci bin untuk setiap area jatuh tempo, atas nama "system (count schedule)". Pengelola sistem dapat mencobanya dengan `--dry-run` untuk hanya melihat daftar area, atau `--zone=KODE` untuk satu area.

## 5. Task Board

**Warehouse > Task Board** adalah papan **baca saja** dengan tiga kolom: **Open**, **In progress**, **Done** (Done = 7 hari terakhir). Isinya:

| Kartu | Kolom | Tag |
|---|---|---|
| Tugas Putaway / Count (nomor `WT...`) | Open atau Done | Putaway / Count; prioritas **High** bila URGENT |
| Pick List | Draft = Open, In progress, Done | Pick List |
| Transfer dua langkah | Shipped atau Discrepancy = In progress; Received atau Closed = Done | In Transit (merah bila Discrepancy) |
| "Count due · KODE · nama area" | Open | Count |

Tombol **Open** di tiap kartu membawa Anda ke layar asalnya; papan tidak mengubah apa pun. Pick List atau tugas yang dibatalkan **tidak** tampil di papan.

## 6. Belum terverifikasi

- Layar tidak dibuka; teks dan tombol dari kode. Tes `CountLockTest` (7 tes) dan `WarehouseTaskBoardTest` (3 tes) dibaca tetapi tidak dijalankan.
- Data contoh berisi 12 tugas hitung uji (WT2600001-12) yang bin dan areanya sudah terhapus; 2 di antaranya masih Open dan tampil di daftar dengan area/bin "—". **Jangan** menekan Confirm pada keduanya: kode akan gagal mencari bin yang hilang. Perilaku ini dibaca dari kode, belum dicoba.
- Pengaruh dokumen stok lain yang diposting ke bin terkunci terhadap hasil hitung hanya disimpulkan dari kode (tidak ada pengecekan kunci di sana), belum diuji.
- Siapa yang boleh Start/Confirm: hanya hak modul Inventory yang terlihat.
