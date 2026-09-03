# COSTING ENGINE V2 — TIER 1–4
**Modul: Inquiry-HPP (Costing) · TMERP Laravel 13 + Livewire · PT. Telasih Makmur**

> **Apa ini.** Desain V2 mesin costing (HPP → harga jual), digabung ke dalam alur Inquiry
> sebagai relasi **master-detail** (1 inquiry = 1 product; product = N part). Menggantikan
> engine Scriptcase `tmhpp` (form `frm_hpp_parts`, method `calc_cogs`) dan tabel costing lama
> `i_inquiry_costing`.
>
> **Sumber kebenaran yang dibaca (sel-per-sel / baris-per-baris):**
> `calc_cogs` v14/v16 · `RULES_tmhpp.yaml` · `STATE_tmhpp.yaml` · dump `tmhpp_20260727_1310_sd.sql`
> (header `hpp` sudah gaya-V2) · Excel `NS40_DC` (DC, incl. margin/PPN/multi-part) ·
> mockup `product_costing.html` (ladder harga jual kanonik) · `inquiry_new.html` (input inquiry).
>
> **Konvensi.** Nama tabel/field **English**, prosa **Indonesia**. Money `DECIMAL(18,4)`, dimensi mm
> `DECIMAL(10,2)`. `i_*` = input (boleh DEFAULT), `c_*` = computed (**tanpa** DEFAULT, DB01).
> Konstanta mentah di tabel; turunan di VIEW `v1_` (DB02). FK lintas-DB = **logical** (Eloquent).

---

## 0 · RINGKASAN ARSITEKTUR V2 (baca ini dulu)

**Empat perubahan struktural terhadap `tmhpp` lama:**

1. **Header roll-up, bukan per-part price.** Excel lama menaruh toleransi & margin di *tiap* part
   (`Bag atas` 6%/15%, `Bag bawah` 3%/10%) lalu `J46 = J45 + 'Bag bawah'!J41`. V2 memindah
   toleransi/margin/PPN ke **header per tier** dan hanya menjumlah HPP part:
   `total_hpp = Σ costing_part.c_total_part_cost_pc`. Satu ladder harga, bukan N ladder. *(Keputusan
   sadar; per-part margin legacy tidak dibawa — bila kelak perlu, tambah kolom di `costing_part`.)*

2. **Tooling diamortisasi di tier, bukan di part.** `calc_cogs` lama membagi film/plate/knife
   `÷ qty_part`, sehingga `c_*` part terikat ke satu qty. V2: `costing_part` menyimpan **tooling
   TOTAL order-level** (`c_tooling_cost_order`); pembagian `÷ qty` terjadi di **`costing_price_tier`**.
   Efek: material/proses/coating **konstan lintas tier**, hanya tooling/pc & harga akhir yang berubah —
   persis narasi mockup ("bahan/proses/coating konstan lintas tier").

3. **Engine = PHP service, bukan method Scriptcase.** Logika 10-sesi `calc_cogs` pindah ke
   `App\Services\Costing\CostingEngine` (Laravel). Aturan Scriptcase (SC01–SC22, sc_lookup guard,
   no-ternary) **tidak berlaku lagi**; digantikan Eloquent + PHP biasa. `description_calc` (jejak
   flag) dipertahankan sebagai kolom audit.

4. **Master-detail di `tm2sdi`, rate di `tm2mst`.** Transaksi (`costing`, `costing_part`,
   `costing_part_coating`, `costing_price_tier`) di **`tm2sdi`**, co-located dengan `inquiry`.
   Master rate (`m_paper_grammage`, `m_sheet_rate`, `m_flute_allowance`, tier diskon, konstanta,
   template) di **`tm2mst`**. Cross-DB via relasi Eloquent (logical FK).

**Enam `part_type` didukung** (pemilih blok formula): `DC`, `DC+SF`, `DC+SHEET`, `KRAFT`, `SHEET`,
`SHEET_DIGITAL`. Isolasi `$is_sheet = part_type IN (SHEET, SHEET_DIGITAL)` — cabang SHEET murni wajib
byte-identik (WF05).

**Ladder harga jual kanonik (dari `product_costing.html`, cross-check Excel NS40):**

```
Σ(mat+proc+finishing) ..................... total_hpp_pc
÷ (1 − tolerance_pct/100) ................. after_tolerance_pc (mark-up on price, SAMA gaya dgn margin)
round(rounding_mode, rounding_step) ....... pembulatan
÷ (1 − margin_pct/100) .................... after_margin_pc  = price_per_pcs (acuan quotation, excl PPN)
× (1 + ppn_pct/100) ....................... after_ppn_pc     (incl, tampil terpisah)
```
Margin = **mark-up on price** (`HPP ÷ (1−m)`), bukan mark-up on cost. Toleransi = mark-up on price
juga (bukan additive `×(1+t)` seperti draf awal) — **dikoreksi 2026-08-13** setelah verifikasi
byte-presisi ke Excel asli `OB COCO TOKYO FLAT 427X220X172MM 1800 BOX ... .xlsx` (cell `C31 =
C30/(1-B31)`). Snapshot saat `publish`.

---

# ═══════════════════════════════════════════════════════════
# TIER 1 — SYSTEM FLOW & PROCESS MATRIX
# ═══════════════════════════════════════════════════════════

