# TIER 4 — PROCUREMENT · SPEC LAYAR & BINDING LIVEWIRE
**PT. Telasih Makmur** · Modul 04 · `tm2pur`

| | |
|---|---|
| **Basis** | `PROC_TIER_1..3.md` · konvensi UI `GLOBAL_RULES` |
| **Stack** | Laravel + Livewire + Alpine + Tailwind (Vite) |
| **Cakupan** | §1 Konvensi · §2 PR · §3 PO Entry · §4 PO Approval · §5 PV · §6 PQ/PRT/Kontan · §7 Nota · §8 Checklist wiring |

---

## 1. KONVENSI WAJIB

| Aspek | Aturan |
|---|---|
| **Warna** | navy `#2c3e69` (`--navy`) · accent `#2f6fed` (`--accent`) |
| **Layout** | Grid & layar operasional = **FULL-PAGE** (`w-full` + padding). **JANGAN** memakai class `container` Tailwind (penyebab bug margin Edge). Nota/form terpusat boleh `max-width`+`mx-auto` |
| **Shell** | `layouts.shell` untuk seluruh layar modul |
| **Toolbar** | New / Edit / Detail — **nonaktif sampai satu baris grid dipilih** |
| **Tabel** | pola `.qtbl` |
| **Topbar** | sticky + breadcrumb (pola `inquiry_hub`) |
| **Badge** | 🔒 terkunci (abu) · ◆ selector (kuning) · ⚙ engine (ungu) · = hasil (hijau) |
| **Grid** | Server-side pagination (`WithPagination`) + pencarian sebagai `WHERE`, **bukan** filter koleksi. Index DB wajib |
| **Route prefix** | `/purchasing/...` |

### 1.1 Peta layar
| Route | Komponen | Fungsi |
|---|---|---|
| `/purchasing/pr` | `Pages\Purchasing\PrIndex` | Grid PR |
| `/purchasing/pr/{id}` | `PrForm` | Entry/edit PR + split alokasi |
| `/purchasing/pr-approval` | `PrApproval` | Antrian approval PR |
| `/purchasing/pq` | `PqIndex` / `PqForm` | Penawaran + pembanding |
| `/purchasing/po` | `PoIndex` | Grid PO |
| `/purchasing/po/{id}` | `PoForm` | **Entry PO** (§3) |
| `/purchasing/po-approval` | `PoApproval` | **Approval PO — layar terpisah** (§4) |
| `/purchasing/pv` | `PvIndex` | Grid penerimaan |
| `/purchasing/pv/{id}` | `PvForm` | Terima + QC (Sisi B) |
| `/purchasing/pv-monitor` | `PvMonitor` | Monitor nilai (Sisi A) |
| `/purchasing/prt` | `PrtIndex` / `PrtForm` | Retur |
| `/purchasing/cash` | `CashPurchase` | Pembelian Kontan |

---

## 2. LAYAR PR

### 2.1 Grid (`PrIndex`)
Kolom: No PR · Tanggal · Departemen · Sumber (MANUAL/MRP) · Jml Baris · Est. Nilai · Status · Dibutuhkan.
Filter: status, departemen, rentang tanggal, pencarian (no/deskripsi item).
Badge status: `DRAFT` abu · `SUBMITTED` kuning · `APPROVED` hijau · `REJECTED` merah · `PARTIALLY_ORDERED` biru muda · `CLOSED` abu tua.

### 2.2 Form (`PrForm`)
**Header:** Tanggal · Departemen (default dari user) · Tgl Dibutuhkan · Catatan. `Sumber` 🔒 terkunci `MANUAL`.

**Baris — komponen kunci `ItemPicker`:**
```
[ ○ Item Master   ● Teks Bebas ]      <- i_item_source
┌── bila Item Master: cascading 5 level ──────────────────┐
│ Kategori ▾   Jenis ▾   Gramatur ▾   Ukuran ▾   Merk ▾   │
│ → Kode terbentuk: [ PYJ31 ]  ⚙                          │
│ → auto: nama, UOM beli, UOM stok, konversi, toleransi,  │
│         harga terakhir (referensi), supplier saran      │
└─────────────────────────────────────────────────────────┘
┌── bila Teks Bebas ──────────────────────────────────────┐
│ Deskripsi [.....] UOM [.....]                           │
│ ⚠ Departemen & Akun COA WAJIB diisi manual              │
└─────────────────────────────────────────────────────────┘
```
Tiap dropdown level hanya memuat anak dari level sebelumnya. Bila item `is_fx_sensitive=1` → tampil ⚠ *"Harga mengikuti kurs — pastikan penawaran terbaru."*

