# Inventory & Warehouse v2.01 — Dokumen Cakupan (Scope)

> **Status: DRAFT rev.3 2026-10-08.** O-1 sampai O-4, O-11, O-12 diputuskan user; sisanya berisi saran menunggu persetujuan (bagian 13). Belum ada kode. Versi: 2.01 (perbaikan modul Inventory dan Warehouse).
> Isi: keputusan user, kondisi nyata sekarang (dicek ke kode dan skema), pembagian Inventory vs Warehouse, daftar kebutuhan
> bernomor (I-, W-, B-, Q-, V-), usulan jalan keluar QC, booking sejak costing, skema yang diusulkan, fasing, dan hal terbuka.
> Rujukan terkait: `inventory_tier1.md`..`inventory_tier4.md`, `warehouselayout_tier1.md`, `materialbooking_tier1.md`,
> Help `guide-inv-*` (`resources/data/help/inventory_concepts*.json`). Aturan user tetap: `globaluserrules.md`.

## 0. Cara membaca

- **Kode kebutuhan**: `I-` Inventory, `W-` Warehouse, `B-` Booking, `Q-` QC, `V-` Valuasi, `D-` keputusan.
- **Status**: `ADA` (sudah dibangun, dicek di kode), `SEBAGIAN`, `BARU` (belum ada), `?` (belum diverifikasi).
- **Prioritas**: `P1` wajib 2.01, `P2` 2.01 bila waktu cukup, `P3` setelah 2.01.
- Kolom "dicek" berarti saya melihat nama berkas, kolom, atau komentar kode. Itu **bukan** pembacaan seluruh logika.

---

## 1. Keputusan yang sudah diambil

| # | Keputusan | Sumber |
|---|---|---|
| D1 | **Dokumen Transfer dan Opname dimiliki Inventory** (dokumen, persetujuan, nilai). **Eksekusi lapangan** (tugas, scan, hitung) di Warehouse. | user 2026-10-08, setuju saran 1 |
| D2 | **Subkon**: vendor = **lokasi virtual milik perusahaan**; stok tetap terlihat dan bernilai. | saran, tidak ada keberatan |
| D3 | **Material titipan customer**: stok dengan atribut **pemilik Customer**; **tidak dinilai, tidak dijurnal**, punya lokasi, bisa di-pick. Menggantikan perilaku sekarang (non-stok). | user 2026-10-08 (O-3) |
| D4 | **QC sampling**, dan barang **langsung dibawa ke produksi bila URGENT**. Rancangan di bagian 7. | user 2026-10-08 (O-2) |
| D14 | Bila (**output + waste**) masih kurang dibanding bahan yang dikeluarkan, berarti **bahan kurang sejak awal**: sistem **meminta kekurangannya dikirim** (7.6). | user 2026-10-08 (O-2) |
| D5 | **Booking dimulai sejak product costing sudah menghitung** (bukan menunggu CO), karena proses cepat dan metode **short run**. Tafsir pre-booking lunak yang naik ke keras pada CO Confirmed **dibenarkan user** (O-1). Rancangan di bagian 6. | user 2026-10-08 |
| D6 | **Lokasi**: RM dan FG sampai tingkat **bin**; WIP lantai produksi sampai tingkat **area**. | saran |
| D7 | **SFG dilacak per tahap proses** (cetak, laminasi, die cut, dst.). | saran |
| D8 | **Cakupan 2.01 mencakup VALUASI dan jurnal otomatis**, bukan hanya operasional. | user 2026-10-08 |
| D9 | Booking tetap di **Production > Planning**; Inventory hanya membaca Reserved dan Available; **pick list di Warehouse**. | user 2026-10-08, setuju saran 5 |
| D13 | Metode nilai = **AVERAGE** (rata-rata bergerak). Rumus dan contoh kenaikan harga di bagian 8. | user 2026-10-08 (O-4) |
| D15 | **Average per item** (satu angka untuk seluruh perusahaan), bukan per bin. Rancangan dan migrasi di 8.5. | user 2026-10-08 (O-11) |
| D16 | **Pengaman URGENT** sesuai saran: yang boleh menandai PPIC, Supervisor Produksi, Ops Manager dan di atasnya; alasan dan tautan WO / CO wajib; bahan FSC / food grade butuh persetujuan kepala QC atau Ops Manager. | user 2026-10-08 (O-12) |

Pertanyaan sebelumnya yang dijawab "sesuai saran" mengikuti usulan ulasan 2026-10-08.

---

## 2. Kondisi sekarang (hasil cek kode dan skema)

### 2.1 Yang sudah ada

| Area | Temuan |
|---|---|
| Tabel `tm2inv` | `rm_stock`/`rm_stock_d`, `sfg_stock`/`sfg_stock_d`, `fg_stock`/`fg_stock_d`, `fg_stockmst`, tiga kartu stok (`rm_`, `sfg_`, `fg_stockcard`), `inv_period`, `inv_stock_layer`, `inv_layer_consumption`, `rm_booking`, `rm_booking_d`, `rm_booking_alloc` (migration 2026-10-01) |
| View | `v_rm_onhand`, `v_rm_available`, `v_sfg_onhand`, `v_fg_onhand`, `v_inv_movement`, `v_inv_wo_reconcile`, `v_rm_out_returnable` |
| Kartu stok | Berantai (`previous_doc_id`), kolom `qty_bb/in/out/adj/eb` dan `value_*` |
| Lapisan harga | `inv_stock_layer` (per kelas, item, lokasi, lot) dan `inv_layer_consumption` |
| Gudang (master) | `tm2mst.wh_warehouse`, `wh_loc` (baris/kolom/tumpukan, `location_type`, `is_usable`, `capacity_kg`) |
| Layanan | `StockLedger`, `StockPostingService`, `StockDocumentService`, `StockTransferService`, `PalletService`, `BinGenerator`, `StockCardChain`, `WarehouseStock`; model `InvTransfer`, `InvPallet`, `InvLabelPrint` |
| Layar Inventory | Stock In / Out / Return / Adjustment & Count untuk RM, SFG, FG; Balance, Card, Slow Moving per kelas; Adjustment Approval; Warehouse & Location |
| Layar Warehouse | Warehouse Map, Stock Transfer, Transfer Approval, Stack Monitor, Buildings, Storage Areas, Bins, Stock by Location, label lokasi / pallet / item (QR), riwayat cetak label, Track via QR |
| Laporan | Stock Card, Recap, Summary, Opname, Loss & Gain, Stock in Inspection |
| Penerimaan khusus | Di Procurement (`SpecialReceiptService`): tipe **PO**, **CUSTOMER**, **SUBCON** |
| Customer | Penerimaan CUSTOMER = bahan dari customer untuk CO tertentu; **bukan stok, bukan aset, tidak ke kartu stok, tidak dijurnal** |
| Subkon | Penerimaan SUBCON atas PO Subkon; biasanya langsung ke proses berikutnya (tidak masuk gudang); Dr HPP Jasa Subkon / Cr GR/IR; `reportShortage()` untuk kekurangan yang baru ketahuan |
| QC penerimaan | Baris penerimaan punya `qc_qty_status` dan `qc_quality_status`; **posting stok diblokir** sampai QC qty selesai dan QC mutu tidak PENDING (`PvPostingService::blockers`) |
| Jurnal | Posting PV membuat Dr Persediaan / Cr GR/IR otomatis, akun dari item atau kategori |
| Booking | Mesin `MaterialPlanningEngine` dari **CO** (Draft dan Submitted = pratinjau, Confirmed = usulan, PPIC Confirm = keras). `v_rm_available` hanya mengurangi booking yang **Confirmed** per bahan + lokasi |
| Notifikasi | Popup kekurangan bahan (sudah dibangun) |

