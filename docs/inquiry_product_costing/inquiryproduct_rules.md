# INQUIRY PRODUCT — Aturan, UI/UX, Tipe Dokumen (living reference)

> Dokumen ini **melengkapi**, bukan menggantikan, `inquiryproduct_tier1.md` (alur
> bisnis & aturan LOCKED), `inquiryproduct_tier2.md` (logic/state machine),
> `inquiryproduct_delta_revise.md` (spesifikasi scope REVISE/RECALC — R-REV-*/
> R-RC-*), dan `inquiryproduct_flow.md`. Baca file-file itu dulu untuk *kenapa*;
> dokumen ini fokus ke *apa yang benar-benar sudah jalan di kode V2 sekarang*
> (nama tabel/kolom real `tm2sdi`, bukan nama ScriptCase V1 lama), UI/UX
> konkret tiap layar, dan status implementasi per fitur — supaya sesi
> berikutnya tidak perlu re-investigasi dari nol. Update setiap ada perubahan
> nyata di salah satu dari 3 layar Inquiry Product.

---

## 1. TIGA TIPE DOKUMEN — 3 LAYAR TERPISAH (bukan 1 form dgn mode)

**Koreksi penting atas CLAUDE.md §4:** klaim lama "3 mode ini baru konsep
dokumen, belum ada percabangan behavior nyata" **SUDAH TIDAK AKURAT** per
2026-09-04 — REVISI & REKALKULASI adalah **layar Livewire terpisah** dengan
behavior nyata, bukan sekadar flag `iqp_inquiry.inquiry_type` di satu form.

| Tipe | File & route | Dari mana | Yang boleh diedit |
|---|---|---|---|
| **BARU** | `resources/views/pages/inquiry/form.blade.php` — `inquiry.form` | Kosong (`mode=NEW`) | Semua field, full CRUD part/material/tooling/biaya eksternal |
| **REVISI** | `resources/views/pages/inquiry/revise.blade.php` — `inquiry.revise` | `?source_id=<inquiry lama>` atau `?id=<draft REVISE tersimpan>` | **Hanya field dalam scope tercentang** (MATERIAL/DIMENSION/ARTWORK/COLOR/PROCESS) — field di luar scope reuse verbatim, read-only |
| **REKALKULASI** | `resources/views/pages/inquiry/recalc.blade.php` — `inquiry.recalc` | `?source_id=<inquiry lama>` atau `?id=<draft RECALC tersimpan>` | **Hanya Qty Order + Deadline Quotation**. Spec/dimensi/artwork/warna/proses 100% reuse (read-only), cuma harga bahan (`mat_raw.avg_unitprice` vs `last_unitprice`) yang ditampilkan sbg refresh |

Behavior detail (scope matrix, trigger imposisi/proof/costing ulang) ada di
`inquiryproduct_delta_revise.md` §TIER 2 — **jangan diduplikasi di sini**,
itu tetap sumber kebenaran untuk aturan bisnisnya.

**Lineage:** REVISI & RECALC menyimpan `parent_product_id` menunjuk induk;
REVISI juga menaikkan `revision_no` dan menandai versi lama
`stat_confirm=true, stat_cancel=true, sub_state=CANCELLED` (superseded,
snapshot beku — dokumen hilir/CO yang sudah pakai versi lama tidak
terpengaruh, lihat CLAUDE.md §4 prinsip snapshot).

---

## 2. UI/UX PER LAYAR

### 2.1 BARU (`form.blade.php`, ~2900 baris)
- Header: Customer/Prospect toggle, Sumber (EMAIL/WA/PHONE/VISIT/MARKETPLACE),
  PIC Sales, Sumber Bahan (OWN/CUSTOMER), Pajak.
- **Diskon Dokumen & Diskon Item DISEMBUNYIKAN dari UI** (keputusan user
  2026-09-04, "belum perlu discount tahap ini") — properti/kolom DB/logic
  simpan **tetap ada** (LOCKED CLAUDE.md §4, dibawa ke CO), cuma UI-nya
  dilepas. Kalau perlu diaktifkan lagi: cari `discount_doc_type`/
  `discount_item_type` di git history commit 4990a82.