## 1.1 Identitas & scope
Modul **Costing (HPP)** sebagai sub-modul Sales/Inquiry. Menghasilkan HPP per part → harga jual per
tier → dipublish ke Quotation. **Di dalam scope:** kalkulasi 6 part_type, coating multi-layer, SF EF,
laminasi sheet, geometri SHEET_DIGITAL, agregasi multi-part, ladder harga jual, 3 tipe dokumen
(NEW/RECALC/REVISE), state DRAFT→PUBLISHED, dua-sisi (A Costing / B Sales). **Di luar scope:**
imposition engine (dokumen sendiri, memberi `up_count/plano`), quotation & CO (stage lanjut),
master rate maintenance.

## 1.2 Tiga tipe dokumen (selaras `m_hpp_calc_type` = `m_inquiry_type`)

| Tipe | Kapan | product_id / mcard_id | Efek saat CONFIRMED |
|---|---|---|---|
| **NEW** | Produk baru, belum ada Master Card | boleh kosong | generate draft `m_card` (BOM+BOP) baru; `mcard_id` ditulis balik |
| **RECALC** | Spec sama, harga bahan/ongkos berubah | **wajib** | `m_card` **tak berubah**; hanya `price_per_pcs` diupdate |
| **REVISE** | Spec/desain berubah | product wajib, mcard = versi lama (ref) | `m_card_material`+`m_card_process` diupdate; `mcard_rev` naik |

Tipe **immutable** (ditetapkan sekali). REVISE = **copy-record** (INSERT `revision_no`+1, versi lama
`is_cancelled=1 cancel_reason='SUPERSEDED'`) — tidak pernah edit record CONFIRMED (freeze trigger).

## 1.3 Dua sisi informasi (dasar visibilitas — CRITICAL)
- **Sisi A (Costing/Direksi):** semua `c_*`, breakdown HPP, margin/toleransi terlihat.
- **Sisi B (Sales):** HPP **redacted** sampai `doc_status = PUBLISHED`. Hanya `price_per_pcs` terbuka.
- Gerbang: **Direksi approve = publish** → `published_flag 0→1` → Sisi B terbuka. Approval Direksi
  wajib untuk semua harga.

## 1.4 Alur proses (make-to-order)
```
Inquiry (Sales isi part+tier+spec)
  → Costing NEW/RECALC/REVISE (Officer isi rate → Engine hitung per tier)
  → doc_status: DRAFT → CALCULATED
  → Submit ke Direksi → PENDING_APPROVAL
  → Direksi approve → PUBLISHED (harga ke Sisi B) → Quotation
```

## 1.5 Relasi & kardinalitas (locked)
- **1 inquiry : 1 costing** (header) — master-detail; keduanya di `tm2sdi`, `costing.inquiry_id` unik.
- **1 costing : N costing_part** (multi-part; NS40 = Bag atas + Bag bawah).
- **1 costing_part : N costing_part_coating** (layer coating berurutan).
- **1 costing : N costing_price_tier** (per tier qty dari `inquiry_qty_tier`; tepat 1 `is_primary`).
- **1 costing_part : 1 grammage/sheet_rate** (snapshot rate + index disalin ke record).

## 1.6 Role matrix
| Role | Aksi |
|---|---|
| Sales | buat inquiry, isi part/tier/spec, lihat Sisi B setelah publish |
| Costing Officer | isi rate/ongkos, jalankan engine, submit ke Direksi |
| Direksi | review margin/toleransi per tier, approve=publish |

---

# ═══════════════════════════════════════════════════════════
# TIER 2 — HAPPY PATH · SPECIAL CASES · STATE/FORM/BUTTON
# ═══════════════════════════════════════════════════════════

## PART A — HAPPY PATH (NEW, part_type = DC, contoh NS40)

1. Officer buka costing dari inquiry → header auto-fill (customer, product, dimensi, tier qty).
2. Ketik nama part → **template match** (`m_part_template`) → auto-fill semua default
   (grammage, plano, cut, isi, warna, film/plate/plong/lem rate). *Officer mulai dari template, bukan nol.*
3. Isi/oversteer rate → **Hitung**. Engine jalankan 10 sesi per part:
   area → paper → proses dasar → coating → laminasi/SF EF → tooling → total/pc.
4. Roll-up header: `total_hpp = Σ part.c_total_part_cost_pc`.
5. Per tier: tooling/pc = `c_tooling_cost_order ÷ qty_tier`; jalankan ladder → `price_per_pcs`.
6. `doc_status = CALCULATED`. Ulangi tiap tier → **Submit ke Direksi** → approve → **PUBLISHED**.

## PART B — SPECIAL CASES

### B.1 Enam part_type (blok formula) — ringkas rumus/pc
| part_type | Bahan | Proses khas | Tooling | Catatan |
|---|---|---|---|---|
| **DC** | duplex/ivory-AP (SESI 3 jalur A/B) | cetak+plong+lem | film+plate+knife | baseline |
| **DC+SF** | duplex + **SF EF auto** (J37) | + laminasi sheet (J36/J38) | idem | `fsf=1` otomatis, `i_sf_ef_flag` diabaikan |
| **DC+SHEET** | duplex + sheet corrugated | + laminasi (titik MERGE duplex+corrugated) | idem | SF EF auto; `c_i_sf_ef` dihitung |
| **KRAFT** | ivory/AP per-kg, **tanpa** `÷RIM` | cetak+plong+lem | knife 1.5jt | rumus paper beda dari ivory-AP |
| **SHEET** | `index_substance` (SESI 3 sheet) | print×2 + diecut/slitter/slotter/join/partisi/layer | **0** (TOOL_SKIP) | **byte-identik wajib** |
| **SHEET_DIGITAL** | idem SHEET + **derivasi geometri** | idem SHEET + tier diskon | 0 | lihat B.5 |

