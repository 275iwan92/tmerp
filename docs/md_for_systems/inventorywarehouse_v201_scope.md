# Inventory & Warehouse v2.01 — Dokumen Cakupan (Scope)

> **Status: DRAFT 2026-10-08, menunggu persetujuan user.** Belum ada kode. Versi: 2.01 (perbaikan modul Inventory dan Warehouse).
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
| D3 | **Material titipan customer**: atribut **pemilik** pada stok, bukan gudang terpisah. Lihat catatan O-3 (bertentangan dengan perilaku sekarang). | saran, tidak ada keberatan |
| D4 | **QC berjalan sambil proses produksi**, karena keterdesakan waktu. Penerimaan tidak boleh tertahan menunggu QC selesai. Usulan jalan keluar di bagian 7. | user 2026-10-08 |
| D5 | **Booking dimulai sejak product costing sudah menghitung** (bukan menunggu CO), karena proses cepat dan metode **short run**. Usulan rancangan di bagian 6. | user 2026-10-08 |
| D6 | **Lokasi**: RM dan FG sampai tingkat **bin**; WIP lantai produksi sampai tingkat **area**. | saran |
| D7 | **SFG dilacak per tahap proses** (cetak, laminasi, die cut, dst.). | saran |
| D8 | **Cakupan 2.01 mencakup VALUASI dan jurnal otomatis**, bukan hanya operasional. | user 2026-10-08 |
| D9 | Booking tetap di **Production > Planning**; Inventory hanya membaca Reserved dan Available; **pick list di Warehouse**. | user 2026-10-08, setuju saran 5 |

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
| **QC menahan posting** | `PvPostingService::blockers` | Bertentangan dengan D4: barang yang sudah datang tidak bisa jadi stok sampai QC selesai |
| **Tidak ada pemilik pada stok** | Tidak ada kolom pemilik di tabel stok; titipan customer sengaja di luar stok | Titipan tidak punya lokasi, tidak bisa di-pick atau dialokasikan secara fisik |
| **Tidak terlihat sisi kirim subkon** | Hanya penerimaan SUBCON yang ditemukan; tidak ditemukan dokumen kirim bahan ke vendor, lokasi vendor, atau laporan stok di vendor | Bahan di vendor tidak terlacak |
| **Booking mulai dari CO** | `demands()` membaca `co_order` | Bertentangan dengan D5 |
| **FG belum punya sumber Available terpusat** | Komentar kode `MaterialPlanningEngine`: "FG belum punya view booking" | Angka Available FG bisa berbeda antar layar |
| **Tidak terlihat pick list, putaway, dok terima** | Tidak ada tabel atau layanan bernama itu | Gudang belum punya tugas terstruktur |
| **Status In Transit** | `InvTransfer` ada; belum diperiksa apakah dua langkah | `?` |
| **Opname sebagai tugas** | Ada layar adjustment & count; belum diperiksa pengunciannya | `?` |
| **Metode nilai** | Ada lapisan FIFO/LIFO dan kolom nilai; metode rata-rata belum diverifikasi | `?` Lihat V-01 |
| **Penguncian periode** | `inv_period` ada; cakupannya belum diperiksa | `?` |

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
| I-05 | **Status stok per batch**: Quarantine, Conditional, Released, Rejected, Blocked | BARU | P1 |
| I-06 | Satuan ganda (lembar dan kg) dengan konversi dari spesifikasi dan berat timbang aktual | ? | P1 |
| I-07 | Roll sisa dengan ID dan berat sisa; kebijakan offcut | BARU | P2 |
| I-08 | SFG per tahap proses (D7) dan perubahan satuan lembar menjadi pcs di die cut | ? | P1 |
| I-09 | Stok FG terikat Customer Order; over-run / under-run; bill and hold | ? | P2 |

### 4.2 Sumber masuk

| Kode | Sumber | Perilaku yang diinginkan | Status | Prio |
|---|---|---|---|---|
| I-10 | Supplier (GRN dari PO) | Posting **tidak menunggu QC selesai** (lihat bagian 7); nilai = harga PO + landed cost | SEBAGIAN (terblokir QC) | P1 |
| I-11 | Produksi (SFG/FG) | Hasil per tahap, dengan yield dan reject | ? | P1 |
| I-12 | **Retur customer** | Layak pakai atau afkir (sudah ada); tautan ke pengiriman asli dan nilai asli | SEBAGIAN | P1 |
| I-13 | **Material titipan customer** | Masuk sebagai stok pemilik Customer, **tidak dinilai, tidak dijurnal**, hanya bisa dialokasikan ke CO customer itu; memiliki lokasi | BARU (sekarang non-stok) | P1 |
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
| I-31 | Transfer **dua langkah** lewat In Transit; selisih terima berstatus Discrepancy | `?` | P1 |
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
| W-08 | Hitung opname dengan scan; bin terkunci selama dihitung | BARU | P1 |
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

