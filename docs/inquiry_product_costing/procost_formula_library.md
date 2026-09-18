---
name: procost_formula_library
description: Pustaka rumus lengkap CostingEngine (semua part_type) + 2 varian referensi tampilan ProCost (Mode Manual vs Mode Integrasi) dari 3 sumber mockup/audit legacy.
---

# ProCost — Pustaka Formula & Referensi Tampilan

**Sumber (dipelajari 2026-09-18):**
1. `docs/mockups/Kalkulasi_HPP_UAT.html` — mockup UAT tampilan **Mode Manual/Instant** (officer isi semua parameter sendiri, tanpa ProDev).
2. `docs/inquiry_product_costing/gemini_spark_productcosting.md` — hasil audit rumus legacy ScriptCase `frm_hpp_parts::calc_cogs()` (**sumber kebenaran matematis**, dipakai `CostingEngine.php`).
3. `docs/mockups/ERP_Product_Costing_Mockup.html` — mockup AI (Gemini) tampilan **Mode Integrasi** (data ditarik dari Inquiry Product + ProDev, officer cuma isi parameter dinamis sisa).

**Ini BUKAN desain arsitektur baru.** Arsitektur & DDL ProCost sudah *locked* di
`productcosting_tier1-4.md`, dan sudah **diimplementasikan live** — layar
`docs/screens/screen_procost_form.md` (`inquiry/costing.blade.php`) + engine
`App\Services\Costing\CostingEngine.php` — dengan 2 mode `data_source` per part
(`MANUAL` vs `PRODEV`, CLAUDE.md §4 ProCost 2-mode). Dokumen ini adalah:
- **(I)** referensi visual 2 mode tampilan dari mockup, untuk polish UI ke depan;
- **(II)** pustaka rumus super-detail per part_type (semua konstanta & guard) —
  "kamus" yang dirujuk balik oleh docblock `CostingEngine.php`;
- **(III)** crosswalk nama field ScriptCase lama ↔ kolom `iqp_costing_part` sekarang;
- **(IV)** hasil cross-check rumus dokumen vs kode live — termasuk **1 gap baru**
  yang ditemukan saat penyusunan dokumen ini (§V.3).

---

## I. Dua Mode Tampilan (dari mockup)

### I.1 Mode Manual/Instant — acuan untuk ProCost Mode #2 (`data_source=MANUAL`)

Sumber: `Kalkulasi_HPP_UAT.html`. Semua parameter diinput officer sendiri, tanpa
link ke ProDev. Layout (referensi polish UI, field-level sudah ada semua di
`iqp_costing_part` menurut `screen_procost_form.md`):

**Section 1 — Global (atas layar):**
- Panel "Inquiry & Product Info" (read-only): Doc No, Doc Date, Status, No Rev,
  Customer, Contact/Lead, Product Ref, Product Name, Dim P/L/T (mm), Lidah Lem.
- Panel "Global Parameters" (editable): Qty Order Pcs, Pilih PPn
  (INCLUDE/EXCLUDE PPN), Ppn %, Margin %, Tolerance %, Rounding Mode
  (NEAREST/CEILING/FLOOR) — persis parameter `iqp_costing_price_tier` +
  `ppn_mode`/`ppn_pct` di header.
- Panel "Notes & Config": Currency (IDR/USD/EUR), Notes bebas.
- Bar ringkasan hasil (hijau, selalu terlihat): Price Per Pcs, Total HPP, Total
  Material, Total Process, Total Tooling | **Total After Margin**, **Total
  After PPn** — bar sejenis ini berguna ditambahkan di atas
  `PriceTierPanel` layar live sebagai ringkasan cepat sebelum scroll ke ladder detail.

**Section 2 — Part Header (baris ringkas per part):** No Urut, Nama Part, Qty
Part, Tipe Part (disabled — hasil dari `part_type_code`), Jumlah Warna
(readonly), Panjang/Lebar Plano (readonly), Gramatur/Bahan (disabled, tampilkan
harga di label — mis. "DUPLEX 250 gsm — Rp 255.000/rim").

**Section 3 — Parameter Kalkulasi (grid 4 kolom, padat):** Ini adalah full
field-list rumus DC/DC+SF/DC+SHEET dalam 1 layar — cocok jadi acuan tata-letak
kalau layar live perlu mode "advanced/dense" untuk officer berpengalaman
(lihat §III untuk mapping formula tiap field):

| Kolom 1 | Kolom 2 | Kolom 3 | Kolom 4 |
|---|---|---|---|
| Dim Panjang (mm) | Dimensi Lebar (mm) | Dim Tinggi (mm) | Lidah Lem (mm) |
| % DC (override) | 1 Plano dipotong jadi *(readonly)* | Isi dalam 1 LB Cetak *(readonly)* | Ukuran Cetak P (cm) |
| Ukuran Cetak L (cm) | Substansi/Tipe SF *(disabled)* | Supplier Sheet *(disabled)* | Panjang Piece SF/SH (cm) |
| Lebar Piece SF (cm) | Lebar Trim (cm) | Isi/Trim | Discount/Surcharge |
| Ongkos Cetak/RIM | Ongkos Plong/LB | Ongkos LEM/PC | Ongkos Hot Print/PC |
| Ongkos Kupas/PC | Ongkos QC/PC | Ongkos Kirim/PC | Jenis Packing *(disabled)* |
| Ongkos Packing/PC | Pakai Laminasi (Ya/Tidak) | Ongkos Laminasi Manual | Ongkos Lam SH (Rp/cm2) |
| Ongkos Lam SF (Rp/cm2) | Lam Sheet Flat (Rp/PC) | Rate SF EF (E/F) *(disabled)* | Harga SF EF (override) |
| Pakai Ukuran SF? (1/0) | Film Rate *(disabled)* | Film Faktor | Plate (Rp/warna) |
| Pisau Faktor | Pisau Rate (Rp/order) | — | — |

