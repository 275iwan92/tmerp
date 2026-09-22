---
name: screen_procost_form
description: Layar Costing (HPP) Mode INTEGRASI per-inquiry — mockup ERP_Product_Costing_Mockup.html + semua info InqProd & ProDev read-only (BOM, BOP, layout, tinta, finishing); hitung via CostingEngine.
---

# Product Costing (HPP) — Mode Integrasi

**File**: `resources/views/pages/inquiry/costing.blade.php`
**Route**: `inquiry.costing` (`/inquiry/{inquiry}/costing`) · read-only lintas modul: `?view=1`
**Koneksi DB**: sdi (tm2sdi) + baca mst (tm2mst) lintas-DB pola 1 (query terpisah)
**Pasangan**: Mode Instant = `docs/screens/screen_procost_instant_form.md`; grid keduanya = `screen_procost_grid.md`.
**Acuan**: mockup `docs/mockups/ERP_Product_Costing_Mockup.html`; deviasi disetujui user 2026-09-18 = `docs/mockups/procost_integrasi_deviasi.jpg`; hasil build = `docs/mockups/procost_integrasi_hasil_build.png`. Rumus: `docs/inquiry_product_costing/procost_formula_library.md` §VII (sumber kebenaran = `docs/ddl/tmhpp.sql`).

## Tujuan
Arahan user 2026-09-18: program lama tidak bisa menampilkan ilustrasi, jadi di mode ini **semua informasi Inquiry Product & ProDev tampil READ-ONLY** sebagai bahan pertimbangan officer — terutama **BOM & BOP**, karena harga ditentukan banyaknya proses, subkon atau tidak, bahan baku, ukuran, merk, tinta. Officer hanya mengisi parameter yang memang perlu (ongkos, margin, toleransi, override harga kertas).

## Tata letak (urutan = mockup #3)
1. Wizard-bar lintas modul (Inquiry/Development/Costing/Master Card) + Back (`data-back`).
2. Header: breadcrumb `ERP System / Product Costing / {doc_no|inquiry_no}`, judul "Kalkulasi HPP (Costing)", badge status, tombol ⚙ Hitung · Submit ke Direksi · ＋ Quotation (deviasi #1).
3. 3 kartu: **Doc & Customer Info** (Doc No/Date, Inquiry, Customer, Type + Rev, PIC Sales) · **Product Specification** (produk, part + tipe, P/L/T, + InqProd: warna, tinta, finishing, material, qty/set, sumber Inquiry+ProDev MC & status, thumbnail artwork; pager part "1..N of N") · **Dynamic Parameters** (Qty = pilihan tier Inquiry, Margin, Paper Index = `i_paper_price_override`, PPN, + Toleransi & Rounding).
4. Tambah part: dari part Inquiry / Part Manual / hapus part.
5. **Visual Imposition Layout (ProDev)**: toggle Ambil Data/Manual, ganti layout CONFIRMED, info plano/material/print area/imposition, kotak biru (print size, metode, UP, efisiensi/waste, pisau+ukuran, meter lari, status), ilustrasi `x-costing.imposition-svg` dari `prod_layout_group.layout_detail` (format optimizer & grid).
6. **Granular Cost Breakdown** (gated `PROCOST_FORMULA_LOGINS`): Materials (plano sheets = qty ÷ (potong × isi), cost/plano, total) · Process & Finishing (tabel Rate/Basis/Subtotal, index coating inline) · Tooling (film/plate/pisau nilai efektif) · `description_calc`.
7. Accordion read-only: **BOM** (`iqp_part.bomMaterials` + merk/ukuran `mat_raw`), **BOP** (`prod_part_process` ProDev, fallback `iqp_part_process`: mesin, In-house/Subkon `subcon_supplier_id`, target/jam, faktor, waste + warning routing vs ongkos), **Tinta**, **Finishing & Detail Part ProDev** (kode/ukuran pisau, bahan mentah, sambungan, ECMA, lidah, warna unit).
8. Accordion **Parameter Ongkos & Bahan (input officer)** — field per Tipe Part; geometri (plano/potong/isi/ukuran cetak) terkunci saat Ambil Data; coating chips.
9. Banner gelap: Total COGS, Margin (+tol), PPN, **Final Selling Price** (incl PPN) + excl PPN + Grand Total.

## Aksi / Method
- `mount(int $inquiry)` — firstOrCreate `iqp_costing` (mode INTEGRATED, snapshot customer/produk), tier dari `iqp_tier` (kosong → 1 tier default 1.000 + peringatan), **auto-buat baris costing utk part Inquiry yang sudah punya layout ProDev** (`applyLayout`: plano, potong, isi, print size, faktor pisau dari `die_rule_meter`).
- `addPart`, `addPartFromInquiry`, `removePart`, `goPart`, `selectTier`, `toggleDataSource`, `pickLayoutGroup` (Tool #10 multi-part wajib pilih part), `updatePart(partId, field, value)` (whitelist semua field legacy + auto-fill ala Ajax lama), `addCoating/updateCoating/removeCoating`, `updateTier`, `updateHeader` (ppn), `setPaperIndex`, `hitung`, `submitToDireksi`, `addToQuotation`, `routingNamesFor/routingWarningsFor`, `processRows`, `toolingInfo`, `materialLabel`, `wizardSteps`.

## Tabel & Service
`iqp_costing`, `iqp_costing_part`, `iqp_costing_part_coating`, `iqp_costing_price_tier` (sdi) · `iqp_inquiry/part/part_material/part_process/part_ink/part_finishing/layout` (sdi) · `prod_part`, `prod_part_process`, `prod_part_finishing`, `prod_layout_group(_part)`, `prod_machine`, `sup_supplier`, `mat_raw`, `ref_finishing_type`, master rate `mat_*` (mst) · `App\Services\Costing\CostingEngine`, `ProCostContextService`, `App\Support\Costing\FormulaAccess`.

## Catatan
- Breakdown & HPP hanya login di konstanta `PROCOST_FORMULA_LOGINS` (default `johiwan,wiwik2`; Carrij Suhendra belum punya akun).
- Geometri dari layout ProDev: optimizer → potong = jumlah kanvas per plano, isi = jumlah objek, print size = kanvas; grid → isi = kolom × baris, potong 1. Group multi-part → potong mengikuti `effectiveCutFor()`.
- Test: `CostingEngineTest`, `CostingRoutingWarningTest`, `CostingLegacyParityTest`.
