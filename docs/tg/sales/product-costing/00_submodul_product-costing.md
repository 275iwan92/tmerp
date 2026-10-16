# Sub-modul: Product Costing (Pro-Cost)

> CONTOH FASE 1 (2026-10-16). Halaman sub-modul hanya merangkum dan menautkan; fakta tabel/kolom ada di kartu.

- **Modul**: Sales > Inquiry & Quotation > Pro-Cost. Menu (FAKTA, `sys_app_menu` mockup): Pro-Cost New, Revise, Recalc, Instant (`v2:sales-procost*`).
- **Tujuan**: menghitung HPP dan harga jual per tier qty dari Inquiry + ProDev; costing rahasia (A.8-A.9), publish = deklasifikasi ke Sales.
- **Zona beku**: flow/data ProCost. Dibaca: `docs/claude_vital_flows.md`, `docs/ENGINE-ATTENTION.md`.

## Relasi
- **sumber**: Inquiry Product (BOM, part, `stat_confirm_sales`), ProDev (layout, waste BOP, `stat_confirm_prodev`), master tarif (`prod_part_template`, `mat_grammage`), konstanta COSTING.
- **dipakai_oleh**: Quotation (harga published, wajib integrated A.1), lalu Customer Order.
- **tanda**: `stat_confirm_procost` di `iqp_inquiry` (A.20).

## Engine di sub-modul ini
| Engine | Kartu | Pemicu |
|---|---|---|
| Costing Advisor | `10_engine_costing-advisor.md` | layar costing (tanpa background) |
| Costing Engine (rumus harga) | BELUM ditulis | layar costing, tombol Calculate (dari kode: dipanggil sebelum `learn`) |
| Reverse Costing | BELUM ditulis | BELUM dicek |

## Diagram
`../img/costing-advisor.svg` (kembaran teks di kartu, bagian "Alur").

## Panduan pemakai (UG) yang sudah ada
`docs/guides/sales/03_procost.md` (section guide). TG tidak mengulang isinya.