Kolom baris: Qty · UOM · Est. Harga (opsional) · Est. Subtotal `=` · Departemen · Akun COA · CO Line (opsional, pencarian) · Tgl Dibutuhkan · Catatan.
Footer: **Total Estimasi** `=` — dijelaskan sebagai dasar tingkat approval.

**Panel Split Alokasi** (muncul saat status `APPROVED`):
```
Baris #1 — PYJ31 Ivory 300gr 79x109   Qty 10.000 LBR
┌───────────────────────────────────────────────┐
│ Alokasi 1  Qty [ 6.000 ]  Supplier [ A ▾ ]    │
│ Alokasi 2  Qty [ 4.000 ]  Supplier [ B ▾ ]    │
│ + Tambah alokasi        Sisa: 0  ✔            │
└───────────────────────────────────────────────┘
```
Validasi langsung: Σ alokasi **tidak boleh** melebihi qty baris; sisa ditampilkan berjalan.

### 2.3 Approval (`PrApproval`)
Antrian dokumen yang menunggu jabatan user (termasuk PLT). Baca-saja + panel keputusan: **Approve** / **Reject** (alasan **wajib**). Menampilkan riwayat `flow_log` dan step berjalan.

---

## 3. LAYAR PO — ENTRY (`PoForm`)

Detail penuh. Terpisah dari layar approval.

**Bagian 1 — Sumber:** memilih `pr_line_alloc` `APPROVED` & belum terkonversi, dari `v1_pr_outstanding`, difilter supplier. Multi-select lintas PR (konsolidasi). Peringatan bila supplier berbeda.

**Bagian 2 — Header:**
| Kolom | Catatan |
|---|---|
| Supplier ◆ | wajib; mengunci pilihan alokasi |
| Penawaran (PQ) ◆ | opsional; bila dipilih → mengisi harga, mata uang, kurs, **masa berlaku** |
| Tipe PO | LOCAL / IMPORT |
| Tipe Pengadaan | REPEAT / ONE_TIME / CAPEX |
| Mata Uang + Kurs | 🔒 dari PQ; dapat diubah selama `DRAFT` |
| **Masa berlaku penawaran** | 🟢 berlaku / 🔴 kadaluarsa — **badge mencolok** |
| Syarat Bayar · Tgl Kirim · Kirim Ke | |

Bila jarak PQ → PO terlalu jauh: 🔴 *"Kurs mungkin sudah berubah — periksa penawaran terbaru."* (peringatan, **bukan** blokir).

