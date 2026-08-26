# TIER 2 — PROCUREMENT · BUSINESS LOGIC & RULES
**PT. Telasih Makmur** · Modul 04 · DB `tm2pur`

| | |
|---|---|
| **Basis** | `procurement_tier1.md` · `flowengine_tier2.md` · `globalrules.yaml` |
| **Struktur** | **Part A** happy path · **Part B** kasus khusus · **Part C** state → form → button |

---

# PART A — HAPPY PATH

## A-1. PR — Purchase Requisition

### A-1.1 Header
| Field | Aturan |
|---|---|
| `doc_no` | Auto `PR/YY/#####` via `sys_sequence`, di-generate saat **SUBMIT** (bukan saat DRAFT) |
| `i_doc_date` | Default hari ini |
| `i_source` | `MANUAL` (default) / `MRP`. MRP belum aktif |
| `i_department_id` | Departemen peminta — **default yang menetes ke baris** |
| `i_need_date` | Tanggal dibutuhkan |
| `i_notes` | Bebas |

### A-1.2 Baris — dua jalur sumber item
`pr_line.i_item_source`:

**(a) `MASTER_ITEM`** — item stok berulang, dipilih lewat **picker berjenjang 5 level**:
```
prefix1 (Kategori)  →  prefix2 (Jenis)  →  prefix3 (Gramatur/Spec)
        →  prefix4 (Ukuran)  →  prefix5 (Merk)  →  material_code (mis. PYJ31)
```
Sistem mengisi otomatis dari `m_raw_material`: nama, `uom_purchase`, `uom_stock`, `conversion_to_stock`, `tol_purchase`, `last_unitprice` (sebagai referensi estimasi), `suggest_supplier_id`.

**(b) `FREE_TEXT`** — pembelian sekali pakai (mesin, laptop, renovasi, jasa tak terdaftar). Tanpa hirarki kategori sama sekali.
- `i_description` wajib · `i_uom` diketik manual · `item_id` NULL
- `i_department_id` dan `i_coa_account_id` **wajib manual** (tak ada prefix1 yang menuntun ke akun)
- Otomatis `is_stock=0` — tak pernah masuk `rm_stock`

> Aturan ini menutup masalah "beli mesin susah dimasukkan kategori": barang sekali beli **tidak dipaksa** masuk `doc_rmprefix*`.

### A-1.3 Klasifikasi beban
- `i_department_id` — pengelompokan / cost center peminta.
- `i_coa_account_id` — akun COA (`tm2acc.fin_account`, hanya `is_postable=1`).
- **Beban vs overhead diturunkan dari klasifikasi akun COA**, bukan picker terpisah.
- `MASTER_ITEM` → akun **disarankan otomatis** dari `doc_rmprefix1.stock_class` + `default_coa_account_id`; officer boleh mengubah.
- `FREE_TEXT` → wajib pilih manual.

### A-1.4 Estimasi nilai (untuk threshold approval)
`i_est_price` **opsional** (harga riil baru pasti di PQ/PO). `c_est_total = Σ(i_qty × i_est_price)` menjadi `amount_snapshot` dokumen PR saat submit — dasar pemilihan step approval berdasarkan threshold (`TIER_2_FLOW_ENGINE` §3).

Jika seluruh baris tanpa estimasi harga → `c_est_total = 0` → hanya step approval `min_amount=0` yang berjalan.

### A-1.5 Submit & approval
SUBMIT → flow engine `module_code='PR'`:
`DRAFT → SUBMITTED → APPROVED` (atau `REJECTED` → `REVISION_NEEDED`).
Approver = **jabatan** (`org_position`) + PLT, bukan username. Riwayat ke `flow_log`.

---

## A-2. PQ — Purchase Quotation (kondisional)

PQ **boleh dilewati** bila harga sudah pasti (reorder rutin, harga kontrak) — PO dapat dibuat langsung dari PR `APPROVED`.

| Field | Aturan |
|---|---|
| `i_supplier_id` | Satu PQ = satu supplier. Membandingkan ≥2 supplier = beberapa PQ untuk PR yang sama |
| `i_quotation_valid_until` | **Masa berlaku penawaran** — wajib; menjadi snapshot di PO |
| `i_currency_id` | Default IDR |
| `i_fx_rate` | Terisi otomatis dari `fin_currency_rate` terakhir; **boleh di-override** (kurs riil supplier sering beda dari kurs acuan) |
| `pq_line.i_price_fx` | Harga dalam mata uang penawaran |
| `pq_line.c_price_idr` | `i_price_fx × i_fx_rate` — dihitung langsung saat entry |