- **Per Part** (bisa >1, tiap part = 1 kartu):
  - Nama Part, Tipe Part (Paper/Paper+Sheet/Paper+SF/Sheet/Kraft), Qty per Set,
    **Artwork/Desain** (lihat §3) — 4 kolom sejajar (`.grid` = grid-cols-4).
  - Tipe Produk (dropdown teks + picker visual popup ala Pacdora, reuse
    `TMDieline.svg()` utk thumbnail — **JANGAN diubah**, verdict eksplisit
    user "sudah bagus", lihat memory `project_inqprod_engine_goal`).
  - **Ukuran Pasaran** (preset dimensi per Tipe Produk) — dropdown, fix bug
    "reset ke placeholder setelah dipilih" via `size_preset_id` + Alpine
    `x-effect` (commit 8f95d55, root cause: Livewire v4 select-morph bug,
    lihat memory `feedback_livewire_select_morph_bug`).
  - Dimensi parametrik (per `prod_type_param`, beda-beda per tipe) → live
    dieline SVG preview kanan (`dieline-part.js` + `tmdieline.js`, murni
    client-side, DSL bukan geometri hardcode — detail teknis Tier 2).
  - Bentangan (Flat) — dipakai Costing: Flat W/H/Area, toggle Auto (dihitung
    dari DSL) / Manual (`flatManual`, field jadi editable).
  - Bahan (Material) — search MatRaw autocomplete, multi-layer, tandai Utama.
  - Cetak/Warna — Notasi Warna (4/4 dst) + turunan front/back/total/side
    otomatis.
  - **TIDAK ADA UI Proses/Finishing** di layar ini (gap nyata, dicatat bukan
    diasumsikan — `iqp_part_process` cuma keisi via REVISI/RECALC clone atau
    seed dummy, lihat `readonly-summary.blade.php` komentar baris ~29-34).
- Qty Order Berjenjang (tiers): qty + Utama (1 wajib). Diskon-per-tier
  disembunyikan (lihat atas).
- Tooling, Biaya Eksternal/Pihak Ketiga — section terpisah, lihat tier1 §5.4/§5.7.
- Tombol per part **"🏭 Kirim ke ProDev"** — muncul kalau part sudah
  tersimpan (`id`) DAN inquiry sudah `stat_confirm` (Confirm = spec FIX).
  Membuat `prod_part` (tm2mst) kalau belum ada + auto-bawa artwork (§4).

### 2.2 REVISI (`revise.blade.php`, ~830 baris)
- Card 1: Produk Asal & Jejak Relasi (read-only trace inquiry lama → versi baru).
- Card 2: **Scope Revisi** — 5 checkbox card (Material/Dimensi/Artwork/Warna/
  Proses), wajib pilih ≥1 + `revise_reason` wajib diisi teks bebas.
- Card 3: Konsekuensi — tabel otomatis dari scope (Imposisi ulang/Proof
  ulang/Costing ulang/Due-date reset), murni tampilan turunan `triggers()`.
- Blok edit **kondisional per scope tercentang** (`@if(in_array('X',$scope))`):
  ARTWORK (No. Design/Proof + upload, §3) · COLOR (notasi warna+ink per
  part) · DIMENSION (parameter dimensi per Tipe Produk) · MATERIAL (search
  substitusi bahan per layer) · PROCESS (baris teks proses/finishing bebas,
  bukan tabel routing branch/flow — replace-all tiap save).
- Field di luar scope yang dicentang **tidak muncul UI-nya sama sekali** —
  otomatis reuse dari source (`fillPartsFrom($this->source)` lalu id
  di-null-kan supaya `persistParts()` bikin baris baru, bukan update baris
  lama).

### 2.3 REKALKULASI (`recalc.blade.php`, ~380 baris)
- Card 1: Produk Asal & Jejak Relasi + **Qty Order** (satu-satunya field
  numerik yg boleh diubah) + Deadline Quotation.
- Card 2: **Spec & Layout (reuse dari baku) — read-only**, per part:
  - Panel visual Artwork + Diecut (§3.3, baru 2026-09-04).
  - Grid ringkasan: Dimensi, Dieline/Tipe, Warna, Qty/SKU — semua badge
    "✓ reuse".
  - Baris Material (nama bahan, koma-separated) & Proses (nama proses,
    panah-separated) — teks polos, bukan tabel detail (cukup utk cek cepat).
