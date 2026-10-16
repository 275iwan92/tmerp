# Sub-modul: Product Costing (Pro-Cost)

> Halaman sub-modul hanya merangkum dan menautkan; fakta tabel/kolom ada di kartu. Diperbarui 2026-10-16 (Fase 2).

- **Modul**: Sales > Inquiry & Quotation > Pro-Cost. Menu (FAKTA, `sys_app_menu` mockup): Pro-Cost New, Revise, Recalc, Instant (`v2:sales-procost*`).
- **Tujuan**: menghitung HPP dan harga jual per tier qty dari Inquiry + ProDev; costing rahasia (A.8-A.9), publish = deklasifikasi ke Sales.
- **Zona beku**: flow/data ProCost. Dibaca: `docs/claude_vital_flows.md`, `docs/ENGINE-ATTENTION.md`.

## Relasi
- **sumber**: Inquiry Product (BOM, part, `stat_confirm_sales`), ProDev (layout, waste BOP, `stat_confirm_prodev`), master tarif (`prod_part_template`, `mat_grammage`), konstanta COSTING.
- **dipakai_oleh**: Quotation (harga published, wajib integrated A.1), lalu Customer Order.
- **tanda**: `stat_confirm_procost` di `iqp_inquiry` (A.20).

## Kartu dan engine
| Isi | Berkas |
|---|---|
| Antarmuka ProCost (sisi Sales) | `11_kartu_antarmuka-procost.md` |
| Costing Advisor | `10_engine_costing-advisor.md` |
| Costing Engine (rumus harga) | `20_engine_costing-engine.md` |
| Reverse Costing | `30_engine_reverse-costing.md` |
| Quotation (penerima harga) | `../quotation/10_kartu_quotation.md` |

## Diagram
`../img/costing-advisor.svg`, `../img/costing-engine.svg` (kembaran teks ada di kartu, bagian "Alur").

## Panduan pemakai (UG) yang sudah ada
`docs/guides/sales/03_procost.md` (section guide). TG tidak mengulang isinya.