**Perbandingan multi-supplier:** semua penawaran dikonversi ke IDR memakai rate snapshot masing-masing, agar setara meski mata uang asalnya berbeda.

**Peringatan fluktuasi:** item dengan `m_raw_material.is_fx_sensitive=1` diberi tanda ⚠ di PR/PQ ("harga mengikuti kurs — pastikan penawaran terbaru").

---

## A-3. PO — Purchase Order

### A-3.1 Pembentukan
1. Officer memilih `pr_line_alloc` berstatus `APPROVED` & belum terkonversi — **boleh lintas PR**, syarat: **supplier sama**.
2. Tiap alokasi → **tepat satu `po_line`** (1:1).
3. `pr_line` diperbarui statusnya (turunan agregat alokasi).

### A-3.2 Header
| Field | Aturan |
|---|---|
| `i_supplier_id` | Wajib; seluruh baris harus supplier yang sama |
| `i_po_type` | `LOCAL` / `IMPORT` |
| `i_procure_type` | `REPEAT` (bahan baku rutin) / `ONE_TIME` / `CAPEX` (mesin/aset) / `CASH` (kontan, §B-8) |
| `i_currency_id`, `i_fx_rate` | **Snapshot dari PQ**. Independen dari `i_po_type` — supplier LOKAL boleh berharga USD (mis. kertas mengikuti harga pulp dunia) |
| `i_quotation_valid_until` | Snapshot dari PQ — **ditampilkan mencolok di layar approval** |
| `i_payment_term`, `i_delivery_date` | Syarat bayar & jadwal kirim |

### A-3.3 Perhitungan (per baris, karena satu PO bisa campur barang + jasa)
```
line_gross   = i_qty × i_price
line_disc    = i_disc_amt  (atau line_gross × i_disc_pct)
c_line_net   = line_gross − line_disc
─────────────────────────────────────────────────────────
c_dpp         = Σ c_line_net                       (dasar pengenaan pajak)
c_ppn         = c_dpp × rate PPN                   (selalu)
c_pph         = Σ(c_line_net baris SERVICE) × rate PPh 23   (hanya jasa)
c_grand_total = c_dpp + c_ppn                      (nilai tagihan supplier)
c_net_payable = c_grand_total − c_pph              (yang benar-benar dibayar; PPh disetor ke kas negara)
```
Tarif dibaca dari **`sys_constant`** (`PPN`, `PPH23_JASA`) — tidak di-hardcode.

### A-3.4 Approval
SUBMIT → flow engine `module_code='PO'`, `amount_snapshot = c_grand_total` (nilai riil, bukan estimasi PR).
`DRAFT → SUBMITTED → APPROVED → SENT`.

**Layar Entry dan layar Approval TERPISAH**, keduanya sama-sama detail (Tier 4 §3 & §4). Approval bersifat baca-saja + panel keputusan, dilengkapi:
- ringkasan **`v1_po_item_consolidated`** (qty & nilai per item),
- **status masa berlaku penawaran** (🟢 berlaku / 🔴 kadaluarsa),
- kelengkapan dokumen impor bila `IMPORT`.

---

## A-4. PV — Purchase Receive

### A-4.1 Dual-UOM (dua satuan tak-terkonversi)
Setiap `pv_line` menyimpan **dua sisi independen**:

| Sisi | Kolom | Fungsi |
|---|---|---|
| **Beli** | `i_qty_purchase`, `i_uom_purchase` | Menutup `po_line`, dasar tagihan |
| **Stok** | `i_qty_stock`, `i_uom_stock` | Yang benar-benar masuk gudang |

Contoh: beli **3 TRUK pasir**, tiap truk ditimbang berbeda → sisi beli `3 TRUK`, sisi stok `= Σ hasil timbang (KG)`. Boleh dipecah menjadi tiga baris (satu per truk) bila timbangan dicatat terpisah.

`m_raw_material.conversion_to_stock` dipakai sebagai **nilai awal/estimasi saja** dan **selalu boleh ditimpa manual** — untuk barang curah konversi statis tidak dapat diandalkan.

Item normal: kedua sisi sama, konversi otomatis, tanpa intervensi.