### 2.2 Celah yang terlihat

| Celah | Bukti | Dampak |
|---|---|---|
| **Batch hanya teks** | `lot_no` varchar di kartu stok dan lapisan; tidak ada master batch, tanggal kedaluwarsa, status QC per batch | Tidak ada FEFO, kedaluwarsa, traceability, atau status per batch |
| **QC menahan posting** | `PvPostingService::blockers` | Bertentangan dengan jalur URGENT (D4): barang mendesak tidak bisa langsung ke produksi |
| **Tidak ada pemilik pada stok** | Tidak ada kolom pemilik di tabel stok; titipan customer sengaja di luar stok | Titipan tidak punya lokasi, tidak bisa di-pick atau dialokasikan secara fisik |
| **Tidak terlihat sisi kirim subkon** | Hanya penerimaan SUBCON yang ditemukan; tidak ditemukan dokumen kirim bahan ke vendor, lokasi vendor, atau laporan stok di vendor | Bahan di vendor tidak terlacak |
| **Booking mulai dari CO** | `demands()` membaca `co_order` | Bertentangan dengan D5 |
| **FG belum punya sumber Available terpusat** | Komentar kode `MaterialPlanningEngine`: "FG belum punya view booking" | Angka Available FG bisa berbeda antar layar |
| **Tidak terlihat pick list, putaway, dok terima** | Tidak ada tabel atau layanan bernama itu | Gudang belum punya tugas terstruktur |
| **Transfer satu langkah** | `StockTransferService`: stok pindah seketika; tidak ada In Transit; persetujuan opsional (flow `TF`, nonaktif); tanpa jurnal | Perlu dua langkah untuk gudang jauh (I-31) |
| **Opname tanpa kunci** | Layar Adjustment & Count ada; tidak ditemukan kunci bin atau freeze | Selisih bisa tercipta saat bin masih bergerak (W-08) |
| **Average dihitung per bin** | `StockCardChain`: rantai per posisi (item di satu bin); transfer membawa Avg bin asal | Biaya keluar bergantung pada bin; lihat 8.5 dan O-11 |
| **FIFO/LIFO belum jalan** | Komentar `StockPostingService`: `inv_stock_layer` belum diimplementasikan | Sesuai: Average dipilih, FIFO ditunda |
| **Jurnal koreksi transaksi mundur** | Hanya peringatan (`StockPostingService`) | Celah O-14 |
| **Kurang kirim belakangan** | Hanya ada `reportShortage()` untuk penerimaan SUBCON | Perlu diperluas ke supplier dan customer (7.6) |
| **Periode inventory** | `inv_period` bulanan (OPEN / CLOSING / CLOSED); tanggal di periode tertutup ditolak; koreksi lewat Stock Adjustment | Sudah sesuai; kunci periode GL belum diperiksa |
| **Rekonsiliasi WO** | `v_inv_wo_reconcile` hanya RM keluar-masuk per WO (issued, return good / scrap, net used) | Tidak memuat output dan waste; Q-13 butuh data output produksi |

---

## 3. Pembagian Inventory dan Warehouse

| | **INVENTORY** (apa, berapa, nilai, pemilik) | **WAREHOUSE** (di mana, bagaimana fisik) |
|---|---|---|
| Objek | Item, qty, nilai, pemilik, status stok, batch | Gedung, area, bin, pallet, dok, tugas |
| Dokumen | Stock In / Out / Return / Adjustment / Transfer / Opname (**pemilik dokumen**) | Tugas: Receive, Putaway, Pick, Move, Count, Stage |
| Hasil | Ledger, kartu stok, saldo, valuasi, jurnal | Perpindahan fisik terkonfirmasi (scan), label, hitungan |
| Persetujuan | Adjustment, selisih opname, transfer | Tidak ada; hanya melaporkan |
| Pengguna | Admin stok, PPIC, akuntansi | Operator dan kepala gudang |

**Titik serah**: setiap tugas Warehouse yang selesai menghasilkan **bukti** (scan, qty, bin) yang menjadi dasar posting Inventory. Warehouse tidak menghitung nilai; Inventory tidak menentukan rute atau bin.

---

## 4. Cakupan INVENTORY

### 4.1 Kelas item dan sumber

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| I-01 | Tiga kelas stok RM, SFG, FG dengan layar masuk / keluar / retur / adjustment | ADA | – |
| I-02 | Kelas tambahan: kemasan, consumable/spare part, scrap (nilai dan lokasi sendiri) | BARU | P2 |
| I-03 | **Master batch**: nomor batch, lot supplier, tanggal produksi, kedaluwarsa, retest, status QC, sertifikasi (FSC, food grade), arah serat | BARU | P1 |
| I-04 | **Pemilik stok**: Company / Customer / Supplier; stok pemilik lain tidak dinilai | BARU | P1 |
| I-05 | **Status stok per batch**: Quarantine, Conditional (URGENT atau menunggu sampling), Released, Rejected, Blocked | BARU | P1 |
| I-06 | Satuan ganda (lembar dan kg) dengan konversi dari spesifikasi dan berat timbang aktual | ? | P1 |
| I-07 | Roll sisa dengan ID dan berat sisa; kebijakan offcut | BARU | P2 |
| I-08 | SFG per tahap proses (D7) dan perubahan satuan lembar menjadi pcs di die cut | ? | P1 |
| I-09 | Stok FG terikat Customer Order; over-run / under-run; bill and hold | ? | P2 |

### 4.2 Sumber masuk

| Kode | Sumber | Perilaku yang diinginkan | Status | Prio |
|---|---|---|---|---|
| I-10 | Supplier (GRN dari PO) | Jalur Normal menunggu sampling; jalur **URGENT** diposting langsung dan ke produksi (bagian 7); kurang kirim diminta dikirim (7.6); nilai = harga PO + landed cost | SEBAGIAN (terblokir QC) | P1 |
| I-11 | Produksi (SFG/FG) | Hasil per tahap, dengan yield dan reject | ? | P1 |
| I-12 | **Retur customer** | Layak pakai atau afkir (sudah ada); tautan ke pengiriman asli dan nilai asli | SEBAGIAN | P1 |
| I-13 | **Material titipan customer** (D3) | Masuk sebagai stok pemilik Customer, **tidak dinilai, tidak dijurnal**, hanya bisa dialokasikan ke CO customer itu; memiliki lokasi dan bisa di-pick | BARU (sekarang non-stok) | P1 |
| I-14 | **Subkon kembali** | Nilai = nilai kirim + ongkos jasa; susut vendor dengan toleransi; lewat lokasi vendor | SEBAGIAN (jurnal ada) | P1 |
| I-15 | Transfer masuk | Membawa nilai asal; selisih terima | `?` | P1 |
| I-16 | Adjustment plus dan saldo awal | Persetujuan, alasan, akun selisih | ADA | – |

### 4.3 Sumber keluar

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| I-20 | Issue ke produksi: manual, backflush, atau overhead per item (metode issue pada master item) | ? | P1 |
| I-21 | Pengiriman FG ke customer, HPP otomatis | ADA via dispatch | – |
| I-22 | Retur ke supplier dengan referensi penerimaan asal | ? | P2 |
| I-23 | **Kirim ke subkon**: dokumen kirim, keluar ke lokasi virtual Vendor, tenggat kembali | BARU | P1 |
| I-24 | Laporan **stok di vendor** (kirim, kembali, susut, sisa) dan umur | BARU | P1 |
| I-25 | Scrap dan pemusnahan dengan berita acara dan jurnal | ? | P2 |
| I-26 | Sample / pemakaian internal dengan kode tersendiri | ? | P3 |

