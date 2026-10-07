# purchasereceive_delta_multipo.md
**Modul 04 — Procurement · Submodul PV (Penerimaan Barang / SPB)**
**Status: KEPUTUSAN TERKUNCI — menggantikan sebagian procurement_tier2.md §5 dan procurement_tier3.md §5**
Tanggal: 21 Agustus 2026 · Basis: benchmark SAP / Odoo / Oracle EBS+Fusion / Dynamics 365 F&O

---

## 0. Ringkasan Perubahan

Satu dokumen Penerimaan Barang (SPB) kini boleh menampung **beberapa PO sekaligus, sepanjang berasal dari satu supplier**. Konsekuensi arsitekturalnya: **referensi PO pindah dari header ke baris**.

| Aspek | Sebelum (PROC_TIER_3 §5) | Sesudah (dokumen ini) |
|---|---|---|
| Pengikat PO | `pv_receipt.po_id` NOT NULL (header) | `pv_line.po_line_id` (baris) — header TIDAK punya `po_id` |
| Kunci dokumen | PO | **Supplier + Gudang** |
| Alur entry | Pilih PO → baris ditarik | **Pilih Supplier → pilih baris PO terbuka (lintas PO)** |
| Kardinalitas | 1 SPB : 1 PO | **1 SPB : N PO** dan 1 PO : N SPB |

### Dasar benchmark (ringkas)

| ERP | Letak referensi PO | Multi-PO dalam 1 dokumen | Alur seleksi |
|---|---|---|---|
| SAP ECC/S4 | Baris — `MSEG.EBELN` + `EBELP`; header `MKPF` tanpa nomor PO | Ya (MIGO / VL31N). Campur vendor dicegah pesan M7 107 (konfigurable via OMCQ) | Ketik PO berulang, atau VL31N "Select Purchase Orders" |
| Oracle EBS / Fusion | Baris — `RCV_SHIPMENT_LINES.PO_HEADER_ID/PO_LINE_ID`; header `RCV_SHIPMENT_HEADERS.VENDOR_ID` | Ya. Lintas-*source* (supplier berbeda) = **hard block** | **Find Expected Receipts: pilih Source Type=Supplier → worklist baris PO terbuka** |
| Odoo 16–18 | Baris — `stock.move.purchase_line_id`; header `purchase_id` hanya *related field* | Secara arsitektur ya, tetapi default 1 picking : 1 PO (butuh merge) | Smart button per PO, atau worklist Receipts difilter vendor |
| Dynamics 365 F&O | **Header** — `VendPackingSlipJour.PurchId` tunggal | **Tidak** — tiap PO menghasilkan product receipt sendiri | Arrival overview multi-PO, tapi hanya tahap registrasi |

**Kesimpulan yang dipakai:** ikuti pola Oracle (paling dekat dengan kebutuhan) untuk alur layar, dan pola SAP/Oracle untuk struktur data (PO di baris). Pola D365 ditolak secara eksplisit karena memaksa dokumen terpisah per PO — bertentangan dengan realita lapangan (satu truk supplier kertas membawa muatan 2–3 PO sekaligus).

---

## 1. Keputusan Terkunci

### PV-01 — Referensi PO ada di baris, bukan header
`pv_receipt.po_id`, `pv_receipt.c_po_doc_no`, dan `pv_receipt.c_po_date` **DIHAPUS**. Pengikat tunggal adalah `pv_line.po_line_id` (sudah ada), ditambah snapshot `pv_line.c_po_doc_no`, `c_po_line_no`, `c_po_date`.

Header tetap menyimpan **ringkasan tercetak** `c_po_count` dan `c_po_doc_list` (daftar nomor PO dipisah koma, dibekukan saat simpan) — hanya untuk grid dan kop nota, **bukan** untuk logika. Semua perhitungan wajib membaca `pv_line`.

