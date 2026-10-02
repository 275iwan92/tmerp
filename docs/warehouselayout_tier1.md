# Tata Letak Gudang (Warehouse Layout) — Desain Tier 1

> **Status 2026-10-02: Tahap 1 DIBANGUN di mockup.test atas perintah user** ("ya saya tunggu warehouse mapping ...
> BUILD") — peta gudang, Stock Transfer, label QR / barcode, lacak, dead stock merah. Yang dibangun dan yang berbeda dari
> rancangan ini: **bagian 13**. Dokumen review user: `md_for_systems/warehouse_review.md`. Bagian 1–12 = rancangan asli.
>
> **Status awal: DESAIN, menunggu persetujuan user** (disusun 2026-10-01 atas instruksi
> user *"Tata letak gudang harus dikerjakan. Bantu designkan sistemnya, refer ke ERP
> terkenal"*). Belum ada kode. Mockup: `docs/mockups/warehouse_layout_v2.jpg`
> (desktop) dan `docs/mockups/warehouse_layout_mobile_v2.jpg` (HP staf gudang).
> Backlog: `backlog05_inventory_warehouse.md` bagian TATA LETAK GUDANG.
> Nama file mengikuti menu baru yang diusulkan **"Warehouse Layout"**
> (`doc_slug = warehouselayout`); baris `sys_app_menu`/`sys_doc_reference`
> dibuat saat desain disetujui.

---

## 1. Keadaan sekarang (dicek ke DB & kode 2026-10-01)

Temuan terpenting: **stok per lokasi SUDAH jalan** — yang belum ada adalah
pengelompokan (zona), peta, perpindahan antar-lokasi, dan tugas gudang.

| Hal | Fakta |
|---|---|
| `tm2mst.wh_warehouse` | 5 baris: WH-RAW-01 (RM), WH-FG-01 (FG), WH-MIX-01 (Transit & Buffer), WH-SCP-01 (Afval/WASTE), WH-TEST-01 (LEGACY test) |
| `tm2mst.wh_loc` | 7 baris, **sudah berbentuk bin**: `row_code`/`col_code`/`stack_code` (R01-C01-S01), `location_code` (WH-RAW-01-R01-C01-S01), `location_type`, `is_usable`, `capacity_kg`. Termasuk `UNKNOWN-LEGACY` (backfill tminv) |
| Kolom `location_id` + `lot_no` | ada di `rm/sfg/fg_stock_d`, `*_stockcard`, `fg_stockmst`, `inv_stock_layer` (lapis FIFO) |
| Posting | `StockPostingService` menghitung saldo **per item × lokasi** dan menolak saldo negatif per lokasi |
| Penerimaan PO | `PvPostingService` sudah punya putaway sederhana: kode lokasi yang diketik → `mat_raw.def_location_id` → lokasi cadangan |
| Data | `rm_stockcard` baru memakai 3 lokasi (1, 3, 8). `mat_raw` 11.401 item: hanya **4** punya `def_location_id`, hanya **1** punya `shelf_life_days` |
| Transfer antar lokasi | **tidak ada** (doc_cat yang ada: STOCK/BB/IN/OU/AD/OP) |
| Menu v2 Inventory | Label & QR Code (Pallet Label, Item Label, Riwayat) dan Scan Center (Scan Transaction, Track via QR) **NOT_BUILT**; "Warehouse & Location" (`v2:md-warehouse`) READY_FOR_QC |
| DevLib | pembuat QR sudah ada (`Qr.php`, `qrcode.js`); **pemindai kamera belum ada** |

Konsekuensi desain: **`wh_loc` dipertahankan sebagai "bin" (lapis paling bawah
tempat stok menempel)**. Usulan awal "tambah tabel `wh_bin` di bawah `wh_loc`"
DITOLAK — itu memaksa mengganti arti `location_id` di 10+ tabel stok. Yang
ditambah adalah lapis **Zona** di atasnya.

---

## 2. Riset: bagaimana ERP terkenal memodelkan gudang

| ERP | Struktur | Penempatan (putaway) | Pengambilan (picking) | Pemilik barang / pihak ketiga | Hitung stok |
|---|---|---|---|---|---|
| **Odoo 18** | Pohon lokasi rekursif (WH/Stock/Rak A/…), lokasi internal/virtual; "Storage Category" = atribut kapasitas (berat, jumlah produk, tipe paket) | Putaway Rule per produk / kategori / tipe paket → "Store to", sub-lokasi: *No / Last Used / Closest Location*; prioritas paket+produk → … → kategori | Removal strategy per kategori/lokasi: FIFO, LIFO, FEFO, Closest | Fitur *Consignment*: kolom **Owner** di stok; tidak masuk valuasi. Subkon = lokasi subcontracting | Cycle count per lokasi (*Inventory Frequency* hari) |
| **SAP EWM / WM** | Warehouse → **Storage Type** (area fungsi: rak tinggi, blok lantai, staging) → **Storage Section** (fast/slow, berat) → **Storage Bin**; Bin Type = ukuran | Strategi: *Fixed bin, Empty bin, Addition to existing stock, General storage area*; urutan pencarian storage type | Strategi pengeluaran FIFO / FEFO / LIFO / quantity-based; **Handling Unit** (pallet) | Stock type & owner/"party entitled"; stok pihak ketiga terpisah | Physical inventory per bin, cycle counting ABC |
| **Oracle WMS (EBS)** | Organization → **Subinventory** → **Locator** (baris.rak.tingkat) | **Rules Engine** (putaway rules: kapasitas, campur item, dll.) | Picking rules + *picking order* per locator (urutan jalan) | Consigned & VMI inventory | Cycle count per subinventory/ABC |
| **Dynamics 365 SCM** | Site → Warehouse → **Zone / Zone group** → **Location** dengan **Location Profile** (boleh campur item/batch, **Prevent multiple owners**, pakai license plate) | **Location Directives** (query → urutan lokasi), dijalankan oleh **Work Template** | Location directive untuk pick; *work* dikerjakan di HP (Warehouse Management mobile app); **License Plate** = pallet | Dimensi **Owner** (consignment) | Cycle count per lokasi/threshold |
| **ERPNext** | Pohon warehouse (grup/anak) | Putaway Rule per item × warehouse dengan **Capacity** + **Priority**; laporan Warehouse Capacity Summary | FIFO / Moving Avg | Customer Provided Item | Stock Reconciliation |
| **Accurate Online** | Multi gudang (tanpa lapis bin) | — (pilih gudang manual) | — | — | Stok opname per gudang |
| **Zahir** | Multi gudang (modul Multiwarehouse) | — | — | — | Stok opname; transfer gudang boleh tanpa jurnal |

**Pola yang sama di semua ERP besar:** (1) gudang → area fungsi → bin;
(2) sistem **menyarankan** bin, petugas **mengonfirmasi** (cocok prinsip proyek
"engine suggests, officer confirms"); (3) pallet punya identitas sendiri
(Handling Unit / License Plate / Package); (4) pemilik barang = atribut stok,
bukan gudang terpisah; (5) cycle count per lokasi menggantikan opname total.
Accurate & Zahir berhenti di "multi gudang + transfer" — terlalu dangkal untuk
kebutuhan "peta & posisi bin" yang diminta.

### Apa yang diambil untuk CV. Telasih Makmur

| Diambil | Dari | Alasan |
|---|---|---|
| Lapis Zona dengan tipe fungsi | SAP storage type, D365 zone | Receiving/QC/Staging/Shipping beda perlakuan |
| Mode zona RACK / FLOOR / AREA | SAP bin type & block storage | Kertas plano lazim ditumpuk per pallet di **lantai (lajur)**, bukan rak |
| Aturan putaway berprioritas + strategi *Fixed / Last used / Nearest empty / Add to existing* | Odoo + SAP + ERPNext | Cukup 1 tabel aturan, tanpa "rules engine" rumit ala Oracle |
| FIFO / FEFO per kategori bahan | Odoo removal strategy | Tinta, lem, coating punya kedaluwarsa |
| Pallet = License Plate ber-QR | D365 / SAP HU | Menu Label & QR sudah ada, tinggal diberi arti |
| Owner di baris stok + "1 pemilik per bin" | Odoo consignment + D365 location profile | Bahan titipan customer |
| Lokasi virtual per subkon | Odoo subcontracting location | SFG di tangan subkon tetap terlihat & dinilai |
| Cycle count per zona/bin + frekuensi | Odoo | Tanpa menghentikan pabrik |
| Tugas gudang (putaway/pick/move) dikerjakan di HP | D365 work + mobile app | Menu Scan Center sudah disiapkan |

**Tidak diambil (terlalu berat untuk skala CV):** wave picking, cross-docking,
slotting otomatis berbasis kecepatan gerak, replenishment otomatis antar zona,
work template bertingkat D365, rules engine Oracle.

---

## 3. Struktur gudang yang diusulkan

```
wh_warehouse          WH-RAW-01  Gudang Utama Bahan Baku        (sudah ada)
 └─ wh_zone           RCV  Receiving           (AREA)            BARU
 └─ wh_zone           QC   Karantina QC        (AREA)
 └─ wh_zone           PPR  Kertas Plano        (FLOOR, lajur×slot)
 └─ wh_zone           CHM  Tinta & Kimia       (RACK, FEFO)
 └─ wh_zone           STG  Staging Produksi    (AREA per mesin)
     └─ wh_loc        WH-RAW-01-PPR-L03-05    (bin = tempat stok, sudah ada, ditambah kolom)
```

### 3.1 Tipe zona (`wh_zone.zone_type`)

| Kode | Arti | Stok boleh dipakai produksi / dikirim? |
|---|---|---|
| `RECEIVING` | area bongkar, barang baru diterima belum ditata | tidak (harus di-putaway) |
| `QC_HOLD` | karantina menunggu QC (`mat_raw.qc_required`) | tidak |
| `STORAGE` | penyimpanan utama (rak / lantai) | ya |
| `STAGING` | area dekat mesin, bahan sudah dikeluarkan untuk WO / SFG antar proses | ya (khusus WO) |
| `SHIPPING` | FG siap kirim menunggu DO/Surat Jalan | ya (khusus DO) |
| `REJECT` | barang rusak/afval menunggu keputusan | tidak |
| `CONSIGN` | bahan titipan customer (dipisah fisik) | ya, hanya untuk order customer pemiliknya |
| `TOOLING` | rak pisau pond / plat / film (lihat keputusan K8) | — (bukan stok bernilai) |
| `SUBCON` | **virtual**: barang sedang di subkon, 1 bin per supplier | tidak |

### 3.2 Mode penyimpanan (`wh_zone.storage_mode`) dan kode bin

| Mode | Pola kode (dibuat generator) | Contoh | Cocok untuk |
|---|---|---|---|
| `RACK` | `<gudang>-<zona>-<lorong><rak>-<tingkat>` | `WH-RAW-01-CHM-A03-2` | tinta, lem, sparepart, FG karton |
| `FLOOR` | `<gudang>-<zona>-L<lajur>-<slot>` | `WH-RAW-01-PPR-L03-05` | kertas plano per pallet, FG pallet |
| `AREA` | `<gudang>-<zona>` (1 bin) | `WH-RAW-01-RCV` | receiving, QC, staging, reject |

Kolom lama dipakai ulang: `row_code` = lorong/lajur, `col_code` = rak/slot,
`stack_code` = tingkat. 7 kode lama **tidak diganti** (sudah dirujuk stok);
lokasi baru dari generator memakai pola di atas.

### 3.3 Peta

Denah = grid sel (mis. 40 × 24 sel, 1 sel ≈ 1 m, ukurannya di
`wh_warehouse.map_cols/map_rows`). Zona = persegi (`map_x/y/w/h`) berwarna.
Bin RACK/FLOOR punya sel `map_x/map_y`; beberapa tingkat rak berbagi 1 sel dan
ditampilkan bertumpuk saat diklik. Tidak perlu CAD — denah digambar dari
layar Setup (drag persegi zona di grid).

---

## 4. Skema

### 4.1 tm2mst (master struktur — prefix `wh_`)

**`wh_warehouse`** (ada) — tambah: `map_cols` SMALLINT, `map_rows` SMALLINT,
`is_virtual` TINYINT (gudang SUBKON), `address` VARCHAR(255) NULL.
`warehouse_type` tambah nilai `VIRTUAL`.

**`wh_zone`** (BARU)

| Kolom | Tipe | Catatan |
|---|---|---|
| `id_zone` | BIGINT PK AI | |
| `warehouse_id` | BIGINT | FK fisik → `wh_warehouse` |
| `zone_code` | VARCHAR(10) | UNIQUE (`warehouse_id`, `zone_code`) |
| `zone_name` | VARCHAR(100) | |
| `zone_type` | VARCHAR(20) | §3.1 |
| `storage_mode` | VARCHAR(10) | RACK / FLOOR / AREA |
| `stock_class` | VARCHAR(5) | RM / SFG / FG / ANY |
| `allow_mixed_item` | TINYINT | 1 bin boleh >1 item? (kertas: tidak) |
| `allow_mixed_lot` | TINYINT | |
| `single_owner_bin` | TINYINT | 1 = 1 bin hanya 1 pemilik (wajib utk CONSIGN) |
| `count_freq_days` | SMALLINT NULL | frekuensi cycle count default zona |
| `map_x`, `map_y`, `map_w`, `map_h` | SMALLINT | persegi di denah |
| `color` | VARCHAR(20) | token tone, bukan hex (§themestandard) |
| `sort_order`, `activate`, `created_*`, `updated_*`, `data_origin` | | standar master |

**`wh_loc`** (ada = BIN) — tambah:

| Kolom | Tipe | Catatan |
|---|---|---|
| `zone_id` | BIGINT NULL→wajib | FK fisik → `wh_zone`; lokasi lama diberi zona "UNASSIGNED" per gudang |
| `pos_code` | VARCHAR(10) NULL | posisi di tingkat (opsional) |
| `capacity_pallet` | SMALLINT NULL | kapasitas pallet (`capacity_kg` sudah ada) |
| `map_x`, `map_y` | SMALLINT NULL | sel di denah |
| `supplier_id` | BIGINT NULL | zona SUBCON: bin milik supplier mana (logis → `sup_supplier`) |
| `machine_id` | BIGINT NULL | zona STAGING: dekat mesin mana (logis → `prod_machine`) |
| `count_freq_days` | SMALLINT NULL | override zona |
| `last_count_at` | DATETIME NULL | diisi saat cycle count di-post |
| `block_reason` | VARCHAR(255) NULL | alasan `is_usable = 0` (rusak, perbaikan) |

PK `location_id` (bukan pola `id_<entitas>`) **dibiarkan** — BIGINT AI sudah
sesuai aturan PK 2026-09-30; mengganti nama merembet ke 10+ kolom stok.

**`wh_putaway_rule`** (BARU)

| Kolom | Catatan |
|---|---|
| `id_putaway_rule` BIGINT PK | |
| `warehouse_id` | berlaku di gudang mana |
| `priority` SMALLINT | 1 = diperiksa pertama |
| `stock_class` NULL, `category_id` NULL (→`mat_category`), `material_id` NULL, `product_id` NULL | kriteria; yang paling spesifik menang |
| `zone_id` | zona tujuan |
| `location_id` NULL | bin tetap (strategi FIXED) |
| `strategy` | `FIXED` / `ADD_EXISTING` (gabung ke bin yang sudah berisi item+lot sama) / `LAST_USED` / `NEAREST_EMPTY` |
| `activate`, audit | |

`mat_raw.def_location_id` tetap dipakai sebagai jalan pintas FIXED per item.

**`mat_category`** — tambah `pick_strategy` (`FIFO` default / `FEFO`).
`mat_raw.shelf_life_days` sudah ada (harus diisi user untuk tinta/lem/coating).

### 4.2 tm2inv (transaksi — kolom baru & tabel baru)

| Perubahan | Tabel | Guna |
|---|---|---|
| `from_location_id` BIGINT NULL | `rm/sfg/fg_stock_d` | dokumen **Transfer** (doc_cat `TF`): 1 baris = pindah dari → ke |
| `pallet_id` BIGINT NULL | `*_stock_d`, `*_stockcard`, `inv_stock_layer` | stok per pallet |
| `owner_customer_id` BIGINT NULL | `*_stock_d`, `*_stockcard`, `inv_stock_layer` | NULL = milik sendiri; terisi = titipan customer, **tidak dinilai** |
| `expiry_date` DATE NULL | `*_stock_d`, `inv_stock_layer` | FEFO (diisi saat terima, default tgl terima + `shelf_life_days`) |
| `scope_zone_id`, `scope_location_id` NULL | `rm/sfg/fg_stock` | opname (doc_cat `OP`, sudah ada) dipersempit jadi cycle count per zona/bin |

**`inv_pallet`** (BARU) — License Plate:
`id_pallet`, `pallet_no` (UNIQUE, isi QR, mis. `PL-2610-00042`), `stock_class`,
`location_id` (posisi sekarang), `owner_customer_id`, `status`
(`ACTIVE`/`EMPTY`/`CLOSED`), `print_count`, `last_printed_at`, audit.
Isi pallet = saldo `*_stockcard` per `pallet_id` (tidak disimpan dobel).

**`inv_wh_task`** (BARU, Tahap 2) — tugas gudang untuk HP:
`id_wh_task`, `task_no`, `task_type` (`PUTAWAY`/`PICK`/`MOVE`/`COUNT`),
`src_doc_type`/`src_doc_id`/`src_doc_no` (PV, WO, DO, …), `wo_id`,
`stock_class`, `item_id`, `lot_no`, `pallet_id`, `qty`, `uom_code`,
`from_location_id`, `suggest_location_id` (saran engine), `to_location_id`
(yang benar-benar dipilih petugas), `status` (`OPEN`/`DONE`/`CANCELLED`),
`assigned_to`, `done_by`, `done_at`, `posted_doc_id` (dokumen TF hasilnya), audit.
Menyelesaikan task = membuat & mem-post dokumen Transfer — **saldo tetap hanya
berubah lewat dokumen stok** (satu sumber kebenaran, kartu stok tetap lengkap).

Semua relasi `tm2inv` → `wh_*` = **logis** (lintas-DB), divalidasi Laravel.
`wh_zone` → `wh_warehouse` dan `wh_loc` → `wh_zone` = FK fisik (1 DB).

### 4.3 Dokumen / layar baru

| Dokumen / layar | Isi | Tahap |
|---|---|---|
| **Setup Gudang** (perluasan `md-warehouse`) | Gudang → Zona → Bin, **generator bin** (lorong × rak × tingkat / lajur × slot), gambar zona di denah, cetak **label bin** QR | 1 |
| **Warehouse Map** | denah berwarna per isi/kapasitas; klik bin = isi (item, lot, pallet, qty, owner, umur) | 1 |
| **Bin Transfer** (doc_cat `TF`) | pindah stok antar bin/gudang; Draft → Confirmed (posting 2 baris kartu stok, nilai tetap) | 1 |
| **Stock by Location** (report) | saldo per gudang/zona/bin/lot/pallet/owner | 1 |
| **Putaway Rules** | tabel aturan §4.1 | 2 |
| **Putaway Tasks / Pick List** (HP) | daftar tugas; scan pallet → saran bin → scan bin → Done | 2 |
| **Cycle Count** | opname per zona/bin, jadwal dari `count_freq_days` | 2 |
| **Bin Utilization** (report) | % terisi per zona (kg / pallet), bin kosong | 3 |
| **Consignment Stock**, **Stock at Subcon** (report) | | 2 |

---

## 5. Aturan bisnis

1. **Terima barang** (PV, retur, hasil produksi, subkon kembali) → stok masuk ke bin
   `RECEIVING` (atau `QC_HOLD` bila `qc_required`). Tahap 1: petugas boleh langsung
   memilih bin tujuan di layar terima (perilaku sekarang, tetap berlaku).
2. **Saran putaway** (Tahap 2) — urutan: `mat_raw.def_location_id` → aturan
   `wh_putaway_rule` paling spesifik (item > kategori > kelas stok) → strategi
   aturan. Bin ditolak bila: `is_usable = 0`, melebihi `capacity_kg`/
   `capacity_pallet`, campur item/lot/owner padahal zona melarang.
   Petugas boleh memilih bin lain (tercatat `suggest` vs `to` → bahan evaluasi).
3. **Picking untuk WO** — dari booking bahan (`rm_booking_d`): engine mengurutkan
   lapis stok FIFO (tgl terima) atau FEFO (`expiry_date`), hanya dari zona
   `STORAGE`, owner = NULL atau = customer pemilik CO/WO. Hasil: pick list →
   barang dibawa ke bin `STAGING` mesin → Stock Out ke WO tetap dokumen yang
   sudah ada (sumber lokasinya kini staging).
4. **Titipan customer**: stok ber-`owner_customer_id` tidak masuk nilai persediaan
   & jurnal; hanya boleh dikeluarkan ke WO/CO milik customer yang sama.
5. **Subkon**: kirim SFG ke subkon = Transfer ke bin virtual supplier (nilai tetap
   milik sendiri); kembali = Transfer balik ke `RECEIVING`/`QC_HOLD`; selisih qty =
   Adjustment dengan alasan.
6. **Cycle count**: layar menampilkan bin yang "jatuh tempo"
   (`last_count_at + count_freq_days ≤ hari ini`); posting OP per zona/bin
   meng-update `last_count_at`. Selama count, bin tetap bisa dipakai (tidak dikunci
   — kebutuhan pabrik jalan terus); selisih dihitung dari saldo saat count dimulai.
7. **Blokir bin**: `is_usable = 0` + `block_reason` → tidak disarankan, tidak bisa
   menerima; stok di dalamnya tetap tercatat.

---

## 6. Dampak ke modul lain

| Modul | Dampak |
|---|---|
| **Procurement** | PV/SPB: bin tujuan default RECEIVING/QC; nanti memunculkan PUTAWAY task. **Receiving dari Customer** (backlog04) = PV tipe CUSTOMER → baris ber-`owner_customer_id`, zona CONSIGN. **Receiving dari Subkon** = Transfer balik dari bin virtual supplier |
| **Produksi** | Booking bahan → Pick List → STAGING mesin → Stock Out ke WO. SFG antar proses berpindah antar bin STAGING. Hasil jadi masuk FG RECEIVING |
| **Delivery** | DO → Pick List FG (FIFO) → zona SHIPPING → Surat Jalan mengeluarkan stok dari SHIPPING |
| **Finance** | Transfer antar bin/gudang **tanpa jurnal** (seperti opsi Zahir) karena pemilik & nilai sama; titipan customer tanpa nilai; subkon tetap aset |
| **Label & QR / Scan Center** | Pallet Label = cetak `inv_pallet`; tambah **Bin Label**; Scan Transaction = mengerjakan task di HP; Track via QR = riwayat pallet/bin |
| **DevLib** | objek baru: `x-dl::wh-map` (denah + warna + klik), `x-dl::scan` (pemindai kamera HP). Didemo di devlib.test dulu |
| **Stok existing** | Tidak ada tabel stok yang diganti; hanya kolom nullable baru. Laporan saldo lama tetap benar |

---

## 7. Tahapan

| Tahap | Isi | Hasil untuk user |
|---|---|---|
| **1 — Struktur & posisi** | `wh_zone`, kolom baru `wh_loc`, generator bin, label bin, Warehouse Map (lihat saja), Bin Transfer, Stock by Location | Tahu barang ada di bin mana, bisa pindah dengan dokumen |
| **2 — Terarah** | pallet (License Plate), putaway rules + saran, Putaway/Pick task di HP (scan), FEFO, cycle count, titipan customer, subkon virtual | Petugas diarahkan, stok per pallet, opname tanpa berhenti |
| **3 — Kapasitas & optimasi** | Bin Utilization, peringatan penuh, heatmap umur stok, evaluasi saran vs pilihan petugas | Gudang dirapikan berdasarkan data |

---

## 8. User journey per peran (open → lihat → klik → hasil)

**A. Staf gudang (HP) — menerima & menata kertas dari PO (Tahap 2)**
1. Buka Scan Center → Putaway → lihat daftar pallet di RECEIVING hasil PV hari ini.
2. Scan QR pallet `PL-2610-00042` → lihat isi (Art Paper 310 g 79×109, 2.000 lbr, lot)
   + **saran bin** `PPR-L03-05` (alasan: aturan "Kertas → zona PPR, ADD_EXISTING").
3. Bawa pallet, scan QR bin → bila cocok hijau; bila beda, sistem tanya alasan singkat.
4. Klik **Done** → dokumen Transfer ter-post, peta bin L03-05 berubah warna.

**B. Staf gudang (HP) — ambil bahan untuk WO**
1. Buka Pick List → pilih WO-2610-0012 → lihat urutan ambil (bin, pallet, qty) berurut
   FIFO/FEFO dan jalur lorong.
2. Scan bin → scan pallet → isi qty yang diambil → **Done** per baris.
3. Hasil: bahan tercatat di STAGING mesin (mis. SM74), PPIC melihat status "Siap".

**C. Kepala gudang (desktop)**
1. Buka Warehouse Map → pilih gudang WH-RAW-01 → lihat zona berwarna menurut isi
   (kosong / terisi / penuh / diblokir) + kartu ringkasan (bin kosong, % terpakai,
   pallet di RECEIVING > 24 jam).
2. Klik bin merah → panel kanan: isi bin, umur stok, riwayat gerak → klik **Transfer**
   atau **Block**.
3. Buka Cycle Count → lihat bin jatuh tempo → buat dokumen OP untuk zona CHM →
   staf menghitung di HP → kepala gudang **Confirm** → selisih jadi adjustment.
4. Setup Gudang → tambah zona / jalankan generator bin → cetak label bin.

**D. PPIC**
1. Buka Stock by Location → filter item/lot → lihat bahan tersedia per zona
   (STORAGE vs QC_HOLD vs titipan customer).
2. Dari WO, klik **Request Pick** → task PICK muncul di HP gudang.
3. Pantau status pick (Open / Done) di WO; bahan di SUBCON terlihat per supplier.

---

## 9. Keputusan yang harus diambil user (dengan rekomendasi)

| # | Keputusan | Pilihan | **Rekomendasi** |
|---|---|---|---|
| K1 | Lapis struktur | (a) Gudang → Zona → Bin (`wh_loc` tetap = bin) · (b) tambah `wh_bin` di bawah `wh_loc` (4 tabel) | **(a)** — stok sudah menempel ke `wh_loc`; rak/tingkat cukup sebagai segmen kode |
| K2 | Pemakaian | (a) pencatatan posisi saja · (b) putaway/picking terarah | **Tahap 1 = (a), Tahap 2 = (b)** — mulai tercatat dulu, arahkan setelah data bin rapi (menjawab backlog 05.4) |
| K3 | Pallet / License Plate | (a) cukup lot · (b) `inv_pallet` + QR per pallet | **(b) di Tahap 2** — kertas diterima & dipakai per pallet; label pallet QR sudah ada di menu |
| K4 | Titipan customer | (a) gudang terpisah saja · (b) kolom owner di stok + zona CONSIGN | **(b)** — pola Odoo/D365; mencegah bahan customer terpakai untuk order lain & tidak menggelembungkan nilai persediaan |
| K5 | Subkon | (a) catat di dokumen subkon saja · (b) bin virtual per supplier | **(b)** — SFG di subkon terlihat di saldo & kartu stok |
| K6 | Transfer antar bin berjurnal? | ya / tidak | **Tidak** (pemilik & nilai sama); transfer antar gudang juga tidak, kecuali Finance minta akun per gudang |
| K7 | Kunci bin saat cycle count | dikunci / tidak | **Tidak dikunci** — hitung selisih dari saldo saat mulai; pabrik tidak berhenti |
| K8 | Rak pisau pond / plat / film masuk tata letak? | ya / nanti | **Ya, zona TOOLING** (posisi dicatat di master tooling) — pisau sering dicari saat reorder; tapi skema tooling-nya dibahas terpisah |
| K9 | Data denah nyata | user kirim sketsa / foto denah gudang + daftar rak | **Wajib sebelum Tahap 1 di tm2** — di mockup.test (ddt*) dipakai denah contoh; jangan mengarang denah nyata |
| K10 | Kedaluwarsa (FEFO) | isi `shelf_life_days` per item / per kategori | **Per kategori dulu** (tinta, lem, coating, varnish), override per item — saat ini baru 1 item terisi |
| K11 | HP gudang | kamera HP biasa (web/PWA) · scanner khusus | **Kamera HP via web (PWA)** dulu, cocok backlog10 mobile; scanner Bluetooth bisa menyusul (masuk sebagai keyboard) |

---

## 10. Sumber riset

- Odoo 18 — Putaway rules: https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/shipping_receiving/daily_operations/putaway.html
- Odoo 18 — Storage categories: https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/shipping_receiving/daily_operations/storage_category.html
- Odoo — Consignment (owned stock): https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/shipping_receiving/daily_operations/owned_stock.html
- Odoo 18 — Cycle counts: https://www.odoo.com/documentation/18.0/applications/inventory_and_mrp/inventory/warehouses_storage/inventory_management/cycle_counts.html
- SAP — Storage Section: https://help.sap.com/docs/SAP_SUPPLY_CHAIN_MANAGEMENT/7a6fc3d598d140b0988065937035fff6/4ab997fcf2b93d86e10000000a421937.html
- SAP — Storage Bin Determination for Putaway: https://help.sap.com/docs/r/3d97bec9bf1649099384bb8167df3cf2/9.5.0.2/en-US/4acdcb53ad377114e10000000a174cb4.html
- SAP Learning — Applying Putaway Rules: https://learning.sap.com/courses/basic-customizing-in-sap-s-4hana-ewm/applying-putaway-rules
- Oracle WMS — Rules Engine: https://docs.oracle.com/cd/E18727_01/doc.121/e13433/T211976T317866.htm
- Dynamics 365 — Work templates & location directives: https://learn.microsoft.com/en-us/dynamics365/supply-chain/warehousing/control-warehouse-location-directives
- Dynamics 365 — Prevent multiple owners in locations: https://learn.microsoft.com/en-us/dynamics365/supply-chain/warehousing/prevent-multiple-owners-locations
- ERPNext — Putaway Rule: https://docs.erpnext.com/docs/v12/user/manual/en/stock/putaway-rule
- Accurate Online — Pemindahan barang antar gudang: https://help.accurate.id/product/accurate-online/fitur-aol/persediaan/pemindahan-barang/mencatat-pemindahan-barang-antar-gudang/
- Zahir — Transfer barang antar gudang tanpa jurnal: https://help.zahironline.com/portal/id/kb/articles/transfer-barang-antar-gudang-tanpa-jurnal

---

## 11. Tambahan 2026-10-01 (permintaan user ke-2): multi lokasi, transfer, laporan, adjustment

Preview interaktif semua layar: artifact "Tata Letak Gudang TMERP"
(salinan repo `docs/mockups/inventory_booking_preview_v2.html`). Booking: `materialbooking_tier1.md`.
Implementasi mengikuti aturan multi-bahasa 2026-10-01 (DB/status English, label English + kamus).

**Multi lokasi.** (a) satu item di banyak bin/gudang: semua laporan & pencarian difilter
Site → Gudang → Zona → Bin dan dijumlah per tingkat. (b) banyak lokasi fisik: tabel baru
`wh_site` (`id_site`, `site_code`, `site_name`, `address`, `geo_id`, `activate`) +
`wh_warehouse.site_id`; tiap site punya gudang virtual `IN-TRANSIT`.

**Transfer** (`doc_cat TF`, nomor `26TF00005`): Antar Bin / Antar Gudang (1 langkah:
Confirm → Closed) / Antar Site (2 langkah: Confirm = kirim ke In Transit + surat jalan
internal; Receive di site tujuan = Closed, boleh sebagian, selisih jadi Adjustment Draft).
In Transit tetap dinilai tapi tidak Available. Tanpa jurnal.

**Cari Barang**: item/lot/pallet/bin → total per site + rincian per bin; aksi Map, Transfer, Stock Card.

**Stock Card** simple (tgl, dokumen, masuk, keluar, saldo; TF dalam gudang disembunyikan) dan
**Extended** (site/zona/bin, lot, pallet, pemilik, referensi, kolom Adj terpisah, nilai rata-rata,
saldo nilai, user; TF tampil 2 baris). Kolom nilai hanya Finance/Costing/Ops Manager+ (disaring server).

**Item per Lokasi**: posisi sekarang per bin + riwayat IN/OUT/ADJ/TF (filter tipe), bisa juga per bin.

**Adjustment + approval**: Confirm = kirim ke approval (Approval Setting v2); approver read-only,
Level n of N, Approve/Reject final, Hold sementara, catatan wajib utk Reject/Hold, log. Stok
berubah setelah approval terakhir; sebelumnya tampil "Pending adj" di On Hand.

**Master Stock**: semua item per periode: saldo awal, masuk, keluar, adjustment, saldo akhir
(+ nilai bila berhak); periode tertutup dari snapshot `inv_period`.

**Stock On Hand**: On Hand, Booked, Available, Incoming (PO), Pending adj, Min, status
(Short / Late PO / Below min / Slow moving / OK) sebagai filter.

| # | Keputusan | Rekomendasi |
|---|---|---|
| M1 | Lapis Site | Tambah bila ≥ 2 alamat fisik (mohon info jumlah lokasi fisik) |
| M2 | Transfer antar site | 2 langkah dengan In Transit |
| M3 | Selisih terima transfer | Otomatis Adjustment Draft + approval |
| A1 | Jenjang approval adjustment | L1 Kepala Gudang; L2 Ops Manager bila minus / ≥ Rp 1 jt; L3 Dir F&A ≥ Rp 10 jt (angka ditetapkan user) |
| A2 | Kapan stok berubah | Setelah approval terakhir |
| S1 | Kolom nilai | Hanya Finance, Costing, Ops Manager+ |

**Temuan data**: saldo berjalan `ddtinv.rm_stockcard.qty_eb` ANIMAL GLUE tidak konsisten
(IN 1.000 → ADJ +100 tercatat saldo 100) dan ada saldo negatif di UNKNOWN-LEGACY — perlu
diperiksa sebelum laporan Stock Card/Master Stock dibangun di atas tabel ini.

---

## 12. Tambahan 2026-10-02: pemetaan gudang nyata (dari data sistem lama)

Dicek ke `tm2old` (baca saja). Sistem lama punya **3 gudang nyata** di `mst_warehouse`,
tanpa lapis rak / bin (hanya nama gudang di tiap dokumen):

| Gudang lama | Item terdaftar | Isi utama (kategori bahan) | Penerimaan PO | Pengeluaran | Surat Jalan |
|---|---|---|---|---|---|
| GUDANG A10 | 3.554 | campuran: Others 1.259, Ink 717, Sheet 609, Single Face 477, Paper 417, Glue 10, Plate 7 | 7.166 | 2.726 | 15.937 |
| GUDANG A5 | 889 | **kertas** (Paper 752) | 6.261 | 1.485 | 505 |
| GUDANG A6 | 7 | hampir tidak dipakai | 38 | 0 | 17 |
| (kosong) | 16.345 item tanpa gudang | — | 6.410 | 632 | 19.437 |

Gudang v2 yang ada sekarang (`WH-RAW-01`, `WH-FG-01`, `WH-MIX-01`, `WH-SCP-01`) adalah
**buatan sistem menurut kelas stok**, tidak sama dengan gedung nyata: A10 menyimpan bahan
sekaligus mengirim barang jadi. Di ERP besar (§2) gudang = gedung fisik, fungsi = zona.
Peta gudang hanya masuk akal bila digambar per gedung.

**Usul pemetaan** (menunggu jawaban user, tidak ada yang diubah):

| Lapis | Isi |
|---|---|
| `wh_warehouse` = gedung | A10, A5, A6 (+ virtual SUBCON, IN-TRANSIT) |
| `wh_zone` = fungsi di dalam gedung | Receiving, QC, Paper, Ink & Chemicals, Sheet / Single Face, Finished Goods, Shipping, Staging, Reject, Tooling — `stock_class` ada di zona |
| `wh_loc` = bin | lajur × slot / lorong-rak-tingkat |

`warehouse_type` hanya dipakai label di layar master (tidak dipakai logika posting), jadi
gudang per gedung aman. 7 lokasi lama + stok dummy yang menempel padanya tidak dihapus
(dummy tm2 = fixture test); `WH-RAW-01` dst dinonaktifkan / dipetakan ke gedung setelah
user memutuskan.

| # | Pertanyaan | Rekomendasi |
|---|---|---|
| G1 | A5, A6, A10 = gedung terpisah (beda alamat / kavling) atau ruangan dalam satu gedung? Masih dipakai semua? | Beda kavling → cukup sebagai 3 gudang dalam 1 site (lapis `wh_site` baru perlu bila beda kota / perlu surat jalan internal) |
| G2 | Gudang v2 mengikuti gedung nyata (A10 / A5 / A6), kelas stok pindah ke zona? | **Ya** |
| G3 | Peta pertama digambar untuk gedung mana? | **A10** (paling ramai, bahan + pengiriman), lalu A5 (kertas) |

Kebutuhan K9 jadi lebih kecil: cukup sketsa tangan / foto per gedung (pintu, area kertas,
rak, area kirim) + perkiraan ukuran; rak dan lajur dibuat lewat generator bin.

Mockup `mockups/warehouse_layout_v2.jpg` diperbarui 2026-10-02: label English, satu baris
filter teks (tanpa kotak statistik) sesuai aturan C.21.

---

## 13. Yang dibangun 2026-10-02 (Tahap 1) dan bedanya dari rancangan

Perintah user 2026-10-02, berurutan: (1) "ya saya tunggu warehouse mapping, lalu input moving item antar warehouse dan
disana akan tertera capacity label dan barcode or QR code; warna merah untuk DEAD stock; Facility for QR Code, Labelling,
BUILD TOO"; (2) "gedung itu pasti terpisah, A5, A6, A10 dan masih gedung yang lain"; (3) "pastikan bisa ditambah area
storage"; (4) "semua gedung bisa di-define, untuk saat ini buat saja menurut estimasi kamu misal 30 x 20 meter, nanti
di-adjust oleh officer"; (5) "bila diimplementasi ke perusahaan lain gudang harus bisa ditambahkan dengan mudah dan
bentuknya, misal huruf L; gudang Telasih A5, A6, A10 berbentuk kotak dan punya 2 lantai; bisa 2 - 3 lantai".

### 13.1 Jawaban atas keputusan terbuka

| # | Hasil |
|---|---|
| G1, G2 | Gudang = gedung nyata (A10, A5, A6, + gedung lain yang ditambah user). Kelas stok dan fungsi = area (`wh_zone`). |
| K1 | (a): Gedung -> Area -> Bin; `wh_loc` tetap = bin. |
| K2 | Tahap 1 = pencatatan posisi + pindah dengan dokumen (dibangun). Tahap 2 (terarah) belum. |
| K6 | Transfer tanpa jurnal (dijalankan; menunggu koreksi user / Finance). |
| K9 | Ukuran gedung = perkiraan agen (A10 30 x 20, A5 24 x 16, A6 20 x 12 m; 2 lantai), disesuaikan officer di Buildings. |
| M1, M2 | Belum ada lapis Site dan belum ada transfer dua langkah: gedung berdekatan, transfer satu langkah. |
| K3, K4, K5, K7, K8, K10, K11 | Belum dibangun (Tahap 2). Zona TOOLING dan CONSIGN sudah bisa dibuat sebagai area. |

### 13.2 Skema yang terpasang (berbeda dari bagian 4)

- `wh_warehouse` + `length_m`, `width_m`, `floor_count` (1-3), `shape` (RECT / L / CUSTOM), `notch_length_m`,
  `notch_width_m`, `notch_corner` (NE / NW / SE / SW), `outline` (titik "x,y; x,y" untuk CUSTOM), `address`.
  Bukan `map_cols / map_rows`: denah memakai **meter**, bukan sel.
- `wh_zone`: seperti 4.1, + `floor_no`; `map_x / y / w / h` dalam meter (desimal). Tanpa `allow_mixed_*`,
  `single_owner_bin`, `count_freq_days`, `color` (warna dari jenis area) — ditambah saat aturannya dibangun.
- `wh_loc` + `zone_id`, `capacity_pallet`, `map_x`, `map_y` (kosong = disusun otomatis di dalam area), `block_reason`.
  `row_code` memuat kode area (`PPR-L03`) karena unik per gudang + row / col / stack; kode area paling panjang 6 huruf.
- **Transfer = tabel sendiri** `tm2inv.inv_transfer` + `inv_transfer_line` (bukan `doc_cat TF` di `rm/sfg/fg_stock`):
  satu dokumen untuk semua kelas stok. Kartu stok: dua baris per baris transfer, `doc_type` TF,
  `reference_name` `tm2inv.inv_transfer_line:OUT` / `:IN` (Cancel: `:CANCEL_OUT` / `:CANCEL_IN`). Nomor `TFyy#####`.
- `tm2inv.inv_label_print`: riwayat cetak label.
- View `v_*_onhand` + `last_item_movement` (pergerakan terakhir item tanpa transfer); `v_inv_movement` membawa nomor transfer.

### 13.3 Aturan yang berlaku

1. **Dead stock** = `last_item_movement` lebih lama dari `ASSISTANT_DEADSTOCK_MONTHS` bulan atau kosong; dihitung per item
   di lokasi mana pun; **transfer tidak dihitung sebagai gerak**. Dipakai peta, transfer, label, laporan, asisten, widget.
2. **Status bin**: BLOCKED (`is_usable = 0`), EMPTY, IN_USE, FULL (>= 90% kapasitas kg), OVER (> 100%). Merah (dead)
   menang atas warna isi.
3. **Berat** diperkirakan (kg): satuan KG = qty; kertas = qty x gsm x panjang x lebar / 10^7 (kolom master, atau dibaca
   dari nama item); RIM = 500 lembar; selain itu tidak diketahui dan tidak dihitung dalam kapasitas.
4. **Transfer**: Draft -> Confirmed (stok pindah, nomor lahir) -> Cancelled (stok kembali, alasan wajib, hanya bila
   stoknya masih di bin tujuan). Ditolak bila qty melebihi saldo bin asal, bin tujuan diblokir / bukan milik gudang
   tujuan / sama dengan bin asal. Lebih dari kapasitas = peringatan. Nilai pindah dengan harga rata-rata bin asal;
   `cur_stock` item tidak berubah; tanpa jurnal.
5. **Letak bin di peta**: lajur / rak ke kanan, slot / kolom ke bawah, tingkat rak = satu sel berisi beberapa bin.

### 13.4 Layar dan objek

Layar v2: `warehouse-map`, `stock-transfer`, `wh-buildings`, `wh-zones`, `wh-bins`, `inv-rpt-location`,
`label-location`, `label-item`, `inv-label-history`, `scan-track`; cetakan `stock-transfer`.
Objek DevLib: `x-dl::wh-map`, `x-dl::label`, `x-dl::label-sheet`, `x-dl::scan`.
Menu, Help, dan data contoh: mockup.test (ddt) saja. Tabel dan view: ddt dan tm2.