### 4.4 Multi-warehouse dan transfer

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| I-30 | Saldo per item + gudang + lokasi + batch + pemilik + status | SEBAGIAN | P1 |
| I-31 | Transfer **dua langkah** lewat In Transit; selisih terima berstatus Discrepancy | BARU (sekarang satu langkah) | P1 |
| I-32 | Transfer antar badan hukum diperlakukan sebagai penjualan antar perusahaan | BARU | P3 |
| I-33 | Pindah antar bin (tanpa jurnal) | ADA (via transfer) | – |

### 4.5 Opname dan koreksi

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| I-40 | Opname: jadwal, **kunci bin**, hitung, selisih, persetujuan, jurnal (dokumen di Inventory, hitung di Warehouse, sesuai D1) | SEBAGIAN | P1 |
| I-41 | Cycle count berdasarkan kelas ABC | BARU | P3 |
| I-42 | Larangan stok minus sebagai default; aturan pengecualian per lokasi | ? | P1 |

### 4.6 Laporan

| Kode | Laporan | Status | Prio |
|---|---|---|---|
| I-50 | Kartu stok, saldo per tanggal (as of), ringkasan, rekap | ADA | – |
| I-51 | Aging dan Dead Stock dengan batas hari yang bisa diatur | SEBAGIAN (slow moving) | P2 |
| I-52 | Kedaluwarsa (near expiry, expired) dengan nilai | BARU | P1 |
| I-53 | In Transit; Discrepancy transfer | BARU | P2 |
| I-54 | Reservasi dan Available (RM dan FG) satu sumber | SEBAGIAN | P1 |
| I-55 | Traceability maju dan mundur per batch | BARU | P1 |
| I-56 | ABC, reorder, pemakaian; commitment value | BARU | P3 |

---

## 5. Cakupan WAREHOUSE

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| W-01 | Struktur gedung, area, bin; jenis lokasi (penyimpanan, dok terima, dok kirim, karantina, blokir, WIP, scrap, **vendor virtual**) | SEBAGIAN (`location_type` ada) | P1 |
| W-02 | Atribut bin: kapasitas, aturan campur, tipe penyimpanan, status (aktif, penuh, blokir, dikunci opname) | SEBAGIAN (`capacity_kg`, `is_usable`) | P1 |
| W-03 | **Receiving**: dok terima, cocokkan surat jalan dan PO, hitung dan timbang, cetak label | BARU | P1 |
| W-04 | **Putaway**: usulan bin (zona, kapasitas, perputaran), konfirmasi scan bin | BARU | P1 |
| W-05 | **Pick list** dari booking dan dari permintaan; urutan FEFO/FIFO dan syarat serat/sertifikasi; konfirmasi scan dua kali | BARU | P1 |
| W-06 | Staging dan pengiriman: verifikasi terhadap DO, label karton dan pallet | ? | P2 |
| W-07 | Eksekusi transfer (kirim, terima) dengan scan | SEBAGIAN | P1 |
| W-08 | Hitung opname dengan scan; **bin terkunci** selama dihitung (sekarang tidak ada kunci) | BARU | P1 |
| W-09 | Replenishment pick face dari cadangan | BARU | P3 |
| W-10 | Label dan scan: lokasi, pallet, item (ADA); **roll, batch, tinta campuran, die, traveler** | SEBAGIAN | P1 |
| W-11 | Peta dan Stack Monitor | ADA | – |
| W-12 | Lokasi khusus: karantina, ruang istirahat kertas, rak offcut, area hazmat | BARU | P2 |
| W-13 | Papan tugas gudang (daftar tugas terbuka per operator, prioritas, umur) | BARU | P2 |
| W-14 | Aset alat cetak (die, plat, klise) berlokasi dengan umur dan pemilik | BARU | P2 |
| W-15 | Wave / batch / zone picking, slotting, cross docking, RFID | BARU | P3 |

---

## 6. Booking sejak costing (D5, D9)

> **Tafsir saya atas D5 (mohon dikoreksi bila keliru):** begitu ProCost sudah menghitung dan hasilnya terbit (PUBLISHED),
> kebutuhan bahan sudah diketahui, maka sistem langsung membuat **booking awal (pre-booking)** supaya PPIC dan Pembelian bisa
> bergerak sebelum CO ada. Alasannya: proses cepat dan short run, waktu antara costing dan produksi sangat pendek.

### 6.1 Tangga booking (satu rantai, bukan tiga booking)

| Tahap | Pemicu | Sifat | Mengunci stok? | Berakhir bila |
|---|---|---|---|---|
| **B0 Pre-booking** | ProCost terbit untuk sebuah inquiry | Lunak (usulan) | **Tidak** (hanya terlihat sebagai kebutuhan dan kekurangan) | Quotation kedaluwarsa atau ditolak, atau inquiry dibatalkan |
| **B1 Terkait Quotation** | Quotation dikirim | Lunak | Tidak | `valid_until` lewat atau ditolak |
| **B2 CO Draft / Submitted** | CO dibuat dari quotation atau langsung | Lunak, berprioritas | Tidak | CO batal |
| **B3 CO Confirmed** | CO Confirmed, PPIC Confirm | **Keras** | **Ya**, per lokasi | WO selesai atau CO batal |
| **B4 Pick** | WO lahir dan pick list dibuat | Dialokasikan ke bin dan batch | Ya | Issued |

Aturan penting:
1. **Satu rantai kebutuhan, satu header booking.** Pre-booking dari costing **dinaikkan statusnya** menjadi booking Quotation, lalu CO,
   bukan dibuat ulang. Kunci rantai: `inquiry_id` dan id costing. Tanpa ini kebutuhan terhitung dua atau tiga kali.
2. **Pre-booking tidak mengurangi Available.** Itu mencegah "kebutuhan hantu" dari penawaran yang tidak menang.
3. **Penahanan opsional (Hold)** oleh PPIC untuk bahan kritis atau barang tertentu, dengan **tanggal habis otomatis**. Hold mengunci seperti booking keras, tetapi bisa dilepas sendiri.
4. **Sinyal pembelian dini**: bahan dengan lead time panjang yang kurang pada tahap B0 muncul di Shortage Planner dengan label "belum CO", sehingga PR bisa dibuat lebih awal atas persetujuan PPIC.
5. Booking **hanya membaca** hasil ProCost dan layout (zona beku: ProCost tidak diubah).
6. Pemilik angka Reserved dan Available tetap Inventory (D9); satu sumber untuk **RM dan FG**.
7. Rebutan stok: CO Confirmed lebih dulu, lalu CO Draft, lalu Quotation, lalu pre-booking; dalam tingkat sama menurut tanggal butuh.

### 6.2 Kebutuhan

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| B-01 | Sumber kebutuhan baru: ProCost terbit tanpa CO | BARU | P1 |
| B-02 | Kenaikan tahap B0 → B3 pada satu header (kunci `inquiry_id`) | BARU | P1 |
| B-03 | Hold dengan tanggal habis otomatis | BARU | P2 |
| B-04 | Label "belum CO" dan ringkasan pre-booking di Shortage Planner | BARU | P1 |
| B-05 | Perubahan CO setelah Confirm: selisih otomatis (kelebihan dilepas, tambahan dicoba di-booking) | BARU | P1 |
| B-06 | Aturan booking basi: peringatan setelah N hari tanpa WO, pelepasan otomatis setelah batas lebih lama (keduanya pengaturan) | BARU | P2 |
| B-07 | Hot job: aturan prioritas, pengambilalihan oleh Ops Manager dengan log, notifikasi popup ke pemilik order yang kehilangan stok | BARU | P2 |
| B-08 | Substitusi saat SHORT: sistem mengusulkan berurutan, PPIC mengonfirmasi (kolom sudah ada di skema) | BARU | P1 |
| B-09 | Batas kebutuhan memuat waste; toleransi over-run menjadi batas penerimaan, bukan booking tambahan | BARU | P2 |
| B-10 | Die tidak tersedia: peringatan saat booking; WO tidak dirilis tanpa die atau override beralasan | BARU | P2 |
| B-11 | CO batal setelah issue: bahan tak terpakai kembali ke stok bebas lewat retur dari produksi; yang tercetak atau terpakai dihapus buku atau ditagih | BARU | P1 |
| B-12 | Pick list per WO dari booking (W-05) | BARU | P1 |
| B-13 | Commitment value dan umur booking sebagai laporan | BARU | P3 |
| B-14 | Kunci konkurensi saat dua orang Confirm bahan yang sama | BARU | P1 |

