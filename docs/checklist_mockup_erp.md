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
- 🟡 [D] Product Costing Engine (HPP) — *product_costing.html* — belum digarap (lihat GAP #2 CLAUDE.md §5: rate versioned coating/film/sheet)

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
- 🟡 [F] Delivery Plan — *co_delivery_plan_mockup.html* — belum digarap (catatan: tab "Delivery" di CO Entry sudah cover jadwal kirim per baris produk; mockup terpisah ini mungkin dashboard konsolidasi lintas-CO, perlu klarifikasi scope kalau mau dilanjut)
- 🟡 [P] Nota CO (print) — *nota_co_mockup.html* — belum digarap

---

## MODUL 2 — INVENTORY
*(urutan dependensi: sebelum Production, karena MRP butuh item_master)*
### 2.1 Item Master
- ⬜ [F] Item Entry
- ⬜ [G] Item List/Catalog
### 2.2 Stock Movement
- ⬜ [G] Stock Movement Ledger (kartu stok)
- ⬜ [P] Kartu Stok print
### 2.3 Stock Adjustment
- ⬜ [F] Adjustment Entry
- ⬜ [P] Adjustment Voucher
- ⬜ [D] Stock Level Dashboard (min/max, reorder)

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
- 🟡 [G] PO Approval Center — *mockup_po_approval_center.html* — mockup ada, belum digarap (menyusul, di luar urutan yang diminta Jo saat ini)
- ⬜ [P] PO Print (dokumen eksternal ke supplier) — belum digarap
### 4.3 Purchase Quotation (PQ) — kondisional, boleh dilewati
- ⬜ [F][G] PQ Entry + Perbandingan — belum digarap
### 4.4 Purchase Receive (PV) / Purchase Return (PRT)
- ⬜ [F] PV Entry (Sisi B, dual-UOM, QC 2 gerbang) — belum digarap, butuh `tminv_20260722_2018_sd.sql` (rm_stock/rm_stock_d/rm_stockcard) yang belum diberikan user
- ⬜ [D] PV Monitor (Sisi A) — belum digarap
- ⬜ [F] PRT (Purchase Return) — belum digarap
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
| 1 Sales (Inquiry/Quotation/CO) | 13 | 5 | 1 |
| 2 Inventory | 0 | 0 | 7 |
| 3 Production | 0 | 2 | 9 |
| 4 Procurement | 5 | 1 | 7 |
| 5 Dispatch | 0 | 0 | 9 |
| 6 Invoicing/Billing (AR-lite) | 0 | 0 | 7 |
| 7 HR | 4 | 0 | 3 |
| 8 Lookup | 0 | 0 | 3 |
| 9 Security | 2 | 0 | 1 |
| 10 Master Data | 3 | 0 | 0 |
| 11 Finance & Accounting | 2 | 0 | 0 |

**29 item live**, **8 item punya mockup HTML tapi belum diimplementasi Livewire**, **47 item belum punya mockup/spec-layar sama sekali** (Inventory/Production inti/Dispatch/Invoicing masih kosong total; Procurement sekarang punya spec Tier 1-4 lengkap dan PR List/Entry/Approval + PO List/Entry sudah live; Notification Center & Nota Inquiry per-tipe kini juga live).

**Prioritas alur bisnis (selaras GLOBAL_START_HERE — urutan dependensi terkunci):**
Sales (spine) → Inventory (fondasi item untuk MRP) → Production (MPS/MRP) → Procurement (dipicu MRP) → Dispatch (DO/DN, CASE PENTING cross-CO) → Invoicing/Billing (AR-lite, penutup order-to-cash).
*(Catatan: urutan lama "Sales→Dispatch→Finance, Procurement/Inventory/Production paralel" sudah tidak berlaku — MRP butuh Inventory lebih dulu, jadi urutan kini strictly sequential per dependensi data, bukan paralel bebas.)*
