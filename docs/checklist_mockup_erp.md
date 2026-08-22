# MASTER CHECKLIST MOCKUP — ERP PT Telasih Makmur
**Peta kerja UI: Form Entry · Grid/List · Print-out.**
Status: ✅ **live di app** (ada route Livewire + teruji) · 🟡 mockup HTML sudah jadi tapi **belum** diimplementasi Livewire · ⬜ belum ada mockup sama sekali.
*(Diperbarui 2026-08-20 — cross-check langsung ke `routes/web.php` + `resources/views/pages/`, bukan asumsi. Versi sebelumnya sudah usang: sempat menandai Security/HRM ⬜ padahal sudah live, dan Nota/Quotation ✅ padahal cuma mockup HTML-nya yang selesai, belum ada Livewire-nya.)*

Legenda tipe: **[F]** Form entry · **[G]** Grid/list · **[P]** Print-out/dokumen · **[D]** Dashboard/report

---

## MODUL 1 — SALES
### 1.1 Inquiry
- ✅ [G] Inquiry Hub (list + filter) — *inquiry_hub.html* → `inquiry.hub`
- ✅ [F] Inquiry New/Entry — *inquiry_new.html* → `inquiry.form`
- ✅ [F] Inquiry Edit RECALC — *inquiry_recalc_v2.html* → `inquiry.recalc`
- ✅ [F] Inquiry Edit REVISE — *inquiry_revise_v2.html* → `inquiry.revise`
- ✅ [D] Imposition Engine — *inquiry_imposition.html* → `inquiry.imposition` (selesai 2026-08-20; tabel baru `iqp_layout` + `m_plano_size`, auto-fill ke Costing saat confirm)
- ✅ [D] Costing Hub — *inquiry_costing_hub.html* → `inquiry.costing`
- ✅ [P] Nota Inquiry (warna + hemat tinta) — *nota_inquiry_mockup.html* + *_bw.html* → `inquiry.nota` (selesai 2026-08-20; satu Livewire, toggle `?bw=` bukan dua file terpisah; harga tersembunyi otomatis kalau `costing.published_flag` masih 0, sesuai Sisi A/B CLAUDE.md §4)
- ✅ [P] Nota Inquiry NEW/RECALC/REVISE — *inquiry_nota_new/recalc/revise.html* → `inquiry.nota` (badge tipe dokumen berwarna: NEW hijau/RECALC ungu/REVISE oranye/PROSPECT amber/CANCELLED merah, sesuai `inquiry_type`. Dicek: ketiga mockup ternyata BUKAN dokumen berbeda struktur — isi/section identik, cuma beda warna badge — jadi bukan 3 layar terpisah, cukup satu Livewire yang sudah live dgn badge dinamis. Diverifikasi lewat data dummy IQ2600003 RECALC & IQ2600004 REVISE)
- ⬜ [F] Prove Warna — *inquiry_proof.html* — log versi v1/v2/v3, belum digarap
- 🟡 [D] Costing Manual Cross-Check — *inquiry_costing_manual.html* — beda dari Costing Hub yang sudah live, belum digarap
- ~~🟡 [D] Product Costing Engine (HPP) — *product_costing.html*~~ — **DIBATALKAN 2026-08-21**: dicek, ternyata duplikat Costing Hub yang sudah live (`inquiry.costing` + `CostingEngine.php`) — ladder formula (HPP→Toleransi→Pembulatan→Margin→PPN) & bucket Material/Proses/Coating/Tooling identik. GAP #2 CLAUDE.md §5 (rate versioned coating/film/sheet) ternyata sudah closed sejak Costing Hub dibangun (`mat_coating_rate`/`mat_sheet_rate`/`mat_grammage` sudah ada & dipakai). Tidak perlu layar baru.

### 1.2 Quotation
> ⚠️ **Redesain 2026-08-20**: Quotation semula 1:1 per inquiry (versi kemarin) — dirombak total jadi 1 Quotation = N inquiry + N baris jasa/tooling per customer yang sama, sesuai `mockup_sales_quotation_entry.html` (terbaru) + `mockup_sales_quotation_letter_print.html`. Tabel `quo_quotation.inquiry_id` (unique) diganti `customer_id`; tabel baru `quo_quotation_item`.
- ✅ [G] Quotation List — *mockup_sales_quotation_entry.html* (panel grid) → `quotation.list` (filter no./customer, badge status, "+ Quotation Baru" via picker customer)
- ✅ [F] Quotation Entry/Edit — *mockup_sales_quotation_entry.html* (panel form) → `quotation.entry` (tarik inquiry published multi-kali dari customer sama, harga/qty/diskon per baris editable, baris bebas, auto-split tooling jadi baris die & plat terpisah, Terbitkan mengunci dokumen)
- ✅ [P] Quotation resmi (print) — *mockup_sales_quotation_letter_print.html* → `quotation.print` (palet navy/accent TMERP dikoreksi dari draf hijau kemarin; kolom Ref per baris; panel Alternatif Kuantitas dari produk utama; bank dari `sys_constant` BANK_*, bukan karangan). Disambung dari Entry via tombol "🖨️ Preview Cetak" (tab baru) dan dari Costing via "+ Tambahkan ke Quotation" (`App\Services\Sales\QuotationBuilder`).
- 🟡 [P] Nota Quotation — *inquiry_nota_quotation.html* — varian print beda, belum digarap

