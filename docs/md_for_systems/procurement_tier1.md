# TIER 1 — PROCUREMENT · BUSINESS CONCEPT & SCOPE
**ERP Cetak Offset & Packaging — PT. Telasih Makmur** · Modul 04 · DB `tm2pur`

| | |
|---|---|
| **Tier** | 1 — Business Concept & Scope |
| **Basis** | Sesi arsitektur 2026-08-20 (Jo) + `GLOBAL_RULES` + `TIER_1/2_FLOW_ENGINE` + struktur legacy `tminv` & `m_raw_material` |
| **Deliverable** | `PROC_TIER_1..4.md` + `procurement_state.yaml` + `procurement_rules.yaml` |
| **Cakupan** | §1 Ruang lingkup · §2 Dokumen & prefix · §3 Rantai dokumen · §4 Aktor & visibilitas · §5 Integrasi lintas modul · §6 Nota · §7 Istilah · §8 Di luar scope |

---

## 1. RUANG LINGKUP

Procurement mengelola siklus **beli → terima → tagih**, dari permintaan internal sampai barang masuk stok dan tagihan supplier tercatat.

Berbeda dari Sales (yang dokumennya berpusat pada satu produk cetak per inquiry), Procurement bersifat **many-to-many secara alami**: satu permintaan bisa dipecah ke beberapa pesanan, beberapa permintaan bisa digabung ke satu pesanan, dan satu pesanan bisa diterima berkali-kali. Desain di Tier 2/3 secara sengaja **memecah M:N menjadi rantai 1:N berjenjang** (lihat §3) supaya penelusuran nilai tetap sederhana.

### 1.1 Yang dibeli
Tiga kelas item, dibedakan oleh `m_raw_material.item_type` + kategori level-1 (`doc_rmprefix1`):

| Kelas | Contoh | Masuk stok? | Pajak |
|---|---|---|---|
| **MATERIAL (RAW)** | Kertas, Sheet, Tinta, Lem, Chemical, Plate, Plastik | ✔ ya (`rm_stock`) | PPN |
| **MATERIAL (INDIRECT)** | ATK, sparepart mesin/kendaraan, tools, komputer, alat kebersihan | ✖ langsung beban | PPN |
| **SERVICE** | Jasa maintenance, subkon, service mesin | ✖ tanpa stok | PPN + **PPh 23** |

`FREE_SAMPLE` (item_type ketiga di master) = barang contoh dari supplier, masuk stok tanpa nilai beli — ditangani sebagai kasus khusus di Tier 2 §B-9.

### 1.2 Yang tidak terdaftar di master
Pembelian **sekali pakai** (mesin, laptop, renovasi) tidak dipaksa masuk hirarki kategori 5-level, karena hirarki itu didesain untuk item stok berulang. Line dokumen punya dua jalur: `MASTER_ITEM` atau `FREE_TEXT` (§Tier 2 §A-1).

---

## 2. DOKUMEN & PREFIX

Semua di `tm2pur` kecuali Purchase Invoice (lihat §5.3).

| Kode | Dokumen | Prefix tabel | Approval | Nota (Bahasa Indonesia) |
|---|---|---|---|---|
| **PR** | Purchase Requisition | `pr_requisition`, `pr_line`, `pr_line_alloc` | ✔ flow engine (threshold nilai) | **Permintaan Pembelian (PP)** |
| **PQ** | Purchase Quotation | `pq_quotation`, `pq_line` | ✖ (kondisional, boleh dilewati) | **Perbandingan Penawaran Harga (PH)** |
| **PO** | Purchase Order | `po_order`, `po_line`, `po_import_doc` | ✔ flow engine (threshold nilai) | **Pesanan Pembelian (PO)** |
| **PV** | Purchase Receive / Goods Receipt | `pv_receipt`, `pv_line` | ✖ (gerbangnya QC, bukan approval) | **Surat Penerimaan Barang (SPB)** |
| **PRT** | Purchase Return | `prt_return`, `prt_line` | ✔ ringan (1 step konfirmasi) | **Nota Retur Pembelian (NRB)** |
| **PI** | Purchase Invoice | `fin_invoice` + `fin_invoice_line` (**tm2acc**) | ✖ | **Faktur Pembelian (FP)** |