### A-4.2 QC dua gerbang
| Gerbang | Isi | Hasil |
|---|---|---|
| **QC qty** | Hitung / timbang | Mengisi `i_qty_stock` aktual |
| **QC kualitas** | Inspeksi mutu | Memecah menjadi `c_qty_accepted` / `c_qty_rejected` (satuan stok) |

`qc_quality_status`: `PENDING → PASS / PARTIAL / FAIL`.
**POST diblokir selama masih ada baris `PENDING`.**

### A-4.3 Toleransi qty
```
c_qty_variance_pct = (i_qty_purchase − qty_outstanding_po) / qty_po × 100
batas = COALESCE(m_raw_material.tol_purchase, sys_constant.RECEIVE_TOLERANCE_PCT)   -- default 5
```
- **Dalam batas** → boleh POST; `po_line` dianggap terpenuhi (sisa kecil ditutup).
- **Di luar batas** → wajib **override + alasan** oleh Operational Manager (`can_approve` PV); atau sisa dibiarkan terbuka untuk kiriman berikutnya.
- Kelebihan di luar batas → **diblokir** sampai di-override.

Toleransi **per item** (`tol_purchase` di master), bukan satu angka global.

### A-4.4 Nilai
`c_unit_price` = snapshot dari `po_line`. `c_line_value = c_qty_accepted × c_unit_price`.
Tersimpan selalu; **tidak ditampilkan di layar gudang** (Sisi B, §Tier 1 §4.1).

### A-4.5 POST → stok
Hanya **`c_qty_accepted`** yang masuk stok. Untuk baris `is_stock=1`:

```
INSERT rm_stock   (doc_type=1, doc_cat='STOCK', doc_no=<no PV>,
                   warehouse_id, notes='Auto dari PV: <no>', stat_confirm=1)
INSERT rm_stock_d (material_id, qty=c_qty_accepted, uom_code=i_uom_stock,
                   unit_price=c_unit_price, qty_unitprice=c_line_value,
                   lot_no, reference_id=pv_line_id,
                   reference_name='tm2pur.pv_line', location_id)
→ trigger legacy mengisi rm_stockcard (qty_bb/in/out/adj/eb)
→ engine harga perolehan: AVG (perbarui avg_unitprice) atau FIFO/LIFO (INSERT inv_stock_layer)
```
Baris `INDIRECT` / `SERVICE` / `FREE_TEXT` → `is_stock=0`, tanpa pergerakan stok.

---

## A-5. PI — Purchase Invoice (`tm2acc`)

| Tipe | Sumber | Baris |
|---|---|---|
| `SUPPLIER_DP` | **PO** (`source_po_id`) — barang belum datang | **Tanpa baris** (uang muka belum terikat barang tertentu) |
| `SUPPLIER_BILL` | **PV** (`fin_invoice_line.pv_line_id`) — realisasi terima | Rincian per baris: nama barang, qty, harga satuan |

- Satu tagihan boleh menggabungkan beberapa `pv_line` **dari PV berbeda**, selama satu PO/supplier (supplier lazim menagih beberapa kiriman sekaligus).
- `pv_line.c_qty_invoiced` diakumulasi → sisa yang belum tertagih tetap terlihat.
- Alokasi DP → tagihan final memakai **`fin_dp_allocation`** (M:N, sudah live di sisi Sales), dengan pengaman agar total alokasi ≤ nilai DP.
- `fin_invoice.subtotal` / `tax_amount` menjadi **hasil hitung dari `fin_invoice_line`**, bukan input manual.

---

# PART B — KASUS KHUSUS

**B-1. Split satu baris ke banyak supplier.** `pr_line` 10.000 lembar → alokasi 6.000 (Supplier A) + 4.000 (Supplier B) → dua `po_line` di dua PO. `pr_line` `PARTIALLY_ORDERED` sampai seluruh alokasi terkonversi. Σ alokasi **tidak boleh melebihi** `pr_line.i_qty`.

**B-2. Konsolidasi lintas PR.** Alokasi dari PR-001, PR-004, PR-009 (supplier sama) → satu `po_order`. Tiap `po_line` tetap membawa `pr_line_alloc_id`, `department_id`, `coa_account_id`, dan `co_line_id` masing-masing — konsolidasi tak pernah melebur asal-usul.