### 1.3 Sales Order (Customer Order)
> Skema `co_order` dibangun dari nol 2026-08-20 (tidak ada Tier doc sebelumnya) berdasarkan `mockup_co_entry.html` (versi terbaru 20 Agustus, superseded *co_entry_mockup.html* lama). "1 CO = 1 Quotation" (keputusan user) — item CO adalah snapshot dari `quo_quotation_item` saat CO pertama dibuat.
- ✅ [G] Sales Order List — *mockup_co_entry.html* (implisit) → `co.list` (filter no. CO/customer, kolom Net Receivable)
- ✅ [F] CO Entry — *mockup_co_entry.html* → `co.entry` (4 tab: Product/Delivery/External Cost/History Flow; item snapshot Quotation terkunci kecuali Due Date; Shipment Location pakai `AddressPicker` existing; diskon dokumen+qty dari `iqp_inquiry`; PPN 11% + PPh 23 2% jasa; `co_order_item.line_kind` P/S; dipicu tombol "Buat Customer Order" di Quotation setelah PUBLISHED via `App\Services\Sales\CoOrderBuilder`)
- ✅ [F][G] Plan Delivery — *co_delivery_plan_mockup.html* → `co.delivery-plan` (2026-08-22). **Scope terjawab**: tab "Delivery" di CO Entry cuma jadwal AWAL saat CO masih Draft (`@disabled` begitu Confirmed); layar ini adalah hub logistik lintas-CO utk SESUDAH CO fix (split/gabung baris, geser tanggal, konservasi qty otomatis) — dua-duanya saling melengkapi, bukan duplikat. MURNI penjadwalan, TIDAK menyentuh stok/COGS sama sekali (itu domain Delivery Order/Dispatch, masih 🔴 belum dibangun) — tidak ada tombol Confirm/Create DO/Print, baris jadi SHIPPED nanti otomatis begitu DO terbit. `co_order_delivery` diberi delta `status`(PLANNED/SHIPPED)+`split_from_id` (self-FK, kolom "Asal"). Diuji end-to-end via tinker (split konservasi qty benar, merge reunit benar).
- 🟡 [P] Nota CO (print) — *nota_co_mockup.html* — belum digarap

---