`module_code` **PR / PO / PV** sudah direservasi di `flowengine_tier1.md` §4 — dipakai apa adanya. `PRT` ditambahkan sebagai `module_code` baru.

Prefix `pr_` dipakai Requisition, sehingga Return memakai **`prt_`** (bukan `pr_`) untuk menghindari tabrakan.

---

## 3. RANTAI DOKUMEN

```
                          ┌──────────── PQ (opsional) ────────────┐
                          │  harga & masa berlaku per supplier    │
                          ▼                                       │
PR ──split──> pr_line_alloc ──1:1──> po_line ─┐                    │
(1 PR, N line)  (pecahan alokasi)             ├─> po_order <───────┘
                                              │   (N PR bisa gabung 1 PO)
                                              ▼
                                     po_line ──1:N──> pv_line ──> rm_stock (IN)
                                              │        (partial receive)
                                              │
                     ┌────────────────────────┴──────────────┐
                     ▼                                       ▼
        fin_invoice SUPPLIER_DP                  fin_invoice SUPPLIER_BILL
        (dari PO, sebelum barang datang)         (dari pv_line, realisasi terima)
                     └──── fin_dp_allocation ────┘
                                     │
                            prt_return (retur) ──> rm_stock (OUT) / potong nilai invoice
```

### 3.1 Kenapa split, bukan many-to-many
Satu `pr_line` (mis. Ivory 300gr 10.000 lembar) bisa dibeli dari **dua supplier berbeda**. Alih-alih membuat relasi M:N `pr_line ↔ po_line` (yang menyulitkan penelusuran nilai dan penutupan sisa), `pr_line` **dipecah** menjadi beberapa `pr_line_alloc`; **tiap alokasi menjadi tepat satu `po_line`** (1:1).

- Line yang tidak dipecah tetap punya **1 alokasi penuh** — jadi tak ada dua jalur logika.
- Status `pr_line` **diturunkan** dari agregat alokasinya (belum semua terkonversi = `PARTIALLY_ORDERED`).
- Konsolidasi tetap mungkin: **banyak `pr_line_alloc` dari PR berbeda → satu `po_order`** (bukan satu `po_line`) — arah gabungnya di header, bukan di baris.

### 3.2 Konsolidasi untuk approver PO
Satu PO hasil konsolidasi bisa memuat puluhan baris dari beberapa PR. Baris tetap **granular per sumber PR** (demi ketertelusuran nilai ke CO/departemen), tetapi approver melihat **`v1_po_item_consolidated`** — ringkasan qty & nilai per item, agar keputusan approval tidak tenggelam di detail.

### 3.3 Penelusuran nilai sampai penerimaan
`co_line_id` (Customer Order line, `tm2sdi`) menetes dari `pr_line` → `pr_line_alloc` → `po_line` → `pv_line`. Tujuannya: begitu barang diterima, **biaya material riil per Customer Order** dapat dihitung (realisasi), sebagai pembanding estimasi di Costing V2 (hulu).

`co_line_id` **nullable** — PR untuk stok umum, ATK, atau jasa tidak selalu terkait order customer.

---

## 4. AKTOR & VISIBILITAS

| Aktor | Peran |
|---|---|
| **Pemohon** (dept manapun) | Membuat PR |
| **Purchasing Officer** | PQ, PO, kirim ke supplier, catat PI |
| **Approver** (jabatan, via flow engine + threshold) | Approve PR & PO |
| **Petugas Gudang** | PV — hitung/timbang & QC qty |
| **QC** | QC kualitas di PV |
| **Operational Manager** | Monitor nilai penerimaan, override toleransi |
| **Accounting** | Monitor nilai, PI, DP, alokasi |

### 4.1 Visibilitas harga — Sisi A / Sisi B (WAJIB)
Mengikuti `GLOBAL_RULES.ui_conventions.sisi_visibilitas`:

- **Sisi B — layar gudang (PV entry):** petugas gudang **tidak melihat harga apa pun**. Hanya item, qty, UOM, lot, dan QC. Ini bukan sekadar kolom disembunyikan di Blade — **query layar gudang tidak menyertakan kolom harga sama sekali**.
- **Sisi A — layar monitor:** Operational Manager & Accounting melihat harga satuan dan nilai penuh per baris penerimaan.

