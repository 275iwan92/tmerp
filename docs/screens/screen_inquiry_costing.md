---
name: screen_inquiry_costing
description: Layar Costing (HPP) per-inquiry — hitung struktur biaya per part (DC/SHEET_DIGITAL), coating, dan ladder harga per qty-tier via CostingEngine.
---

# Product Costing (HPP)

**File**: `resources/views/pages/inquiry/costing.blade.php`
**Route**: `inquiry.costing` (`/inquiry/{inquiry}/costing`, param `inquiry`)
**Koneksi DB utama**: sdi (tm2sdi), baca lintas-DB ke mst (tm2mst) untuk rate/material
**Modul**: Inquiry Product

## Tujuan
Layar kerja utama role Costing untuk menghitung HPP (harga pokok produksi) dan ladder harga jual per qty-tier dari 1 inquiry. Entry primer dari worklist `inquiry.costing.list` (buka mode edit full engine); juga reachable read-only via wizard-bar lintas modul (`?view=1`) dari Inquiry/ProDev — mode itu hanya menampilkan status & harga final per tier setelah `published_flag=true`, tidak bisa mengubah apa pun dan tidak menampilkan breakdown HPP (jaga Sisi A/Sisi B, CLAUDE.md §4). Punya 2 mode sumber data per part ("Ambil Data" dari `prod_layout_group` ProDev yang CONFIRMED, atau "Manual" isi semua parameter sendiri) — keduanya harus menghasilkan HPP sama untuk geometri setara (CLAUDE.md §4 ProCost 2-mode). Breakdown formula HPP (biaya bahan/proses/coating/tooling, kolom HPP di ladder harga) disembunyikan dari semua user KECUALI login literal `johiwan` (`canSeeFormula()`) — lebih ketat dari aturan umum Costing+Direksi.

## Field & Kolom
Header costing (`iqp_costing`, `mount()` auto-`firstOrCreate` + auto-fill dimensi dari part pertama inquiry + auto-generate 1 baris `IqpCostingPriceTier` per `iqp_tier`):
- `calc_type` (readonly display, saat ini selalu `NEW`), `doc_status` (badge: DRAFT/CALCULATED/PENDING_APPROVAL/PUBLISHED/FAILED), `i_dim_panjang_mm/i_dim_lebar_mm/i_dim_tinggi_mm`, `ppn_mode`/`ppn_pct`.