## MODUL 2 — INVENTORY
*(urutan dependensi: sebelum Production, karena MRP butuh item_master)*
### 2.0 Master Warehouse
> Dibangun 2026-08-21 dari `mockup_master_warehouse.html`, menutup `PROC-WAREHOUSE-MASTER`
> (`PROC_STATE.yaml`) — sebelumnya `pv_receipt.i_warehouse_code`/`pv_line.i_location_code`
> teks bebas menunggu tabel ini. Route `/master/warehouse` (menu `MD_WH` yang sudah
> pre-seeded, sebelumnya belum ditautkan). **Perbedaan disengaja dari mockup**, atas
> instruksi user (deviasi boleh langsung dibangun asal ditulis):
> 1. Mockup: 1 baris grid = 1 "gudang" yang sekaligus 1 bin (row/col/stack nempel di
>    record gudang) — tak bisa 1 gudang punya banyak rak. **Dipisah** jadi `m_warehouse`
>    (header) 1:N `m_wh_loc` (rak/bin) — gudang fisik nyata punya banyak posisi.
>    (**Update 2026-08-21 sore**: tabel `m_wh_location` di-rename jadi `m_wh_loc` +
>    kolom `id_wh_location`→`location_id`/`bin_code`→`location_code`, dan
>    `warehouse_type` diperluas `RAW/FG/MIX/SCRAP`→`RM/SFG/FG/MIX/WASTE/TRANSIT` —
>    supaya cocok dengan puluhan VIEW Inventory yang baru dibangun, lihat 2.2.)
> 2. Mockup: field SKU/Nama Barang/Jumlah Stok/UoM nempel di tiap baris gudang, datanya
>    dikarang di JS. **Tidak dibuat** — itu posisi stok transaksional (butuh ledger
>    Inventory yang belum dibangun), bukan master data; kalau dipaksa jadi dua sumber
>    kebenaran begitu Inventory jalan. KPI stok di mockup ikut didrop, diganti KPI
>    struktural (jumlah gudang/lokasi).
> 3. Kode gudang tetap input manual (bukan auto-generate) — jumlah gudang kecil, beda
>    dari Item Master yang katalognya ribuan.
> 4. Fitur cetak (Nota Alokasi / Rekap Semua Gudang) di mockup **belum dibangun** —
>    diprioritaskan Form/Grid dulu sesuai instruksi standing user.
- ✅ [G] Warehouse List — `master.warehouse.list` (stats gudang/lokasi, filter tipe, cari kode/nama)
- ✅ [F] Warehouse Entry — `master.warehouse.create` / `.edit` (identitas+tipe gudang, repeater lokasi/bin row-col-stack dengan bin_code auto-generate & unique constraint). Diverifikasi via tinker (create, tambah bin, tabrakan bin_code diblokir, withCount). **Belum diverifikasi visual browser.**
- ⬜ [P] Nota Alokasi / Label Rak — belum digarap (ada di mockup, sengaja ditunda)
### 2.1 Item Master
> Dibangun 2026-08-21 dari `mockup_master_rawmaterial.html` + `ITEM_MASTER_DELTA.md` (dokumen
> user), atas `m_raw_material` yang sudah diisi 5.893 baris riil sejak PR Entry. Route
> `/master/raw-material` (menu `MD_RAW` yang sudah pre-seeded, sebelumnya belum ditautkan).
- ✅ [G] Item List/Catalog — `master.raw-material.list` (stats card total/material/jasa/stok kritis, filter tipe & kategori, cari kode/nama/HS code)
- ✅ [F] Item Entry — `master.raw-material.create` / `.edit` (5 tab: Kategori+kode-auto dari `doc_rmprefix1..5`, Spesifikasi & QC, UOM & Pengadaan, Stok & Lokasi, Akuntansi GL langsung ke `fin_account`/`fin_cost_center`/`fin_profit_center` baru). Kategori terkunci setelah dibuat (kode ikut kombinasi itu). **Belum diverifikasi visual browser**, logika diverifikasi via tinker (generate kode, cek tabrakan, create+reload seluruh field termasuk costing_method STANDARD baru)
### 2.2 Stock Movement
> **2026-08-21**: `INV_TIER_1..4.md` + `INV_RULES.yaml` + `INV_STATE.yaml` diterima dari user
> (spec Inventory lengkap, ditulis Telma). Dump produksi `tminv_20260722_2018_sd.sql`
> diberikan user & diimpor (13 tabel legacy: `rm_stock*`/`sfg_stock*`/`fg_stock*`/`rm_booking*`).
> **Tier 3 (DDL/skema) dieksekusi PENUH**: bersihkan data kotor (doc_no dobel, location_id
> NULL, baris kartu stok duplikat bukti bug posting-ganda — semua terverifikasi persis
> sesuai yang didiagnosis `INV_STATE.yaml`), delta kolom `flow_status`/`period_id`/dst ke
> `rm_stock*`/`sfg_stock*`/`fg_stock*`, 3 tabel baru (`inv_period`/`inv_stock_layer`/
> `inv_layer_consumption`), 5 VIEW (`v_rm_onhand`/`v_rm_available`/`v_rm_out_returnable`/
> `v_inv_movement`/`v_inv_wo_reconcile`) + 2 tambahan (`v_fg_onhand`/`v_sfg_onhand`), posisi
> "Kepala Gudang" baru dibuat (org_position), Flow Engine IN/OUT/AD/OP/RTP di-seed, menu
> `INV_IN`/`INV_OUT`/`INV_RTP`/`INV_ADJ2`/`INV_APRV`/`INV_PERIOD` ditautkan ke grup
> `INV_TRX`/`INV_RPT` yang **sudah pre-seeded** (ditemukan saat baru — bukan `MDATA`,
> percobaan pertama salah taruh, sudah dikoreksi).
> **3 penyimpangan nyata dari teks spec** (diverifikasi ke DB, bukan ditebak):
> `sys_sequence` tidak ada sama sekali (project ini pakai pola `nextDocNo()` statis per
> model, bukan tabel sequence — dipakai nanti saat service posting digarap);
> `sys_constant` ada di **tm2mst** bukan tm2sys (sama kasus lama `PROC-SYSCONST-COL`);
> produk FG/SFG adalah `tm2mst.prod_product`/`prod_part` (bukan `m_product`/`m_product_parts`
> yang disebut teks asli — tabel itu tidak ada). Detail penuh + alasan tiap keputusan ada
> di komentar migration `2026_08_21_00000{8,9,10,11,12}` dan memory `project_inventory_module_status`.
> **Tier 4 (layar transaksi) BELUM digarap** — StockPostingService, Stock In/Out/Adjustment/
> Return, dan layar approval masih kosong. Yang dibangun baru 2 layar laporan/cetak di bawah,
> sesuai permintaan spesifik user, dari `mockup_print_stock_card.html` + `mockup_print_master_stock.html`.
- ✅ [P] Kartu Stok — `inv.print.stock-card` (menu `INV_CARD`, pre-seeded). Pilih item + rentang tanggal, baca `v_inv_movement` (kelas RM saja — SFG/FG belum ada data riil), saldo awal dari baris pertama/qty_eb sebelum rentang. Diverifikasi query langsung ke data riil (material_id=1: 8 baris, saldo 0→50). **Belum diverifikasi visual browser.**
- ✅ [P][G] Laporan Master Stok — `inv.print.master-stock` (menu `INV_RAW`, pre-seeded). Rentang tanggal + filter gudang, agregat per item dari `rm_stockcard` (saldo awal/masuk/keluar/penyesuaian/saldo akhir), dikelompokkan per kategori sesuai mockup, badge status vs `min_stock`. **Belum diverifikasi visual browser.**
- ⬜ [G] Stock Movement Ledger interaktif (grid `StockCard`/`OnhandGrid` penuh sesuai `INV_TIER_4.md` §6) — layar laporan yang dibangun baru versi cetak langsung, belum ada grid filter interaktifnya
### 2.3 Stock In / Stock Out / Return / Adjustment (Tier 4)
- ✅ [F][G] Stock In Entry + Grid — `inv.stock-in.index/{RM|FG|SFG}` (2026-08-21). MANUAL saja (PV/WO auto-post belum diintegrasikan).
- ✅ [F][G] Stock Out Entry + Grid — `inv.stock-out.index/{RM|SFG}` (RM 2026-08-22 pagi, **diperluas ke SFG 2026-08-22 sore** atas permintaan user — SFG keluar gudang lanjut ke proses produksi berikutnya, skenario nyata: barang setengah jadi sempat nunggu di gudang krn keterlambatan material/prioritas produk lain). **FG TETAP ditolak di level route** (`fg_out_bukan_milik_modul`) — user sendiri mengkonfirmasi alur FG-nya "masuk gudang dulu (Stock In, sudah ada) → sebagian diambil truk utk kirim", yaitu **Delivery Order** (modul Dispatch, belum dibangun), bukan Stock Out generik; jangan buka kelas FG di sini tanpa DO-nya lebih dulu ada, krn kehilangan link CO + aturan jurnal HPP-kirim yang sudah locked (`FIN_TIER_1.md`). MANUAL v1 — tanpa WO picker/`pullRequirements()`/cabang SUBSTITUTE-PURCHASE (modul Production/WO belum dibangun sama sekali di codebase ini); baris cuma ISSUE, alur langsung DRAFT→POSTED tanpa approval sesuai `alasan_out_rtp_tanpa_approval`. Auto-jurnal pemakaian ikut diperluas ke SFG (`prod_part.cogs_account_id`/`stock_asset_account_id`, TANPA fallback kategori krn `prod_part` tak punya tabel setara `doc_rmprefix1` — part yg belum diisi akunnya selalu dilewati+peringatan). Diuji end-to-end via tinker (Stock In→Out SFG; part tanpa akun → warning tapi tetap posting; part dgn akun diisi → voucher USG benar).
- ✅ [F][G] Return from Production Entry + Grid — `inv.stock-return.index/{RM|SFG}` (RM 2026-08-22, **diperluas ke SFG 2026-08-22 sore** sesudah Stock Out SFG — kalau SFG bisa keluar ke produksi wajar bisa diretur juga, pola identik). **FG tetap tak relevan** — FG tak pernah bisa Stock Out, jadi tak ada sumber utk diretur. Referensi wajib ke Stock Out POSTED, qty dibatasi server-side `qty_issued - qty_returned` (akumulasi di `rm_stock_d`/`sfg_stock_d.qty_returned`), split GOOD (lokasi `is_usable=1`)/SCRAP (lokasi `is_usable=0`, warehouse afkir `WH-SCP-01` sudah ada). Harga retur diambil dari `avg_unitprice` stockcard baris OUT sumber (bukan input manual), sesuai `retur: HARGA SAAT KELUAR`. Simplifikasi vs spec: tanpa tombol "Pecah Baris" (buat 2 dokumen terpisah kalau perlu split GOOD+SCRAP dari 1 baris), tanpa tab Rekonsiliasi WO. Diuji end-to-end via tinker utk SFG (In→Out→Return 12 dari 20, `qty_returned` akumulasi benar jadi 12, sisa boleh 8, stockcard retur bertipe IN).
- ✅ [F][G] Stock Adjustment & Opname Entry + Grid — `inv.stock-adj.index/{category}` (2026-08-22). RM/FG/SFG, toggle Jenis AD/OP, blok Opname (petugas/waktu/saksi/full_scope) + tombol "Tarik Semua Item Bersaldo". `qty_system`+`unit_price` disnapshot dari baris `*_stockcard` terakhir (bukan field harga master — FG/SFG tak punya field cost, AVG stockcard generik lintas kelas).
- ✅ [G] Persetujuan Adjustment — `inv.stock-adj.approval/{category}` (2026-08-22), route terpisah dari entri. **Approve/Reject digabung ke form entri itu sendiri** (bukan ringkasan duplikat) — persis pola PR/PO Approval yang dirombak hari yang sama (lihat MODUL 04 §4.1/4.2): field otomatis read-only saat status≠DRAFT/REVISION_NEEDED, approver lihat & putuskan di dokumen ASLI. Flow Engine module AD/OP sudah live (3 tingkat: Kepala Gudang ≤5jt, Manajer Operasional 5-25jt, Direksi >25jt dari `flow_config` yang sudah diseed), approval level terakhir (utk tier nilai manapun) langsung memanggil `StockPostingService::postDocument()`. Diuji end-to-end via tinker dalam transaksi yang di-rollback: Stock In→Out→Return (saldo 50→150→120→130 sesuai perhitungan) lalu Adjustment submit→approve(final)→posted (saldo 130→125 sesuai qty_physical), plus jalur reject→REVISION_NEEDED — semua benar. **Belum diverifikasi visual browser** (perlu login, lihat `feedback_no_credential_automation`).
- ⬜ [D] Period Manager (buka/tutup periode stok) — belum digarap, `PeriodService::assertOpen()` juga belum dipanggil di jalur posting manapun (sama seperti Stock In)
- ⬜ Service: `StockValuationService` (FIFO/LIFO — masih AVG moving average saja utk semua kelas)/`StockBalanceService`/`PeriodService`, `LocationPicker`/`ItemPicker`/`FlowActionBar`/`FlowHistoryLog` sbg komponen reusable (masih inline per layar, "no shared partial" convention di codebase ini)
- ⬜ Permission granular (`INV.can_view_cost`/`can_override_stock`/`can_manage_period`) — kolom nilai & override tampil ke semua peran utk v1, sama seperti Stock In