## 7. QC sambil proses berjalan: usulan jalan keluar (D4)

### 7.1 Masalah
Sekarang posting stok **diblokir** sampai QC qty dan mutu selesai (`PvPostingService::blockers`). Barang sudah di dok, produksi menunggu,
sedangkan QC masih berjalan. Pilihannya bukan "QC atau tidak QC", melainkan **"siapa menunggu siapa"**.

### 7.2 Prinsip
1. **Pisahkan "Diterima dan diposting" dari "Dirilis".** Barang boleh masuk stok dan nilai jurnalnya jalan; yang berbeda adalah **status mutu** batch.
2. **Kendali berdasarkan risiko**, bukan satu aturan untuk semua. QC ketat hanya di tempat yang risikonya besar.
3. **Tidak ada pemakaian tanpa jejak.** Setiap pemakaian bahan yang belum dirilis tercatat per batch, supaya bila QC gagal, pekerjaan terdampak langsung terlihat.
4. **QC punya tenggat.** Keputusan yang terlambat memicu eskalasi, bukan menunggu diam-diam.

### 7.3 Tiga kelas risiko (pada item, dapat ditimpa per supplier dan per job)

| Kelas | Isi | Perlakuan saat barang tiba |
|---|---|---|
| **A kritis** | Bahan food grade, klaim FSC, job kritis warna, supplier baru, bahan berbahaya | Status **Quarantine**; tidak bisa dipakai. **Jalur cepat**: QC di dok dengan uji singkat (gsm, caliper, kelembapan, uji warna tinta), target keputusan dalam hitungan menit (tenggat dapat diatur). |
| **B standar** | Kertas, board, film, lem dari supplier yang sudah dikenal | Status **Conditional**: masuk stok, dihitung Available **dengan tanda**, boleh dipakai job kelas B dan C. QC jalan paralel (sampel di dok atau di mesin pada lembar pertama). |
| **C ringan** | Barang habis pakai, bahan penolong bernilai kecil | Langsung **Released**; cukup sertifikat supplier (COA). |

### 7.4 Aturan inti
1. **Conditional dihitung Available, tetapi bertanda.** Booking dan pick list boleh mengalokasikannya hanya untuk job yang kelasnya mengizinkan.
2. **Job kelas A hanya memakai batch Released.**
3. **QC di mesin (first article)**: hasil uji lembar pertama pada WO dicatat dan **memperbarui status batch** (Released atau Rejected). Itu titik inspeksi sah, bukan tambahan beban.
4. **Tenggat keputusan** (pengaturan): bila Conditional belum diputuskan dalam N jam, atau job selesai sebelum keputusan, wajib **Usage Decision** susulan; eskalasi ke kepala QC dan Ops Manager.
5. **Supplier tepercaya** (konsep skip lot SAP DMR): bila riwayat mutu supplier baik, frekuensi uji dikurangi; bila ada masalah, kembali ke uji penuh. Rencana sampel mengikuti AQL (misalnya ANSI/ASQ Z1.4).
6. **Bila Rejected setelah dipakai**: sistem menelusuri maju (batch → issue → WO → batch FG → pengiriman), menandai WO dan FG terdampak (ditahan), membuat dokumen **klaim ke supplier**, dan mencatat kerugian ke supplier atau scrap.
7. **Nilai tidak berubah oleh status QC.** Jurnal penerimaan tetap; Reject memicu retur atau klaim, bukan koreksi diam-diam.
8. **Pengecualian dengan persetujuan**: kepala QC atau Ops Manager boleh melepas batch kelas A secara bersyarat (**use under deviation**), tercatat siapa, alasan, dan batas jumlah.

### 7.5 Alur bahan kelas B (kasus terbanyak)
```
Tiba di dok -> W Receive (hitung, timbang, label, ambil sampel)
  -> I posting GRN, status batch = Conditional, jurnal GR/IR berjalan
  -> W Putaway ke bin; booking / pick boleh alokasikan (job kelas B/C)
  -> QC paralel: sampel di dok ATAU first article di mesin
        lulus   -> Released
        gagal   -> Rejected -> telusur maju -> tahan WO/FG terdampak -> klaim supplier
        lewat tenggat -> eskalasi
```