### B.2 Rumus bahan (SESI 3) — inti
- **Duplex (jalur A, `duplex_price_list>0`):**
  `c_paper_cost_pc = (plano_L×plano_W / PLANO_REF_AREA) × price × dcf / RIM / cut / isi`
  `dcf = 1 + (override>0 ? override : duplex_dc_pct/100)` — DC% disimpan int×100 (530→5.3%).
- **Ivory/AP (jalur B):** `× ivory_price_kg × (grammage/2) / RIM / cut / isi`; set `is_ap_paper=1`
  (film boleh 0 di SESI 7).
- **KRAFT:** sama jalur B **tanpa `/RIM`**.
- **SHEET:** `c_sheet_cost_pc = (lebar_trim×panjang_sheet/10000) × index_substance / isi_trim × (1 − discount_rm)`.
  `discount_rm` **negatif = surcharge** (COCO −0.05 = +5%); pakai `(1−rm)`, disengaja.

### B.3 Coating multi-layer (SESI 5, `$is_sheet=0`)
Child `costing_part_coating` per layer; sync: `cost_per_lc = area × index_coating`,
`cost_per_pc = cost_per_lc / isi` (**tak dibagi cut**). `c_total_coating_pc = SUM(cost_per_pc)`.
Urutan: **COATING dulu → baru LAMINASI** (koreksi kritis, jangan terbalik).

### B.4 Laminasi & SF EF (SESI 6)
- J36 laminasi sheet flat = `fsheet × i_lam_sheet_flat_pc`.
- J37 SF EF = `(area/10000) × harga_sf / isi`; prioritas harga: override>0 → lookup `v1_sf_ef_rate` → 0.
  SF EF **auto** untuk DC+SF & DC+SHEET.
- J38 ongkos laminasi = per-cm² (`sh`/`sf` rate) atau fallback flat `i_ongkos_laminasi_pc`.
- Output: `c_sf_ef_cost_pc = J37`, `c_ongkos_lam_pc = J36 + J38`. **Anti double-count:** J36/J37/J38
  sudah masuk `c_process_cost_pc`; jangan ditambah ulang di total.

### B.5 Geometri SHEET_DIGITAL (SESI 3b — sisipan, SHEET murni dilewati)
Gate `part_type='SHEET_DIGITAL' AND P>0 AND L>0 AND flute≠''` (else `GEO_MANUAL`).
1. Flute dari `sheet_rate_id` (Opsi A, tak ada kolom `i_flute_type`) → `v1_sheet_rate.flute_type`.
2. Allowance `v1_flute_allowance` (total_p_mm, total_l_mm).
3. Dimensi sheet (Excel B4/C4):
   `panjang_cm = (((P+L)×2) + total_p_mm + lidah_lem) / 10` · `lebar_cm = ((L+T) + total_l_mm) / 10`.
4. Trim (B13/B14): `isi_trim = floor((180−2)/lebar_cm)` · `lebar_trim = ceil(((lebar_cm×isi_trim)+2)/5)×5`
   (180=lebar mesin, 2=gap; hardcode setia Excel).
5. Diskon tier: `rm = (qty/isi)/isi_trim × (panjang_cm/100)`; lookup `v1_sheet_digital_tier`
   (`supplier_code`, `sheet_rate_id`, `rm BETWEEN rm_from AND rm_to`) → `discount_rm`.
   Sukses `GEO_OK TIER_OK`, no-match `GEO_OK TIER_MANUAL`. *(supplier_code masih hardcode 'UJK' —
   kandidat parameterisasi.)*

### B.6 Tooling (SESI 7, `$is_sheet=0`)
`film = (ff²) × area × frate` (ff dikuadratkan; `film_rate_id` dipakai sebagai NILAI, bukan FK) ·
`plate = plate_rate × colors` · `knife = pisau_faktor × pisau_rate`.
**V2:** ketiganya disimpan **order-level** (`c_tooling_cost_order`), `÷qty` di tier (bukan di part).

### B.7 Agregasi multi-part & ladder harga (header)
`total_material/process/finishing/hpp = Σ part`. Per `costing_price_tier` jalankan ladder §0.
Toleransi & margin **per tier** (Direksi bisa tipiskan margin di qty besar).

## PART C — STATE / FORM / BUTTON

**State (`doc_status`):** `DRAFT → CALCULATED → PENDING_APPROVAL → PUBLISHED` (+ `FAILED`, `CANCELLED`).
`is_confirmed`, `is_approved`, `published_flag` backbone.

| State | Sisi B lihat harga | Tombol aktif |
|---|---|---|
| DRAFT | ✗ | Hitung, Simpan |
| CALCULATED | ✗ | Hitung (ulang), Submit ke Direksi, Batalkan |
| PENDING_APPROVAL | ✗ | (Direksi) Approve / Tolak |
| PUBLISHED | ✓ | — (immutable; ubah = REVISE) |

**Siapa menulis apa:** `i_*` = user/officer. `c_*` = **engine only** (tak pernah diketik).
`description_calc` = ditulis engine (jejak flag). `calc_notes` = catatan bebas user, **jangan
di-overwrite** engine. Field CONFIRMED immutable (freeze trigger; revisi = copy-record).

---

# ═══════════════════════════════════════════════════════════
# TIER 3A — DDL (IDEAL DESIGN, ALL-NEW)
# ═══════════════════════════════════════════════════════════

