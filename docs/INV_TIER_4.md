# TIER 4 — INVENTORY / STOCK MOVEMENT · SPEC LAYAR & BINDING LIVEWIRE

> Turunan Tier 1–3. Semua nama komponen, route, dan properti di sini **mengikat**.
> Mockup acuan: `mockup_stock_in.html`, `mockup_stock_out.html`,
> `mockup_stock_adjustment.html`, `mockup_stock_return.html` + `tmerp_inv.css`.

---

## 1. KONVENSI WAJIB

1. **Laravel + Livewire v4 + Blade + Tailwind (compiled via Vite).** CDN hanya untuk mockup.
2. **Layout:** `layouts.shell` untuk semua layar operasional. Mode **FULL-PAGE** (`w-full` + padding) — bukan `container`.
3. **Token warna:** `--navy #2c3e69`, `--accent #2f6fed`. Ambil dari CSS variable, bukan hex di kelas.
4. **Grid → klik ganda → form.** Panel-switching di dalam satu route, tanpa reload. Grid dan form adalah dua komponen Livewire terpisah yang dipertukarkan oleh komponen induk halaman.
5. **Layar entri dan layar persetujuan adalah route berbeda.** Tidak ada tombol Setujui/Tolak di layar entri, bahkan disembunyikan.
6. **Sisi B tidak menyeleksi kolom nilai.** Repository menyediakan dua metode; komponen gudang memanggil `forWarehouse()`.
7. **Model per koneksi:** `protected $connection = 'tm2inv';`
8. Semua aksi menulis melewati service, tidak ada `Model::create()` langsung di komponen untuk dokumen stok.

### 1.1 Peta layar

| Route | Komponen | Peran |
|---|---|---|
| `inv.stock-in.index` | `Inventory\StockIn\StockInPage` | induk: grid ⇄ form |
| `inv.stock-out.index` | `Inventory\StockOut\StockOutPage` | induk |
| `inv.stock-return.index` | `Inventory\StockReturn\StockReturnPage` | induk |
| `inv.stock-adj.index` | `Inventory\StockAdj\StockAdjPage` | induk |
| `inv.stock-adj.approval` | `Inventory\StockAdj\StockAdjApproval` | **terpisah** |
| `inv.stock-card` | `Inventory\Report\StockCard` | laporan |
| `inv.onhand` | `Inventory\Report\OnhandGrid` | laporan |
| `inv.period.index` | `Inventory\Period\PeriodManager` | admin periode |

### 1.2 Komponen yang dipakai ulang

| Komponen | Asal | Fungsi di sini |
|---|---|---|
| `ItemPicker` | Procurement (5 level kategori) | pilih material/produk/komponen |
| `LocationPicker` | **baru, milik modul ini** | pilih gudang + lokasi, filter `is_usable` |
| `FlowActionBar` | Flow Engine | tombol aksi menurut step berjalan |
| `FlowHistoryLog` | Flow Engine | riwayat dokumen + badge `🔁 PLT` |
| `SearchableSelect` | global | WO, dokumen referensi |

`LocationPicker` menerima prop `usableOnly` (bool). Stock Out & Stock In-GOOD memakai `true`; Return-SCRAP memakai `false` dengan filter `warehouse_type = 'WASTE'`.

---

## 2. LAYAR STOCK IN

### 2.1 Grid — `StockInGrid`

**Sumber:** `rm_stock` / `sfg_stock` / `fg_stock` sesuai filter kelas, join agregat detail.

| Properti | Tipe | Default |
|---|---|---|
| `$fStockClass` | string | `'RM'` |
| `$fWarehouseId` | ?int | null |
| `$fDocCat` | ?string | null (`STOCK` / `BB`) |
| `$fFlowStatus` | ?string | null |
| `$fRefDocType` | ?string | null |
| `$fDateFrom`, `$fDateTo` | date | awal bulan → hari ini |
| `$search` | string | `''` |
| `$showValue` | bool | `false`, hanya bisa `true` bila `INV.can_view_cost` |

**Kolom:** No. Dokumen · Tanggal · Kelas · Kategori · Gudang · Sumber (`ref_doc_type` + `ref_doc_no`) · Ringkasan item · Jumlah baris · Total qty · *(Nilai)* · Status · Dibuat oleh.

**KPI di atas grid:** draft belum diposting · diposting bulan ini · otomatis dari PV · input manual.

**Aksi:** `+ Stock In Baru` (hanya `INV_IN.create`) · Export · klik ganda baris → `$dispatch('open-form', docId)`.