### PV-02 — Supplier adalah kunci dokumen (HARD BLOCK)
`pv_receipt.i_supplier_id` NOT NULL. Setiap baris wajib memenuhi `po_order.i_supplier_id = pv_receipt.i_supplier_id`. Pelanggaran = error, bukan warning. (Oracle memblokir mutlak; SAP membuat ini konfigurable — untuk TMERP dipilih hard block demi kesederhanaan.)

### PV-03 — Satu gudang penerima per SPB (HARD BLOCK)
`pv_receipt.i_warehouse_id` NOT NULL untuk penerimaan barang stok. Baris dari PO yang ditujukan ke gudang berbeda **tidak boleh** digabung; buat SPB terpisah. Alasan: memecah dokumen jauh lebih murah daripada memecah posting stok lintas gudang dalam satu transaksi. (SAP menyelesaikannya dengan *delivery split* otomatis; TMERP memilih memblokir di muka agar operator sadar sejak awal.)

### PV-04 — Mata uang harus sama (HARD BLOCK)
Semua PO dalam satu SPB wajib `c_currency_code` identik. Fase ini seluruh PO IDR, tetapi aturan ditulis sekarang agar tidak jadi bug saat PO IMPORT diaktifkan.

### PV-05 — Perbedaan lain hanya WARNING
Termin pembayaran, tanggal kirim diminta, dan incoterms boleh berbeda antar-PO dalam satu SPB. Tampilkan lencana peringatan di header form ("2 termin berbeda") dan simpan apa adanya. Ini murni urusan AP saat penagihan, bukan urusan gudang.

### PV-06 — Satu nomor SPB untuk seluruh dokumen multi-PO
Mengikuti Oracle (`RECEIPT_NUM` tunggal) dan SAP (satu material document). **Bukan** satu nomor per PO. Telusur balik ke tiap PO dijamin lewat `pv_line.po_line_id`.

### PV-07 — Surat jalan supplier boleh lebih dari satu
Realita: supplier kadang menerbitkan satu SJ untuk semua PO, kadang satu SJ per PO. Maka:
- `pv_receipt.i_supplier_do_no` = SJ utama (wajib diisi), diperlebar ke VARCHAR(150).
- `pv_line.i_supplier_do_no` = SJ khusus baris tersebut (nullable). Kosong berarti mengikuti SJ header.
Nota SPB mencetak kolom SJ per baris hanya bila ada minimal satu baris yang mengisinya.

### PV-08 — Rekalkulasi status PO per PO yang tersentuh
Saat POST, `PvPostingService` wajib:
1. `SELECT DISTINCT po_id` dari `po_line` milik seluruh `pv_line` dokumen ini.
2. Untuk tiap `po_id` tersebut, hitung ulang `po_line.c_qty_received` / `c_qty_outstanding` / `c_line_status`, lalu status header PO.
Jangan pernah mengasumsikan hanya ada satu PO.

### PV-09 — Baris SERVICE tidak pernah masuk PV (menutup open item PROC-SERVICE-RECEIPT)
Ditambahkan kolom `po_line.is_receivable` (default 1; **0 untuk `i_line_class='SERVICE'`**). Worklist baris PO terbuka hanya menampilkan `is_receivable=1`.

Penutupan baris jasa dilakukan di **layar PO**, bukan PV: tombol **"Konfirmasi Penyelesaian Jasa"** pada baris SERVICE, hak akses `PO.can_approve` (Manajer Operasional). Aksinya mengisi `c_qty_received = i_qty`, `c_line_status='RECEIVED'`, dan menulis `flow_log` (`module_code='PO'`, `action='SERVICE_CONFIRMED'`). Tidak ada pergerakan stok, tidak ada dokumen SPB. Tanpa aturan ini, PO campur barang+jasa tidak akan pernah bisa berstatus `RECEIVED`.