> Semua `c_*` **tanpa DEFAULT** (DB01). Turunan geometri/allowance di VIEW `v1_` (DB02).
> Transaksi di `tm2sdi`; master rate di `tm2mst` (FK logical). Audit `created_by/at`, `updated_by/at`
> di tiap tabel transaksi (dipadatkan di DDL agar ringkas).

## BAGIAN 1 — TRANSAKSI (tm2sdi)

### 1.1 `costing` — header (eks `hpp`), 1:1 dengan `inquiry`
```sql
CREATE TABLE `costing` (
  `costing_id`       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK costing/HPP',
  `doc_no`           VARCHAR(20) NULL COMMENT 'Format HPP{YY}{NNNNN}, reset tahunan, immutable',
  `doc_date`         DATE NULL DEFAULT (CURDATE()),
  `inquiry_id`       BIGINT UNSIGNED NOT NULL COMMENT 'FK logical inquiry (tm2sdi). 1:1 — UNIQUE',
  `calc_type`        VARCHAR(10) NOT NULL DEFAULT 'NEW' COMMENT 'NEW|RECALC|REVISE. FK m_hpp_calc_type. Immutable',
  `revision_no`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Naik saat copy-record (REVISE)',
  `parent_costing_id` BIGINT UNSIGNED NULL COMMENT 'FK ke costing asal (lineage revisi)',
  `product_id`       BIGINT UNSIGNED NULL COMMENT 'FK logical m_product (tm2mst). Wajib RECALC/REVISE',
  `mcard_id`         BIGINT UNSIGNED NULL COMMENT 'FK logical m_card. Ditulis balik setelah NEW confirmed',
  `customer_id`      BIGINT UNSIGNED NULL COMMENT 'FK logical m_customer',
  -- dimensi produk jadi (auto-fill dari m_product; diturunkan ke part & geometri SHEET_DIGITAL)
  `i_dim_panjang_mm` DECIMAL(10,2) NULL DEFAULT 0 COMMENT 'P jadi (mm)',
  `i_dim_lebar_mm`   DECIMAL(10,2) NULL DEFAULT 0 COMMENT 'L jadi (mm)',
  `i_dim_tinggi_mm`  DECIMAL(10,2) NULL DEFAULT 0 COMMENT 'T jadi (mm)',
  `i_lidah_lem_mm`   DECIMAL(10,2) NULL DEFAULT 0 COMMENT 'Lidah lem (mm) — dipakai derivasi sheet',
  -- roll-up hasil (Σ costing_part) — level order, konstan lintas tier
  `total_material_cost_pc`  DECIMAL(18,4) NULL COMMENT 'Σ bahan/pc (konstan lintas tier)',
  `total_process_cost_pc`   DECIMAL(18,4) NULL COMMENT 'Σ proses/pc',
  `total_finishing_cost_pc` DECIMAL(18,4) NULL COMMENT 'Σ coating+laminasi+SF EF /pc',
  `c_tooling_cost_order`    DECIMAL(18,4) NULL COMMENT 'Σ tooling ORDER-level (film+plate+knife). ÷qty di tier',
  -- parameter harga (default; nilai final di-snapshot per tier)
  `is_ppn`           TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=include PPN',
  `ppn_pct`          DECIMAL(5,2) NOT NULL DEFAULT 11.00,
  `currency_code`    VARCHAR(10) NOT NULL DEFAULT 'IDR',
  -- state & publish
  `doc_status`       VARCHAR(30) NOT NULL DEFAULT 'DRAFT' COMMENT 'DRAFT|CALCULATED|PENDING_APPROVAL|PUBLISHED|FAILED|CANCELLED',
  `is_confirmed`     TINYINT(1) NOT NULL DEFAULT 0,
  `is_approved`      TINYINT(1) NOT NULL DEFAULT 0,
  `published_flag`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=harga terbuka ke Sisi B (Sales). Di-set saat Direksi approve',
  `published_at`     TIMESTAMP NULL,
  `calc_notes`       TEXT NULL COMMENT 'Catatan bebas user — JANGAN di-overwrite engine',
  `created_by` VARCHAR(100) NULL, `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(100) NULL, `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`costing_id`),
  UNIQUE KEY `uq_costing_doc` (`doc_no`,`revision_no`),
  UNIQUE KEY `uq_costing_inquiry` (`inquiry_id`),
  KEY `idx_costing_product` (`product_id`)
) COMMENT='Header costing/HPP. 1:1 inquiry. Roll-up Σ part; harga di-snapshot per costing_price_tier';
```