Nilai **tetap tersimpan** di `pv_line` (`c_unit_price`, `c_line_value`) untuk setiap qty yang diterima — yang diatur adalah siapa yang boleh melihatnya, bukan apakah datanya ada.

---

## 5. INTEGRASI LINTAS MODUL

### 5.1 Inventory (`tm2inv`) — adopsi struktur legacy
PV **tidak membuat tabel pergerakan stok baru**. Struktur legacy diadopsi apa adanya karena sudah terbukti dan ringkas:

| Kelas stok | Header | Detail | Kartu stok |
|---|---|---|---|
| Bahan baku | `rm_stock` | `rm_stock_d` | `rm_stockcard` |
| Setengah jadi | `sfg_stock` | `sfg_stock_d` | `sfg_stockcard` |
| Barang jadi | `fg_stock` | `fg_stock_d` | `fg_stockcard` |

Pola legacy yang dipertahankan: `stat_confirm=1` → trigger mengisi `*_stockcard` (`qty_bb / qty_in / qty_out / qty_adj / qty_eb`). Pola penanda sumber juga dipertahankan: `rm_stock.notes = 'Auto dari PV: <no>'`, `rm_stock_d.reference_name = 'tm2pur.pv_line'`.

### 5.2 Engine harga perolehan (AVG / FIFO / LIFO)
Harga bahan berfluktuasi, sehingga nilai stok tak bisa memakai satu harga statis. Metode ditetapkan **per item** (`m_raw_material.costing_method`), bukan global — kertas dan sparepart wajar berbeda kebijakan.

- **AVG** — moving average; `rm_stockcard` diperluas dengan kolom nilai; `avg_unitprice` di master ter-update tiap IN.
- **FIFO / LIFO** — butuh lapisan lot: tabel **`inv_stock_layer`** (qty tersisa + harga per lot). OUT "memakan" layer sesuai urutan; `*_stockcard` tetap menjadi ringkasan saldo.

Engine ini **milik modul Inventory**; Procurement hanya **memanggil kontraknya** saat PV posting. Definisi lengkap layer & konsumsi OUT ada di `INV_TIER_*` (belum dibangun) — lihat issue `PROC-COSTING-ENGINE`.

### 5.3 Finance (`tm2acc`) — Purchase Invoice
Tidak ada tabel `pi_*` baru. `fin_invoice` **sudah** polymorphic AR+AP lewat `invoice_type`:
`CUSTOMER_INVOICE` · `SUPPLIER_BILL` · `CUSTOMER_DP` · `SUPPLIER_DP`.

- **SUPPLIER_DP** — uang muka ke supplier, terbit dari **PO** (barang belum datang, jadi tanpa referensi PV). Jurnal cermin dari Sales DP: `Dr 143 Uang Muka Pembelian`. Desainnya sudah ditulis di `downpayment_tier1-4.md` §5 tapi belum diimplementasi.
- **SUPPLIER_BILL** — tagihan final, terbit dari **PV** (realisasi terima, bukan estimasi PO). Boleh menggabungkan beberapa `pv_line` dari PV berbeda selama satu PO/supplier.
- **Alokasi DP → tagihan final** memakai `fin_dp_allocation` (M:N, sudah live & teruji di sisi Sales).

**Delta wajib:** `fin_invoice` saat ini **header-only**. Rincian baris (nama barang/produk, qty, harga satuan) dibutuhkan baik oleh Purchase maupun Sales → tabel baru **`fin_invoice_line`** (Tier 3 §6). Ini perubahan lintas modul terhadap `tm2acc` yang sudah live, dicatat di `financeaccounting_state.yaml` maupun `procurement_state.yaml`.

### 5.4 Production / MRP
PR punya `i_source ENUM(MANUAL, MRP)`. Saat ini **seluruhnya MANUAL** karena MRP belum dibangun; kolom `mrp_run_id` disiapkan nullable agar penyambungan nanti tidak perlu perubahan struktur.