Catatan: butir B-05 sampai B-11 memuat jawaban referensi dari Help `guide-inv-booking-decisions` (SAP, NetSuite, Odoo, Dynamics 365, ERPNext).

---

## 7. QC: sampling, URGENT langsung ke produksi, dan kurang kirim (D4, D14)

> **Keputusan user 2026-10-08 (O-2):** "QC sampling, dan langsung dibawa ke produksi jika URGENT." Tambahan user: bila kemudian dihitung
> **output + waste masih kurang, berarti qty bahan dari awal sudah kurang, maka minta dikirim kekurangannya** (7.6).
> Usulan kelas A / B / C sebelumnya **disederhanakan**: kelas risiko kini hanya menentukan **intensitas sampling**, tidak lagi menahan barang.

### 7.1 Masalah
Sekarang posting stok **diblokir** sampai QC qty dan mutu selesai (`PvPostingService::blockers`). Untuk pekerjaan mendesak, barang sudah di dok dan
produksi menunggu. Dua kebutuhan harus hidup bersama: **mutu terjaga** (sampling) dan **waktu tidak terbuang** (URGENT langsung ke produksi).

### 7.2 Dua jalur

| | **Jalur Normal** | **Jalur URGENT** |
|---|---|---|
| Pemicu | Semua penerimaan | Ditandai URGENT saat Receive oleh pihak berwenang, dengan alasan dan WO / CO tujuan |
| Urutan | Receive di dok → **ambil sampel** menurut rencana sampling → uji cepat di dok → keputusan lot (Released atau Rejected) → posting GRN → putaway | Receive → **posting langsung** (status batch **Conditional**) → **langsung ke produksi** (dock-to-line, tanpa putaway) → sampling berjalan **paralel** → Released atau Rejected |
| Status batch saat dipakai | Released | Conditional |
| Lokasi tujuan | Bin penyimpanan | Area WIP / mesin yang meminta |
| Jika gagal | Ditolak di dok: retur atau klaim, belum masuk stok | Telusur maju, tahan WO dan FG terdampak, klaim supplier (7.5) |

Perubahan kode: `PvPostingService::blockers()` tidak lagi memblokir baris bertanda URGENT yang QC mutunya masih PENDING. Baris normal tetap menunggu hasil sampling.

### 7.3 Rencana sampling
Yang diuji adalah **sampel**, bukan seluruh barang; hasilnya menentukan nasib satu lot.

| Intensitas | Kapan | Contoh isi |
|---|---|---|
| **Tightened** (ketat) | Kelas A (food grade, klaim FSC, job kritis warna, bahan berbahaya), supplier baru, setelah ada reject | Sampel lebih besar |
| **Normal** | Kelas B (kertas, board, film, lem dari supplier dikenal) | Sampel standar |
| **Reduced** (ringan) | Supplier konsisten lulus beberapa lot berturut-turut | Sampel kecil atau tiap lot ke-N |
| **Skip / COA** | Kelas C (bahan penolong kecil) | Cukup sertifikat supplier |

- Aturan berpindah intensitas mengikuti konsep *dynamic modification rule* SAP: lulus N lot berturut-turut → Reduced; satu reject → Tightened sampai pulih.
- Ukuran sampel menurut jumlah lot dari **tabel pengaturan** (mengikuti ANSI/ASQ Z1.4 atau persentase sederhana); semua angka bisa diubah user.
- Uji cepat di dok: gsm (timbang sampel), caliper, kelembapan, arah serat, kondisi fisik, uji warna tinta, cek sertifikat dan batch.

### 7.4 Pengaman URGENT (usulan)
1. **Siapa boleh menandai URGENT**: PPIC, Supervisor Produksi, Ops Manager dan di atasnya (pengaturan).
2. **Alasan wajib** dan **tautan ke WO / CO** yang membutuhkan.
3. **Bahan dengan klaim FSC atau food grade**: tetap boleh langsung, tetapi muncul peringatan dan perlu persetujuan kepala QC atau Ops Manager. *Pengaman ini usulan saya; bisa dihapus bila Anda ingin URGENT benar-benar tanpa syarat.*
4. **Tenggat keputusan** (pengaturan): bila Conditional belum diputuskan dalam N jam, atau job selesai lebih dulu, wajib **Usage Decision** susulan dan eskalasi ke kepala QC dan Ops Manager.
5. **Laporan URGENT**: jumlah, pemohon, supplier, dan hasil sampling kemudian, supaya penggunaan URGENT tidak melebar tanpa terlihat.
6. Nilai jurnal **tidak berubah** oleh status QC; Reject memicu retur atau klaim, bukan koreksi diam-diam.

### 7.5 Bila ditolak setelah dipakai
Sistem menelusuri maju (batch → issue → WO → batch FG → pengiriman), menandai WO dan FG terdampak (ditahan), membuat **dokumen klaim ke supplier**,
dan mencatat kerugian ke supplier atau ke scrap.

### 7.6 Kurang kirim yang baru ketahuan belakangan (D14)

> **Aturan user:** setelah produksi, bila **output + waste masih kurang** dibanding bahan yang dikeluarkan, berarti **qty bahan dari awal sudah kurang**.
> Sistem lalu **meminta kekurangannya dikirim**.

Ini terutama terjadi pada jalur URGENT (barang langsung ke mesin sebelum dihitung atau ditimbang teliti), tetapi berlaku untuk semua jalur.

**Cara kerja**
```
Kebutuhan bahan   = (Output baik + Waste STANDAR) dalam satuan bahan
Qty tersedia      = Qty bahan yang dikeluarkan ke WO (dari batch itu)
Kekurangan        = Kebutuhan bahan - Qty tersedia

Kekurangan > toleransi  ->  "Bahan kurang sejak awal"  ->  usulan Permintaan Kirim Kekurangan
```
- Perhitungan memakai **waste standar** (bukan waste aktual). Waste aktual yang melebihi standar adalah **varians pemakaian**, bukan kurang kirim, jadi tidak ditagihkan ke pemasok.
- Sebelum dikonfirmasi, petugas melakukan **hitung ulang atau timbang ulang** sisa lot sebagai bukti. Tanpa bukti, selisih tetap varians pemakaian.
- Sumbernya bisa **supplier (PO)**, **subkon** (pola `reportShortage()` yang sudah ada), atau **customer** (bahan dari customer untuk CO itu: customer diminta mengirim kekurangannya).