- Card 3: Refresh Harga Bahan — tabel `avg_unitprice` (baku) vs
  `last_unitprice` (kini) + delta %. **Belum ada snapshot harga per-inquiry
  riil** (costing versioned belum digarap penuh, lihat CLAUDE.md §5) — ini
  approksimasi pakai 2 kolom `mat_raw` yang memang ada.

---

## 3. ARTWORK ENGINE (dibangun 2026-09-04, sesi ini — fitur baru, belum ada di tier1/tier2)

Field upload gambar/artwork per **Part** (`iqp_part.artwork_path` +
`artwork_original_filename`, migration `2026_09_04_000003`), plus engine
saran otomatis. Detail penuh & riwayat keputusan: memory
`project_inquiry_artwork_suggestion_engine.md`. Ringkasan:

### 3.1 Upload (semua 3 layar, beda titik masuk)
- **BARU** — field "Artwork/Desain" di grid Part, ikon jepitan kertas (📎)
  trigger file dialog (bukan `<input type=file>` polos). **Bisa dipakai
  SEBELUM part/inquiry pernah disimpan** — pakai temp upload Livewire murni
  (`updatedArtworkUploads()` hook, preview via `temporaryUrl()` bawaan),
  baru dipindah ke storage permanen saat tombol Simpan ditekan
  (`persistPendingArtwork()` dari `persistParts()`).
- **REVISI** — HANYA muncul saat scope **ARTWORK** dicentang (representasi
  "gambar berubah"). Side-by-side: **Artwork Lama** (read-only dari inquiry
  sumber) vs **Artwork Baru** (slot upload). Kalau scope Artwork TIDAK
  dicentang atau tidak upload apa pun, artwork lama **disalin fisik** ke
  path baru milik part revisi (bukan reuse path yang sama) — supaya versi
  lama & baru independen, hapus/ganti di 1 versi tidak pernah merusak versi
  lain.
- **REKALKULASI** — TIDAK ADA upload (spec 100% reuse/terkunci, sesuai
  R-RC-1). Cuma preview read-only (§3.3).

### 3.2 Engine saran (BARU & REVISI, saat upload)
"Engine suggests, officer confirms" — pola sama scheduling/imposisi
(CLAUDE.md §4), semua via tombol "Pakai", tidak pernah auto-terapkan:
- **Notasi warna/tinta** — heuristik analisis warna file (PHP GD, downsample
  40×40px, kuantisasi channel, deteksi grayscale) → saran kode `1/0`…`4/4`.
  KASAR, selalu berlabel "cek ulang".
- **Jenis kertas (bahan)** — histori Inquiry lain dgn `dieline_spec->type_code`
  sama, bahan (`iqp_part_material.rawmat_id`) paling sering dipakai + %.
- **Finishing** — histori sama via `iqp_part_process`, tapi **info-only**
  (tidak ada UI proses di Inquiry — lihat §2.1).
- **Ukuran (Flat W/H)** — HANYA kalau file punya metadata **DPI asli**
  (JFIF APP0 utk JPEG / chunk `pHYs` utk PNG, parser manual — `exif_read_data()`
  PHP tidak baca ini). Kalau tidak ada metadata DPI, saran **tidak muncul
  sama sekali** (keputusan eksplisit: drpd nebak dari resolusi pixel mentah
  yg bisa meleset >4x). ⚠️ DPI 72/96 = default layar/software, bukan skala
  cetak asli — UI kasih warning eksplisit.

### 3.3 Preview (RECALC, read-only)
Panel visual per part: thumbnail artwork (klik = zoom overlay) + render
diecut asli via `window.TMDieline.svg()` (dieline_spec + dims yang
dibekukan di part) — supaya officer bisa cek visual cepat sebelum submit
RECALC, bukan cuma baca teks "Shallow Box" yang gampang salah pilih kalau
ada banyak tipe mirip.

### 3.4 Alir ke ProDev & ProCost
- **ProDev** — `sendToProDev()` (BARU) auto-create `ProdPartAttachment`
  (tm2mst, file_type='JPEG') menunjuk ke **file yang sama** (disk 'public',
  tidak disalin). Panel "📎 Referensi" yang sudah ada di
  `production/prodev.blade.php` otomatis menampilkannya — **tidak ada
  perubahan UI ProDev** untuk ini.