### 1.2 `costing_part` — detail (eks `hpp_parts`), semua `c_*` = hasil engine
```sql
CREATE TABLE `costing_part` (
  `costing_part_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'PK part costing',
  `costing_id`      BIGINT UNSIGNED NOT NULL COMMENT 'FK costing (master-detail)',
  `no_urut`         TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Urutan part (1=Bag atas, 2=Bag bawah...)',
  `part_name`       VARCHAR(100) NULL COMMENT 'Nama part; match m_part_template kata pertama',
  `part_type`       VARCHAR(20) NOT NULL DEFAULT 'DC' COMMENT 'DC|DC+SF|DC+SHEET|KRAFT|SHEET|SHEET_DIGITAL — pemilih blok formula',
  `qty_per_set`     DECIMAL(10,2) NOT NULL DEFAULT 1 COMMENT 'Jumlah part ini per 1 set produk',

  -- ── INPUT dimensi & bahan ──
  `i_dim_panjang_mm` DECIMAL(10,2) NULL, `i_dim_lebar_mm` DECIMAL(10,2) NULL,
  `i_dim_tinggi_mm`  DECIMAL(10,2) NULL, `i_lidah_lem_mm` DECIMAL(10,2) NULL COMMENT 'Untuk derivasi SHEET_DIGITAL',
  `gramatur_id`     INT UNSIGNED NULL COMMENT 'FK logical m_paper_grammage. Sumber harga & DC%',
  `i_dc_persen_override` DECIMAL(6,4) NULL COMMENT 'Override DC% (mis 0.053). NULL/0 = ambil dari grammage',
  `i_panjang_plano_cm` DECIMAL(10,4) NOT NULL DEFAULT 0, `i_lebar_plano_cm` DECIMAL(10,4) NOT NULL DEFAULT 0,
  `i_cut_per_plano` DECIMAL(8,4) NOT NULL DEFAULT 1 COMMENT 'Pembagi paper cost (guard >0)',
  `i_isi_per_lb`    DECIMAL(8,4) NOT NULL DEFAULT 1 COMMENT 'Isi pcs/lembar (pembagi, guard >0)',
  `i_jumlah_warna`  TINYINT UNSIGNED NOT NULL DEFAULT 4 COMMENT 'Plate count',
  `pakai_laminasi`  TINYINT(1) NOT NULL DEFAULT 0,

  -- ── INPUT sheet / SHEET_DIGITAL ──
  `sheet_rate_id`   BIGINT UNSIGNED NULL COMMENT 'FK logical v1_sheet_rate. Sumber index_substance & flute',
  `supplier_sheet_code` VARCHAR(20) NULL COMMENT 'Kode supplier sheet (mis UJK)',
  `i_sheet_panjang_cm` DECIMAL(10,4) NULL COMMENT 'Manual atau DERIVED (SHEET_DIGITAL SESI 3b)',
  `i_sheet_lebar_cm`   DECIMAL(10,4) NULL, `i_lebar_trim_cm` DECIMAL(10,4) NULL,
  `i_isi_per_trim`  DECIMAL(8,4) NULL COMMENT 'Guard >0→1',
  `i_discount_rm`   DECIMAL(8,4) NULL DEFAULT 0 COMMENT 'Diskon(+)/surcharge(−). Pakai (1−rm). Dari tier atau manual',

  -- ── INPUT ongkos proses non-sheet ──
  `i_ongkos_cetak_rim` DECIMAL(18,4) NULL DEFAULT 0, `i_ongkos_plong_lb` DECIMAL(18,4) NULL DEFAULT 0,
  `i_ongkos_lem_pc` DECIMAL(18,4) NULL DEFAULT 0, `i_ongkos_hotprint_pc` DECIMAL(18,4) NULL DEFAULT 0,
  `i_ongkos_kupas_pc` DECIMAL(18,4) NULL DEFAULT 0, `i_ongkos_qc_pc` DECIMAL(18,4) NULL DEFAULT 0,
  `i_ongkos_kirim_pc` DECIMAL(18,4) NULL DEFAULT 0, `i_ongkos_packing_pc` DECIMAL(18,4) NULL DEFAULT 0,
  `packing_type_id` INT UNSIGNED NULL COMMENT 'FK m_packing_type',

  -- ── INPUT ongkos proses SHEET ──
  `i_ongkos_print_pc` DECIMAL(18,4) NULL DEFAULT 0 COMMENT 'Print sheet (×2 di engine)',
  `i_ongkos_diecut_pc` DECIMAL(18,4) NULL DEFAULT 0, `i_ongkos_slitter_pc` DECIMAL(18,4) NULL DEFAULT 0,
  `i_ongkos_slotter_pc` DECIMAL(18,4) NULL DEFAULT 0, `i_ongkos_join_pc` DECIMAL(18,4) NULL DEFAULT 0,
  `i_ongkos_partisi_pc` DECIMAL(18,4) NULL DEFAULT 0, `i_ongkos_layer_pc` DECIMAL(18,4) NULL DEFAULT 0,
  `i_ongkos_pisau_pc` DECIMAL(18,4) NULL DEFAULT 0, `join_type` VARCHAR(20) NULL COMMENT 'GLUE|STITCHING (assembly, ≠ flute)',

  -- ── INPUT laminasi / SF EF ──
  `i_lam_sheet_flat_pc` DECIMAL(18,4) NULL DEFAULT 0, `i_ongkos_laminasi_pc` DECIMAL(18,4) NULL DEFAULT 0,
  `i_ongkos_lam_rp_cm2_sh` DECIMAL(18,6) NULL DEFAULT 0, `i_ongkos_lam_rp_cm2_sf` DECIMAL(18,6) NULL DEFAULT 0,
  `i_lam_pakai_ukuran_sf` TINYINT(1) NULL DEFAULT 0,
  `sf_ef_rate_id`   BIGINT UNSIGNED NULL COMMENT 'FK logical v1_sf_ef_rate',
  `i_sf_ef_rate_override` DECIMAL(18,4) NULL DEFAULT 0 COMMENT '>0 menang atas lookup',

  -- ── INPUT tooling ──
  `film_rate_id`    INT NULL COMMENT 'Dipakai sbg NILAI rate (bukan FK). NULL→FILM_RATE_DEFAULT',
  `i_film_faktor`   DECIMAL(8,4) NULL DEFAULT 0 COMMENT 'ff (dikuadratkan di engine)',
  `i_pisau_faktor`  DECIMAL(8,4) NULL DEFAULT 0, `i_pisau_rate_rp` DECIMAL(18,4) NULL DEFAULT 0,
  `i_ongkos_plate_warna` DECIMAL(18,4) NULL DEFAULT 0 COMMENT '>0 override PLATE_PER_COLOR',

  -- ══ COMPUTED (c_*) — engine only, NO DEFAULT ══
  `c_uk_cetak_panjang_cm` DECIMAL(10,4) NULL, `c_uk_cetak_lebar_cm` DECIMAL(10,4) NULL,
  `c_area_cetak_cm2` DECIMAL(14,4) NULL COMMENT 'panjang×lebar cetak',
  `c_jumlah_cetak_lc` DECIMAL(14,4) NULL, `c_jumlah_bahan_lb` DECIMAL(14,4) NULL,
  `c_paper_cost_pc` DECIMAL(18,4) NULL, `c_sheet_cost_pc` DECIMAL(18,4) NULL,
  `c_cetak_cost_pc` DECIMAL(18,4) NULL, `c_plong_cost_pc` DECIMAL(18,4) NULL, `c_lem_cost_pc` DECIMAL(18,4) NULL,
  `c_print_cost_pc` DECIMAL(18,4) NULL COMMENT 'SHEET: i_ongkos_print_pc×2',
  `c_total_coating_pc` DECIMAL(18,4) NULL, `c_total_coating_lc` DECIMAL(18,4) NULL,
  `c_sf_ef_cost_pc` DECIMAL(18,4) NULL COMMENT 'J37', `c_ongkos_lam_pc` DECIMAL(18,4) NULL COMMENT 'J36+J38',
  `c_film_cost_order` DECIMAL(18,4) NULL, `c_plate_cost_order` DECIMAL(18,4) NULL, `c_knife_cost_order` DECIMAL(18,4) NULL,
  `c_tooling_cost_order` DECIMAL(18,4) NULL COMMENT 'film+plate+knife ORDER-level (÷qty di tier)',
  `c_process_cost_pc` DECIMAL(18,4) NULL COMMENT 'proses+coating+lam+sfef /pc (anti double-count)',
  `c_total_part_cost_pc` DECIMAL(18,4) NULL COMMENT 'TOTAL bahan+proses+coating /pc (TANPA tooling — tooling di tier)',
  `description_calc` VARCHAR(500) NULL COMMENT 'Jejak engine: versi+ts+flag. Ditulis sistem',
  `created_by` VARCHAR(100) NULL, `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_by` VARCHAR(100) NULL, `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`costing_part_id`), KEY `idx_cpart_costing` (`costing_id`)
) COMMENT='Detail costing per part. c_*=engine, NO DEFAULT. Tooling disimpan order-level; ÷qty di costing_price_tier';
```
> **Catatan desain:** `c_total_part_cost_pc` V2 **tidak** memasukkan tooling (beda dari `hpp_parts` lama
> yang membagi tooling ÷qty di dalam part). Tooling ditarik ke tier agar konstan/tier lain terjaga.

