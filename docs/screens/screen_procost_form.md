---
name: screen_procost_form
description: Layar Costing (HPP) — 1 komponen Livewire dipakai 2 mode dokumen (Integrated per-inquiry, Instant standalone); hitung struktur biaya per part, coating, ladder harga via CostingEngine.
---

# Product Costing (HPP)

**File**: `resources/views/pages/inquiry/costing.blade.php`
**Route**: `inquiry.costing` (`/inquiry/{inquiry}/costing`, param `inquiry` — mode **INTEGRATED**) **atau** `inquiry.costing.instant` (`/costing/instant/{costing}`, param `costing` — mode **INSTANT**)
**Koneksi DB utama**: sdi (tm2sdi), baca lintas-DB ke mst (tm2mst) untuk rate/material
**Modul**: Inquiry Product

## Tujuan
Layar kerja utama role Costing untuk menghitung HPP (harga pokok produksi) dan ladder harga jual per qty-tier. **1 komponen Livewire dipakai 2 route** (keputusan user 2026-09-18, lihat `docs/inquiry_product_costing/procost_formula_library.md` §I) — `mount()` menerima `?int $inquiry` (mode INTEGRATED, perilaku asli tidak berubah) ATAU `?int $costing` (mode INSTANT, langsung by costing id tanpa inquiry). Template bercabang total via `$this->costing->isIntegrated()`/`isInstant()` (header, wizard-bar, sumber identitas customer/produk) sementara logic inti (part repeater, `CostingEngine`, coating, ladder tier) **tetap 1 kode yang sama** untuk kedua mode — sengaja tidak diduplikasi ke file terpisah supaya rumus tidak bisa "diam-diam beda" antar mode.

- **Mode INTEGRATED**: entry primer dari worklist `inquiry.costing.list`; juga reachable read-only via wizard-bar lintas modul (`?view=1`) dari Inquiry/ProDev — mode itu hanya menampilkan status & harga final per tier setelah `published_flag=true`, tidak bisa mengubah apa pun dan tidak menampilkan breakdown HPP (jaga Sisi A/Sisi B, CLAUDE.md §4). Header (customer/produk/dimensi) = read-only, snapshot dari `iqp_inquiry`.
- **Mode INSTANT**: entry primer dari worklist `inquiry.costing.instant.list` ("+ New Record" bikin baris kosong). Kalkulator HPP berdiri sendiri, tanpa Inquiry — header (Customer, Nama Produk, Dimensi) diisi **manual** (`updateHeaderField()`, kolom `iqp_costing.customer_id`/`product_name_draft`), tier qty ditambah/dihapus manual (`addTier()`/`removeTier()`, tidak ada `iqp_tier` sumber). Semua part selalu `data_source=MANUAL` — toggle "Ambil Data (ProDev)" disembunyikan total di mode ini (belum ada ProDev utk ditarik, itu baru muncul lewat Reverse). Wizard-bar (`<x-prodev.wizard-bar>`) tidak dirender sama sekali di mode ini (chrome-nya Inquiry/ProDev-sentris, tidak relevan).
- **Tombol Reverse** (`reverseToIntegrated()`, header mode INSTANT): **MASIH STUB** — cuma flash pesan error, belum generate Inquiry+ProDev. Tujuan akhirnya: auto-bikin `iqp_inquiry`(BARU)+`iqp_part` dari data Instant, **plus** `prod_product`(Master Product draft, `card_status=DRAFT`)+`prod_part`+`prod_layout_group`(DRAFT) karena kedua tabel ProDev itu `product_id NOT NULL` — lalu `iqp_costing.mode` di-flip ke INTEGRATED + `inquiry_id` diisi. Angka costing yang sudah ada **tidak boleh berubah** (part tetap `data_source=MANUAL` pasca-reverse, cuma silsilahnya bertambah) — keputusan user 2026-09-18. Belum dikerjakan krn perlu 1 keputusan lagi (apakah Reverse memang boleh auto-bikin Master Product, atau ProDev-nya ditunda sampai Master Product dibuat manual).
- Punya 2 mode sumber data **per part** untuk INTEGRATED ("Ambil Data" dari `prod_layout_group` ProDev yang CONFIRMED, atau "Manual" isi semua parameter sendiri) — keduanya harus menghasilkan HPP sama untuk geometri setara (CLAUDE.md §4 ProCost 2-mode). Breakdown formula HPP (biaya bahan/proses/coating/tooling, kolom HPP di ladder harga) disembunyikan dari semua user KECUALI login literal `johiwan` (`canSeeFormula()`) — lebih ketat dari aturan umum Costing+Direksi.

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
- `mount(?int $inquiry = null, ?int $costing = null)` — cabang di awal: `$costing` terisi = mode INSTANT (`findOrFail` langsung, generate 1 price tier default kalau belum ada apa pun); selainnya = mode INTEGRATED (perilaku asli: `firstOrCreate` `iqp_costing` by `$inquiry`, auto-fill dimensi & price tier dari `iqp_tier`). Mode `$view=true` (INTEGRATED saja) tidak membuat draft (murni baca).
- `updateHeaderField(string $field, $value)` — **INSTANT only** (no-op kalau dipanggil di INTEGRATED), whitelist `customer_id`/`product_name_draft`/`i_dim_*`/`ppn_mode`/`ppn_pct`.
- `addTier()`/`removeTier(int $tierId)` — **INSTANT only**, tier manual (Integrated tier sumbernya `iqp_tier`, immutable di layar ini).
- `reverseToIntegrated()` — **STUB**, lihat bagian Tujuan.
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
- Pustaka rumus super-detail (semua konstanta/guard per part_type) + referensi visual 2 mode tampilan (Manual vs Integrasi, dari mockup) + hasil cross-check formula vs `CostingEngine.php`: `docs/inquiry_product_costing/procost_formula_library.md`. Termasuk gap DC+SHEET/DC+SF yang belum lengkap (proses & coating asli) — lihat §V.3-nya sebelum menyentuh `calculateDcSheetPart()`.
- Test coverage mode Instant + regresi cepat mode Integrated: `tests/Feature/CostingInstantModeTest.php`.
- Mode Instant belum punya UI dense ala mockup `Kalkulasi_HPP_UAT.html` (Detailed Breakdown Per PC/Per Order, panel Coating grid terpisah) — saat ini reuse tampilan part-editor yang sama dengan Integrated. Polish visual itu, plus tombol Reverse sungguhan, adalah pekerjaan lanjutan (lihat `procost_formula_library.md` §VI).