**Wajib** mengikuti `STANDAR GRID/LIST BERVOLUME BESAR` di `GLOBAL_RULES.yaml`: server-side pagination, debounce pencarian 300 ms, kolom urut, tanpa `->get()` tanpa batas.

### 2.2 Form — `StockInForm`

**Header**
| Field | Binding | Aturan |
|---|---|---|
| No. Dokumen | `$docNo` | readonly, terisi setelah simpan pertama |
| Tanggal | `$docDate` | wajib, dalam periode `OPEN`, ≤ hari ini, mundur maks `INV_BACKDATE_DAYS` |
| Kelas Stok | `$stockClass` | wajib, **mengunci** daftar item; mengubahnya mengosongkan seluruh baris (dengan konfirmasi) |
| Kategori | `$docCat` | `STOCK` / `BB`; `BB` hanya untuk `INV.can_manage_period` |
| Sumber | `$refDocType` | `MANUAL` (default di layar ini). `PV`/`WO` hanya tampil sebagai baca — dokumen otomatis tidak dibuat lewat layar ini |
| No. Referensi | `$refDocNo` | disabled bila `MANUAL` |
| Gudang | `$warehouseId` | wajib, mengunci lokasi baris |
| Diterima oleh | `$receivedBy` | auto dari user login, readonly |
| Catatan | `$notes` | opsional |
| Lampiran | `$attachment` | **wajib** bila `MANUAL` |

**Baris** (`$rows[]`): `item_id`, `item_code`, `item_name`, `lot_no`, `location_id`, `qty`, `uom_code`, `unit_price`*, `value`*, `note_detail`
(*) hanya di-bind bila `can_view_cost`.

**Validasi baris:** item wajib · lokasi wajib & `is_usable = 1` · `qty > 0` kecuali `docCat = 'BB'` · tidak ada duplikat (item, lokasi, lot).

**Aksi**
| Tombol | Kondisi | Aksi |
|---|---|---|
| Simpan Draft | `flow_status ∈ {DRAFT, REVISION_NEEDED}` | `StockDocumentService::saveDraft()` |
| Ajukan | idem + `refDocType = MANUAL` | `FlowEngine::submit('IN', $docId)` |
| Batalkan | idem | set `CANCELLED` + `stat_cancel = 1` |
| Cetak | `POSTED` / `CANCELLED` | nota Bukti Terima Barang |

**Tab bawah:** Riwayat Dokumen (`FlowHistoryLog`) · Dampak Kartu Stok (pratinjau `qty_bb` / `qty_in` / `qty_eb` per baris, dihitung `StockPostingService::preview()` tanpa menulis).

Pratinjau memakai jalur kode yang sama dengan posting sungguhan — kalau pratinjau benar, posting benar.

---

## 3. LAYAR STOCK OUT

### 3.1 Grid — `StockOutGrid`
Kelas terkunci **RM**; selector kelas tidak dirender.

**Kolom:** No. Dokumen · Tanggal · No. WO · No. CO · Produk/Pelanggan · Gudang · Baris · Total keluar · Kelengkapan · Status.

`Kelengkapan` adalah badge turunan: `Penuh` (semua baris tertutup) · `Ada substitusi` · `Kurang → PR`.

**Filter tambahan:** `$fCompleteness` (`FULL` / `SUBSTITUTED` / `SHORTAGE`).

### 3.2 Form — `StockOutForm`

**Header:** No. Dokumen · Tanggal · **No. WO (wajib, lewat modal `WoPicker`)** · Gudang · No. CO / Pelanggan / Produk / Qty Order (semua `readonly` — 🔒 terkunci dari WO) · Diserahkan kepada · Proses tujuan (`process_code`) · Catatan.

**Modal `WoPicker`:** hanya WO berstatus dirilis; kolom No. WO · No. CO · Produk · Pelanggan · Qty · Jatuh tempo. Memilih WO memicu `pullRequirements()`.

**`pullRequirements()`**
```
1. Baca tm2prd.prd_wo_material untuk wo_id  -> baris ISSUE (parent_detail_id = null)
2. Untuk tiap baris, baca v_rm_available    -> qty_available, qty_booked
3. qty default = MIN(qty_required, qty_available)
4. Baris yang sudah diisi user TIDAK ditimpa — tarik ulang meminta konfirmasi
```