**Section 4 — Output HPP (panel kanan):** kartu besar "Harga Pokok (HPP)/PC"
(hijau) + "Jumlah Cetak (LC)", textarea **Description Calc** (log mesin,
format `v{n} {tanggal} {jam}` + breakdown singkat + `flag=...` — persis
`description_calc` di `iqp_costing_part`, lihat contoh format di §II.7),
textarea **Catatan Kalkulasi** (bebas user = `calc_notes`, jangan di-overwrite
engine — sudah locked di `productcosting_tier1-4.md` §Part C).

**Section 5 — Detailed Breakdown (grid kecil, 2 sub-grup):**
- **Per PC** (13 sel): Luas Cetak cm², Jumlah Bahan (LB), Harga Kertas/PC,
  Cetak/PC, Plong/PC, Lem/PC, Coating/PC, SF EF/PC, Ongkos Laminasi/PC, Harga
  Sheet/PC, Film/PC, **Info Harga Supplier** (blok teks multi-baris hasil
  `compare_sheet_suppliers()` — lihat §II.8), Ongkos Proses/PC, Plate/PC,
  Pisau/PC, Tooling/PC.
- **Per Order** (23 sel): versi `×Qty` dari semua sel Per PC, plus breakdown
  proses SHEET (Die Cut/Slitter/Slotter/Join/Partisi/Layer/Pisau Diecut) yang
  di mode DC tersembunyi/0.
- **Coating** (tabel child, kolom: Act, Coating Type, Index Coating, Cost Per
  Lembar Cetak, Cost Per Pcs, Notes Coating, Isi per Lembar, Area Cetak) —
  persis struktur `iqp_costing_part_coating` di layar live.

**Rekomendasi pemakaian:** field-list ini **sudah 100% tercakup** oleh kolom
`iqp_costing_part` yang ada (`screen_procost_form.md`). Nilai tambah mockup ini
untuk UI Mode Manual layar live adalah (a) grid Detailed Breakdown Per
PC/Per Order sebagai panel "lihat semua angka sekaligus" (`canSeeFormula`-gated,
Sisi A saja), (b) format `description_calc` yang lebih informatif (breakdown
ringkas + flag, bukan cuma "CostingEngine v1 (DC) · timestamp" seperti
sekarang — lihat §V.4).

### I.2 Mode Integrasi — acuan untuk ProCost Mode #1 (`data_source=PRODEV`)

Sumber: `ERP_Product_Costing_Mockup.html` (mockup AI/Gemini). Filosofi:
begitu Inquiry + ProDev sudah menghasilkan spec & layout CONFIRMED, officer
Costing **hampir tidak perlu mengetik apa-apa** — hanya parameter yang
memang berubah-ubah per keputusan bisnis (qty, margin, index harga, pajak).

**Top bar:** breadcrumb (ERP System / Product Costing / doc_no), judul +
badge status (DRAFT/dst).

**3 panel info atas (grid 5-4-3 kolom):**
1. **Doc & Customer Info** (read-only): Doc No, Doc Date, Customer, **Type**
   (mis. "Hitung Ulang (Rev 0)" — istilah UI untuk `calc_type=RECALC`, selaras
   3 doc-type CLAUDE.md §4).
2. **Product Specification** (read-only, dari Inquiry+Master Product): kode+nama
   produk, Nama Part, Dim P/L/T (mm).
3. **Dynamic Parameters** (kotak biru — SATU-SATUNYA yang bisa diketik officer):
   Qty Order (Pcs), Margin (%), **Paper Index (Rp/Rim)**, PPN (%). Catatan:
   "Paper Index" di sini menggantikan seluruh blok gramatur/DC%/dst — asumsi
   mockup adalah 1 angka index sudah cukup karena grammage & plano sudah
   tetap dari ProDev; di implementasi nyata field ini idealnya tetap mapped ke
   `grammage_id` (lookup), bukan angka bebas, supaya snapshot rate tetap
   tertelusuri (prinsip snapshot CLAUDE.md §4).

**2 panel bawah (grid 2 kolom):**
- **Visual Imposition Layout (Prodev)** — read-only: Plano Size, Material,
  Print Area, Imposition ("4 Sheet/Plano, 2 Up/Sheet"), + **render SVG dieline
  plano** (grid 2×2 kuadran, color-bar kalibrasi, crop-mark, 8× symbol
  box-net/dieline mengikuti jumlah UP) — ini adalah **preview visual hasil
  ProDev** (`prod_layout_group`) ditarik ke layar Costing untuk konteks
  officer, murni display (bukan input). **Belum ada** di layar live sekarang
  (`screen_procost_form.md` hanya tampilkan field angka plano/UP, tidak ada
  render dieline) — kandidat improvement UX kalau dianggap perlu (perlu
  konfirmasi user sebelum dikerjakan, lihat §VI).