### 7.6 Kebutuhan

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| Q-01 | Kelas risiko A / B / C pada item, supplier, dan job | BARU | P1 |
| Q-02 | Status batch: Quarantine, Conditional, Released, Rejected (I-05) | BARU | P1 |
| Q-03 | Ubah aturan posting penerimaan: blokir hanya untuk kelas A; kelas B dan C boleh diposting | BARU (mengubah `PvPostingService::blockers`) | P1 |
| Q-04 | Alokasi booking dan pick mempertimbangkan kelas job dan status batch | BARU | P1 |
| Q-05 | Catatan hasil first article di WO yang memperbarui status batch | BARU | P1 |
| Q-06 | Tenggat keputusan dan eskalasi (pengaturan) | BARU | P1 |
| Q-07 | Usage Decision susulan | BARU | P1 |
| Q-08 | Telusur maju saat Rejected dan penahanan WO/FG | BARU | P1 |
| Q-09 | Dokumen klaim ke supplier | BARU | P2 |
| Q-10 | Skor mutu supplier dan frekuensi uji dinamis | BARU | P3 |
| Q-11 | Pelepasan bersyarat (deviation) dengan persetujuan | BARU | P2 |
| Q-12 | Laporan: Conditional terbuka, umur keputusan, tingkat lulus per supplier (widget QC yang ada dilanjutkan) | SEBAGIAN | P2 |