**Struktur baris — hierarkis.** Baris anak dirender dengan indentasi dan penanda `↳`, ditahan tepat di bawah induknya (urut: `parent_detail_id` ASC, lalu `is_child`, lalu `doc_detail_id`).

| Kolom | Induk (`ISSUE`) | Anak (`SUBSTITUTE`) | Anak (`PURCHASE`) |
|---|---|---|---|
| Kebutuhan | dari WO | `—` | `—` |
| Tersedia | `v_rm_available` | `v_rm_available` | `0` |
| Dibooking | `rm_booking_d` | idem | `—` |
| Qty Keluar | input | input | input (qty untuk PR) |
| Aksi | badge `ISSUE` | badge `SUBSTITUTE` | badge `PURCHASE` |

**Tombol `↳`** pada baris induk hanya aktif bila `qty` induk < `qty_required`.

**Validasi**
- `qty` ≤ `qty_available`, kecuali `INV.can_override_stock` + alasan terisi.
- Lokasi `is_usable = 1` saja.
- Baris `PURCHASE` tidak boleh punya lokasi/lot (di-disable).
- Kelas FG ditolak di level route — komponen ini tidak menerima `stockClass = 'FG'`.

**Aksi:** Simpan Draft · **Konfirmasi & Posting** (dialog konfirmasi wajib) · Batalkan · Cetak Bon · `Buat PR dari Kekurangan` (mengirim baris `PURCHASE` ke `tm2pur`).

**Tab bawah:** Riwayat · Dampak Kartu Stok · **Ketersediaan & Booking** (menjelaskan selisih on hand vs available, kolom "Booking terkait" menyebut nomor booking dan WO pemesannya).

---

## 4. LAYAR STOCK ADJUSTMENT & OPNAME

### 4.1 Grid — `StockAdjGrid`
**Kolom:** No. Dokumen · Tanggal · Jenis (`AD`/`OP`) · Kelas · Gudang · Alasan · Baris · Selisih + · Selisih − · *(Nilai selisih)* · Status.

Status menampilkan level berjalan (`Menunggu Lvl 1`) dan badge `🔁 PLT` bila pemegang step saat ini adalah pejabat sementara.

**KPI:** menunggu persetujuan · disetujui bulan ini · nilai selisih kurang · nilai selisih lebih.

### 4.2 Form entri — `StockAdjForm`

**Header:** No. Dokumen · Tanggal · **Jenis** (`OP`/`AD`, mengubah tampilan) · Kelas Stok · Gudang · Cakupan lokasi · Alasan (`reason_code`) · Diajukan oleh.

**Blok khusus Opname** (muncul hanya bila `$docKind = 'OP'`): Petugas Opname · Mulai Hitung · Selesai Hitung · Saksi · checkbox `full_scope`. Semua wajib saat `OP`.

**Blok bawah:** Catatan/Kronologi (wajib) · Bukti foto/berita acara (wajib bila nilai > batas level 1).

**Baris — kolom & perilaku**

| Kolom | Binding | Perilaku |
|---|---|---|
| Material | `item_id` | `ItemPicker` |
| Lot / Lokasi | `lot_no`, `location_id` | `LocationPicker`, `usableOnly = false` (waste juga bisa di-opname) |
| Saldo Sistem | `qty_system` | **readonly**, snapshot; disimpan ke DB |
| Hitung Fisik | `qty_physical` | **satu-satunya input qty** |
| Selisih | `qty` | dihitung live: `qty_physical − qty_system`; hijau/merah/abu |
| Nilai Selisih | `value` | Sisi A saja |
| Alasan Baris | `note_detail` | wajib bila selisih ≠ 0 |

**Kolom selisih tidak pernah bisa diedit.** Ini bukan preferensi tampilan — membiarkan user mengetik selisih membuat `qty_system` dan `qty_physical` bisa tidak konsisten dengan `qty`, dan kartu stok kehilangan makna.

**`pullSystemBalance()`** menarik saldo dari `v_rm_onhand` untuk seluruh lokasi dalam cakupan. Bila `full_scope = 1`, seluruh item yang punya saldo ≠ 0 di cakupan wajib masuk daftar dan tidak bisa dihapus.

**Blok Persetujuan (baca-saja di layar ini):** tabel level · jabatan · pelaksana (dengan `🔁 PLT`) · batas kewenangan · status · waktu. Disertai catatan eksplisit bahwa persetujuan dilakukan di layar terpisah.

**Aksi:** Simpan Draft · Ajukan Persetujuan · Batalkan · Cetak Berita Acara.

