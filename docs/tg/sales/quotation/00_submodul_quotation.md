# Sub-modul: Quotation

> Kelompok B, 2026-10-16. Halaman sub-modul hanya merangkum dan menautkan. FAKTA = menu `sys_app_menu` mockup dan kode dengan baris.

- **Modul**: Sales > Inquiry & Quotation. Menu (FAKTA): item `Quotation` (`v2:quotation`, id 113) langsung di bawah grup "Inquiry & Quotation" (bukan grup sendiri); grup Report (id 1089): Open Quotations (`v2:quotation-outstanding`), Follow-up Quotation (`v2:quotation-followup`), Win-Rate Sales (`v2:sales-win-rate`; item yang sama juga ada di Sales > CRM > Report, id 1194). Item "Inquiry Note" (id 931) tidak aktif.
- **Tujuan**: Sales memilih inquiry berharga (costing PUBLISHED) milik satu customer, menyusun penawaran, menerbitkannya (PUBLISHED), menindaklanjuti (follow-up), lalu WIN (CO lahir) atau LOST.
- **Aturan keras**: Quotation WAJIB integrated (A.1); 1 Quotation = 1 CO (A.4/A.10, indeks unik di DB); CO boleh direct (INSTANT) tanpa Quotation (A.2); harga hanya untuk yang berhak (A.8-A.9).
- **Zona beku**: Quotation termasuk alur vital (`docs/claude_vital_flows.md`).

## Relasi
- **sumber**: Inquiry dengan costing PUBLISHED (kartu `../product-costing/11_kartu_antarmuka-procost.md`), customer, Sales PIC.
- **dipakai_oleh**: Customer Order (WIN; kartu CO milik kelompok lain), Master Product (lahir saat WIN), Win-Rate, Follow-up (`crm_activity`).
- **tanda**: Quotation tidak punya `stat_confirm_*`; statusnya `doc_status` DRAFT/PUBLISHED/CANCELLED, WON/LOST diturunkan dari status inquiry.

## Kartu konsep di sub-modul ini
| Kartu | Isi |
|---|---|
| `10_kartu_quotation.md` | Quotation (tabel, status, wajib integrated, 1 Quotation = 1 CO, visibilitas harga; bagian harga rahasia ada setelah penanda johiwan-only) |
| `../master-data/10_kartu_customer.md`, `../master-data/11_kartu_sales-pic.md` | Customer dan Sales PIC yang dipakai header Quotation |

## Engine di sub-modul ini
Tidak ada engine milik sub-modul ini. Pembangun baris: `QuotationBuilder` (layanan biasa; pendaftaran di `EngineRegistry` BELUM dicek). Hilir WIN: `../order/20_engine_co-order-builder.md`, kartu `../order/10_kartu_customer-order.md` dan `../order/12_kartu_status-dokumen.md` (kelompok lain).

## Temuan lintas kartu (ringkasan)
- Server tidak menegakkan "wajib integrated": baris bebas (FREEFORM) bisa membuat quotation tanpa inquiry; 5 quotation PUBLISHED di data tanpa satu baris pun.
- Quotation tidak memakai penyaring harga `canViewSalesPrice` (hanya gerbang modul SALES).
- Label sumber usang `quo_activity` (tabel sebenarnya `crm_activity`).

## Diagram
Belum dibuat. Alur teks: Inquiry (costing PUBLISHED) -> `QuotationBuilder::createFromInquiries` -> DRAFT -> Confirm (PUBLISHED) -> WIN: `CoOrderBuilder::findOrCreateFromQuotation` -> CO + Master Product; atau LOST/Cancel.

## Panduan pemakai (UG) yang sudah ada
`docs/guides/sales/04_quotation_order_cpdel.md` (DARI DOKUMEN, tidak dibuka di sesi ini).