- **Granular Cost Breakdown** — read-only kecuali 1 override index inline:
  - Grup **Materials**: Plano Sheets Needed, Cost per Plano, **Total Material
    Cost** (bold).
  - Grup **Process & Finishing**: tabel Process | Rate | Basis | Subtotal
    (Offset Printing, UV Coating — dengan input kecil inline utk override
    index coating, Plong/Die Cut, Lem/Gluing), footer **Total Process**.

**Banner bawah (dark, full-width):** Total COGS(HPP) + Rp/pcs, Margin(+X%)
amount, PPN(+X%) amount, dan kartu highlight biru **Final Selling Price**
(Rp/pcs besar + Grand Total order).

**Rekomendasi pemakaian:** ini referensi UX yang bagus untuk mempercantik
tampilan **read-only** Mode PRODEV di layar live — sekarang field ProDev
ditampilkan sebagai input `disabled` biasa (fungsional tapi datar); mockup ini
menunjukkan pola "dashboard ringkasan + breakdown tabel + banner harga akhir"
yang lebih mudah dibaca officer. **Tidak mengubah logika/rumus apa pun** —
murni referensi presentasi.

---

## II. Pustaka Formula Lengkap (per Sesi, sumber `gemini_spark_productcosting.md` §IV)

> Notasi: `{field}` = field ScriptCase lama, dipetakan ke kolom `iqp_costing_part`
> di §IV. Semua rumus di bawah **sudah diverifikasi ada di `CostingEngine.php`**
> kecuali ditandai ⚠ di §V.

### II.1 Guard pembagi & area cetak (semua part_type)
```
Q_part  = max(1, Q_part)        (qty per bagian — guard, di kode: max(cut, 0.0001) / max(isi, 0.0001))
C_plano = max(1, C_plano)       → i_cut_per_plano
I_lb    = max(1, I_lb)          → i_isi_per_lb
A_cetak = P_cetak × L_cetak (cm²)  → i_panjang_plano_cm × i_lebar_plano_cm
is_sheet = 1 jika part_type ∈ {SHEET, SHEET_DIGITAL}, else 0
```

### II.2 Material — Kertas (SESI 3)

**A. Duplex** (`duplex_price_list > 0`):
```
F_dc = 1 + override            , jika override > 0      (override 5.30 → F_dc = 6.30)
     = 1 + (dc_x100 / 100)     , selainnya              (DB legacy simpan 530 → 5.30 → F_dc = 6.30)

Cost_paper/pc = (Panjang_plano × Lebar_plano / 8611) × Harga_duplex × F_dc / 500 / C_plano / I_lb
```
⚠ **KOREKSI 2026-09-18 (diverifikasi ke dump `docs/ddl/tmhpp.sql`)**: "5,30" BUKAN persen —
F_dc = **6,30**, bukan 1,053. Bukti: komentar kolom legacy `hpp_parts.i_dc_persen_override`
("5.30 → dc_factor = 1+5.30 = 6.30"), NS40 duplex 350 (314.000/rim, plano 70×100, cut 2) =
**1.608,11** hanya cocok dgn 6,30; RAJA BAKERY (255.000, 120×104, cut 4, isi 2) = **582,08**;
DUS (314.000, override 5,60 → 6,60, isi 3) = **1.123,12**. Lihat §VII.
`8611` = `PLANO_REF_AREA` (luas plano acuan 79×109cm), `500` = `RIM_SHEETS`.

**B. Ivory/AP** (per Kg):
```
Cost_paper/pc = (A_cetak / 10.000) × Harga_kg × (Gramatur/2) / 500 / C_plano / I_lb
```
⚠ **Koreksi 2026-09-02** (dicatat di docblock `CostingEngine.php`): versi kode
sebelumnya salah pakai `/8611` di jalur ini (harusnya `/10000`, konversi cm²→m²,
bukan referensi ukuran plano). Sudah diperbaiki di kode live.

**C. Kraft** (per Kg, **tanpa** `/RIM`):
```
Cost_paper/pc = (A_cetak / 10.000) × Harga_kg × (Gramatur/2) / C_plano / I_lb
```

**D. Sheet (corrugated, SHEET/SHEET_DIGITAL):**
```
Cost_sheet/pc = (Lebar_trim × Panjang_sheet / 10.000) × Index_substansi / Isi_trim × (1 − discount_rm)
```
`discount_rm` **negatif = surcharge** (mis. COCO −0.05 = markup +5%) — rumus
tetap `(1 − discount_rm)`, disengaja, jangan "diperbaiki" jadi `(1+x)`.

### II.3 Derivasi Geometri SHEET_DIGITAL (SESI 3b — juga dipakai SHEET & J36 DC+SHEET/DC+SF)
```
Panjang_sheet_cm = (2×(P_box+L_box) + total_p_mm + Lidah_lem) / 10
Lebar_sheet_cm   = ((L_box+T_box) + total_l_mm) / 10

Isi_trim    = max(1, floor((180 − 2) / Lebar_sheet_cm))      (180=lebar mesin, 2=gap, dari SysConstant)
Lebar_trim  = ceil((Lebar_sheet_cm × Isi_trim + 2) / 5) × 5

Running_Meter (RM) = (Qty / Isi_per_lb_kerja) / Isi_trim × (Panjang_sheet_cm / 100)   ← rumus SUDAH DIPILIH/benar (lihat §V.1)
```
`total_p_mm = (leb_panjang+leb_lebar)×2`, `total_l_mm = (leb_lebar+leb_tinggi)+3`
— dari `mat_flute_allowance`. Diskon dilookup dari tier RM
(`mat_sheet_discount_tier`, `rm BETWEEN rm_from AND rm_to` per `supplier_code`).