**Bagian 3 — Baris:** Item · Kelas (RAW/INDIRECT/**SERVICE**) · Qty · UOM · Harga · Disc % / Rp · **Net** `=` · Dept · Akun COA · CO Line · asal PR (🔒 no PR + no baris).

**Bagian 4 — Ringkasan pajak** (kanan bawah, semua `=`):
```
DPP                    xx.xxx.xxx
PPN 11%                 x.xxx.xxx      <- sys_constant
─────────────────────────────────
Total Tagihan          xx.xxx.xxx
PPh 23 (jasa) 2%         (xxx.xxx)     <- hanya baris SERVICE
─────────────────────────────────
Dibayar ke Supplier    xx.xxx.xxx
```

**Bagian 5 — Dokumen impor:** hanya bila `IMPORT`; checklist `po_import_doc` (**INACTIVE** fase ini — tampil sebagai catatan, tidak memblokir).

Tombol: Simpan · **Submit** · Hapus (`DRAFT`) · Cetak PO · Revise.

---

## 4. LAYAR PO — APPROVAL (`PoApproval`)

**Layar terpisah, sama detailnya dengan entry, tetapi baca-saja.** Tak ada satu pun field dapat diedit; tak ada tombol Simpan.

Susunan dari atas:
1. **Panel keputusan (sticky)** — Approve · Reject (alasan wajib) · step berjalan · nilai `amount_snapshot`.
2. **Papan peringatan:**
   - 🔴/🟢 masa berlaku penawaran + tanggalnya,
   - ⚠ kurs (bila mata uang asing) — kurs snapshot vs kurs terkini,
   - ⚠ tipe pengadaan `CAPEX` (nilai besar, non-rutin),
   - ⚠ kelengkapan dokumen impor (bila IMPORT).
3. **Ringkasan konsolidasi** — `v1_po_item_consolidated`: qty & nilai **per item**, rentang harga min–max. Inilah yang dibaca approver lebih dulu; detail baris ada di bawahnya.
4. **Detail baris penuh** (dapat dilipat) — termasuk asal PR & departemen tiap baris.
5. **Perbandingan penawaran** — bila ada >1 PQ untuk item sama, tampil pembanding harga antar supplier (sudah dikonversi IDR).
6. **Ringkasan pajak** — identik dengan entry.
7. **Riwayat `flow_log`.**

> Approve/Reject **tidak pernah** muncul di layar entry, dan hanya aktif untuk actor hasil resolusi flow engine.

---

## 5. LAYAR PV

### 5.1 Entry gudang (`PvForm`) — **SISI B, TANPA HARGA**
> **Aturan keras:** query layar ini **tidak menyertakan** `c_unit_price` / `c_line_value`. Bukan disembunyikan lewat CSS — memang tidak diambil.

Header: No SPB (auto) · Tanggal · **PO** ◆ (dari `v1_po_outstanding`) · Supplier 🔒 · Gudang · No Surat Jalan Supplier · No Kendaraan.

Baris — panel dua satuan:
```
PYJ31 Ivory 300gr            Sisa PO: 10.000 LBR
┌──────────────── Satuan Beli ────────────────┐
│ Qty [ 3 ]  UOM [ TRUK ] 🔒     (menutup PO)  │
└─────────────────────────────────────────────┘
┌──────────────── Satuan Stok ────────────────┐
│ Qty [ 14.850 ]  UOM [ KG ] 🔒  (masuk gudang)│
│ ☑ Timbang manual (abaikan konversi master)  │
│   konversi master: 5.000 KG/TRUK → est 15.000│
└─────────────────────────────────────────────┘
Lot [ ...... ]   Lokasi [ ▾ ]
```
Untuk item biasa kedua sisi terisi otomatis dari konversi; panel tetap tampil agar konsisten. Barang curah → centang **Timbang manual**.

**Panel QC:**
```
QC Qty       [ Tandai Selesai ]
QC Kualitas  ( ) PASS  ( ) PARTIAL  ( ) FAIL
   Diterima [ 14.500 ] KG      Ditolak [ 350 ] KG
   Catatan  [ ....................................... ]
```
Diterima + Ditolak **wajib** sama dengan qty stok.

**Panel toleransi** (muncul bila terlampaui):
```
⚠ Selisih 7,2% melebihi toleransi item (5%)
   Alasan [ .......................... ]
   [ Override ]   ← hanya can_approve PV (Manajer Operasional)
```

Tombol **POST** terkunci selama: ada baris QC `PENDING`, **atau** toleransi terlampaui tanpa override. Tooltip menjelaskan alasan terkunci.
Setelah POST: seluruh layar baca-saja + tombol **Cetak SPB** dan **Buat Retur**.

### 5.2 Monitor (`PvMonitor`) — **SISI A**
Untuk Manajer Operasional & Accounting (`PV.can_view_cost`). Kolom: No SPB · Tanggal · Supplier · Item · Qty Diterima · **Harga Satuan** · **Nilai** · Selisih toleransi · CO terkait · Status tagih (`c_qty_invoiced`).
Ringkasan: nilai penerimaan per periode, per supplier, **per CO** (`v1_material_cost_per_co`), dan daftar baris yang melampaui toleransi.

---

## 6. LAYAR LAIN

### 6.1 PQ (`PqForm`)
Header: Supplier · **Masa Berlaku** (wajib) · Mata Uang · **Kurs** (auto-isi, dapat di-override, tampil sumber & tanggal kurs) · Syarat Bayar.
Baris: Item · Qty · UOM · Harga (mata uang asing) · **Harga IDR** `=` · Lead time.
Tab **Perbandingan**: bila ada beberapa PQ untuk PR yang sama → tabel silang supplier × item dalam IDR; harga terendah per item ditandai hijau. Keputusan tetap di tangan officer (engine menyarankan, officer mengonfirmasi).

### 6.2 PRT (`PrtForm`)
Header: Sumber (`QC_REJECT` / `FROM_STOCK`) · PV asal ◆ · Supplier 🔒 · **Disposisi** (`REPLACEMENT` / `CREDIT_NOTE`) · Alasan (wajib).
Baris: dari qty ditolak PV (otomatis), atau pilih item dari stok. Menampilkan `is_stock_moved` — menentukan perlu `rm_stock` OUT atau tidak.
`CREDIT_NOTE` → tampil **Nilai Kredit** `=`. `REPLACEMENT` → kolom PV pengganti (terisi belakangan).

### 6.3 Pembelian Kontan (`CashPurchase`)
Satu form ringkas — tanpa PR/PQ.
Isi: Tanggal · Supplier/Toko · Item (Master **atau** Teks Bebas) · Qty · Harga · Dept · Akun COA · Gudang (bila barang stok).
Tombol **Simpan & Posting** → membuat otomatis PO (`CASH`, `APPROVED`) + PV (`POSTED`) + pergerakan stok.
Setelah simpan: tampil **Bukti Pembelian Kontan** (satu lembar) + tautan ke PO/PV yang terbentuk.
Banner: *"PO & SPB dibuat otomatis agar pembelian tetap tercatat dan masuk stok."*

---

## 7. NOTA (CETAKAN)

Mode **TERPUSAT** (`max-width` + `mx-auto`), A4 portrait, judul Bahasa Indonesia, kop perusahaan.

| Nota | VIEW | Isi khas | Tanda tangan |
|---|---|---|---|
| **Permintaan Pembelian** | `vw_nota_pr` | Departemen, kebutuhan, baris + qty, est. nilai | Pemohon · Ka. Dept · Direksi |
| **Perbandingan Penawaran Harga** | — (dari `PqIndex`) | Matriks supplier × item (IDR), masa berlaku | Purchasing · Manajer |
| **Pesanan Pembelian** | `vw_nota_po` | **Dokumen eksternal** — supplier, syarat bayar, tgl kirim, baris + harga + diskon, DPP/PPN/PPh, total terbilang | Purchasing · Manajer · Direksi |
| **Surat Penerimaan Barang (SPB)** | `vw_nota_pv` | No PO, supplier, no surat jalan, **dua satuan**, lot, hasil QC — **TANPA HARGA** | Pengirim · Gudang · QC |
| **Nota Retur Pembelian (NRB)** | `vw_nota_prt` | PV asal, alasan, disposisi, qty, nilai kredit | Gudang · Purchasing · Supplier |
| **Faktur Pembelian** | `fin_invoice` + `fin_invoice_line` | Rincian baris (nama barang, qty, @harga), DP teralokasi, sisa tagihan | Accounting |
| **Bukti Pembelian Kontan** | gabungan PO+PV | Satu lembar mewakili keduanya | Pembeli · Accounting |

Angka uang: format Indonesia (`1.234.567,89`). Nilai total PO disertai **terbilang**.

---

## 8. CHECKLIST WIRING

**Model** (`App\Models\Pur\`): `PrRequisition`, `PrLine`, `PrLineAlloc`, `PqQuotation`, `PqLine`, `PoOrder`, `PoLine`, `PoImportDoc`, `PvReceipt`, `PvLine`, `PrtReturn`, `PrtLine` — seluruhnya `protected $connection = 'tm2pur'`.

**Service** (`App\Services\Pur\`):
| Service | Tanggung jawab |
|---|---|
| `PrAllocationService` | Split/gabung alokasi + validasi Σ qty |
| `PoBuilderService` | Alokasi → `po_line` (1:1), konsolidasi lintas PR |
| `PoTaxCalculator` | DPP/PPN/PPh/net payable dari `sys_constant` |
| `PvPostingService` | **Kontrak POST → `tm2inv`** (Tier 3 §5.1), transaksional |
| `StockCostingService` | AVG / FIFO / LIFO (kontrak modul Inventory) |
| `ToleranceService` | Batas per item + fallback + override |
| `CashPurchaseService` | Auto-generate PO + PV |
| `DocNumberService` | `sys_sequence` untuk seluruh dokumen |

**Livewire**: sesuai peta §1.1. **Menu** `sys_app_menu`: grup **Pembelian** → Permintaan Pembelian · Penawaran · Pesanan Pembelian · Approval PO · Penerimaan Barang · Monitor Penerimaan · Retur Pembelian · Pembelian Kontan.

**Permission**: `PR`, `PO`, `PV`, `PRT` × (`can_access`, `can_input`, `can_approve`, `can_export`) + **`PV.can_view_cost`** (Manajer Operasional & Accounting saja — **jangan** ke gudang).

**Urutan implementasi yang disarankan:**
1. Master & delta DB (Tier 3 §7, §10) → 2. `ItemPicker` 5 level (dipakai semua layar) → 3. PR + split → 4. Flow engine PR → 5. PQ → 6. PO entry → 7. PO approval → 8. PV + QC + posting stok → 9. PV monitor → 10. PRT → 11. Pembelian Kontan → 12. Nota → 13. Purchase Invoice (`tm2acc`, setelah `fin_invoice_line` ada).