### PV-10 — Pembatalan tetap satu transaksi
Batal/reversal SPB multi-PO membalik seluruh baris sekaligus dan merekalkulasi setiap PO yang tersentuh (aturan PV-08 berlaku terbalik). Tidak ada pembatalan sebagian per-PO — kalau perlu, batalkan seluruh SPB lalu buat ulang. Mengikuti prinsip SAP: dokumen material dibatalkan utuh (movement 102), bukan diedit.

### PV-11 — Toleransi tetap per baris PO
Tidak berubah. `c_tol_pct` di-snapshot dari `po_line`, variance dihitung terhadap **sisa PO baris itu**, bukan terhadap total dokumen. Satu SPB bisa berisi campuran baris yang lolos dan baris yang melanggar toleransi; POST terkunci selama masih ada satu baris yang melanggar dan belum di-override.

---

## 2. Delta DDL

Dieksekusi lewat `PATCH_DDL_PV_MULTIPO.sql`. Ringkasan:

**`pv_receipt`**
- DROP: `po_id`, `c_po_doc_no`, `c_po_date`, index `idx_pv_po`
- ADD: `c_po_count SMALLINT UNSIGNED`, `c_po_doc_list VARCHAR(255)`
- MODIFY: `i_supplier_id` NOT NULL (tetap), `i_supplier_do_no` → VARCHAR(150)

**`pv_line`**
- ADD: `c_po_id BIGINT UNSIGNED`, `c_po_doc_no VARCHAR(30)`, `c_po_line_no SMALLINT UNSIGNED`, `c_po_date DATE`, `i_supplier_do_no VARCHAR(50) NULL`, `c_receipt_seq_po SMALLINT UNSIGNED`
- ADD INDEX: `idx_pvl_po (c_po_id)`

**`po_line`**
- ADD: `is_receivable TINYINT(1) NOT NULL DEFAULT 1`
- ADD INDEX: `idx_pol_receivable (is_receivable)`

**VIEW** — `v1_po_outstanding` ditambah filter `is_receivable=1`; `vw_nota_pv` dan `vw_nota_pv_line` ditulis ulang; ditambah `v1_pv_po_link` (peta SPB ⇄ PO untuk penagihan & audit).

---

## 3. Alur Layar (PvForm) — supplier-first

### Langkah 1 — Pilih Supplier
Modal berisi daftar supplier yang **punya minimal satu baris PO terbuka**, dengan kolom: Supplier, Jumlah PO terbuka, Jumlah baris terbuka, PO terlama. Sumber: agregasi `v1_po_outstanding`.

### Langkah 2 — Worklist Baris PO Terbuka (meniru Oracle Find Expected Receipts)
Grid centang-banyak berisi seluruh baris terbuka milik supplier terpilih. Kolom wajib:

`☑ | No PO | Tgl PO | Baris | Kode | Nama Barang | Gudang | Qty PO | Sudah Diterima | Sisa | UOM | Tgl Kirim | Toleransi`

Perilaku:
- Baris dikelompokkan visual per No PO (header grup), tetapi centangnya per baris — operator bebas mengambil sebagian baris dari PO A dan sebagian dari PO B.
- Filter cepat: No PO, kode/nama barang, "hanya yang jatuh tempo ≤ hari ini".
- **Validasi hidup di footer**: begitu centang mengenai dua gudang berbeda atau dua mata uang berbeda, tombol "Buat SPB" terkunci dan muncul pesan sebab (aturan PV-03 / PV-04).
- Baris `is_receivable=0` (jasa) tidak pernah muncul.

### Langkah 3 — Form SPB
- Header: No SPB (auto), Tanggal, **Supplier (terkunci)**, Gudang (terkunci dari worklist), SJ utama, No Kendaraan, Catatan. Ditambah chip daftar PO: `PO/26/00031` `PO/26/00035` + lencana peringatan bila termin/tgl kirim berbeda.
- Tiap kartu baris menampilkan lencana asal: **`PO/26/00031 · baris 2`**, plus field SJ per baris (opsional).
- Sisanya (dual-UOM, QC dua gerbang, panel toleransi, tombol POST terkunci berikut daftar penghambat) tidak berubah dari desain sebelumnya.
- Tombol "＋ Tambah Baris dari PO Lain" membuka kembali Langkah 2 dengan supplier & gudang sudah terkunci — operator bisa menambah baris tanpa membuang isian yang sudah ada.