### 1.3 `costing_part_coating` — layer coating
```sql
CREATE TABLE `costing_part_coating` (
  `coating_id`      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `costing_part_id` BIGINT UNSIGNED NOT NULL COMMENT 'FK costing_part',
  `seq`             TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Urutan layer (berurutan)',
  `coating_type`    VARCHAR(50) NULL COMMENT 'WB|UV|varnish|laminating (kolom coating_type, bukan _name)',
  `index_coating`   DECIMAL(20,4) NULL COMMENT 'SNAPSHOT index saat hitung (audit historis)',
  `c_cost_per_lc`   DECIMAL(20,6) NULL COMMENT 'area × index_coating',
  `c_cost_per_pc`   DECIMAL(20,4) NULL COMMENT 'cost_per_lc / isi (TIDAK /cut)',
  `notes_coating`   TEXT NULL,
  PRIMARY KEY (`coating_id`), KEY `idx_coating_cpart` (`costing_part_id`)
) COMMENT='Coating per part, multi-layer berurutan. Index di-snapshot. COATING dulu → baru laminasi';
```

### 1.4 `costing_price_tier` — harga per tier qty (BARU di V2)
```sql
CREATE TABLE `costing_price_tier` (
  `price_tier_id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `costing_id`      BIGINT UNSIGNED NOT NULL COMMENT 'FK costing',
  `inquiry_tier_id` BIGINT UNSIGNED NULL COMMENT 'FK logical inquiry_qty_tier',
  `qty_pcs`         DECIMAL(18,2) NOT NULL COMMENT 'Qty tier',
  `is_primary`      TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Tepat 1 UTAMA (acuan quotation)',
  -- parameter di-snapshot saat publish (boleh beda per tier)
  `tolerance_pct`   DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Toleransi produksi/waste',
  `margin_pct`      DECIMAL(6,2) NOT NULL DEFAULT 0 COMMENT 'Mark-up on price: ÷(1−m)',
  `rounding_mode`   VARCHAR(10) NOT NULL DEFAULT 'NEAREST' COMMENT 'NEAREST|UP|DOWN',
  `rounding_step`   DECIMAL(12,2) NOT NULL DEFAULT 1 COMMENT 'Kelipatan pembulatan',
  -- hasil ladder (c_*)
  `c_tooling_pc`    DECIMAL(18,4) NULL COMMENT 'c_tooling_cost_order ÷ qty_pcs',
  `c_hpp_pc`        DECIMAL(18,4) NULL COMMENT 'total_hpp_pc + tooling_pc',
  `c_after_tolerance_pc` DECIMAL(18,4) NULL, `c_after_round_pc` DECIMAL(18,4) NULL,
  `c_after_margin_pc` DECIMAL(18,4) NULL COMMENT '= price_per_pcs (excl PPN, acuan quotation)',
  `c_after_ppn_pc`  DECIMAL(18,4) NULL COMMENT 'incl PPN (tampil terpisah)',
  `price_per_pcs`   DECIMAL(18,4) NULL COMMENT 'Harga jual acuan = c_after_margin_pc',
  PRIMARY KEY (`price_tier_id`), KEY `idx_ptier_costing` (`costing_id`)
) COMMENT='Ladder harga per tier qty. Tooling amortized di sini; parameter di-snapshot saat publish';
```

### 1.5 `costing_state_log` — audit transisi
```sql
CREATE TABLE `costing_state_log` (
  `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `costing_id` BIGINT UNSIGNED NOT NULL,
  `from_state` VARCHAR(30) NULL, `to_state` VARCHAR(30) NOT NULL,
  `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, `changed_by` VARCHAR(100) NULL,
  `duration_sec` BIGINT NULL COMMENT 'Lama di state sebelumnya (cycle time report)',
  PRIMARY KEY (`log_id`), KEY `idx_cslog` (`costing_id`)
) COMMENT='Jejak state costing untuk report pipeline & cycle time';
```

## BAGIAN 2 — MASTER RATE (tm2mst) — struktur, sumber dari dump `tmhpp`

Tabel berikut **diadopsi apa adanya** dari `tmhpp` (sudah matang & terverifikasi), dipindah ke `tm2mst`:

| Tabel | Isi | Kolom kunci |
|---|---|---|
| `m_hpp_calc_type` | NEW/RECALC/REVISE | `type_code, type_name, description` |
| `m_hpp_constants` | konstanta pabrik key-value | `constant_key, constant_group, constant_number, is_active` |
| `m_part_type` | 6 tipe + default | `part_code, part_label, pisau_rate_rp, pakai_laminasi, default_template_id` |
| `m_part_template` | template + default value part | `template_code, part_type, gramatur_id, plano_*, cut, isi, num_colors, film_rate_id, rate ongkos` |
| `m_paper_grammage` | harga duplex/ivory-AP + DC% | `id_cons_grammage, duplex_price_list, duplex_dc_percentage, ivory_ap_price_per_kg, grammage` |
| `m_sheet_rate` | index substance sheet | `doc_id, flute_type, index_substance, mat_subtype, label` |
| `m_sheet_supplier` | supplier sheet | `supplier_code, ...` |
| `m_sheet_discount_tier` | tier diskon/surcharge | `supplier_code, part_type, sheet_rate_id, rm_from, rm_to, discount_pct` |
| `m_flute_allowance` | allowance mentah K/L/M | `flute_type, leb_panjang_mm, leb_lebar_mm, leb_tinggi_mm` |
| `m_coating_rate`, `m_film_rate`, `m_join_type`, `m_packing_type`, `m_routing_template` | rate & lookup pendukung | — |

**Konstanta wajib (`m_hpp_constants`):** `PLANO_REF_AREA=8611`, `RIM_SHEETS=500`,
`FILM_RATE_DEFAULT=55`, `FILM_FACTOR_DEFAULT=0.25`, `PLATE_PER_COLOR=90000` (+ machine_width=180,
gap=2 untuk SHEET_DIGITAL).

## BAGIAN 3 — VIEW `v1_` (turunan, DB02)
```sql
-- Allowance turunan (mentah K/L/M → total)
CREATE VIEW `v1_flute_allowance` AS
SELECT flute_type,
       (leb_panjang_mm + leb_lebar_mm) * 2         AS total_p_mm,
       (leb_lebar_mm + leb_tinggi_mm) + 3          AS total_l_mm