### II.4 Proses & Finishing dasar (non-sheet, DC family)
```
Cetak/pc = Ongkos_cetak_per_RIM / C_plano / I_lb
Plong/pc = Ongkos_plong_per_LB / I_lb
Lem/pc   = Ongkos_lem_per_pc                    (langsung, tanpa pembagi)
```

### II.5 Coating multi-layer (SESI 4/5, `is_sheet=0`)
```
Cost_per_LC (per lembar cetak) = A_cetak × Index_coating
Cost_per_PC                    = Cost_per_LC / I_lb        (TIDAK dibagi C_plano!)
Total_coating/pc = Σ Cost_per_PC (semua layer, urut: COATING dulu → baru LAMINASI)
```

### II.6 Laminasi & SF EF (SESI 6 — khusus DC+SF, DC+SHEET)
```
J36 (Laminasi Sheet Flat) = F_sheet × Ongkos_lam_sheet_flat_per_pc
J37 (SF EF, AUTO utk DC+SF & DC+SHEET) = (A_cetak / 10.000) × Rate_SF / I_lb
    Rate_SF: prioritas override>0 → lookup v1_sf_ef_rate → 0 (aman, tak ada kontribusi)
J38 (ongkos laminasi per-cm²) = Panjang×Lebar × (F_sheet×Rate_lam_sh + F_sf×Rate_lam_sf) / I_lb
    (fallback: pakai ongkos laminasi manual flat kalau rate per-cm² tak diisi)
```
**Anti double-count**: J36/J37/J38 sudah masuk `Cost_process/pc` total — jangan
ditambahkan lagi terpisah ke grand total.

### II.7 Tooling (SESI 7, `is_sheet=0` — SHEET/SHEET_DIGITAL/KRAFT = TOOL_SKIP, tooling = 0)
```
Film/order  = (Film_faktor²) × A_cetak × Film_rate     ← faktor DIKUADRATKAN
Plate/order = Plate_rate_per_warna × Jumlah_warna
Pisau/order = Pisau_faktor × Pisau_rate_per_order

Tooling_order = Film + Plate + Pisau     (disimpan ORDER-level di V2, ÷Qty terjadi di tier — bukan di part)
```
Konstanta default: `FILM_RATE_DEFAULT=55`, `FILM_FACTOR_DEFAULT=0.25`,
`PLATE_PER_COLOR=90000` (semua di `sys_constant`, override per part kalau diisi).

**Contoh format `description_calc`** (dari mockup, pola log v14 legacy — acuan
kalau engine live mau dibuat lebih informatif, lihat §V.4):
```
v14 07/07/2026 08:15:19
DC+SF paper=582.08
proc=827.29 coat=101.4
lam=97.79 sfef=405.6
tool=78.09 sheet=0
TOTAL=1487.46
flag=PAPER_OK COAT_OK SFEF_OVR LAM_OK TOOL_OK
```

### II.8 Perbandingan Supplier Sheet (`compare_sheet_suppliers()`)
Bandingkan harga semua supplier sheet aktif pada RM order yang sama, tampil
sebagai info (bukan dipakai kalkulasi utama kecuali dipilih):
```
Contoh output: "UJK: Rp 1.075,72 (surcharge 10.0%) RM=1.880,6"
                "RCA: Rp 723,66 (diskon 26.0%) RM=1.880,6"
```
Berguna untuk officer membandingkan sebelum memilih `supplier_sheet_code`.

### II.9 Akumulasi Total per Part

| part_type | HPP/pc |
|---|---|
| **DC** | `Cost_paper/pc + Cetak + Plong + Lem + Total_coating + Cost_tooling*` |
| **DC+SF / DC+SHEET** | `Cost_paper/pc + Cetak + J36(Lam) + J37(SFEF) + Plong + Lem + Hotprint + Kupas + QC + Kirim + Packing + Coating + Cost_tooling*` |
| **KRAFT** | `Cost_paper/pc + (Diecut+Slitter+Slotter+Join+Partisi+Layer+Kupas+QC+Kirim+Packing)` |
| **SHEET / SHEET_DIGITAL** | `Cost_sheet/pc + (2×Print + Diecut+Slitter+Slotter+Join+Partisi+Layer+Kupas+QC+Kirim+Packing)` |

`*` = di V2, tooling **tidak** masuk `c_total_part_cost_pc` (dipisah ke
order-level, diamortisasi di `costing_price_tier`) — lihat `productcosting_tier1-4.md` §1.2 catatan desain.

### II.10 Ladder Harga Jual (header/tier, sudah live di `calculateTier()`)
```
HPP/pc  = Σ(material+proses+finishing per part) + (Tooling_order / Qty_tier)
÷ (1 − Toleransi%/100)  → after_tolerance      (mark-up ON PRICE, bukan additive!)
round(mode, step)       → after_round
÷ (1 − Margin%/100)     → after_margin = price_per_pcs (acuan quotation, excl PPN)
× (1 + PPN%/100)        → after_ppn (incl, kalau ppn_mode ≠ NON_PPN)
```
Verifikasi byte-presisi ke Excel asli "OB COCO TOKYO..." (`C31=C30/(1-B31)`) —
Toleransi DAN Margin sama-sama mark-up-on-price, **bukan** `×(1+x)`.