Per Part (`iqp_costing_part`, repeater, tombol "+ Part DC" / "+ Part Sheet" / dropdown "dari part inquiry"):
- Umum: `part_name`, `part_type_code` (DC/SHEET/SHEET_DIGITAL), toggle `data_source` (MANUAL vs PRODEV — disembunyikan untuk part type SHEET/SHEET_DIGITAL).
- Sumber Layout (kalau `data_source=PRODEV`): pilih `prod_layout_group_id` dari `ProdLayoutGroup::where('status','CONFIRMED')`; kalau group menaungi >1 part (Tool #10 COMBINED_SPLIT) wajib pilih `prodev_part_id` juga (alokasi proporsional by luas via `effectiveCutFor()`).
- DC: `grammage_id`, `i_panjang_plano_cm`/`i_lebar_plano_cm` (disabled saat PRODEV), `i_cut_per_plano` (disabled saat PRODEV), `i_isi_per_lb`, `i_jumlah_warna`, `i_ongkos_cetak_rim`, `i_ongkos_plong_lb`, `i_ongkos_lem_pc`, `i_film_faktor`, `i_pisau_faktor`, `i_pisau_rate_rp`, `i_ongkos_plate_warna`. Punya warning validasi silang terhadap routing ProDev (`routingWarningsFor()`) untuk 3 field ongkos (CETAK/PLONG/LEM MESIN) vs `ProdPartProcess` — engine suggests, officer confirms.
- SHEET_DIGITAL: dimensi `i_dim_panjang_mm/lebar/tinggi`, `i_lidah_lem_mm`, `flute_type`, `sheet_rate_id`, `supplier_sheet_code`, `i_isi_per_lb_kerja`, `i_ongkos_print_pc`, `i_ongkos_diecut_pc`, `i_ongkos_slitter_pc`, `i_ongkos_slotter_pc`, `i_ongkos_join_pc`, `i_ongkos_partisi_pc`, `i_ongkos_layer_pc`, `i_ongkos_qc_pack_pc`, `i_ongkos_kirim_pc`, `i_ongkos_kupas_pc`, `i_ongkos_packing_pc`, `jenis_packing` (free-text, belum ada daftar baku).
- Coating (nested repeater per part, disembunyikan untuk SHEET_DIGITAL): `coating_type`, `index_coating` (auto dari `mat_coating_rate`).
- Artwork (read-only, dari `iqp_part.artwork_path` via relasi `inquiryPart`) — preview + zoom, mengalir dari Inquiry, tidak diupload ulang di sini.
- Hasil hitung (tampil hanya kalau `canSeeFormula`): `c_paper_cost_pc`, `c_cetak_cost_pc+c_plong_cost_pc+c_lem_cost_pc`, `c_total_coating_pc`, `c_tooling_cost_order`, `c_total_part_cost_pc`; untuk SHEET_DIGITAL juga `c_panjang_sheet_cm`/`c_lebar_sheet_cm`/`c_isi_trim`/`c_lebar_trim_cm`/`c_running_meter`/`c_discount_rm`.

Ladder Harga per Tier (`iqp_costing_price_tier`, 1 baris per `iqp_tier`):
- `qty_pcs` (readonly, dari tier), `tolerance_pct`, `margin_pct`, `rounding_mode` (NEAREST/UP/DOWN), `c_hpp_pc` (gated `canSeeFormula`), `price_per_pcs`, `c_after_ppn_pc`.

## Aksi / Method
- `mount(int $inquiry)` — firstOrCreate `iqp_costing`, auto-fill dimensi & price tier awal. Mode `$view=true` tidak membuat draft (murni baca).
- `addPart(string $partType)`, `addPartFromInquiry(int $inquiryPartId)` — tambah baris part, yang kedua auto-fill plano/cut dari `IqpLayout` confirmed (Imposition).
- `toggleDataSource(int $partId)` — switch MANUAL <-> PRODEV per part (bukan per-costing).
- `pickLayoutGroup(int $partId, int $groupId, ?int $prodPartId)` — tarik plano/UP/pisau-faktor dari `ProdLayoutGroup`, alokasi proporsional kalau group multi-part, langsung panggil `hitung()`.
- `removePart`, `addCoating`, `removeCoating`, `updatePart(int $partId, string $field, $value)` (whitelist field, guard field terkunci saat PRODEV), `updateCoating`, `updateTier`.
- `hitung()` — jalankan `CostingEngine::run($costing)`, tangkap error ke `$engineError`.
- `submitToDireksi()` — set `doc_status=PENDING_APPROVAL` (hanya dari CALCULATED).
- `addToQuotation()` — `QuotationBuilder::findOrCreateDraft()` + `addInquiry()`, redirect ke `quotation.entry`.
- `#[Computed] wizardSteps()` — step-bar lintas modul (Inquiry/Development/Costing/Master Card/Analytics).
- `routingNamesFor()`/`routingWarningsFor()` — baca `ProdPartProcess` (routing ProDev) untuk validasi silang ongkos manual DC.

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpCosting` (`iqp_costing`, sdi) — header, relasi `parts`, `priceTiers`, `inquiry`.
- `App\Models\Sdi\IqpCostingPart` (`iqp_costing_part`, sdi) — baris part costing, relasi `coatings`, `inquiryPart`.
- `App\Models\Sdi\IqpCostingPartCoating` (`iqp_costing_part_coating`, sdi).
- `App\Models\Sdi\IqpCostingPriceTier` (`iqp_costing_price_tier`, sdi).
- `App\Models\Sdi\IqpInquiry`, `App\Models\Sdi\IqpPart`, `App\Models\Sdi\IqpLayout` (sdi) — inquiry & Imposition confirmed.
- `App\Models\Master\MatGrammage`, `MatCoatingRate`, `MatSheetRate` (tm2mst) — lookup rate/gramatur, query terpisah (pola 1).
- `App\Models\Master\ProdLayoutGroup`, `ProdPartProcess` (tm2mst) — Mode "Ambil Data" & routing ProDev, lintas-DB query terpisah.
- `App\Services\Costing\CostingEngine` — mesin hitung HPP, dipanggil dari `hitung()`.
- `App\Services\Sales\QuotationBuilder` — dipakai `addToQuotation()`.

## Alur / Business Logic Penting
- **2-mode input (CLAUDE.md §4)**: Mode #1 "Ambil Data" tarik plano/UP dari `prod_layout_group` CONFIRMED ProDev; Mode #2 "Manual" isi semua parameter sendiri. Field plano/cut terkunci (disabled + guard di `updatePart()`) selama `data_source=PRODEV`.
- **Group multi-part (Tool #10)**: kalau 1 layout menaungi >1 part, wajib pilih part representatif — biaya kertas/plate dialokasi proporsional by luas (`effectiveCutFor()`), bukan dobel-hitung.
- **Costing rahasia Sisi A**: breakdown formula & kolom HPP hanya untuk login `johiwan`; staf Costing lain tetap bisa atur margin/toleransi/rounding tanpa melihat breakdown.
- **Cross-nav read-only** (`$view=1`): dipaksa `readOnly` total, tidak `firstOrCreate` draft baru (hindari side-effect dari navigasi pasif), hanya tampilkan status + harga final per tier setelah PUBLISHED.
- Validasi silang routing ProDev vs 3 field ongkos manual DC — peringatan saja (tidak blocking), pola "engine suggests, officer confirms".
- Publish (Direksi approve `doc_status=PUBLISHED`) mendeklasifikasi harga ke Sisi B (Sales) — terjadi di luar layar ini (grid/approval Direksi).

## Catatan
- Gap dari komentar kode: Jenis Packing masih free-text (belum ada daftar baku dikonfirmasi user). Ongkos Kupas ditambahkan menyusul gap-analysis vs ScriptCase lama (QC+Packing dulu 1 kolom, sekarang dipisah).
- ProCost belum konsumsi urutan proses ProDev untuk auto-hitung angka (tidak ada kolom rate Rupiah di `prod_process`/`prod_part_process`) — cuma dipakai validasi silang (warning), bukan sumber angka.