### 4.3 Layar persetujuan — `StockAdjApproval` (route terpisah)

Daftar dokumen yang **menunggu jabatan user login** (atau jabatan yang sedang ia PLT-kan). Klik ganda → detail lengkap, sama detailnya dengan layar entri tetapi seluruhnya baca-saja.

**Tambahan yang hanya ada di sini:**
- `FlowActionBar`: **Setujui** · **Tolak** (alasan wajib, min. 10 karakter).
- **Peringatan saldo berubah**: bila `v_rm_onhand` saat ini ≠ `qty_system` yang tersimpan pada baris mana pun, tampilkan banner kuning yang menyebut baris-baris terdampak, beserta tombol `Tarik Ulang Saldo Sistem`. Penyetuju yang memutuskan; sistem tidak menarik ulang diam-diam.
- Ringkasan nilai mutlak dokumen dan batas kewenangan step berjalan.

Persetujuan level terakhir memanggil `StockPostingService::postDocument()` di dalam transaksi yang sama dengan penulisan `flow_log`.

---

## 5. LAYAR RETURN FROM PRODUCTION

### 5.1 Grid — `StockReturnGrid`
**Kolom:** No. Dokumen · Tanggal · No. WO · Ref. Stock Out · Produk/Bagian pengembali · Kelas · Baris · Qty Layak · Qty Afkir · Status.
**Filter tambahan:** `$fCondition` (`GOOD` / `SCRAP` / `MIXED`).
**KPI:** WO selesai yang returnya belum masuk · draft · diterima bulan ini · total masuk afkir.

### 5.2 Form — `StockReturnForm`

**Header:** No. Dokumen · Tanggal · **Ref. Stock Out (wajib, modal `StockOutPicker`)** · Kelas Stok · No. WO / No. CO / Produk / Pelanggan (🔒 dari dokumen referensi) · Dikembalikan oleh · Proses asal · **Gudang tujuan (bahan layak)** · **Gudang afkir** · Alasan (`reason_code`) · Catatan.

**Modal `StockOutPicker`:** sumber `v_rm_out_returnable` dikelompokkan per dokumen; default hanya menampilkan yang `qty_returnable > 0`.

**`pullReturnableLines()`** menarik baris `ISSUE` + `SUBSTITUTE` beserta `qty_issued`, `qty_returned`, `qty_returnable`.

**Baris**

| Kolom | Binding | Perilaku |
|---|---|---|
| Material | readonly | dari dokumen sumber |
| Dikeluarkan / Sudah Diretur / Sisa Boleh | readonly | dari view |
| Qty Retur | `qty` | **validasi live** ≤ sisa boleh; melebihi → border merah + tooltip, dan ditolak server |
| Kondisi | `return_condition` | `GOOD` / `SCRAP`; mengubahnya **mengganti daftar lokasi tujuan** |
| Lokasi Tujuan | `location_id` | `GOOD` → gudang tujuan (`is_usable = 1`); `SCRAP` → gudang afkir (`is_usable = 0`) |
| Keterangan | `note_detail` | wajib bila `SCRAP` |

**Tombol `Pecah Baris`** menduplikasi baris terpilih, menyetel salinannya ke `SCRAP` dan qty `0`, lalu memindahkan lokasinya ke gudang afkir. Batas `qty_returnable` dibagi antara kedua baris — total keduanya tetap tidak boleh melebihi sisa.

**Validasi silang (server, tidak bisa dilewati):** `return_condition = 'SCRAP'` ⟺ lokasi `is_usable = 0`. Pelanggaran arah mana pun ditolak.

**Footer tabel** menampilkan dua baris: total qty, lalu pecahan layak / afkir.

**Tab bawah:** Riwayat · Dampak Kartu Stok · **Rekonsiliasi WO** (dari `v_inv_wo_reconcile` + standar dari `tm2prd`; kolom Varian dengan warna).

---

## 6. LAYAR LAPORAN

### 6.1 `StockCard` — kartu stok
Sumber `v_inv_movement`. Filter: kelas · item (wajib) · gudang · lokasi · rentang tanggal · periode.
Kolom: Tanggal · No. Dokumen · Tipe · Referensi · Lot · Saldo Awal · Masuk · Keluar · Penyesuaian · Saldo Akhir · *(Nilai · Harga Rata-rata)*.
Dua varian query: `forWarehouse()` tanpa kolom nilai, `forFinance()` lengkap.
Ekspor Excel & cetak.