- **ProCost** — panel baru di `inquiry/costing.blade.php`, per part costing,
  baca `IqpCostingPart::inquiryPart()->artwork_path` (relasi `part_id`,
  file sama persis). Null aman kalau part costing dibuat manual (bukan via
  `addPartFromInquiry()`).

---

## 4. KEPUTUSAN: ProCost = KONSUMEN parameter dari Inquiry + ProDev (dicatat 2026-09-04, permintaan user)

**Keputusan arsitektur (bukan cuma UI):** Layar ProCost harus bisa melihat
view yang **sudah tertata** dari data yang didapat dari Inquiry Product DAN
dari ProDev. Keputusan seperti **bahan baku**, **urutan proses**, **jumlah
UP**, dan sejenisnya menjadi **masukan parameter** bagi ProCost — bukan
angka yang diketik ulang manual dari nol oleh Costing.

**Status per parameter (per 2026-09-04 — jangan asumsikan semua sudah
otomatis, cek ulang kode kalau ragu):**

| Parameter | Sumber | Status |
|---|---|---|
| Geometri part (P×L×T, flat W/H) | Inquiry → snapshot `iqp_part` | ✅ LIVE — dasar Mode #1 & #2 ProCost sama-sama |
| **Bahan baku** | Inquiry (`iqp_part_material`) via `addPartFromInquiry()`, atau ProDev (Master Card/`prod_layout_group`) via toggle "Ambil Data" | ✅ LIVE — Mode #1 tarik `grammage_id`/plano dari ProDev; Mode #2 dari Inquiry, officer isi manual |
| **Jumlah UP** | ProDev (`prod_layout_group.up_count`, hasil ImpositionEngine) | ✅ LIVE — cuma via Mode #1 "🔗 Ambil Data (ProDev)", terisi otomatis begitu `pickLayoutGroup()` dipanggil |
| **Urutan proses / routing** (`iqp_part_process`, atau routing ProDev) | — | ❌ **BELUM** — `CostingEngine.php` hitung `c_process_cost_pc` dari field RATE manual per part (`i_ongkos_cetak_rim`, `i_ongkos_plong_lb`, dst, diisi/di-override officer Costing), **TIDAK membaca** urutan/jumlah step dari `iqp_part_process` maupun routing ProDev sama sekali. Ini **gap nyata**, bukan sudah-jalan-tapi-belum-terlihat — perlu keputusan desain terpisah kalau mau dibangun (mis. tiap step routing → 1 baris rate otomatis) sebelum mulai kerja. |
| Artwork (visual referensi) | Inquiry → ProDev/ProCost | ✅ LIVE (§3.4) |

**Untuk sesi berikutnya:** kalau user minta "urutan proses jadi ikut
ngitung HPP otomatis", itu FITUR BARU (bukan bug fix) — perlu desain dulu:
apakah tiap `process_name` di `iqp_part_process`/rute ProDev punya rate
default (dari master proses `prod_process`?), bagaimana konflik dgn field
rate manual yang sudah ada di `iqp_costing_part` (override vs auto-fill),
dan apakah berlaku Mode #1 saja atau juga Mode #2. Jangan mulai coding
sebelum ini diputuskan (CLAUDE.md §7.1 — arsitektural, tanya dulu).

---

## 5. REFERENSI LANJUTAN
- `inquiryproduct_tier1.md` — alur bisnis, aturan LOCKED, role matrix.
- `inquiryproduct_tier2.md` — logic detail, state machine, pseudocode HPP.
- `inquiryproduct_delta_revise.md` — scope REVISE (R-REV-*) & reuse RECALC (R-RC-*), matriks pemicu.
- `inquiryproduct_flow.md` — alur proses tambahan.
- `griduistandard.md` / `uistandardindex.md` — standar UI grid & peta semua standar tampilan lain.
- Memory: `project_inquiry_artwork_suggestion_engine.md`,
  `project_inqprod_engine_goal.md`, `project_prodev_procost_2mode_architecture.md`,
  `project_quotation_co_flow.md`.