FROM `m_flute_allowance` WHERE is_active = 1;

-- Rate sheet (+ flute untuk Opsi A)
CREATE VIEW `v1_sheet_rate` AS
SELECT doc_id, flute_type, index_substance, mat_subtype, `label`
FROM `m_sheet_rate` WHERE is_active = 1;

-- SF EF (subset sheet_rate)
CREATE VIEW `v1_sf_ef_rate` AS
SELECT doc_id AS id_sf, material_substance AS description, index_substance AS rate, display_label
FROM `m_sheet_rate` WHERE flute_type='E/F' AND mat_subtype='SF' AND is_active=1;

-- Tier diskon SHEET_DIGITAL
CREATE VIEW `v1_sheet_digital_tier` AS
SELECT supplier_code, sheet_rate_id, rm_from, rm_to, discount_pct
FROM `m_sheet_discount_tier` WHERE part_type='SHEET_DIGITAL' AND is_active=1;
```
> ⚠ **VERIF01:** kolom label di `m_sheet_rate` bernama `label` (dump 15-07) — konfirmasi sebelum query.

## BAGIAN 4 — GUARD (freeze CONFIRMED)
```sql
-- Tolak UPDATE bila is_confirmed=1 (izinkan hanya transisi cancel). Revisi = copy-record.
DELIMITER $$
CREATE TRIGGER `trg_costing_freeze` BEFORE UPDATE ON `costing`
FOR EACH ROW BEGIN
  IF OLD.is_confirmed = 1 AND NEW.doc_status <> 'CANCELLED' AND NEW.published_flag = OLD.published_flag THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Costing CONFIRMED immutable — gunakan REVISE (copy-record)';
  END IF;