---

## III. Matriks 6 Part Type — Komponen Biaya

| Komponen | DC | DC+SHEET | DC+SF | KRAFT | SHEET/DIGITAL |
|---|:---:|:---:|:---:|:---:|:---:|
| Material Paper (duplex/ivory) | Ya | Ya | Ya | Ya | Tidak |
| Material Sheet (corrugated) | Tidak | Ya (via Laminasi) | Ya (via Laminasi) | Tidak | Ya (utama) |
| Proses Offset (cetak/plong/lem) | Ya | Ya | Ya | Tidak | Tidak |
| Proses Corrugated (diecut/slitter/dst) | Tidak | Ya | Ya | Ya | Ya |
| Coating & Tooling | Ya | Ya | Ya | Tidak (TOOL_SKIP) | Tidak (TOOL_SKIP) |

### III.1 Studi Kasus Audit — DC+SHEET (dari legacy, angka riil)
Nilai referensi dari audit sistem lama (dipakai sebagai *acuan regresi* — lihat
rekomendasi §VI):

| Komponen | Nilai (Rp/pc) |
|---|---:|
| Kertas (`c_paper_cost_pc`) | 1.608,11 |
| Laminasi Sheet (J36) | 1.790,50 |
| **Coating** (layer asli, TERPISAH dari Laminasi) | 473,55 |
| Proses (cetak+plong+lem+hotprint+kupas+qc+kirim+packing) | 3.645,50 |
| Tooling (film+plate+pisau) | 595,76 |
| **Total HPP** | **6.322,92** |

Catatan penting dari audit: pada DC+SHEET, `c_sheet_cost_pc` **selalu 0**
secara arsitektur — harga sheet corrugated yang ditempel ke duplex dihitung
sebagai **Laminasi Sheet Flat**, bukan material utama (beda dari part_type
`SHEET` murni di mana sheet = material utama). Kasus ini jadi bukti nyata
bahwa DC+SHEET tetap punya **komponen Coating asli** (bukan cuma Laminasi) —
lihat gap di §V.3.

---

## IV. Crosswalk Field: ScriptCase Legacy → `iqp_costing_part` (V2)

| Field legacy (`p_*`/`h_*`) | Arti | Kolom `iqp_costing_part` (V2) |
|---|---|---|
| `p_dimp`/`p_diml`/`p_dimt` | Dimensi part (mm) | `i_dim_panjang_mm`/`i_dim_lebar_mm`/`i_dim_tinggi_mm` |
| `p_dc` | % DC override | `i_dc_persen_override` |
| `p_ukcp`/`p_ukcl` | Ukuran cetak P/L (cm) | *turunan* `c_uk_cetak_panjang_cm`/`lebar_cm` → `c_area_cetak_cm2` |
| `p_ocetakrim` | Ongkos cetak/RIM | `i_ongkos_cetak_rim` |
| `p_oplong` | Ongkos plong/LB | `i_ongkos_plong_lb` |
| `p_olem` | Ongkos lem/pc | `i_ongkos_lem_pc` |
| `p_okupas` | Ongkos kupas/pc | `i_ongkos_kupas_pc` |
| `p_opack` | Ongkos packing/pc | `i_ongkos_packing_pc` |
| `p_oqc` | Ongkos QC/pc | `i_ongkos_qc_pack_pc` |
| `p_okirim` | Ongkos kirim/pc | `i_ongkos_kirim_pc` |
| `p_ohotprint` | Ongkos hot print/pc | *(belum ada kolom — lihat §V.3)* |
| `p_pakailam`, `p_lamsheetf` | Pakai laminasi, Lam sheet flat/pc | `pakai_laminasi` (header/part), *(J36 belum ada kolom `i_lam_sheet_flat_pc` eksplisit — sekarang direkonstruksi via `computeSheetGeometry()`)* |
| `p_olamsf`/`p_olamsh` | Ongkos lam per cm² SF/SH | `i_ongkos_lam_rp_cm2_sf`/`sh` *(nama beda tipis, cek migrasi)* |
| `p_ratesfef`/`p_hsfef` | Rate/harga SF EF | `sf_ef_rate_id` / `i_sf_ef_rate_override` |
| `p_filmfaktor`/`p_filmrate` | Film faktor/rate | `i_film_faktor` / `i_film_rate` |
| `p_pfaktor`/`p_pisaurate` | Pisau faktor/rate | `i_pisau_faktor` / `i_pisau_rate_rp` |
| `p_plate` | Plate Rp/warna | `i_ongkos_plate_warna` |
| `p_ltrim`/`p_isitrim` | Lebar trim/Isi per trim | `c_lebar_trim_cm` (computed) / `i_isi_per_lb_kerja` |
| `p_suppsheet`/`p_disc` | Supplier sheet / diskon-surcharge | `supplier_sheet_code` / *(diskon dilookup otomatis dari tier, bukan input manual di V2 — `MatSheetDiscountTier::lookup()`)* |
| `p_jpack` | Jenis packing | `jenis_packing` (masih free-text, gap dicatat di `screen_procost_form.md`) |
| `h_tipe` | Tipe Part | `part_type_code` |
| `h_bahan` | Gramatur/Bahan | `grammage_id` |