**Tindak lanjut setelah petugas berwenang mengonfirmasi**
1. **Permintaan Kirim Kekurangan** dibuat: qty kekurangan, pemasok, WO yang menunggu, tanggal butuh (mendesak bila WO sedang jalan), bukti hitung ulang.
2. **Qty terima dikoreksi turun** pada penerimaan asal dan jurnal GR/IR dikoreksi (pola `reportShortage()` / `PV_SHORT` yang sudah ada untuk subkon); stok diperbaiki lewat dokumen yang merujuk penerimaan itu.
3. **Baris PO dibuka kembali** untuk kekurangannya (backorder), atau dibuat permintaan pembelian tambahan bila PO sudah tertutup, dengan rujukan ke penerimaan asal.
4. **Pemberitahuan popup** ke Pembelian dan PPIC; booking menandai kebutuhan itu sebagai kurang dan mendesak.
5. Bila pemasok menolak: dicatat sebagai klaim (debit note atau potongan tagihan); keputusannya di O-15.
6. Hasil masuk **laporan kurang kirim per pemasok** dan menjadi bahan skor mutu pemasok (Q-10).

### 7.7 Kebutuhan

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| Q-01 | Rencana sampling: tabel ukuran sampel, intensitas (Tightened / Normal / Reduced / Skip), kelas risiko pada item dan supplier | BARU | P1 |
| Q-02 | Status batch: Quarantine, Conditional, Released, Rejected (I-05) | BARU | P1 |
| Q-03 | Tanda **URGENT** pada baris penerimaan (pemohon, alasan, WO / CO tujuan) dan perubahan `PvPostingService::blockers()` | BARU | P1 |
| Q-04 | **Dock-to-line**: lokasi tujuan area WIP / mesin tanpa putaway; booking dan pick mengenali stok Conditional | BARU | P1 |
| Q-05 | Hasil sampling di dok dan **first article di mesin** memperbarui status batch | BARU | P1 |
| Q-06 | Tenggat keputusan dan eskalasi (pengaturan) | BARU | P1 |
| Q-07 | Usage Decision susulan | BARU | P1 |
| Q-08 | Telusur maju saat Rejected dan penahanan WO / FG | BARU | P1 |
| Q-09 | Dokumen klaim ke supplier | BARU | P2 |
| Q-10 | Perpindahan intensitas otomatis menurut riwayat supplier, termasuk kejadian kurang kirim | BARU | P2 |
| Q-11 | Persetujuan untuk URGENT bahan FSC / food grade | BARU | P2 |
| Q-12 | Laporan: Conditional terbuka, umur keputusan, pemakaian URGENT, tingkat lulus per supplier (widget QC yang ada dilanjutkan) | SEBAGIAN | P2 |
| Q-13 | **Deteksi kurang kirim** saat rekonsiliasi WO: (output + waste standar) dibanding qty yang dikeluarkan; toleransi mengikuti toleransi PO. `v_inv_wo_reconcile` hanya memberi sisi issue; output diambil dari produksi | BARU | P1 |
| Q-14 | **Permintaan Kirim Kekurangan** ke supplier, subkon, atau customer, dengan bukti hitung ulang dan tanggal butuh mendesak | BARU | P1 |
| Q-15 | Koreksi qty terima dan jurnal GR/IR, serta buka kembali baris PO (backorder) | BARU (pola `reportShortage` ada untuk subkon) | P1 |
| Q-16 | Laporan kurang kirim per pemasok | BARU | P2 |