### 5.5 Master & sistem (`tm2mst`, `tm2sys`)
- `m_raw_material` — item master (kode, UOM beli/stok/pakai, konversi, **`tol_purchase`**, harga rata-rata & terakhir, supplier rekomendasi).
- `doc_rmprefix1..5` — pembentuk **kode item** 5 level (kategori → jenis → gramatur/spec → ukuran → merk), mis. `P`+`Y`+`J`+`3`+`1` = `PYJ31`.
- `sup_supplier` — master supplier.
- `sys_constant` — **tarif pajak** (PPN, PPh 23) dan **toleransi penerimaan default**.
- `flow_config` / `flow_config_step` / `flow_log` — approval & riwayat status.

---

## 6. NOTA (CETAKAN)

Setiap dokumen wajib punya nota sendiri, **berbahasa Indonesia**, sebagai VIEW `vw_nota_*` + layout cetak (Tier 4 §7). Mode layout **TERPUSAT** (dokumen), bukan full-page.

| Dokumen | Judul nota | Fungsi |
|---|---|---|
| PR | Permintaan Pembelian | Bukti permintaan + jejak approval |
| PQ | Perbandingan Penawaran Harga | Bahan keputusan pemilihan supplier |
| PO | Pesanan Pembelian | **Dikirim ke supplier** — dokumen eksternal |
| PV | Surat Penerimaan Barang (SPB) | Bukti terima; **tanpa harga** (Sisi B) |
| PRT | Nota Retur Pembelian | Bukti pengembalian barang ke supplier |
| PI | Faktur Pembelian | Rekap tagihan + rincian baris |
| Kontan | Bukti Pembelian Kontan | Gabungan PO+PV dalam satu lembar |

Istilah **SPB** diambil dari penomoran legacy (`26SPB00007`) agar tidak membingungkan pengguna lama.

---

## 7. ISTILAH BAKU

| Istilah | Definisi |
|---|---|
| **Split / Alokasi** | Pemecahan satu `pr_line` menjadi beberapa `pr_line_alloc`, tiap alokasi menuju satu `po_line` |
| **Konsolidasi** | Penggabungan alokasi dari beberapa PR ke satu `po_order` (per supplier) |
| **Toleransi terima** | Batas selisih qty terima vs qty PO yang masih dianggap wajar; per item (`tol_purchase`), fallback `sys_constant` |
| **Dual-UOM** | Penerimaan dengan dua satuan tak-terkonversi: satuan beli (untuk menutup PO/tagihan) dan satuan stok (untuk masuk gudang) |
| **QC qty** | Verifikasi jumlah diterima (hitung/timbang) |
| **QC kualitas** | Inspeksi mutu; memecah qty menjadi diterima / ditolak |
| **PO Kontan** | Pembelian tunai tanpa PR/PQ; PO & PV dibuat otomatis agar tetap tertelusur dan masuk stok |
| **Sisi A / Sisi B** | A = nilai/harga terlihat (Manajemen, Accounting); B = nilai disembunyikan (Gudang) |

---

## 8. DI LUAR SCOPE (fase ini)

| Hal | Alasan |
|---|---|
| **Posting GL otomatis dari PV/PI** | AP & posting otomatis masih fase-2 Finance (`FIN_RULES.integrasi.keputusan_A`). PI mencatat tagihan & DP, belum menjurnal |
| **Bea Cukai / PIB / dokumen impor aktif** | Jarang impor. Struktur `po_import_doc` disiapkan tapi **INACTIVE**; BM/PPN impor/PPh 22 tidak dihitung |
| **Kontrak & harga jangka panjang (blanket PO)** | Belum dibutuhkan |
| **Supplier performance / rating** | Menyusul |
| **Konsumsi FIFO/LIFO di sisi OUT** | Milik modul Inventory; Procurement hanya mengisi layer saat IN |

---

## 9. REFERENSI

- `globalstarthere.md`, `globalrules.yaml`
- `flowengine_tier1.md`, `flowengine_tier2.md`, `flowengine_tier3.md`
- `downpayment_tier1-4.md` (§5 PURCHASE DP mirror), `financeaccounting_rules.yaml`, `financeaccounting_state.yaml`
- `prefixlist.md`
- Legacy: `tminv_20260722_2018_sd.sql`, `m_raw_material.sql`, `doc_rmprefix1.sql`