**B-3. Penerimaan bertahap.** Satu `po_line` 10.000 → PV#1 6.000, PV#2 3.800. `c_qty_received` diakumulasi; `c_qty_outstanding = i_qty − c_qty_received`. PO `PARTIALLY_RECEIVED` → `RECEIVED` saat seluruh baris terpenuhi (termasuk yang ditutup lewat toleransi).

**B-4. Barang ditolak QC.** `c_qty_rejected > 0` → **tidak masuk stok**, menjadi kandidat PRT. Sisa PO tetap terbuka (barang belum benar-benar diterima).

**B-5. Purchase Return.** Sumber ganda:
- dari `pv_line` yang ditolak QC (**belum pernah masuk stok** → tanpa pergerakan stok), atau
- dari stok (cacat diketahui belakangan → **`rm_stock` OUT, `doc_cat='RETURN'`**).

`i_disposition`:
- `REPLACEMENT` → menunggu barang pengganti (tertaut ke PV berikutnya),
- `CREDIT_NOTE` → `c_credit_value` mengurangi nilai tagihan supplier (kait AP, fase-2).

PRT **tidak mengenal REVISE** (barang fisik sudah bergerak); koreksi = Stock Adjustment di Inventory.

**B-6. PR ditutup otomatis.** Saat seluruh PO turunannya `RECEIVED`, PR → `CLOSED` lewat step `AUTO` flow engine (`flow_log.action='AUTO_ADVANCED'`, `actual_user_id` NULL) — persis contoh di `TIER_2_FLOW_ENGINE` §5.

**B-7. REVISE.** Berlaku untuk PR & PO: **copy-record**, `revision_no+1`, versi lama `is_cancelled=1`, `cancel_reason='SUPERSEDED'`. Dokumen `confirmed` tak pernah diedit. PV & PRT **tanpa REVISE** (fisik). `RECALC` tak relevan di Procurement.

**B-8. PO Kontan (pembelian tanpa PO).** Untuk pembelian mendadak/kecil (mis. laptop) — satu layar ringkas, tanpa PR/PQ. Saat disimpan, sistem **membuat otomatis**:
- `po_order` (`i_procure_type='CASH'`, status langsung `APPROVED`, `is_auto_generated=1`),
- `po_line`,
- `pv_receipt` + `pv_line` (langsung `POSTED`).

Approval tetap dicatat namun bersifat **pemberitahuan** (`step_type='NOTIFY_ONLY'` ke Accounting/Op Manager) — barang sudah dibeli, jadi tak masuk akal memblokirnya. Nota gabungan: **Bukti Pembelian Kontan** (satu lembar, walau di database tetap dua dokumen demi konsistensi penelusuran & stok). Nilai tetap memutakhirkan `avg_unitprice` / `last_unitprice`.

**B-9. FREE_SAMPLE.** Barang contoh dari supplier: masuk stok dengan `unit_price=0`, tanpa PO/tagihan. `m_raw_material.free_note` menyimpan alasan/syarat. Tidak mempengaruhi harga rata-rata.

**B-10. Fluktuasi kurs setelah PQ.** Bila jarak PQ → PO terlalu jauh, tampil peringatan 🔴 "kurs mungkin sudah berubah" (peringatan, **bukan blokir** — officer yang memutuskan re-quote atau lanjut). Setelah PO `APPROVED`, `i_fx_rate` **dibekukan** — nilai PO/PV/nota tak berubah retroaktif meski kurs bergerak.

**B-11. Impor.** `po_import_doc` menampung checklist dokumen (PIB, Invoice, Packing List, B/L–AWB, COO, Asuransi, NIB/API, HS, SPPB, DO forwarder, bukti bayar BM+PDRI, Incoterms). **Status INACTIVE pada fase ini** — struktur disiapkan, perhitungan BM/PPN impor/PPh 22 belum dijalankan. Lihat issue `PROC-IMPORT-TAX`.

**B-12. Approver tak tersedia.** Bila jabatan tak punya pemegang dan tak ada PLT aktif → flow `BLOCKED` + alert ke Admin/HR (`TIER_2_FLOW_ENGINE` §2).

**B-13. Nilai berubah setelah submit.** `amount_snapshot` dibekukan saat submit pertama. Bila dokumen diedit dan nilainya berubah → **wajib submit ulang dari step 1**, bukan threshold dievaluasi ulang di tengah alur.

---

# PART C — STATE · FORM · BUTTON

## C-1. Status per dokumen