### 7.8 Rujukan
- **SAP**: stok dalam inspeksi mutu dan *usage decision*; *dynamic modification rule* mengurangi inspeksi bagi supplier yang konsisten baik ([SAP Insider](https://sapinsider.org/dmr-leads-to-more-efficient-procurement-and-production-processes/)).
- **Dynamics 365**: quality association berisi uji, AQL, dan sampling plan ([Microsoft Learn](https://learn.microsoft.com/en-us/dynamics365/supply-chain/inventory/quality-associations)).
- **Odoo**: cek mutu pada penerimaan dapat menahan validasi. Jalur URGENT sengaja melewatinya.
- **Praktik umum**: sampling ANSI/ASQ Z1.4; disposisi gunakan apa adanya dengan deviasi, kembali ke supplier, atau scrap.
- Status **Conditional**, jalur **dock-to-line**, dan **deteksi kurang kirim dari rekonsiliasi WO** adalah adaptasi kita, bukan fitur baku produk-produk itu.

---

## 8. Valuasi dan akuntansi (D8, D13)

### 8.1 Keputusan
**Metode nilai = AVERAGE (rata-rata bergerak, *moving average*).** Kode sekarang **sudah** memakainya (`StockCardChain`, kolom `avg_unitprice`,
`value_eb`); komentar kode menyatakan FIFO/LIFO (`inv_stock_layer`) belum diimplementasikan. LIFO tidak dipakai untuk pembukuan (dilarang PSAK 14 / IAS 2).
FIFO ditunda ke "Nanti".

### 8.2 Rumus
```
Saat PENERIMAAN:
   Avg baru = (Qty saldo x Avg lama + Qty masuk x Harga masuk) / (Qty saldo + Qty masuk)

Saat PENGELUARAN:
   Nilai keluar = Qty keluar x Avg saat itu          (Avg TIDAK berubah)
   Nilai saldo  = Qty sisa x Avg

Bila Qty saldo menjadi 0:  Nilai saldo dipaksa 0 (sisa pembulatan masuk ke baris terakhir)
```

| Kejadian | Perlakuan |
|---|---|
| Adjustment plus (opname lebih) | Masuk pada Avg saat ini; Avg tidak berubah |
| Adjustment minus | Keluar pada Avg saat ini |
| Transfer antar lokasi | Membawa Avg asal; nilai total tidak berubah |
| Landed cost (ongkos kirim, bea) | Menambah **nilai tanpa qty**: Avg baru = (Nilai saldo + Biaya) / Qty saldo, hanya untuk qty yang masih ada; bagian yang sudah keluar masuk varians (lihat O-13) |
| Retur customer | Masuk pada **nilai keluar aslinya**, lalu ikut dirata-rata |
| Retur ke supplier | Keluar pada **nilai penerimaan aslinya** (Avg bisa bergeser) |
| Koreksi kurang kirim (7.6) | Qty terima turun pada penerimaan asal; nilai ikut turun sebesar qty kurang x harga penerimaan; Avg dihitung ulang |
| Stok milik Customer / Supplier | **Tidak dinilai**, tidak ikut Avg (D3) |
| Transaksi bertanggal mundur | Disisipkan di tempatnya; rantai sesudahnya **dihitung ulang** (sudah ada di kode) |
| Presisi | Harga 5 desimal, nilai 2 desimal |

### 8.3 Contoh: harga bahan naik (kertas art carton, kg)

| # | Kejadian | Qty | Harga / Nilai kejadian | Saldo qty | Nilai saldo | Avg per kg |
|---|---|---|---|---|---|---|
| 1 | Saldo awal | +1.000 | 15.000 = 15.000.000 | 1.000 | 15.000.000 | **15.000** |
| 2 | Terima (harga naik 10%) | +500 | 16.500 = 8.250.000 | 1.500 | 23.250.000 | **15.500** |
| 3 | Issue ke produksi | -800 | 15.500 = **12.400.000** | 700 | 10.850.000 | 15.500 |
| 4 | Terima (naik lagi ke 18.000) | +600 | 18.000 = 10.800.000 | 1.300 | 21.650.000 | **16.653,85** |
| 5 | Issue ke produksi | -1.000 | 16.653,85 = **16.653.846** | 300 | 4.996.154 | 16.653,85 |

- Langkah 2: (15.000.000 + 8.250.000) / 1.500 = **15.500**.
- Langkah 4: (10.850.000 + 10.800.000) / 1.300 = **16.653,85**.
- Pemeriksaan: nilai keluar 12.400.000 + 16.653.846 + saldo akhir 4.996.154 = **34.050.000** = saldo awal 15.000.000 + pembelian 8.250.000 + 10.800.000.

**Artinya bagi Anda:** harga terbaru 18.000, tetapi issue langkah 5 dihitung **16.654**. Avg selalu **tertinggal di belakang harga pasar** saat harga naik
(dan menahan harga saat turun). Dampaknya pada quotation: costing yang dikutip memakai harga bahan terbaru (ProCost, beku, tidak diubah); biaya aktual dari stok
memakai Avg. Selisihnya tercatat sebagai varians. Contoh: dikutip pada 18.000 per kg, aktual 16.654, selisih menguntungkan 1.346 per kg; bila harga turun, selisih berbalik.

**Pembanding FIFO** (bukan yang dipilih, hanya untuk memahami selisihnya), pergerakan sama:

| | Avg (dipilih) | FIFO |
|---|---|---|
| Issue langkah 3 (800 kg) | 12.400.000 | 12.000.000 |
| Issue langkah 5 (1.000 kg) | 16.653.846 | 16.650.000 (200x15.000 + 500x16.500 + 300x18.000) |
| Nilai saldo akhir (300 kg) | 4.996.154 | 5.400.000 (300x18.000) |
| Total | 34.050.000 | 34.050.000 |

Total sama; yang berbeda hanya **pembagian** antara HPP dan saldo. Saat harga naik, FIFO mencatat HPP lebih rendah dan saldo lebih tinggi.

### 8.4 Contoh: penerimaan terlambat dicatat (bertanggal mundur)
Misal penerimaan langkah 4 (600 kg @18.000) baru dicatat setelah issue langkah 3, tetapi tanggal terimanya **sebelum** issue itu. Sistem menyisipkannya di tempat yang benar dan menghitung ulang:

- Sebelum issue langkah 3: saldo 1.500 kg (23.250.000) + 600 kg (10.800.000) = 2.100 kg, 34.050.000, Avg **16.214,29**.
- Issue 800 kg menjadi 800 x 16.214,29 = **12.971.429** (sebelumnya 12.400.000, selisih +571.429).
- Semua baris sesudahnya ikut dihitung ulang. **Kode sekarang menampilkan peringatan** bahwa jurnal yang sudah diposting untuk baris keluar itu **tidak otomatis dikoreksi** (celah O-14).

### 8.5 Tingkat rata-rata: per item (D15)

> **Keputusan user 2026-10-08 (O-11): Average per item.**

**Sekarang:** `StockCardChain` menghitung Avg per posisi (satu item di satu bin). Transfer membawa Avg bin asal lalu mencampurnya dengan Avg bin tujuan,
sehingga satu item punya beberapa Avg dan **biaya keluar bergantung pada bin yang diambil**.

**Rancangan 2.01**
1. **Qty tetap per bin** (rantai qty per posisi tidak berubah).
2. **Nilai dan Avg dihitung pada satu rantai per item**, urut `(doc_date, doc_id)` lintas semua bin dan gudang milik perusahaan.
3. **Biaya keluar = Avg item saat itu**, apa pun bin yang diambil.
4. **Transfer tidak mengubah Avg** dan tidak menimbulkan jurnal; baris keluar dan masuk memakai Avg item.
5. **Transaksi bertanggal mundur** disisipkan di rantai item; semua baris item itu sesudahnya (di semua bin) dihitung ulang. Penguncian baris per item.
6. Stok **pemilik Customer / Supplier** tidak ikut rantai nilai (D3).
7. Praktik umum: SAP per material per valuation area, Odoo per produk per perusahaan. Satu kesamaan: bin bukan unit penilaian.

**Migrasi saldo (tanpa jurnal)**
- Pada hari peralihan, Avg awal item = **(jumlah `value_eb` semua bin) / (jumlah `qty_eb` semua bin)**.
- Nilai total item **tidak berubah**, jadi tidak ada jurnal koreksi.
- Baris riwayat lama tidak dihitung ulang; rantai item baru dimulai dari saldo peralihan.
- Dummy `ddt*` diganti baru (aturan dummy basi).

**Dampak kode** (file yang membaca atau menulis `avg_unitprice` / rantai): `StockCardChain`, `StockPostingService`, `StockTransferService`, `StockLedger`, `WarehouseStock`,
model `RmStockcard` / `SfgStockcard` / `FgStockcard`, laporan Loss & Gain, data Sales, `MatRaw`, dan layanan lain yang membaca harga rata-rata.

**Risiko:** rantai per item lebih panjang (hitung ulang lebih banyak baris saat transaksi mundur) dan posting item yang sama menjadi **serial** karena kunci per item.
Dua hal itu dapat diterima untuk volume pabrik ini, tetapi perlu diuji.

### 8.6 Kebutuhan

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| V-01 | **Average** bergerak (D13). Rumus dan contoh di 8.2 - 8.4 masuk Help | **ADA** (per bin) | – |
| V-01b | Avg **per item** (satu angka perusahaan), bukan per bin (**D15**, rancangan di 8.5) | BARU (mengubah rantai kartu) | P1 |
| V-02 | Jurnal otomatis tiap pergerakan bernilai (terima, issue, hasil produksi, kirim, adjustment, scrap, transfer, retur) | SEBAGIAN (PV ada) | P1 |
| V-03 | **GR/IR** dan umur GR/IR | SEBAGIAN | P1 |
| V-04 | **Landed cost** dibagi menurut nilai, berat, atau volume | BARU | P1 |
| V-05 | Tanggal posting terpisah dari tanggal input; **kunci periode** | ADA untuk inventory (`inv_period`); kunci periode GL belum diperiksa | P1 |
| V-06 | **Rekonsiliasi harian** subledger stok dengan GL | BARU | P1 |
| V-07 | Jurnal koreksi bersusun untuk baris keluar yang Avg-nya berubah karena transaksi mundur, plus laporan Repost (saran O-14) | BARU | P1 |
| V-08 | **WIP**: nilai bahan + proses per tahap SFG (D7) | ? | P1 |
| V-09 | Nilai subkon: nilai kirim + jasa, susut vendor | SEBAGIAN | P1 |
| V-10 | Penyisihan persediaan usang (NRV) dengan tabel umur yang bisa diatur | BARU | P2 |
| V-11 | Varians: harga beli, pemakaian, substitusi, material | BARU | P2 |
| V-12 | Multi mata uang pada pembelian | ? | P2 |
| V-13 | Cut-off: tanggal posting mengikuti penerimaan fisik | `?` | P1 |
| V-14 | Stok pemilik Customer / Supplier tidak dinilai dan tidak dijurnal (bersama I-04) | BARU | P1 |
| V-15 | FIFO per kelompok item (lapisan `inv_stock_layer`) | BARU | Nanti |

Catatan: costing beku pada quotation tidak ditimpa. Inventory menyediakan harga dan qty **aktual** sebagai pembanding.

---

## 9. Alur gabungan (I = Inventory, W = Warehouse)

| Alur | Urutan |
|---|---|
| **Penerimaan PO normal** | W Receive (dok, hitung, label, ambil sampel) → uji cepat di dok → Released → I posting GRN → W Putaway |
| **Penerimaan URGENT** | W Receive → tandai URGENT (alasan, WO tujuan) → I posting GRN, batch Conditional → langsung ke area produksi (dock-to-line) → sampling paralel → Released / Rejected |
| **Kurang kirim terdeteksi** | Rekonsiliasi WO: (output + waste standar) > qty issue → hitung ulang sebagai bukti → I koreksi qty terima dan jurnal → Permintaan Kirim Kekurangan ke supplier / subkon / customer → baris PO dibuka kembali |
| **Issue produksi** | B booking keras → W Pick list → W scan ambil → I posting issue ke WO |
| **Kirim subkon** | I dokumen kirim → W siapkan dan scan keluar → lokasi virtual Vendor → W terima kembali → QC → I posting nilai + jasa; susut diakui bila dalam toleransi |
| **Pengiriman** | I reservasi FG → W pick, staging, verifikasi DO → I posting keluar dan HPP |
| **Retur customer** | W terima di dok → I dokumen retur → QC (layak pakai atau afkir) → W putaway atau karantina |
| **Titipan customer** | W terima → I stok pemilik Customer (tidak dinilai, tidak dijurnal) → W putaway; dipakai hanya oleh WO dari CO itu |
| **Opname** | I jadwal dan kunci → W hitung dengan scan → I tinjau selisih, persetujuan, jurnal |
| **Transfer** | I dokumen → W kirim (In Transit) → W terima → I tutup; selisih Discrepancy diselesaikan |

---

## 10. Skema yang diusulkan (ringkas, belum final)

Aturan: PK `id_<entitas>` BIGINT auto number; nama English; **tanpa FK lintas database**; status siklus `stat_*`; master berubah hanya oleh user.

| Entitas | DB | Isi inti | Terkait |
|---|---|---|---|
| `inv_batch` | tm2inv | batch_no, item, lot supplier, tgl produksi, kedaluwarsa, retest, qc_status, sertifikasi, arah serat, risk_class | I-03, I-05 |
| kolom `owner_type`, `owner_id` pada saldo dan kartu stok | tm2inv | Company / Customer / Supplier | I-04, V-14 |
| kolom `batch_id`, `qc_status` pada kartu stok dan lapisan | tm2inv | menggantikan `lot_no` teks (lot lama tetap dipetakan) | I-03 |
| `inv_qc_decision` | tm2inv | batch, keputusan, oleh, kapan, alasan, deviasi | Q-07, Q-11 |
| `inv_qc_sampling_plan` (+ aturan perpindahan) | tm2inv | ukuran sampel per jumlah lot, intensitas, ambang pindah | Q-01, Q-10 |
| kolom `is_urgent`, `urgent_by`, `urgent_reason`, `urgent_ref` pada baris penerimaan | tm2pur | tanda URGENT | Q-03 |
| `inv_shortage_request` (+ baris) | tm2inv / tm2pur | Permintaan Kirim Kekurangan: sumber (supplier, subkon, customer), qty, bukti hitung ulang, status, rujukan penerimaan dan PO | Q-13..Q-16 |
| `inv_qc_first_article` | tm2inv | WO, batch, hasil uji, oleh | Q-05 |
| `inv_subcon_issue` (+ baris) | tm2inv | dokumen kirim ke subkon, vendor, tenggat, qty, lokasi Vendor | I-23, I-24 |
| lokasi virtual (`wh_loc.location_type` = VENDOR, TRANSIT, LOSS) | tm2mst | lokasi tanpa tempat fisik | W-01 |
| `wh_task` (+ baris) | tm2inv | RECEIVE, PUTAWAY, PICK, MOVE, COUNT; status; operator; bukti scan | W-03..W-08, W-13 |
| `wh_pick_list` (+ baris) | tm2inv | dari booking (`booking_detail_id`), bin, batch, qty | W-05, B-12 |
| `rm_booking` tambahan | tm2inv | `chain_key` (inquiry_id), `stage` B0..B4, `hold_until` | B-02, B-03 |
| `inv_item_policy` | tm2inv | risk_class, issue_method, serat wajib, MRSL, dll. per item | Q-01, I-20 |
| `inv_tool` (+ `inv_tool_use`) | tm2inv | die, plat, klise: pemilik, lokasi, impresi, umur | W-14 |
| `inv_claim` | tm2inv | klaim ke supplier dari batch Rejected | Q-09 |
| tabel pengaturan | tm2sys | tenggat QC, hari booking basi, tabel provisi, batas dead stock | semua |

Semua angka batas adalah **pengaturan** yang bisa diubah user berwenang lewat layar (aturan 7.12 proyek).

---

## 11. Usulan struktur menu 2.01

**INVENTORY**
- Stock Documents (In, Out, Return, Adjustment per kelas RM / SFG / FG)
- Subcontract (Send, Receive, Stock at Vendor)
- Customer Material
- Batch & Quality (Batch, Conditional Stock, Usage Decision, First Article, Supplier Claim)
- Reservation (tautan ke Production > Planning)
- Valuation & Period (Valuation, GR/IR, Landed Cost, Reconciliation, Provision, Period Lock)
- Reports

**WAREHOUSE**
- Structure (Buildings, Areas, Bins, Location Types)
- Receiving & Putaway
- Pick List
- Transfer
- Count (Opname)
- Labels & Scan
- Map & Monitor
- Task Board
- Tools (Die, Plate, Block)

---

## 12. Fasing yang disarankan

| Fase | Isi | Alasan urutan |
|---|---|---|
| **F1 Fondasi** | I-03, I-04, I-05, I-42, **V-01b**, V-13, W-01, W-02, kolom pemilik dan batch | Semua fitur lain bergantung pada batch, pemilik, status, dan lokasi bertipe |
| **F2 QC sampling, URGENT, kurang kirim** | Q-01..Q-08, Q-13..Q-15, I-10, W-03, W-04, V-03, V-04 | Menjawab keterdesakan waktu (D4) dan kurang kirim (D14) |
| **F3 Booking sejak costing** | B-01, B-02, B-04, B-05, B-08, B-11, B-12, B-14, W-05, I-54 | Menjawab D5 dan D9 |
| **F4 Subkon, titipan customer, opname** | I-13, I-14, I-23, I-24, I-40, W-08, V-09 | Alur khas percetakan |
| **F5 Valuasi lengkap** | V-02, V-06, V-07, V-08, V-10, V-11 | Rekonsiliasi dan nilai; sebagian berjalan paralel sejak F1 |
| **F6 Penyempurnaan** | B-03, B-06, B-07, B-09, B-10, W-12..W-14, I-51..I-56, Q-09..Q-12 | Meningkatkan kualitas |
| **Nanti** | W-09, W-15, I-32, I-41, Q-10 | Dampak kecil di short run |

Setiap fase: layar baru berupa objek DevLib (`x-dl::*`), Help topik layar, `php artisan build:catalog --env=mockup`, uji,
dummy baru (aturan: dummy basi diganti), dan **commit + push per potongan kerja**.

---

## 13. Hal terbuka dan saran

**Sudah diputuskan user:** O-1 (booking dari costing), O-2 (QC sampling, URGENT, kurang kirim), O-3 (titipan = stok pemilik Customer), O-4 (Average),
**O-11 (Average per item)**, **O-12 (pengaman URGENT sesuai saran)**.

**Menunggu persetujuan user (saran saya di kolom tengah; semuanya usulan):**

| # | Hal | Saran | Rujukan / dasar |
|---|---|---|---|
| O-13 | **Landed cost datang setelah sebagian stok keluar** | Tambahkan ke **qty yang masih ada** (Avg naik). Bagian yang **sudah keluar** masuk akun **Selisih Landed Cost**, ditutup ke HPP di akhir periode. Tidak menulis ulang HPP yang sudah dijurnal atau periode yang sudah tertutup. | Odoo AVCO: landed cost terlambat merevisi rata-rata untuk sisa stok, **tidak** untuk unit yang sudah keluar ([Cleverence](https://www.cleverence.com/articles/for-business/landed-cost-odoo-4927/)). SAP (MAP): selisih harga ke akun stok bila stok cukup, ke **akun selisih harga** bila stok tidak cukup ([SAP Blogs](https://blogs.sap.com/2021/12/09/sap-mm-change-of-material-moving-average-price-after-goods-receipt-and-invoice-verification-posting-for-po/)) |
| O-14 | **Jurnal koreksi saat transaksi mundur mengubah Avg** | Repost otomatis: satu **jurnal koreksi bersusun** per proses hitung ulang (selisih nilai baris keluar yang berubah), **tidak mengubah jurnal lama**. Bertanggal hari repost bila periode asal tertutup, bertanggal asli bila masih terbuka. Laporan "Repost" menampilkan baris, selisih, dan jurnalnya. | ERPNext Repost Item Valuation menghitung ulang ledger dan **memposting ulang GL** bila perlu, dan di bawah moving average mengubah valuation rate dan HPP ([ERPNext](https://docs.frappe.io/erpnext/repost-item-valuation)). Kode kita sudah menghitung ulang rantai tetapi baru memberi **peringatan** |
| O-15 | **Kurang kirim: toleransi, konfirmasi, bila pemasok menolak** | **Toleransi:** pakai toleransi penerimaan PO yang **sudah ada** (`is_tol_exceeded` pada baris penerimaan). **Konfirmasi:** Kepala Gudang menghitung ulang, PPIC mengonfirmasi, Pembelian menindaklanjuti. **Jendela:** pemberitahuan ke pemasok segera; pemasok diberi N hari (usul 7) untuk mengirim kekurangan. **Bila menolak atau lewat batas:** tutup baris PO pada qty yang diterima dan minta **debit note / potong tagihan**. | Praktik ERP untuk short shipment: turunkan baris PO ke qty yang diterima dan tutup (*delivery complete*) agar pencocokan tagihan benar; kirim pemberitahuan secepatnya, idealnya 24 sampai 48 jam ([Racklify](https://racklify.com/encyclopedia/short-shipment-claims-liability-documentation-and-recovery/)). Kita menahan baris PO terbuka dulu karena yang diminta adalah **dikirim**, bukan dikreditkan |
| O-5 | **Nilai awal pengaturan** | Lihat tabel di bawah. Semuanya pengaturan yang bisa diubah user. | Dasar di tabel |
| O-6 | **Kelas risiko awal** | **A:** bahan kemasan makanan (food grade), klaim FSC / PEFC, bahan berbahaya (B3), dan **5 lot pertama supplier baru**. **B:** kertas, board, film, corrugated, lem dari supplier dikenal. **C:** bahan penolong (fountain solution, alkohol, powder, kain lap, strapping, pembungkus). Daftar per kategori material diisi kepala QC, lalu disalin ke master. | Pola AQL / intensitas sampling (7.3) |
| O-7 | **Dokumen kirim subkon** | **Pakai PO Subkon yang sudah ada sebagai induk.** Dokumen kirim bahan merujuk baris PO Subkon, dan penerimaan SUBCON yang sudah ada merujuk baris yang sama. Satu rantai, tanpa dokumen ganda. | `SpecialReceiptService::openSubconLines` sudah membaca PO bertipe SUBCONTRACT |

**Nilai awal pengaturan (O-5), semua usulan:**

| Pengaturan | Nilai awal | Dasar |
|---|---|---|
| Tenggat keputusan stok Conditional | Eskalasi 4 jam, tenggat 8 jam kerja (satu shift) | Pekerjaan mendesak |
| Booking basi: peringatan | 7 hari setelah tanggal butuh lewat tanpa WO | Umum |
| Booking basi: dilepas otomatis | 30 hari | Standar SAP: reservasi lama ditandai dihapus setelah 30 hari ([SAP](https://help.sap.com/saphelp_46C/helpdata/EN/a5/63374743a211d189410000e829fbbd/content.htm)) |
| Hold booking habis otomatis | 3 hari kerja | Sama dengan `BOOKING_NEED_WORKDAYS` yang sudah ada |
| Batas aging stok | Slow 90 hari, non-moving 180 hari, dead 365 hari | Umum |
| Provisi persediaan usang | 0% sampai 180 hari, 25% sampai 365, 50% sampai 730, 100% di atasnya | Umum (NRV) |
| Tabel sampel | ANSI/ASQ Z1.4 Level II normal, ketat, ringan; AQL awal 1,0% untuk kelas A dan 2,5% untuk kelas B | Standar sampling |
| Toleransi kurang kirim | Mengikuti toleransi PO | Sudah ada di penerimaan |
| Jendela kirim kekurangan | 7 hari kalender | Usulan |

**Hasil pemeriksaan kode (menutup O-8 dan O-9):**

| # | Temuan |
|---|---|
| O-8 | **Data costing cukup untuk pre-booking** dengan satu syarat. `iqp_costing_part` memuat ukuran plano, cut per plano, isi per lembar kerja (ups), gramatur, kode sheet pemasok, jenis flute; kunci `published_flag` menandai ProCost terbit. Tetapi sheet **belum menunjuk `mat_raw.material_id`**, jadi item dipilih PPIC (mekanisme Pick Item / `NEED_ITEM` yang sudah ada). Tinta, varnish, lem tetap dari standar pemakaian. Tabel yang sama memuat **harga** (Sisi A rahasia): booking hanya boleh membaca kolom qty dan ukuran. |
| O-9 | **`inv_period`**: ada, bulanan (OPEN / CLOSING / CLOSED); dokumen bertanggal di periode tertutup ditolak, koreksi lewat Stock Adjustment. **Transfer**: **satu langkah**, stok pindah seketika, **tidak ada In Transit**; persetujuan opsional (modul flow `TF`, nonaktif sekarang); transfer tanpa jurnal. **Opname**: layar Adjustment & Count ada, **tidak ada kunci bin**. **`v_inv_wo_reconcile`**: hanya sisi RM keluar-masuk per WO dan bahan (issued, return good, return scrap, net used); **tidak memuat output atau waste**, jadi deteksi kurang kirim (Q-13) butuh data output produksi. |
| O-10 | Migrasi `lot_no` teks ke master batch: batch lama dibuat otomatis dari nilai `lot_no` yang ada (satu batch per item + lot), tanpa kedaluwarsa; nilai kosong menjadi batch "NO-LOT". Usulan. |

---

## 14. Riwayat dokumen

| Tanggal | Perubahan |
|---|---|
| 2026-10-08 | Rev.3: O-11 diputuskan per item (D15, 8.5 ditulis ulang dengan rancangan, migrasi tanpa jurnal, dampak kode, risiko); O-12 sesuai saran (D16); saran berikut rujukan untuk O-13, O-14, O-15 dan nilai awal O-5, O-6, O-7; pemeriksaan kode menutup O-8 dan O-9 (transfer satu langkah, opname tanpa kunci, `inv_period` ada, `v_inv_wo_reconcile` tanpa output); V-01b dipindah ke Fase 1 |
| 2026-10-08 | Rev.2: O-1..O-4 dijawab. QC = sampling + URGENT langsung ke produksi (bagian 7 ditulis ulang, kelas risiko jadi intensitas sampling) dan **kurang kirim**: bila output + waste masih kurang, bahan kurang sejak awal dan kekurangannya diminta dikirim (D14, 7.6, Q-13..Q-16); titipan customer = stok pemilik Customer; Average dengan rumus dan contoh kenaikan harga (bagian 8); temuan kode: Average sudah ada tetapi per bin (O-11), FIFO belum jalan, jurnal koreksi transaksi mundur belum ada (O-14) |
| 2026-10-08 | Draft pertama v2.01: keputusan D1-D9 (user menyetujui saran 1, 4 dan 5 dengan penyesuaian, nilai valuasi masuk), cek kode dan skema, daftar kebutuhan bernomor, wayout QC, booking sejak costing, skema, fasing, hal terbuka |