### 7.7 Rujukan
- **SAP**: stok dalam inspeksi mutu dan *usage decision*; *dynamic modification rule* mengurangi inspeksi bagi supplier yang konsisten baik ([SAP Insider](https://sapinsider.org/dmr-leads-to-more-efficient-procurement-and-production-processes/)).
- **Dynamics 365**: quality association berisi uji, AQL dan sampling plan; pesanan karantina ([Microsoft Learn](https://learn.microsoft.com/en-us/dynamics365/supply-chain/inventory/quality-associations)).
- **Odoo**: cek mutu pada penerimaan dapat menahan validasi, atau alur tiga langkah lewat area QC. Di sini sengaja **tidak** menahan karena keterdesakan waktu.
- **Praktik umum**: bahan di area karantina bertanda hold; disposisi gunakan apa adanya dengan deviasi, kembali ke supplier, atau scrap; sampling ANSI/ASQ Z1.4.
- Pola "Conditional" adalah **adaptasi kita**, tidak ada sebagai fitur baku di produk-produk itu.

---

## 8. Valuasi dan akuntansi (D8)

| Kode | Kebutuhan | Status | Prio |
|---|---|---|---|
| V-01 | **Metode nilai default: rata-rata tertimbang perpetual**; lapisan FIFO yang sudah ada tetap tersedia per kelompok item. **LIFO tidak dipakai untuk pembukuan** (dilarang PSAK 14 / IAS 2). *Usulan, belum dikonfirmasi user.* | `?` | P1 |
| V-02 | Jurnal otomatis tiap pergerakan bernilai (terima, issue, hasil produksi, kirim, adjustment, scrap, transfer, retur) | SEBAGIAN (PV ada) | P1 |
| V-03 | **GR/IR** dan umur GR/IR | SEBAGIAN | P1 |
| V-04 | **Landed cost** (ongkos kirim, bea, asuransi) dibagi menurut nilai, berat, atau volume | BARU | P1 |
| V-05 | Tanggal posting terpisah dari tanggal input; **kunci periode** | `?` (`inv_period`) | P1 |
| V-06 | **Rekonsiliasi harian** subledger stok dengan GL | BARU | P1 |
| V-07 | Repost nilai untuk transaksi bertanggal mundur | BARU | P1 |
| V-08 | **WIP**: nilai bahan + proses per tahap SFG (D7) | ? | P1 |
| V-09 | Nilai subkon: nilai kirim + jasa, susut vendor | SEBAGIAN | P1 |
| V-10 | Penyisihan persediaan usang (NRV) dengan tabel umur yang bisa diatur | BARU | P2 |
| V-11 | Varians: harga beli, pemakaian, substitusi, material | BARU | P2 |
| V-12 | Multi mata uang pada pembelian | ? | P2 |
| V-13 | Cut-off: tanggal posting mengikuti penerimaan fisik | `?` | P1 |
| V-14 | Stok pemilik Customer / Supplier tidak dinilai dan tidak dijurnal | BARU (bersama I-04) | P1 |

Catatan: costing beku pada quotation tidak ditimpa. Inventory menyediakan harga dan qty **aktual** sebagai pembanding.

---

## 9. Alur gabungan (I = Inventory, W = Warehouse)

| Alur | Urutan |
|---|---|
| **Penerimaan PO** | W Receive (dok, hitung, label) → I posting GRN dengan status batch menurut kelas risiko (bagian 7) → W Putaway → QC paralel |
| **Issue produksi** | B booking keras → W Pick list → W scan ambil → I posting issue ke WO |
| **Kirim subkon** | I dokumen kirim → W siapkan dan scan keluar → lokasi virtual Vendor → W terima kembali → QC → I posting nilai + jasa; susut diakui bila dalam toleransi |
| **Pengiriman** | I reservasi FG → W pick, staging, verifikasi DO → I posting keluar dan HPP |
| **Retur customer** | W terima di dok → I dokumen retur → QC (layak pakai atau afkir) → W putaway atau karantina |
| **Titipan customer** | W terima → I stok pemilik Customer (tidak dinilai) → W putaway; dipakai hanya oleh WO dari CO itu |
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
| **F1 Fondasi** | I-03, I-04, I-05, I-42, V-05, V-13, W-01, W-02, kolom pemilik dan batch | Semua fitur lain bergantung pada batch, pemilik, status, dan lokasi bertipe |
| **F2 QC paralel dan penerimaan** | Q-01..Q-08, I-10, W-03, W-04, V-03, V-04 | Menjawab keterdesakan waktu (D4) |
| **F3 Booking sejak costing** | B-01, B-02, B-04, B-05, B-08, B-11, B-12, B-14, W-05, I-54 | Menjawab D5 dan D9 |
| **F4 Subkon, titipan customer, opname** | I-13, I-14, I-23, I-24, I-40, W-08, V-09 | Alur khas percetakan |
| **F5 Valuasi lengkap** | V-01, V-02, V-06, V-07, V-08, V-10, V-11 | Rekonsiliasi dan nilai; sebagian berjalan paralel sejak F1 |
| **F6 Penyempurnaan** | B-03, B-06, B-07, B-09, B-10, W-12..W-14, I-51..I-56, Q-09..Q-12 | Meningkatkan kualitas |
| **Nanti** | W-09, W-15, I-32, I-41, Q-10 | Dampak kecil di short run |

Setiap fase: layar baru berupa objek DevLib (`x-dl::*`), Help topik layar, `php artisan build:catalog --env=mockup`, uji,
dummy baru (aturan: dummy basi diganti), dan **commit + push per potongan kerja**.

---

## 13. Hal terbuka

| # | Hal | Pemilik | Catatan |
|---|---|---|---|
| O-1 | **Konfirmasi tafsir D5** (pre-booking dari costing terbit, tidak mengunci stok, naik ke B3 pada satu rantai) | user | Bagian 6 |
| O-2 | **Konfirmasi wayout QC** (kelas A / B / C, status Conditional, first article di mesin, tenggat, telusur maju) | user | Bagian 7. Mengubah aturan posting Procurement. |
| O-3 | D3 bertentangan dengan perilaku sekarang: penerimaan CUSTOMER sengaja **bukan stok**. Pilih: (a) tetap dokumen saja di 2.01, atau (b) jadikan stok pemilik Customer agar punya lokasi dan bisa di-pick | user | Saran saya (b), dengan syarat tidak dinilai dan tidak dijurnal |
| O-4 | Metode nilai default (V-01) | user | Usulan rata-rata tertimbang perpetual |
| O-5 | Batas pengaturan awal: tenggat QC (jam), booking basi (hari), tabel provisi, batas dead stock | user | Nilai awal usulan boleh diubah |
| O-6 | Kelas risiko tiap kelompok bahan dan supplier | user + QC | Perlu daftar awal |
| O-7 | Dokumen kirim subkon: apakah memakai PO Subkon yang sudah ada sebagai induk | user + Procurement | Menghindari dokumen ganda |
| O-8 | Apakah ProCost terbit sudah memuat daftar bahan lengkap dan layout (penting untuk B-01) | agen | Perlu diperiksa di `iqp_costing` dan layout |
| O-9 | Cakupan sebenarnya `inv_period`, `InvTransfer` (dua langkah?), pengunci opname, dan metode rata-rata | agen | Ditandai `?` di atas; perlu pembacaan kode penuh |
| O-10 | Data lama (`lot_no` teks) dipetakan ke master batch | agen | Rencana migrasi |

---

## 14. Riwayat dokumen

| Tanggal | Perubahan |
|---|---|
| 2026-10-08 | Draft pertama v2.01: keputusan D1-D9 (user menyetujui saran 1, 4 dan 5 dengan penyesuaian, nilai valuasi masuk), cek kode dan skema, daftar kebutuhan bernomor, wayout QC, booking sejak costing, skema, fasing, hal terbuka |