> Field yang di legacy manual (`p_ltrim`, `p_isitrim`, `p_disc`) sekarang
> **dihitung otomatis** oleh `computeSheetGeometry()` di V2 (bukan input
> officer lagi) — penyederhanaan yang disengaja, bukan gap.

---

## V. Cross-Check Rumus Dokumen vs Kode Live (`CostingEngine.php`)

### V.1 Inkonsistensi Running Meter (RM) — **SUDAH RESOLVED**
`gemini_spark_productcosting.md` §V mencatat legacy punya **3 rumus RM
berbeda** di 3 titik kode (`calc_cogs`, `compare_sheet_suppliers`,
`supplier_sheet_code_onChange`) — hasil bisa beda ratusan kali lipat kalau
salah pilih. Rekomendasi dokumen: pakai rumus meter-lari corrugator
`rm = (qty/isi_trim) × (panjang/100)`. **Kode live (`computeSheetGeometry()`)
sudah memilih rumus yang benar** (lihat §II.3) — tidak ada tindak lanjut.

### V.2 Ivory/AP salah pakai `/8611` — **SUDAH DIPERBAIKI**
Dicatat sebagai "TEMUAN 2026-09-02" di docblock `calculateDcPart()` — versi
kode sebelumnya keliru pakai `PLANO_REF_AREA` (8611) di jalur ivory/AP,
seharusnya `/10000`. Sudah dikoreksi di kode live saat ini.

### V.3 ⚠ GAP BARU (ditemukan saat penyusunan dokumen ini) — DC+SHEET/DC+SF proses tidak lengkap
`calculateDcSheetPart()` di `CostingEngine.php` (baris ~357-434) saat ini
HANYA menjumlahkan `Cetak + Plong` sebagai `processCostPc`, dan **tidak pernah
memproses `$part->coatings`** (loop coating asli seperti di `calculateDcPart()`
tidak ada). Padahal:
- Formula legacy (§II.9 & §II.4 gemini_spark) untuk keluarga **Offset**
  (DC/DC+SF/DC+SHEET semuanya offset, lihat matriks §III "Proses Offset: Ya")
  seharusnya: `Cetak+Plong+Lem+Hotprint+Kupas+QC+Kirim+Packing+Lam+SFEF+Coating`.
- Studi kasus audit riil (§III.1) menunjukkan DC+SHEET punya **Coating
  Rp473,55 sebagai baris terpisah** dari Laminasi (Rp1.790,50) — bukti bahwa
  coating asli (UV/varnish dsb, bukan cuma laminasi sheet) memang berkontribusi
  ke part_type ini.
- Kode `calculateDcSheetPart()` sekarang juga tidak menyertakan Lem/Hotprint/
  Kupas/QC/Kirim/Packing sama sekali di `processCostPc`.

Ini **konsisten dengan disclaimer yang sudah ada** di docblock class
(`"DC+SHEET/DC+SF... BELUM ADA bukti Excel... TANDAI BELUM DIVERIFIKASI"`) —
bukan regresi baru, tapi analisis kali ini mempersempit *persis apa* yang
kurang, plus menyediakan angka target untuk verifikasi (§III.1). Tindak lanjut
diusulkan di §VI, **belum dikerjakan** (di luar scope dokumentasi ini, perlu
konfirmasi karena menyentuh formula harga produksi).

### V.4 `description_calc` kurang informatif dibanding mockup
Kode live menulis `description_calc` cuma
`"CostingEngine v1 (DC) · {timestamp}"` — mockup UAT (§I.1, §II.7) menunjukkan
format legacy jauh lebih berguna untuk audit (breakdown ringkas tiap bucket +
kamus flag `PAPER_OK`/`COAT_OK`/`SFEF_OVR`/dst). Peningkatan kosmetik, tidak
mengubah angka HPP — kandidat perbaikan kecil kalau user mau (lihat §VI).

### V.5 Diskon/surcharge sign convention — konsisten
Baik dokumen legacy maupun kode live memakai `(1 − discount_rm)` dengan
`discount_rm` negatif = surcharge. Tidak ada gap.

### V.6 Aturan ScriptCase (SC01-22, no-ternary, `is_array()` guard) — tidak relevan
Bagian audit legacy soal larangan ternary di Ajax event & guard `is_array()`
pada `sc_lookup()` murni artefak arsitektur ScriptCase — **tidak berlaku** di
Laravel/Livewire (`CostingEngine.php` PHP biasa). Dicatat di sini hanya supaya
tidak salah kira sebagai aturan yang masih perlu diikuti.

---

## VI. Rekomendasi Tindak Lanjut (belum dikerjakan — perlu konfirmasi)

1. **Lengkapi `calculateDcSheetPart()`** dengan bucket proses penuh
   (Lem+Hotprint+Kupas+QC+Kirim+Packing) + loop `$part->coatings` asli
   (sama seperti `calculateDcPart()`), lalu tambahkan test regresi baru di
   `CostingEngineTest.php` memakai angka studi kasus §III.1 (Kertas 1.608,11 /
   Laminasi 1.790,50 / Coating 473,55 / Proses 3.645,50 / Tooling 595,76 /
   Total 6.322,92) sebagai target — mengikuti pola test SHEET_DIGITAL yang
   sudah ada (verifikasi byte-presisi ke Excel).
