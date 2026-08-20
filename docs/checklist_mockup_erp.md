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
- 🟡 [P] Nota Inquiry NEW/RECALC/REVISE — *inquiry_nota_new/recalc/revise.html* — varian per tipe dokumen, belum digarap (beda dari nota generik yang sudah live)
- ⬜ [F] Prove Warna — *inquiry_proof.html* — log versi v1/v2/v3, belum digarap
- 🟡 [D] Costing Manual Cross-Check — *inquiry_costing_manual.html* — beda dari Costing Hub yang sudah live, belum digarap
- 🟡 [D] Product Costing Engine (HPP) — *product_costing.html* — belum digarap (lihat GAP #2 CLAUDE.md §5: rate versioned coating/film/sheet)

### 1.2 Quotation
- ✅ [P] Quotation resmi (print) — *quotation_mockup.html* → `inquiry.quotation` (selesai 2026-08-20; tabel baru `quo_quotation` — header tipis, nomor QUO+YY+NNNNN auto-terbit HANYA kalau costing sudah `published_flag=1`; bank tujuan transfer dari `sys_constant` BANK_*, bukan angka karangan)
- 🟡 [P] Nota Quotation — *inquiry_nota_quotation.html* — idem, belum digarap (varian print beda dari quotation resmi di atas)
- ⬜ [F] Quotation Entry/Edit — *inquiry_quotation.html* — belum digarap (CRUD/revisi manual, beda dari header auto-generate di atas)
- ⬜ [G] Quotation List — belum ada mockup

### 1.3 Sales Order (Customer Order)
- 🟡 [F] CO Entry — *co_entry_mockup.html* — mockup selesai, belum ada Livewire
- 🟡 [F] Delivery Plan — *co_delivery_plan_mockup.html* — idem
- 🟡 [P] Nota CO (print) — *nota_co_mockup.html* — idem
- ⬜ [G] Sales Order List — belum ada mockup

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
*(dipicu MRP)*
### 4.1 Purchase Requisition
- 🟡 [F] PR Entry — *mockup_purchase_requisition.html* — mockup ada, belum digarap
- 🟡 [G] PR Approval Center — *mockup_pr_approval_center.html* — idem
- ⬜ [P] PR Print — belum ada mockup
### 4.2 Purchase Order
- 🟡 [G] PO Approval Center — *mockup_po_approval_center.html* — mockup ada, belum digarap
- ⬜ [F] PO Entry — belum ada mockup
- ⬜ [P] Purchase Order (cetak ke supplier) — belum ada mockup
### 4.3 Receiving
- ⬜ [F] Goods Receipt Entry (terhadap PO)
- ⬜ [G] Receiving List
- ⬜ [P] Receiving Note / GRN

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
- 🟡 [G] Notification Center — ada widget notifikasi di main-menu, belum ada layar tersendiri
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
| 1 Sales (Inquiry/Quotation/CO) | 8 | 7 | 4 |
| 2 Inventory | 0 | 0 | 7 |
| 3 Production | 0 | 2 | 9 |
| 4 Procurement | 0 | 3 | 6 |
| 5 Dispatch | 0 | 0 | 9 |
| 6 Invoicing/Billing (AR-lite) | 0 | 0 | 7 |
| 7 HR | 4 | 0 | 3 |
| 8 Lookup | 0 | 0 | 3 |
| 9 Security | 1 | 1 | 1 |
| 10 Master Data | 3 | 0 | 0 |
| 11 Finance & Accounting | 2 | 0 | 0 |

**18 item live**, **13 item punya mockup HTML tapi belum diimplementasi Livewire**, **49 item belum punya mockup sama sekali** (Inventory/Production inti/Procurement inti/Dispatch/Invoicing masih kosong total).

**Prioritas alur bisnis (selaras GLOBAL_START_HERE — urutan dependensi terkunci):**
Sales (spine) → Inventory (fondasi item untuk MRP) → Production (MPS/MRP) → Procurement (dipicu MRP) → Dispatch (DO/DN, CASE PENTING cross-CO) → Invoicing/Billing (AR-lite, penutup order-to-cash).
*(Catatan: urutan lama "Sales→Dispatch→Finance, Procurement/Inventory/Production paralel" sudah tidak berlaku — MRP butuh Inventory lebih dulu, jadi urutan kini strictly sequential per dependensi data, bukan paralel bebas.)*