| Dokumen | Status |
|---|---|
| **PR** | `DRAFT` → `SUBMITTED` → `APPROVED` → `PARTIALLY_ORDERED` → `FULLY_ORDERED` → `CLOSED` · cabang: `REJECTED`, `REVISION_NEEDED`, `CANCELLED` |
| **PQ** | `DRAFT` → `CONFIRMED` · `EXPIRED` (lewat masa berlaku) · `CANCELLED` |
| **PO** | `DRAFT` → `SUBMITTED` → `APPROVED` → `SENT` → `PARTIALLY_RECEIVED` → `RECEIVED` → `CLOSED` · cabang: `REJECTED`, `CANCELLED` |
| **PV** | `DRAFT` → `QC_PENDING` → `POSTED` · `CANCELLED` (hanya sebelum POST) |
| **PRT** | `DRAFT` → `CONFIRMED` → `COMPLETED` · `CANCELLED` |

## C-2. Matriks tombol

### PR
| Status | Tombol aktif | Field dapat diedit |
|---|---|---|
| `DRAFT` | Simpan · **Submit** · Hapus | semua |
| `SUBMITTED` | Batal Submit (pemohon, sebelum ada approval) | — (baca-saja) |
| `REVISION_NEEDED` | Simpan · **Submit** ulang | semua |
| `APPROVED` | **Split Alokasi** · **Buat PO** · Cetak PP · Revise | — |
| `PARTIALLY_ORDERED` | Buat PO (sisa) · Cetak PP | — |
| `FULLY_ORDERED` / `CLOSED` | Cetak PP | — |
| `REJECTED` / `CANCELLED` | Cetak PP | — |

### PO
| Status | Tombol aktif | Catatan |
|---|---|---|
| `DRAFT` | Simpan · **Submit** · Hapus | Entry penuh |
| `SUBMITTED` | **Approve** · **Reject** | Hanya di **layar Approval**, hanya actor sah |
| `APPROVED` | **Kirim ke Supplier** · Cetak PO · Revise | |
| `SENT` | **Buat PV** · Cetak PO | |
| `PARTIALLY_RECEIVED` | Buat PV (sisa) · Cetak PO | |
| `RECEIVED` | **Tutup PO** · Cetak PO | |
| `CLOSED` | Cetak PO | Terkunci |

### PV
| Status | Tombol aktif | Catatan |
|---|---|---|
| `DRAFT` | Simpan · Hapus | Input qty dua satuan |
| `QC_PENDING` | **QC Qty** · **QC Kualitas** · **POST** | POST terkunci selama ada baris `PENDING` |
| `POSTED` | Cetak SPB · **Buat Retur** | Tak dapat diubah — koreksi lewat Adjustment |

### PRT
| Status | Tombol aktif |
|---|---|
| `DRAFT` | Simpan · **Konfirmasi** · Hapus |
| `CONFIRMED` | Cetak NRB · **Selesai** (barang pengganti diterima / nota kredit terbit) |
| `COMPLETED` | Cetak NRB |

## C-3. Aturan tombol universal
1. Tombol **Edit / Detail** di grid **nonaktif sampai satu baris dipilih** (konvensi UI TMERP).
2. **Hapus** hanya pada `DRAFT`. Setelah itu: `CANCELLED` (jejak dipertahankan).
3. **Revise** hanya pada dokumen `confirmed`, selalu **copy-record**.
4. Tombol lintas-dokumen (**Buat PO**, **Buat PV**, **Buat Retur**) hanya muncul bila status hulunya mengizinkan.
5. **Approve / Reject tidak pernah tampil di layar Entry** — hanya di layar Approval, dan hanya untuk actor hasil resolusi flow engine (termasuk PLT).
6. **Reject wajib mengisi alasan** (`flow_log.notes`).

## C-4. Permission (mengikuti model global 4 aksi)
| module_code | can_access | can_input | can_approve | can_export |
|---|---|---|---|---|
| `PR` | lihat PR | buat/edit | approve sesuai jabatan | cetak PP |
| `PO` | lihat PO | buat/edit | approve sesuai jabatan | cetak PO |
| `PV` | lihat PV | terima & QC | override toleransi | cetak SPB |
| `PRT` | lihat retur | buat retur | konfirmasi | cetak NRB |

**Tambahan khusus:** `PV.can_view_cost` — hak melihat harga & nilai di PV. Diberikan ke Operational Manager & Accounting, **tidak** ke petugas gudang.