2. **(Opsional, UX)** Tambah bar ringkasan hijau (Price/Total HPP/Material/
   Process/Tooling/After Margin/After PPN) di atas `PriceTierPanel` layar live,
   meniru §I.1 Section 1 mockup UAT — supaya officer tak perlu scroll ke ladder
   detail untuk lihat angka akhir.
3. **(Opsional, UX)** Perbaiki format `description_calc` mengikuti pola log
   §II.7 (breakdown + flag), bukan sekadar nama+timestamp.
4. **(Opsional, UX, effort lebih besar)** Kalau user mau, render preview
   dieline/imposition ala §I.2 di layar Mode PRODEV (saat ini field ProDev
   cuma tampil sebagai input disabled) — perlu link ke output visual ProDev
   (`prod_layout_group`), bukan pekerjaan kecil.
5. **Klarifikasi kolom laminasi**: field V2 `i_ongkos_lam_rp_cm2_sf`/`_sh`
   apakah sudah dipetakan sesuai `p_olamsf`/`p_olamsh` legacy (nama mirip
   tapi belum diverifikasi 1:1 terhadap migration) — cek sebelum dipakai
   sebagai sumber J38.

Item 1 adalah yang paling penting (menyangkut akurasi harga produksi
DC+SHEET/DC+SF yang sudah ditandai "BELUM DIVERIFIKASI" sejak awal) — item 2-5
murni polish, prioritas rendah.

---

## VII. Verifikasi ke Dump Legacy `docs/ddl/tmhpp.sql` (2026-09-18) — SUMBER KEBENARAN

Dump database program lama (Navicat, server TM 176, schema `tmhpp`, 03/09/2026) +
screenshot layar "UPDATE HPP V3.027" dari user. Bagian ini **mengalahkan** §II bila
bertentangan (§II diturunkan dari ringkasan Gemini, §VII dari data asli).

### VII.1 Konstanta (`m_hpp_constants`) — nilai asli
| Key | Nilai | Keterangan | Ada di `tm2sys.sys_constant`? |
|---|---:|---|---|
| PLANO_REF_AREA | 8611 cm² | plano acuan 79×109 | ✓ |
| RIM_SHEETS | 500 | lembar/rim | ✓ |
| FILM_RATE_DEFAULT | 55 | film normal | ✓ |
| FILM_RATE_FULLPRINT | 60 | full cetak + WB lam | ✗ (lewat master film rate) |
| FILM_FACTOR_DEFAULT | 0,25 | | ✓ |
| KNIFE_RATE_DC | 900.000 Rp/order | DC, DC+SF, DC+SHEET | ✗ (ada di `prod_part_type.pisau_rate_rp`) |
| KNIFE_RATE_KRAFT | 1.500.000 | | ✗ (idem) |
| KNIFE_RATE_OB | 0 | SHEET | ✗ (idem) |
| KNIFE_FACTOR_DEFAULT | 0,25 | | ✗ **belum ada** |
| PLATE_PER_COLOR | 90.000 Rp/warna | | ✓ |
| PCS_PER_LB_DEFAULT / CUT_PER_PLANO_DEFAULT | 1 | guard | (guard di kode) |
| OB_DISCOUNT_RM_MIN / MAX | −0,99 / 0,99 | batas diskon/surcharge | ✗ **belum ada** |
| KALENDER_ONLY, KLEP_DEFAULT_MIKA_PC 170, KLEP_DEFAULT_ONGKOS_PC 100 | | blok KLEP kalender | ✗ **belum ada** |

### VII.2 Master legacy vs tm2mst sekarang
| Legacy | Isi penting | Status di V2 |
|---|---|---|
| `m_paper_grammage` (28 baris) | DUPLEX 250–500 gsm @ 253.000–439.000/rim, DC 530; IVORY 120 @ 15.500/kg; KRAFT 250 @ 6.700→8.350/kg (versi `effective_date`) | `mat_grammage` baru **3 baris dummy** (duplex 12.500/14.200, DC 5,3) — harga BUKAN legacy, perlu impor |
| `m_coating_rate` | Varnish/WB 0,035 · UV 0,020 · UV-Spot 0,025 · Lam Biasa/Glossy 0,165 · Lam Doff/3D 0,210 · Other/No Coating 0 | cek `mat_coating_rate` |
| `m_film_rate` | 55 Normal · 60 Full Cetak+WB · 45 Ivory · 30 Bag Bawah (PK = nilai rate) | belum ada master (kolom `i_film_rate` bebas) |
| `m_join_type` | STITCHING_SINGLE 375 · STITCHING_DOUBLE 750 · GLUE_SINGLE 250 · GLUE_DOUBLE 500 | belum ada |
| `m_packing_type` | NONE / WRAP / SHRINK / WRAP_SHRINK (rate 0, isi per order) | belum ada (`jenis_packing` free-text) |
| `m_sheet_supplier` | **UJK mesin 170 cm gap 2** · RCA 215 cm gap 5 | V2 hardcode `SHEET_MACHINE_WIDTH_CM=180` (dari Excel COCO TOKYO) — **beda dgn master supplier**, perlu keputusan |
| `m_sheet_discount_tier` | UJK: K125 −5% (Apr-26), WK150 −6,5%, default −10%; RCA tier RM 20/20/25/26%, WK150 22,5% | cek `mat_sheet_discount_tier` |
| `m_sheet_rate` (107) | B/F, C/F, BC/F sheet; SINGLE FACE B/F; SF E/F (M125/M125 2.500, M125/K125 2.600) | cek `mat_sheet_rate` |
| `m_part_type` | DC+SF=`DC + Single Face`, DC+SHEET=`DC + Laminasi Sheet`, DC, KRAFT, SHEET=`Outer Box/OB`, SHEET_DIGITAL | `prod_part_type` |
| `m_part_template` | NS40, NKC135-BADAN/-TUTUP, OB_A1, KALENDER, FS GTZ-5S — auto-fill 40 field saat Nama Part diketik | belum ada |

