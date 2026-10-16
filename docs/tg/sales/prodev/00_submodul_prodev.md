# Sub-modul: Pro-Dev (Product Development)

> DRAFT KELOMPOK C (2026-10-16). Halaman sub-modul hanya merangkum dan menautkan; fakta ada di kartu. Menu (FAKTA, `sys_app_menu` mockup, parent `SALES_PRODEVGRP` di bawah Inquiry & Quotation): Pro-Dev New (`v2:prodev-new`), Pro-Dev Revise (`v2:prodev-revise`), Pro-Dev Manual (`v2:prodev`).

- **Modul**: Sales > Inquiry & Quotation > Pro-Dev.
- **Tujuan**: dari Inquiry, menurunkan layout produksi (plano, UP), BOP, BOM, Ink Configuration dan Master Card; officer memutuskan (A.7). Hasil menjadi dasar Pro-Cost, lalu WO.
- **Zona beku**: flow/data ProDev. Dibaca: `docs/claude_vital_flows.md`, `docs/ENGINE-ATTENTION.md`.

## Relasi
- **sumber**: Inquiry Product (part, finishing, bahan, qty tier, `stat_confirm_sales`), master `prod_type` (dieline), aturan `prod_bop_rule`.
- **dipakai_oleh**: Pro-Cost (layout CONFIRMED lewat `ProCostContextService`), Material Planning (UP dari layout), pembuatan WO (BOP/BOM).
- **tanda**: `stat_confirm_prodev` di `iqp_inquiry` (A.20), dihitung ulang setelah Save/Confirm layout.

## Engine di sub-modul ini
| Engine | Kartu | Pemicu ringkas |
|---|---|---|
| Imposition Engine | `10_engine_imposition.md` | layar v1 Imposition per Inquiry, Form WO (pratinjau), Costing Advisor; ProDev aktif TIDAK memakainya |
| Spec Engine (BOP, BOM, Ink) | `20_engine_spec.md` | tombol/aksi ProDev (generate, Save), pembuatan dan penyusunan ulang WO |
| Usage Rate Engine | `30_engine_usage-rate.md` | jadwal bulanan tgl 1 02:00, tombol di layar BOP Rule |
| Routing Template Engine | TIDAK ditulis | tidak terhubung ke ProDev menurut kode; lihat `../90_temuan_engine-kelompok-c.md` |
| Antarmuka Inquiry <-> ProDev (kartu konsep, kelompok lain) | `10_kartu_antarmuka-prodev.md` | tombol Kirim ke ProDev, Save/Confirm layout |
| Dieline Engine | `../inquiry-product/20_engine_dieline.md` | tampilan Part & Dieline (Inquiry dan ProDev) |

## Diagram
`../img/imposition.svg`, `../img/spec-engine.svg` (kembaran teks di tiap kartu, bagian "Alur").

## Panduan pemakai (UG)
`docs/guides/sales/02_prodev.md` (FAKTA: berkas ada; isinya tidak dibaca). TG tidak mengulang isinya.
