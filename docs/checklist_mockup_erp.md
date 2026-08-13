# MASTER CHECKLIST MOCKUP — ERP PT Telasih Makmur
**Peta kerja UI: Form Entry · Grid/List · Print-out** — scope 10 minggu (lihat GLOBAL_START_HERE.md).
Status: ✅ selesai · 🟡 sebagian/perlu revisi · ⬜ belum

Legenda tipe: **[F]** Form entry · **[G]** Grid/list · **[P]** Print-out/dokumen · **[D]** Dashboard/report

---

## MODUL 1 — SALES
### 1.1 Inquiry
- ✅ [G] Inquiry Hub (list + filter) — *inquiry_hub.html*
- ✅ [F] Inquiry New/Entry — *inquiry_new.html*
- 🟡 [F] Inquiry Edit — REVISE/RECALC (perlu varian dari New)
- ✅ [P] Nota Inquiry — *nota_inquiry_mockup.html*
- ✅ [P] Nota Inquiry B&W hemat tinta — *nota_inquiry_mockup_bw.html*
- ⬜ [F] Prove Warna — log versi v1/v2/v3
- ⬜ [F] Imposition — sub-engine (DRAFT→VERIFIED)
- ⬜ [D] Product Costing Engine — *product_costing.html* (🟡 ada, perlu cek final)

### 1.2 Quotation
- ✅ [P] Quotation resmi — *quotation_mockup.html*
- ⬜ [F] Quotation Entry/Edit
- ⬜ [G] Quotation List
- ⬜ [P] Quotation B&W hemat tinta

### 1.3 Sales Order (Customer Order)
- ⬜ [F] Sales Order Entry
- ⬜ [G] Sales Order List
- ⬜ [P] Sales Order Confirmation (cetak)

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
- 🟡 [D] Production Capacity Engine (HTML+CSS sudah dibuat — cek lokasi file)
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
- ⬜ [F] PR Entry
- ⬜ [G] PR List + approval status
- ⬜ [P] PR Print
### 4.2 Purchase Order
- ⬜ [F] PO Entry
- ⬜ [G] PO List
- ⬜ [P] Purchase Order (cetak ke supplier)
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

## MODUL 7 — HUMAN RESOURCE ⚪ DITUNDA (di luar scope 10 minggu)
> Tidak dikerjakan sampai spine order-to-cash selesai. Daftar dipertahankan sebagai referensi masa depan, bukan task aktif.
- ⬜ [F] Applicant Entry / Recruitment Pipeline
- ⬜ [G] Attendance Sheet / Dashboard
- ⬜ [F] Payroll Run Entry, Slip Gaji, Payroll Summary

---

## MODUL 8 — LOOKUP DATA
- ⬜ [F] Global Reference Entry
- ⬜ [G] Lookup Master (generic maintainer)
- ⬜ [F] Parameter/Config Entry

---

## MODUL 9 — SECURITY & NOTIFICATION
- ⬜ [F] User & Role Management
- ⬜ [G] User List + role matrix
- ⬜ [G] Notification Center
- ⬜ [G] Audit Trail Viewer

---

## REKAP STATUS (scope 10 minggu aktif)
| Modul | Selesai | Sebagian | Belum |
|---|---|---|---|
| 1 Sales | 5 | 3 | 8 |
| 2 Inventory | 0 | 0 | 8 |
| 3 Production | 0 | 1 | 8 |
| 4 Procurement | 0 | 0 | 9 |
| 5 Dispatch | 0 | 0 | 10 |
| 6 Invoicing/Billing (AR-lite) | 0 | 0 | 7 |
| 7 HR | — | — | ⚪ ditunda, tak dihitung |
| 8 Lookup | 0 | 0 | 3 |
| 9 Security | 0 | 0 | 4 |

**Prioritas alur bisnis (selaras GLOBAL_START_HERE — urutan dependensi terkunci):**
Sales (spine) → Inventory (fondasi item untuk MRP) → Production (MPS/MRP) → Procurement (dipicu MRP) → Dispatch (DO/DN, CASE PENTING cross-CO) → Invoicing/Billing (AR-lite, penutup order-to-cash).
*(Catatan: urutan lama "Sales→Dispatch→Finance, Procurement/Inventory/Production paralel" sudah tidak berlaku — MRP butuh Inventory lebih dulu, jadi urutan kini strictly sequential per dependensi data, bukan paralel bebas.)*