---

## 4. Aturan Validasi (untuk `PvValidator`)

| Kode | Aturan | Jenis |
|---|---|---|
| PV-V01 | `po_order.i_supplier_id` setiap baris = `pv_receipt.i_supplier_id` | ERROR |
| PV-V02 | Status PO setiap baris ∈ (`SENT`, `PARTIALLY_RECEIVED`) | ERROR |
| PV-V03 | Semua baris menuju satu `i_warehouse_id` yang sama | ERROR |
| PV-V04 | Semua PO ber-`c_currency_code` sama | ERROR |
| PV-V05 | `po_line.is_receivable = 1` | ERROR |
| PV-V06 | Satu `po_line_id` tidak boleh muncul dua kali dalam satu SPB | ERROR |
| PV-V07 | `i_qty_purchase > 0` dan `i_qty_stock > 0` | ERROR |
| PV-V08 | Diterima + Ditolak = `i_qty_stock` (bila QC kualitas ≠ PENDING) | ERROR |
| PV-V09 | Semua baris `qc_qty_status='DONE'` dan `qc_quality_status<>'PENDING'` sebelum POST | ERROR |
| PV-V10 | Tiap baris `is_tol_exceeded=1` wajib punya `tol_override_by` + `tol_override_reason` | ERROR |
| PV-V11 | Termin pembayaran / tanggal kirim berbeda antar-PO | WARNING |
| PV-V12 | Ada baris dengan variance negatif besar (penerimaan bertahap) | INFO |

---

## 5. Dampak ke Modul Lain

- **Invoicing / AP (AR-lite fase ini, AP menyusul):** satu SPB bisa memicu tagihan lintas PO. Pencocokan 3 arah (PO–SPB–Faktur) tetap mungkin karena granularitas ada di `pv_line.po_line_id` — persis alasan SAP MIRO dan Oracle AP bisa mencocokkan satu faktur ke banyak PO. Gunakan `v1_pv_po_link` sebagai sumber.
- **Inventory:** tidak ada perubahan. Satu SPB tetap menghasilkan satu `rm_stock` (doc_type=1) dengan banyak `rm_stock_d` — kebetulan sekarang barisnya bisa berasal dari PO berbeda, dan itu tidak berpengaruh karena `rm_stock_d.reference_id` menunjuk `pv_line_id`.
- **Retur (PRT):** `prt_return.po_id` menjadi **nullable**; ambil PO dari `pv_line.c_po_id` baris yang diretur. Satu NRB sebaiknya tetap satu PO agar nota kredit ke supplier jelas.
- **Costing per CO:** `v1_material_cost_per_co` tidak berubah (sudah berbasis `pv_line`).

---

## 6. Yang Harus Diperbarui di Dokumen Lain

1. `procurement_tier2.md` §5 — ganti aturan "PV mengacu satu PO" dengan PV-01…PV-11 di atas.
2. `procurement_tier3.md` §5 — ganti definisi tabel `pv_receipt`/`pv_line`, hapus `po_id` dari header.
3. `procurement_tier4.md (diarsipkan)` §5.1 — ganti alur entry menjadi tiga langkah (supplier → worklist → form).
4. `procurement_state.yaml`:
   - `keputusan_terkunci` → tambah **W_pv_multi_po_supplier_driven** (PV-01…PV-11)
   - `open_items` → **PROC-SERVICE-RECEIPT dinyatakan CLOSED** oleh PV-09
   - `artefak_selesai` → tambah 5 file rilis ini
5. `checklistmockuperp.md (diarsipkan)` §4 — Receiving: tandai Goods Receipt Entry + Receiving List 🟡, tambah baris "[P] Nota SPB multi-PO".