### VII.3 Rumus yang dikoreksi / dipastikan (dari kolom komentar + data)
- **Luas cetak** = `Ukuran Cetak P × L` (INPUT dari Master Card, Excel B22/C22) — **bukan** luas plano. Dipakai coating, film, SF EF, laminasi J38. Plano hanya utk kertas.
- **Kertas duplex**: F_dc = 1 + 5,30 = 6,30 (lihat §II.2).
- **Kertas KRAFT/ivory**: `harga_kg × (gsm/1000) × (plano_cm²/10000) / (cut × isi)` (map_hpp_parts #62) — perhatikan **gsm/1000**, bukan gsm/2 seperti ringkasan Gemini. ⚠ perlu dicek ulang ke data KALENDER (Ivory 120, `PAPER_AP_OK`, 1.209,00).
- **Tooling/PC** = Film + Plate + Pisau dibagi **qty saja** (data RAJA BAKERY: 468/5000, 360.000/5000, 30.000/5000) — komentar "/isi" di kolom tidak dipakai data.
- **Film** = faktor² × luas_cetak × film_rate.
- **Proses DC-family/KRAFT** = cetak + plong + lem + hotprint + kupas + qc + kirim + packing + J36 (lam sheet flat, INPUT Rp/PC final, mis. NS40 1.360) + J37 SF EF + J38 ongkos laminasi.
- **J38** = flag_sheet × uk × faktor_sh / isi + flag_sf × uk × faktor_sf / isi; `uk` = ukuran SF/SH bila "Pakai Ukuran SF?"=1, selain itu ukuran cetak.
- **Coating/PC** = uk_cetak × index / isi (TIDAK dibagi cut).
- **LC = LB = qty ÷ (cut × isi)** (data: 3000/(2×1)=1500, 10000/(1×3)=3333).
- **Ladder** (hpp #9 COCO TOKYO, cocok ke screenshot): Price Per Pcs = HPP ÷ (1−toleransi) → 28.201,78; Total After Margin = ÷ (1−margin) → 33.178,56. (Hati-hati: `map_hpp` menulis ×(1+x) dan hpp #1 memakai ×(1+tol) — versi lama; ikuti versi terbaru.)
- **Header "Total …" di layar = nilai per PC**, bukan × qty.

### VII.4 Kasus uji legacy (acuan regresi `CostingEngineTest`)
| hpp_parts | Tipe | Input kunci | Hasil legacy /PC |
|---|---|---|---|
| #1/#3 NS40 | DC+SHEET | duplex 350 (314.000), DC 5,30, plano 70×100, cut 2, isi 1, uk cetak 70×41, cetak 900.000/rim, plong 250, lem 375, kupas 30, qc 100, kirim 200, J36 1.360, faktor_sh 0,15, lam biasa 0,165, film 60 ff 0,25, plate 4 warna, pisau 0,25×900.000, qty 1.000 | kertas 1.608,11 · proses 3.645,50 · coating 473,55 · tooling 595,76 · **TOTAL 6.322,92** |
| #51 RAJA BAKERY (= mockup #1) | DC+SF | duplex 250 (255.000), DC 5,30, plano 120×104, cut 4, isi 2, uk cetak 60×52, SF E/F 2.600, faktor_sf 0,065, pakai uk SF 59×51, UV 0,065, film 60 ff 0,05, pisau 0,05×600.000, qty 5.000 | kertas 582,08 · proses 827,29 · coat 101,40 · SF EF 405,60 · lam 97,79 · tooling 78,09 · **TOTAL 1.487,46** |
| #52 DUS | DC | duplex 350, override 5,60, plano 70×100, cut 1, isi 3, uk cetak 62×100, film 45 ff 0,5, pisau 0,25×1.000.000, qty 10.000 | kertas 1.123,12 · proses 145 · tooling 67,98 · **TOTAL 1.336,10** |
| #53 COCO TOKYO | SHEET | 427×220×172 lidah 35, K200/M125/K200 B/F, UJK, trim 155, isi/trim 3, surcharge −5, print 300, diecut 350, qc 100, kirim 100, qty 1.800.000 | sheet 23.949,58 · proses 1.150 · **TOTAL 25.099,58** |

### VII.5 Bug engine V2 (`CostingEngine.php`) yang terungkap — BELUM diperbaiki
1. `F_dc = 1 + dcPct/100` → harus `1 + dcPct` → **kertas duplex 6× terlalu murah** di semua costing V2.
2. Luas cetak = luas plano (belum ada kolom Ukuran Cetak P/L) → coating & film **×cut terlalu mahal**.
3. DC+SF/DC+SHEET: J36 dihitung dari geometri sheet (legacy = input Rp/PC), proses tidak lengkap, coating asli tidak diloop (§V.3).
4. Tooling dibagi qty tier ✓ (sesuai), tapi KNIFE_FACTOR_DEFAULT 0,25 belum jadi default.
