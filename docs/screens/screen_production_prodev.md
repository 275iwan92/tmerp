---
name: screen_production_prodev
description: Workbench Product Development -- kerja utama ProDev officer, isi spesifikasi teknis part & jalankan Imposition Engine (Tingkat 1/2, Best Sheet, Combiner) untuk hasilkan prod_layout_group.
---

# Product Development (ProDev Workbench)

**File**: `resources/views/pages/production/prodev.blade.php`
**Route**: `production.prodev` (`/production/prodev/{part?}`, param `part` numeric opsional)
**Koneksi DB utama**: mst (tm2mst) — model `ProdPart`, `ProdLayoutGroup`, `ProdPartProcess`, `ProdPartFinishing`, `ProdPartAttachment`, `MatPlanoSize`, `ProdMachine`, `ProdProcess`, `RefFinishingType`; sdi (tm2sdi) untuk `IqpPart`/`IqpCostingPart` (lintas-DB logis); service `App\Services\Imposition\ImpositionEngine`
**Modul**: Production Scheduling & Product Development

## Tujuan
Layar KERJA ProDev (`docs/inquiry_product_costing/productdevelopment_tier1.md` §4.1/§4.2/§4.3a) — bukan Master Card itu sendiri (Master Card = tombol cetak dari data yang sama, lihat `prodev-print.blade.php`). Officer mengisi spesifikasi teknis `prod_part`, menjalankan ENGINE Tingkat 1 (parametric flap sweep) atau Tingkat 2 (interlock), memilih hasil terbaik → tersimpan ke `prod_layout_group`. Scope saat ini: 1 group = 1 part (Tool #10 Intra-Product Combiner untuk gabung >1 part sudah tersedia sebagai fitur terpisah). Aktor: ProDev/Desainer.

## Field & Kolom
**Spesifikasi Teknis (Master Card)**: `mastercard_code`, `die_code`, `joint_type`, `die_w_cm`/`die_h_cm`, `sheet_raw_w_cm`/`sheet_raw_h_cm`, `printsize_p`/`printsize_l`, `cm_length`/`cm_width` (dimensi badan, dasar Engine), `pack_qty`, `color_set_1`/`color_set_2` (array warna, input CSV dipisah koma).

**Lidah/Flap**: `flap_side` (TOP/BOTTOM/LEFT/RIGHT), `flap_length_mm`, `flap_min_mm`/`flap_max_mm` (fallback ke `sys_constant PRODEV_FLAP_MIN_MM`), `flap_width_mm`/`flap_offset_mm` (khusus Tingkat 2).

**Imposition** (3 kolom layout): Kiri = Nesting Strategy (3 pilihan: Standard Step-and-Repeat, AI Interlocking, Staggered Brick Offset [belum diimplementasi]) + Machine Specifications (gap/gripper/margin dari `sys_constant PRODEV_*`, read-only) + Stock Specifications (`selectedPlanoId` dropdown, sheet raw size, sisa bahan/lembar). Tengah = Viewer tab (2D Sheet / 1-Up Dieline / 3D Mockup [belum ada]) dengan kandidat kartu hasil sweep atau layout aktif. Kanan = Efficiency Metrics (UPS, Sheet Yield %, AI Waste Reduction, meter lari pisau, tombol Confirm/Unlock Layout, link ke Costing).

**Tool #10 Combiner**: `combineWithPartId` (dropdown part lain dalam produk sama).

**Routing**: `newProcessId`, `newMachineId` (opsional), `newProcessNotes`.

**Finishing**: `newFinishingTypeId`, `newFinishingNotes`.

**Attachment**: `newAttachmentFile` (upload, max via `SysConstant PRODEV_UPLOAD_MAX_MB` default 25MB), `newAttachmentType` (JPEG/PDF/AI).

## Aksi / Method
Query/computed utama: `part()`, `activeLayoutGroup()`, `theme()` (redesign ikut `sys_theme` — instance pertama halaman iframe yang ikut tema penuh), `wasteReductionVsStandard()` (baseline ImpositionEngine tanpa shift vs waste_pct aktif — "AI Waste Reduction"), `dielineOneUp()`, `wizardSteps()` (Inquiry/Development/Costing/Master Card/Analytics, step Costing hanya aktif jika ada origin Inquiry), `jobOptions()`, `activeNestingMethod()`, `planoWasteAreaCm2()`, `activeLayoutCells()`, `originInquiryParts()` (reverse-lookup `IqpPart` yang link ke part ini, hanya Inquiry CONFIRMED & bukan DRAFT/CANCELLED/LOST — read-only referensi), `combinableParts()`, `gapMm()`/`gripperMm()`/`marginMm()` (dari `sys_constant`).

Mutasi field:
- `updateField(field, value)` — whitelist field yang boleh diedit inline (mastercard_code, die_*, sheet_raw_*, printsize_*, joint_type, pack_qty, cm_length/width, flap_*).
- `updateColorSet(field, csv)` — parse CSV jadi array untuk `color_set_1`/`color_set_2`.

Engine Imposition:
- `runEngineTingkat1()` — parametric flap sweep pada 1 plano terpilih (`ImpositionEngine::sweepFlap()`), hasil ke `$sweepCandidates` (top 5).
- `runEngineTingkat2()` — sweep interlock dengan `shift_offset_mm` (`ImpositionEngine::sweepInterlock()`).
- `runEngineBestSheet()` (Tool #2) — loop SEMUA plano aktif, ranking lintas-plano by biaya (`ImpositionEngine::bestSheet()`), hasil disimpan ke `$sweepCandidates` yang sama (shape kompatibel + `plano_size_id`).
- `pickCandidate(idx)` — simpan kandidat terpilih ke `ProdLayoutGroup` (create atau update kalau existing belum CONFIRMED), hitung `die_rule_meter` otomatis via Tool #4 (`ImpositionEngine::dieRuleMeterLari()`), set `mastercard_code = "MC-{id}"`, sinkron `flap_length_mm` part.
- `runToolCombine()` (Tool #10) — bandingkan gabung vs pisah 2 part dalam produk sama (`ImpositionEngine::combinePair()`), hasil ke `$combineResult`.
- `confirmCombine()` — terapkan rekomendasi COMBINED: buat `ProdLayoutGroup` BARU (bukan timpa solo existing) menaungi kedua part, method `COMBINED_SPLIT`.
- `confirmLayout()` — set status `CONFIRMED` + `confirmed_by`/`confirmed_at`.
- `unlockLayout()` — kembalikan ke DRAFT, TAPI ada guard: cek `IqpCostingPart::where('prod_layout_group_id', ...)->exists()` (lintas-DB tm2mst→tm2sdi) — kalau layout sudah ditarik Costing, unlock ditolak dengan pesan error (mencegah HPP basi diam-diam).

Routing/Finishing/Attachment CRUD: `addProcess()`/`removeProcess(id)`, `addFinishing()`/`removeFinishing(id)`, `uploadAttachment()`/`deleteAttachment(id)`.

## Tabel & Model yang Disentuh
- `App\Models\Master\ProdPart` → tm2mst (entitas utama, banyak field teknis).
- `App\Models\Master\ProdLayoutGroup` → tm2mst (hasil Engine, PK `id_layout_group`, relasi many-to-many `parts` dengan pivot `qty_up_in_group`).
- `App\Models\Master\ProdPartProcess`, `ProdPartFinishing`, `ProdPartAttachment` → tm2mst (child tables per part).
- `App\Models\Master\MatPlanoSize`, `ProdMachine`, `ProdProcess`, `RefFinishingType` → tm2mst (referensi/dropdown).
- `App\Models\Sdi\IqpPart` → tm2sdi (lintas-DB logis, reverse-lookup origin Inquiry).
- `App\Models\Sdi\IqpCostingPart` → tm2sdi (lintas-DB logis, guard unlock).
- `App\Models\Sys\SysConstant` → tm2sys (parameter fisik mesin: `PRODEV_GAP_MM`, `PRODEV_GRIPPER_MM`, `PRODEV_MARGIN_MM`, `PRODEV_FLAP_MIN_MM`, `PRODEV_INTERLOCK_SAVING_MIN/MAX_PCT`, `PRODEV_UPLOAD_MAX_MB`).
- `App\Services\Imposition\ImpositionEngine` — engine inti (bukan model, service class dengan method `sweepFlap`, `sweepInterlock`, `bestSheet`, `cells`, `combinePair`, `dieRuleMeterLari`, `applyFlap`, `run`).

## Alur / Business Logic Penting
Ini adalah implementasi **ProDev Engine** yang dijelaskan di CLAUDE.md §4 — GOAL: dari geometri 1 part, cari layout produksi fisik paling hemat bahan+plate+tooling. Parameter fisik mesin (gap/gripper/margin) DIPINDAH dari hardcode ke `sys_constant` (migration `2026_09_03_000014`) supaya 1 sumber kebenaran, admin bisa adjust tanpa deploy.

**Tingkat 1 vs Tingkat 2 vs Tool #2 (Best Sheet) vs Tool #10 (Combiner)**:
- Tingkat 1 = grid orthogonal tanpa rotasi/interlock pada 1 plano terkunci.
- Tingkat 2 = perluasan dengan `shift_offset_mm` (interlock 180°) — disclaimer "belum divalidasi dataset riil" ada di docblock `sweepInterlock()`.
- Tool #2 = loop SEMUA plano aktif, ranking lintas-plano.
- Tool #10 = MVP 2-part combiner intra-produk (beda dari gang-run lintas-produk).

**Snapshot & guard integritas**: `originInquiryParts()` murni read-only referensi (prinsip snapshot/beku CLAUDE.md §4) — ProDev tetap isi field teknisnya sendiri secara independen. `unlockLayout()` punya guard nyata mencegah HPP Costing jadi basi tanpa peringatan.

**Wizard-bar lintas modul**: step "Inquiry" SELALU punya url (bahkan Mode #2/manual, fallback ke form baru dengan `from_part`); step "Costing" hanya aktif kalau ada origin Inquiry.

## Catatan
- "Staggered Brick Offset" dan "3D Mockup" tab ditampilkan di UI tapi disabled — belum diimplementasi di `ImpositionEngine`.
- `die_rule_meter` untuk hasil COMBINED_SPLIT (Tool #10) TIDAK dihitung otomatis — formula Tool #4 belum punya varian untuk 2-region split gabungan; officer isi manual di Costing.
- File ini besar (~1579 baris) — komentar kode sangat rinci menjelaskan tiap keputusan desain 2026-09-03/04, baik dibaca langsung untuk detail lebih lanjut.