---

## MODUL 3 — PRODUCTION
### 3.1 MPS/MRP & Planning
- 🟡 [D] Forecast Engine — *forecast_engine.html* — mockup ada, belum digarap
- 🟡 [D] Flow Engine (workflow/state-machine) — *mockup_flow_engine.html* — mockup ada, belum digarap
- ⬜ [F] MPS Entry
- ⬜ [F] MRP Run / Requirement Explosion → feed Procurement
- ⬜ [F] Production Order / Lot Entry
- ⬜ [G] Schedule Board (per resource/mesin)
- ⬜ [P] Work Order / SPK cetak (per proses: belah→cetak→coating→laminasi→plong→lem→packing)
### 3.2 Capacity Planning
- ⬜ [D] Capacity Load View (net vs effective rate)
### 3.3 Production Output
- ⬜ [F] Output/Reporting Entry (per lot, per proses)
- ⬜ [G] Output List
- ⬜ [P] Production Report / laporan hasil

---

## MODUL 4 — PROCUREMENT
> ⚠️ **Spec dirombak total 2026-08-20**: `PROC_TIER_1..4.md` + `PROC_STATE.yaml` + `PROC_RULES.yaml`
> sekarang jadi acuan (bukan lagi mockup HTML mentah) — rantai penuh PR→PQ→PO→PV→PRT→Purchase
> Invoice, approval via Flow Engine generik, split-alokasi anti-M:N. Lihat `PROC_STATE.yaml`
> untuk status detail & open items per sub-modul.
### 4.1 Purchase Requisition (PR)
- ✅ [G] PR List/Hub — *mockup_purchase_requisition.html* → `pur.pr.list` (filter no./item/status, "+ PR Baru" via picker departemen)
- ✅ [F] PR Entry — *mockup_purchase_requisition.html* → `pur.pr.entry` (item picker 5-level kategori→jenis→gramatur→ukuran→merk dari `doc_rmprefix1..5` + `m_raw_material` riil 5893 item, baris MASTER_ITEM/FREE_TEXT, submit → Flow Engine). **Diverifikasi via migration+tinker functional test, BELUM diverifikasi visual browser** (login otomatis via browser ditolak — kategori "entering credentials", lihat `PROC_STATE.yaml`). Jo perlu cek tampilan sendiri sebelum ditandai matang.
- ✅ [G] PR Approval Center — *mockup_pr_approval_center.html* → `pur.pr.approval` (antrian per posisi jabatan user login termasuk PLT, approve/reject via `FlowEngineService`, riwayat `flow_log`; diuji tuntas via tinker: single-step <50jt, reject, dan eskalasi multi-step ke Direksi ≥50jt — semua benar)
- ⬜ [P] PR Print (Nota Permintaan Pembelian) — belum digarap
### 4.2 Purchase Order (PO)
- ✅ [G] PO List — `pur.po.list` (filter no./item/status, "+ PO Baru" via picker supplier)
- ✅ [F] PO Entry — `pur.po.entry` (tarik alokasi `pr_line_alloc` APPROVED lintas-PR ke satu PO per supplier via `PoBuilderService`, kelas RAW/INDIRECT/SERVICE otomatis dari `doc_rmprefix1.stock_class`, harga/diskon per baris editable, ringkasan DPP/PPN/PPh23/Net Payable, submit → Flow Engine). Diuji tuntas via tinker (tarik 2 alokasi lintas-PR, edit harga, submit → benar). **Belum diverifikasi visual browser** (sama seperti PR, lihat `PROC_STATE.yaml`)
- ✅ [G] PO Approval Center — *mockup_po_approval_center.html* → `pur.po.approval` (antrian per posisi jabatan, ringkasan konsolidasi per item, detail baris, ringkasan pajak, riwayat; approve/reject via `FlowEngineService`). Tombol "📤 Kirim ke Supplier" ditambahkan di PO Entry (APPROVED→SENT, PROC_TIER_2 §C-2) — sebelumnya tak ada jalan resmi PO mencapai SENT. Diuji lewat alur SUNGGUHAN end-to-end: submit→antrian→approve→kirim→muncul di worklist PV, tanpa paksaan tinker seperti sesi sebelumnya
- ⬜ [P] PO Print (dokumen eksternal ke supplier) — belum digarap
### 4.3 Purchase Quotation (PQ) — kondisional, boleh dilewati
- ✅ [F][G] PQ Entry + Perbandingan — `pur.pq.list`/`pur.pq.entry` (2026-08-22, dari `mockup_purchase_quotation.html`). Tabel `pq_quotation`/`pq_line` baru dibuat (belum ada sebelumnya, sesuai DDL `PROC_TIER_3.md` §3 + delta header `i_lead_time_day` yang diminta mockup tapi tak ada di draf DDL). Alur DRAFT→CONFIRMED itu **konfirmasi diri sendiri** (bukan approval berjenjang, sesuai sifat "kondisional, boleh dilewati") — Aksi grid = ↗ Buka + ✓ Confirm, pola sama Quotation Sales. Tab Perbandingan menampilkan semua PQ dgn `i_pr_id` yang sama, menyorot harga termurah. Tombol "Buat PO dari PQ ini" menyalin baris PQ langsung ke `po_line` (`alloc_id` NULL — TIDAK lewat `pr_line_alloc`, simplifikasi sengaja karena PQ boleh berdiri sendiri tanpa PR). **Bug nyata ketemu+diperbaiki saat testing**: `po_line.i_department_id` & `i_coa_account_id` ternyata NOT NULL tanpa default (cuma pernah terisi lewat jalur PR) — dibuat nullable via migration setelah INSERT gagal di test tinker sungguhan, bukan cuma php -l.
- ⬜ [P] Cetak PQ (kirim RFQ ke supplier) — belum digarap
### 4.4 Purchase Receive (PV) / Purchase Return (PRT)
> Dirombak 2026-08-21 oleh `DELTA_PROC_PV_MULTIPO.md` (KEPUTUSAN TERKUNCI, benchmark SAP/Oracle/
> Odoo/D365): satu SPB boleh menampung banyak PO dari satu supplier — referensi PO pindah dari
> header ke baris. Skema fisik dibangun ULANG dari draf patch user (`PATCH_DDL_PV_MULTIPO.sql`
> mengasumsikan kolom snapshot & prasyarat `DDL_PROCUREMENT_tm2pur.sql`/`SEED_PROC_DUMMY.sql`
> yang tak ada di project ini) — aturan bisnis PV-01..PV-11 diadopsi penuh, kolom fisik
> disesuaikan ke konvensi `po_order`/`po_line` yang sudah berjalan.
- ✅ [G] PV List — `pur.pv.list` (filter status, badge multi-PO)
- ✅ [F] PV Entry — `pur.pv.entry` (picker 2-langkah supplier→worklist lintas-PO ala Oracle Find Expected Receipts, dual-UOM, QC 2 gerbang, panel toleransi+override, "+ Tambah Baris dari PO Lain", rekalkulasi PER PO yang tersentuh saat POST/PV-08). Diuji tuntas via tinker: satu SPB 2 PO sekaligus, toleransi terlampaui+override, POST merekalkulasi kedua PO — semua benar. **`POST` BELUM menulis ke `rm_stock`/`rm_stock_d`** (dump legacy `tminv_20260722_2018_sd.sql` masih belum diberikan) — status PV/PO tetap benar, pergerakan stok fisik menyusul begitu dump tersedia (`PvPostingService::post()` sudah ditandai TODO persis di titiknya)
- ⬜ [D] PV Monitor (Sisi A) — belum digarap
- ⬜ [P] Nota SPB (cetak) — mockup `nota_pv_spb.html` sudah ada, Livewire-nya belum
- ✅ [F][G] PRT (Purchase Return) — `pur.prt.list`/`pur.prt.entry`/`pur.prt.approval` (2026-08-22, dari `mockup_purchase_return.html`). Tabel `prt_return`/`prt_line` baru dibuat + delta signifikan vs draf DDL awal (`PROC_TIER_3.md` §6): tambah `qc_inspector`/`comm_method`/`supplier_pic`/`physical_return_date`/`expedition`/`cn_supplier_no`/`cn_received_date`/`replacement_due_date` (semua diminta mockup, nol di draf DDL), tambah status `SUBMITTED` (aman, `status` VARCHAR polos bukan ENUM), tambah tracking pengganti PER BARIS (`prt_line.replacement_pv_id`/`replacement_qty`/`replacement_status`, header punya versi sederhananya juga). Picker sumber 2 langkah: **DARI PV** (cuma PV `POSTED` yg punya baris `c_qty_rejected>0` blm ditarik retur lain) atau **DARI STOK** (pilih supplier langsung, item ditambah manual). Approval SUNGGUHAN 1 level (Manajer Operasional, `flow_config` module `PRT` baru diseed) digabung ke form entri sendiri (pola sama PR/PO/Adjustment) dengan 3 aksi: Setujui/Tolak/**Minta Info** (persis mekanisme Hold Procurement yang sudah ada, cuma diberi label sesuai mockup). Reject → `DRAFT` (bukan status revisi terpisah, `DRAFT` sudah cukup editable). v1 **tidak** otomatis membuat dokumen Stock Out (RETURN) di Inventory saat disetujui — `rm_stock_doc_id` sengaja tetap NULL/manual sesuai comment DDL sendiri, ada catatan info di layar utk follow-up manual. Diuji end-to-end via tinker (submit→hold→masih di antrian→approve→CONFIRMED→Tandai Selesai→COMPLETED, plus jalur reject→DRAFT terpisah) dalam transaksi yang di-rollback.
### 4.5 Purchase Invoice — pakai `fin_invoice`/`fin_invoice_line` (tm2acc), bukan tabel baru
- ⬜ [F] Purchase Invoice (SUPPLIER_DP/SUPPLIER_BILL) — belum digarap, butuh `fin_invoice_line` (delta wajib, belum dibuat)

---

## MODUL 5 — DISPATCH / DISTRIBUTION
### 5.1 Picking & Packing
- ⬜ [F] Picking List Entry
- ⬜ [P] Packing List (cross-CO consolidated — line level!)
### 5.2 Delivery Order
- ⬜ [F] DO Entry (multi-CO, harga per line — CASE PENTING)
- ⬜ [G] DO List
- ⬜ [P] **Delivery Order / Surat Jalan** (line-level CO ref + price)
- ⬜ [F] Price-change approval *(⚠️ semula 3-party termasuk Finance — Finance di luar scope; verifikasi ulang alur approval jadi 2-party Costing+Sales, atau tahan fitur ini sampai keputusan bisnis)*
### 5.3 Shipment
- ⬜ [D] Load Planner (tonnage + m³)
- ⬜ [D] Route Planner (map, nearest-neighbor)
- ⬜ [P] Shipment Manifest

---

## MODUL 6 — INVOICING & BILLING (AR-lite)
> ⚠️ **Diganti dari "Finance & Accounting" penuh.** Cashier, General Ledger, Journal Entry, Trial Balance, AP **DI LUAR scope 10 minggu** — lihat `GLOBAL_RULES.yaml → modules_excluded`. Yang tersisa di sini murni AR-lite: invoice dari CO/DN + status tagih/bayar, tanpa buku besar.

### 6.1 Invoice Generation
- ⬜ [F] Invoice Entry (dari CO/DN)
- ⬜ [P] **Invoice / Faktur** (per CO)
- ⬜ [P] Faktur Pajak / e-Faktur layout

### 6.2 Billing Status
- ⬜ [G] Billing Status List (tagih/lunas/sebagian)
- ⬜ [F] Payment Record Entry (pencatatan bayar masuk, BUKAN cashier/jurnal)

### 6.3 AR Aging (basic)
- ⬜ [G] AR Aging (per CO — invoice setelah semua shipment lock)
- ⬜ [P] Statement of Account

**Di luar scope 10 minggu (jangan dikerjakan sampai fase Finance dibuka):**
- ~~Cashier: Cash Receipt/Payment Entry, Kwitansi~~
- ~~AP Aging~~
- ~~General Ledger: Journal Entry, Trial Balance, Financial Dashboard~~

---

## MODUL 7 — HUMAN RESOURCE
> ⚠️ Sudah **tidak ditunda** — HRM digarap paralel di luar urutan asli dokumen ini (lihat memory `project_hrm_module_status`). Master Employee, Machine/Shift, dan Self-Service sudah live.
> **2026-08-21**: `tm2old.emp` (485 karyawan riil) dimigrasikan via Security → Migrasi Data Legacy (`App\Services\Migration\EmployeeMigrationService`). Departemen/jabatan asli nyaris tak tercatat di sumber (cuma 1 & 5 dari 485 baris) — semua karyawan hasil migrasi sementara dibucket ke satu departemen/jabatan "Migrasi Legacy - Belum Dipetakan", **wajib di-assign ulang manual** oleh HR. Menyusul: 137 akun login (`sec_user`) dibuat untuk karyawan aktif (`EmployeeLoginProvisionService`, tombol "Buat Login Karyawan" di tab yang sama) — karyawan yang sudah keluar sengaja tidak dibuatkan akun. Detail lengkap + cara lihat password di memory `project_hrm_module_status`.
- ✅ [F][G] Master Employee — *mockup_master_employee.html* → `hrm.employee`
- ✅ [D] Shift Roster — *mockup_shift_roster_v2.html* → `hrm.shift-roster`
- ✅ [F] Personal Data Self-Service — *mockup_personal_data_self_service.html* → `self-service.index`
- ✅ [G] Master Mesin & Shift (Produksi) — *mockup_master_produksi.html* → `master.machine` + `master.shift`
- ⬜ [F] Applicant Entry / Recruitment Pipeline — belum ada mockup
- ⬜ [G] Attendance Sheet / Dashboard — belum ada mockup
- ⬜ [F] Payroll Run Entry, Slip Gaji, Payroll Summary — belum ada mockup

---

## MODUL 8 — LOOKUP DATA
- ⬜ [F] Global Reference Entry
- ⬜ [G] Lookup Master (generic maintainer)
- ⬜ [F] Parameter/Config Entry

---

## MODUL 9 — SECURITY & NOTIFICATION
- ✅ [F][G] User & Role Management — *security.html* → `security.index` (grup, permission per modul, user list jadi satu layar)
- ✅ [G] Notification Center — `notifications.index` (grid penuh: cari, filter tipe, hanya-belum-dibaca, klik baris = tandai terbaca + buka `link` tujuan; drawer bel di main-menu dapat tautan "Lihat semua notifikasi →" ke layar ini, dan drawer itu sendiri diperbaiki supaya klik notifikasi ber-link benar-benar navigasi, bukan cuma tandai terbaca). Ditemukan 17 notifikasi riil "Pendaftaran baru menunggu approval" yang sudah menumpuk belum dibaca sejak sebelum sesi ini — lihat pesan ke Jo.
- ⬜ [G] Audit Trail Viewer — belum ada mockup

---

## MODUL 10 — MASTER DATA
> Tidak ada di dokumen asli ini (checklist lama ditulis sebelum modul Master Data dipisah dari Sales). Sudah live duluan dari beberapa modul lain di atas.
- ✅ [F][G] Customer — *mockup_master_customer.html* → `master.customer` (v2: grid+form bersatu, lihat memory `project_customer_master_v2_status`)
- ✅ [F][G] Supplier — *mockup_master_customer.html* (varian) → `master.supplier`
- ✅ [F][G] UoM — *mockup_master_uom_v4.html* → `master.uom`

---

## MODUL 11 — FINANCE & ACCOUNTING
> ⚠️ **Kontradiksi dengan Modul 6 di bawah** (yang bilang "GL/Journal/COA di luar scope 10 minggu") — pada praktiknya COA sudah dibangun & live. Perlu keputusan user: apakah catatan "di luar scope" di Modul 6 mau direvisi, atau COA yang sekarang live dianggap pengecualian scoped-in. Belum saya ubah sepihak.
- ✅ [G] Chart of Account — *master_coa_mockup.html* → `finance.coa`
- ✅ [F] DP Invoice — *mockup_down_payment.html* → `finance.dp-invoice` (Event 1/2/4 teruji, lihat memory `project_dp_invoice_status`)

---

## REKAP STATUS
| Modul | ✅ Live | 🟡 Mockup ada, belum digarap | ⬜ Belum ada mockup |
|---|---|---|---|
| 1 Sales (Inquiry/Quotation/CO) | 13 | 4 | 1 |
| 2 Inventory | 6 | 0 | 8 |
| 3 Production | 0 | 2 | 9 |
| 4 Procurement | 8 | 0 | 7 |
| 5 Dispatch | 0 | 0 | 9 |
| 6 Invoicing/Billing (AR-lite) | 0 | 0 | 7 |
| 7 HR | 4 | 0 | 3 |
| 8 Lookup | 0 | 0 | 3 |
| 9 Security | 2 | 0 | 1 |
| 10 Master Data | 3 | 0 | 0 |
| 11 Finance & Accounting | 2 | 0 | 0 |

**38 item live**, **6 item punya mockup HTML tapi belum diimplementasi Livewire**, **48 item belum punya mockup/spec-layar sama sekali** (Production inti/Dispatch/Invoicing masih kosong total; Inventory sudah punya fondasi skema Tier 3 penuh + 2 layar laporan/cetak, tapi mesin transaksi Tier 4 — Stock In/Out/Adjustment/Return — masih kosong; Procurement spine PR→PR Approval→PO→PO Approval→PV sudah live penuh dan bisa dijalankan ujung-ke-ujung tanpa akal-akalan, dan sejak Inventory Tier 3 jalan, blocker lama "dump tminv belum ada" untuk `PvPostingService::post()` sudah **selesai** — tinggal disambungkan; Notification Center & Nota Inquiry per-tipe kini juga live). *(Product Costing Engine (HPP) dikeluarkan dari hitungan 🟡 2026-08-21 — dibatalkan sebagai duplikat Costing Hub, lihat 1.1.)*

**Prioritas alur bisnis (selaras GLOBAL_START_HERE — urutan dependensi terkunci):**
Sales (spine) → Inventory (fondasi item untuk MRP) → Production (MPS/MRP) → Procurement (dipicu MRP) → Dispatch (DO/DN, CASE PENTING cross-CO) → Invoicing/Billing (AR-lite, penutup order-to-cash).
*(Catatan: urutan lama "Sales→Dispatch→Finance, Procurement/Inventory/Production paralel" sudah tidak berlaku — MRP butuh Inventory lebih dulu, jadi urutan kini strictly sequential per dependensi data, bukan paralel bebas.)*