### 6.2 `OnhandGrid` — saldo per lokasi
Sumber `v_rm_available` (+ padanan SFG/FG). Kolom: Item · Gudang · Lokasi · Dapat dipakai? · On hand · Dibooking · Tersedia · *(Nilai)* · Mutasi terakhir.
Baris lokasi `is_usable = 0` diberi latar berbeda dan badge `Tidak dapat dipakai`.
Indikator min/max stok dari master (di bawah `min_stock` → badge merah).

### 6.3 `PeriodManager` — periode stok
Daftar periode + status. Aksi: Buka periode berikutnya · Set `CLOSING` · **Tutup periode** (memerlukan: tidak ada dokumen berstatus selain `POSTED`/`CANCELLED`/`DRAFT` di dalamnya; daftar penghalang ditampilkan sebelum tombol aktif). Hanya `INV.can_manage_period`.

---

## 7. SERVICE & REPOSITORY

```
App\Services\Inventory\
  StockPostingService      postDocument() · preview() · reverse()
                           postIn()  — dipanggil PV & Production
  StockDocumentService     saveDraft() · cancel() · pullFromWo() · pullReturnable()
  StockValuationService    AVG moving average · FIFO/LIFO layer pick & consume
  StockBalanceService      onHand() · available() · systemBalanceSnapshot()
  PeriodService            resolvePeriod() · assertOpen() · close()

App\Repositories\Inventory\
  StockMovementRepository  forWarehouse() — TANPA kolom nilai
                           forFinance()   — lengkap
```

**Aturan keras:** komponen Livewire tidak pernah memanggil `DB::` atau menulis model dokumen stok langsung. Seluruh mutasi lewat service, agar validasi periode, idempotensi, dan penulisan `flow_log` tidak bisa dilewati dari satu jalur pun.

---

## 8. NOTA (CETAKAN)

| Blade | Dokumen | Sisi | Catatan |
|---|---|---|---|
| `inv.print.receipt` | Stock In | B | Tanpa kolom harga |
| `inv.print.issue-slip` | Stock Out | B | Baris substitusi ditandai jelas; kolom tanda tangan penyerah & penerima |
| `inv.print.opname-ba` | Opname | A | Menyertakan waktu mulai/selesai, petugas, saksi |
| `inv.print.adjustment-ba` | Adjustment | A | Nilai selisih per baris |
| `inv.print.return-slip` | Return | B | Baris `SCRAP` diberi blok terpisah |
| `inv.print.stock-card` | Kartu stok | A/B | Dua varian |

Label tanda tangan diambil dari `flow_config_step.label`. Dokumen `CANCELLED` dicetak dengan watermark diagonal "DIBATALKAN".

---

## 9. CHECKLIST WIRING

- [ ] Trigger legacy sudah di-DROP di database target (blokir keras — cek di `AppServiceProvider::boot()` pada environment lokal, gagalkan boot bila masih ada).
- [ ] `UNIQUE (reference_name, reference_detail_id)` terpasang di ketiga `*_stockcard`.
- [ ] `inv_period` terisi untuk periode berjalan; `PeriodService::assertOpen()` dipanggil di seluruh jalur posting.
- [ ] `LocationPicker` menghormati `is_usable` di semua pemakaian.
- [ ] Layar Stock Out menolak `stockClass = 'FG'` di level route, bukan hanya di UI.
- [ ] Kolom nilai tidak ikut di `SELECT` untuk peran tanpa `INV.can_view_cost` — diverifikasi lewat query log, bukan inspeksi visual.
- [ ] `qty_system` disimpan ke DB, bukan dihitung ulang saat posting.
- [ ] Layar persetujuan berada di route berbeda dan tidak mengekspos aksi edit.
- [ ] Badge `🔁 PLT` muncul di `FlowActionBar` **dan** `FlowHistoryLog`.
- [ ] Uji regresi bug legacy: posting dokumen dua kali harus gagal bersih dengan pesan "dokumen sudah diposting", bukan menghasilkan saldo ganda.
- [ ] Uji: retur melebihi `qty_returnable` ditolak server meski validasi klien dilewati.
- [ ] Uji: substitusi + pembelian pada satu baris induk menghasilkan tepat satu baris kartu stok untuk substitusi dan nol untuk pembelian.
- [ ] Pratinjau "Dampak Kartu Stok" memakai `StockPostingService::preview()`, bukan perhitungan terpisah di komponen.
- [ ] `checklist_mockup_erp.md` MODUL 2 dan warna menu di shell diperbarui **bersamaan**.