END$$
DELIMITER ;
```

## RINGKASAN & NARASI 3A (keputusan D)
- **7 tabel baru di `tm2sdi`** (`costing`, `costing_part`, `costing_part_coating`,
  `costing_price_tier`, `costing_state_log`) + adopsi **~14 master** di `tm2mst`.
- **Perbedaan inti vs `tmhpp`:** (a) tooling ditarik ke tier (`c_tooling_cost_order` +
  `costing_price_tier`), (b) toleransi/margin/PPN di header/tier bukan per-part, (c) 1:1 inquiry
  (UNIQUE `inquiry_id`), (d) `c_*` tanpa DEFAULT, turunan di VIEW, (e) SHEET_DIGITAL first-class.
- **Yang dipertahankan identik:** seluruh rumus 10-sesi, kamus flag, isolasi `$is_sheet`,
  snapshot index coating, aturan `calc_notes` vs `description_calc`.
- **Cerita alur data:** `inquiry` isi part+dimensi+tier → `costing_part` isi rate → **engine**
  hitung `c_*` per part (tooling ke order-level) → header roll-up Σ → `costing_price_tier`
  amortisasi tooling + ladder → `price_per_pcs`. Publish → snapshot parameter → Sisi B terbuka.

---

# ═══════════════════════════════════════════════════════════
# TIER 4 — SCREEN / LIVEWIRE BINDING
# ═══════════════════════════════════════════════════════════

> Replikasi struktur layar `product_costing.html` (bukan desain baru). Komponen Livewire, bukan
> form Scriptcase. Binding field lama diambil dari `map_hpp_parts` (kolom `nama_field, nama_block,
> visible_type, cell_addr`) sebagai kamus migrasi.

## 4.1 Peta komponen Livewire
| Komponen | Tabel | Peran |
|---|---|---|
| `CostingHeader` | `costing` | identitas, calc_type, dimensi, status bar, tombol Hitung/Submit |
| `CostingPartRepeater` | `costing_part` | master-detail part (add/remove), collapse per part |
| `CoatingGrid` (nested) | `costing_part_coating` | grid layer coating (add/remove) |
| `PriceTierPanel` | `costing_price_tier` | tabel tier + panel margin/toleransi + ladder harga |
| `EngineRunner` (action) | service | tombol Hitung → `CostingEngine::run()` → refresh `c_*` |

## 4.2 Binding block → visible_type (dari `map_hpp_parts`)
Gunakan `nama_block` + `visible_type` untuk show/hide per `part_type` (strategi block-level, homogen):
- Block **PAPER** (duplex/ivory) → visible: DC, DC+SF, DC+SHEET, KRAFT.
- Block **SHEET** (index, trim, print, diecut…) → visible: SHEET, SHEET_DIGITAL.
- Block **GEO_SHEET_DIGITAL** (dim, lidah, flute, tier) → visible: SHEET_DIGITAL.
- Block **LAMINASI/SF_EF** → visible: DC+SF, DC+SHEET (+ manual bila `pakai_laminasi`).
- Block **TOOLING** (film/plate/pisau) → hidden untuk SHEET/SHEET_DIGITAL (TOOL_SKIP).
- Block **COATING** → hidden untuk SHEET/SHEET_DIGITAL.

## 4.3 Layar harga (PriceTierPanel) — ladder
Tampilkan bucket (Material / Proses / Finishing) per part → HPP/pc → panel parameter
(Toleransi %, Rounding mode+step, Margin %, PPN include/exclude, Currency) → **ladder 5 baris** (§0)
→ `price_per_pcs`. Snapshot saat Submit/publish. **Sisi B:** sembunyikan bucket & ladder,
tampilkan hanya `price_per_pcs` bila `published_flag=1`.

## 4.4 Binding table (per-screen, contoh header — pola lengkapi dari `map_hpp_parts`)
| Field layar | Kolom | Sumber default | Visible |
|---|---|---|---|
| Tipe Kalkulasi | `costing.calc_type` | m_hpp_calc_type | selalu |
| Qty Order | `inquiry_qty_tier.qty_pcs` | inquiry | selalu |
| Nama Part | `costing_part.part_name` | ketik → template match | selalu |
| Tipe Part | `costing_part.part_type` | m_part_type | selalu |
| Gramatur | `costing_part.gramatur_id` | m_part_template | block PAPER |
| Index Substance | via `sheet_rate_id` | v1_sheet_rate | block SHEET |
| HPP/pc | `costing_price_tier.c_hpp_pc` | engine | Sisi A |
| Harga jual/pc | `costing_price_tier.price_per_pcs` | engine | Sisi A + B(published) |

---

## LAMPIRAN — CHECKLIST VERIFIKASI (sebelum implementasi)
- [ ] **VERIF01** kolom `label` vs `display_label` di `m_sheet_rate`.
- [ ] **DATA01** koreksi `m_sheet_rate` grammage M150 (ditunda pasca COCO TOKYO).
- [ ] Regresi: `SHEET` murni `c_sheet_cost_pc` byte-identik (OB_A1=3260.31, COCO=3536.04).
- [ ] Parameterisasi `supplier_code` (kini hardcode 'UJK' di tier query).
- [ ] Port `CostingEngine` dari `calc_cogs` — pertahankan urutan 10 sesi & kamus flag.
- [ ] Trigger cross-DB `costing.is_confirmed 0→1` → generate `m_card`/`m_card_parts` (NEW),
      update `m_card_material`/`_process` + `mcard_rev` (REVISE); idempotency `mcard_id IS NULL`.
