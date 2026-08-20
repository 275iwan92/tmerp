# TIER 2 — INQUIRY · BUSINESS LOGIC & RULES
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 2 — Inquiry · Business Logic & Rules |
| **Versi** | 3.0 (restrukturisasi 3 bagian: Alur · Special Case · State/Form/Button) |
| **Basis** | Tier 1 v2.1 (alur, aturan, relasi §3.4, fitur F1–F21) |
| **Pembaca** | Sistem Analis · Developer (Backend/Frontend) · DBA |
| **Goal** | Menerjemahkan Tier 1 jadi logika presisi, disusun agar mudah diikuti: baca **Bagian A** untuk alur normal, **Bagian B** saat ketemu kasus khusus, **Bagian C** saat membangun UI/state. |
| **Baca juga** | [FORECAST_ENGINE_spec.md](FORECAST_ENGINE_spec.md) |

### Cara membaca dokumen ini
```
BAGIAN A — LOGIKA ALUR (happy path)      → "apa yang terjadi pada order normal"
BAGIAN B — SPECIAL CASE (kondisional)    → "kasus yang tidak selalu muncul" (+ dipakai di layar apa)
BAGIAN C — STATE, FORM & BUTTON (UI/UX)  → "bagaimana form & tombol berperilaku"
```

---

## SECTION 2 — SCOPE LOGIKA

| Bagian | Area | Isi |
|---|---|---|
| **A** | Alur | state backbone, alur utama inquiry, kalkulasi HPP normal (PRODUCT+PPN), publish gate, convert to quotation |
| **B** | Special Case | jasa/bahan customer, tooling aus, PPh/non-PPN, diskon, biaya eksternal, estimasi>due-date, versioning REVISE/RECALC, dieline DSL |
| **C** | State/Form/Button | form actions, state+sub-state, logging, pemetaan button↔state |
| — | Report | analisa Board of Management |

---
---

# ══════════════════════════════════════════════════════
# BAGIAN A — LOGIKA ALUR (HAPPY PATH)
# ══════════════════════════════════════════════════════

## SECTION 3 — ALUR UTAMA INQUIRY

### 3.1 State Backbone (ringkas — detail di Bagian C §9)
```text
DRAFT (stat_confirm=0) ──confirm──► CONFIRMED (stat_confirm=1) ──approval──► DONE (approved)
   │ delete                              │ cancel
   ▼                                     ▼
 (hapus)                            CANCELLED (stat_cancel=1)
```

### 3.2 Alur Normal (produk, bahan sendiri, PPN)

```text
[Sales] Buat inkuiri (DRAFT)                          → layar: inquiry_new
   → isi produk, tipe, dimensi (dieline live), tier qty (1 UTAMA)
   → Confirm (stat_confirm=1) → SUBMITTED
[Costing] Hitung HPP per tier                         → layar: inquiry_costing_pricing
   → Material + Process + Tooling + Coating → margin → PPN
   → status: CALCULATED → PENDING_APPROVAL
[Direksi] Approve = PUBLISH                            → deklasifikasi harga ke Sisi B
   → status costing: PUBLISHED ; inquiry: PRICE_APPROVED
[Sales] Convert to Quotation                          → layar: inquiry → inquiry_quotation
   → inquiry jadi 1 baris di quotation (1 quotation : N inquiry)
[Sales] Kirim quotation + nota                        → QUOTED
[Customer] Setuju harga
[Design/Sales] Prove warna (v1..v3)                   → layar: inquiry_proof
   → approval FISIK (tanda tangan basah) → PROVE_APPROVED
   → ═ due-date = +14 hari kerja dari tanggal fisik ═
[Customer] Kirim PO → CONVERTED → Customer Order (Stage 2)
```

### 3.3 Kalkulasi HPP — Jalur Normal (PRODUCT + PPN)

> Cabang JASA/bahan customer → Bagian B §5. PPh/non-PPN → Bagian B §7.

```text
Σ Material + Σ Process + Σ Tooling + Σ Coating          → hpp/pc
  × (1 + tolerance%)  → rounding  → ÷ (1 − margin%)      → after_margin (excl)
  − diskon item (bila ada)                               → net (excl PPN)
  × (1 + PPN 11%)                                        → incl (display)
```
```pseudo
FUNCTION calc_hpp_normal(costing_id):
    c ← costing[id]
    hpp ← Σmaterial + Σprocess + Σtooling + Σcoating
    IF hpp<=0: FAIL
    after ← apply_rounding(hpp*(1+c.tolerance/100), c.rounding) / (1 - c.margin/100)
    net  ← after - item_discount(c)
    incl ← net * 1.11                     # PPN normal
    UPDATE costing SET ..., doc_status='CALCULATED'
```
- Margin boleh beda antar tier. Saat PUBLISHED semua parameter di-freeze (snapshot audit).

### 3.4 Publish Gate & Convert to Quotation

**Publish gate:** Sisi A (costing rahasia) → Sisi B (harga) hanya via `doc_status=PUBLISHED` (approve Direksi).

**Convert to Quotation** — tombol di **tiap layar inquiry** (new/recalc/revise):
```pseudo
FUNCTION convert_to_quotation(inquiry_id):
    # selalu aktif; 1 quotation bisa memuat BANYAK inquiry (many-to-one)
    q ← pilih: quotation baru  ATAU  quotation draft existing (customer & lokasi sama)
    INSERT quotation_line { quotation_id:q.id, inquiry_id:inquiry_id,
                            qty, harga (snapshot dari tier UTAMA), disc_item }
    # inquiry → 1 baris quotation. Konsolidasi multi-item terjadi di quotation.
    # Quotation → CO one-to-one (Tier 1 §3.4)
```
> **Perilaku tombol (keputusan):** selalu aktif. Bila harga belum published, quotation tetap bisa dibuat sbg draft, tapi baris harga menunggu publish. Konsolidasi banyak inquiry ke satu quotation = inti many-to-one.
>
> **📌 Status implementasi (2026-08-20):** dibangun dgn gate LEBIH KETAT dari spec di atas —
> `App\Services\Sales\QuotationBuilder::addInquiry()` menolak (return null, tak ada baris
> ditambah) kalau costing belum `published_flag=1`, bukan "selalu aktif + baris nunggu
> publish" seperti pseudo-code ini. Tombolnya juga ditaruh di layar Costing (bukan "tiap
> layar inquiry"). Lihat `SALES_STATE.yaml → deviasi_dari_tier1_locked #2, #4`.

---
---

# ══════════════════════════════════════════════════════
# BAGIAN B — SPECIAL CASE (KONDISIONAL)
# ══════════════════════════════════════════════════════

> Tiap special case menyebut **layar tempat ia muncul**. Tidak selalu terjadi; hanya pada kondisi tertentu.

## SECTION 4 — JASA & BAHAN CUSTOMER
**Dipakai di layar:** `inquiry_new`, `inquiry_revise` (panel Sifat Order & Bahan Customer).
**Kondisi:** `material_source = CUSTOMER` → `job_nature = JASA`.
```pseudo
IF job_nature='JASA':
    material_cost ← 0                              # bahan disediakan customer
    handling ← compute_handling_fee(basis, process)
    spoilage ← process * waste_allowance%/100
    hpp ← process + tooling + coating + handling + spoilage   # tanpa material
# bahan customer masuk via Receiving non-costed (consignment, bukan aset)
```
→ CO menjadi **CO JASA**. Kena PPh 23 (lihat §7).

## SECTION 5 — TOOLING LIFECYCLE
**Dipakai di layar:** `inquiry_new` (semua BARU), `inquiry_revise` (pisau baru bila ukuran berubah), `inquiry_recalc` (cek aus repeat order).
**Kondisi:** repeat order / ukuran berubah / tooling aus.
```pseudo
fn_tooling_status(id):
    IF null: 'TIDAK_ADA' ; IF impr>=max: 'AUS'
    IF impr>=max*0.85: 'ADA_WARNING' ; ELSE 'ADA'

FOR t IN {PLATE, KNIFE, FILM}:
    IF status IN ('AUS','TIDAK_ADA'):
        add_line_tooling(t, rate)          # LINE TERPISAH, tak masuk harga/pc
sp_add_impressions(): counter += n (per lembar plano, dari produksi)
sp_rebuild_tooling(): reset counter=0, status='ADA'
```

## SECTION 6 — IMPOSISI & ROUTING (variasi finishing)
**Dipakai di layar:** `inquiry_imposition`.
**Kondisi:** selalu ada, tapi urutan **bervariasi** per layer finishing.
- Routing standar (Lampiran A) = ACUAN awal; engine menyarankan, Product Designer menyesuaikan.
- Guard urutan wajib: COATING sebelum LAMINASI · PLONG butuh PISAU · CETAK butuh PLATE.
- Coating multi-layer & kombinasi laminasi menggeser/menambah step.

## SECTION 7 — PAJAK KHUSUS (PPh 23 & non-PPN)
**Dipakai di layar:** `inquiry_new`, `inquiry_revise`, `inquiry_recalc` (panel Pajak); final di CO.
**Kondisi:** PPh → hanya komponen JASA; non-PPN → skema tertentu.
```text
PPN (normal, Bagian A) → MENAMBAH tagihan
PPh 23 (jasa)          → MENGURANGI kas (dipotong customer, 2% atas DPP jasa)
non-PPN                → include harga, TIDAK masuk DPP PPN (tanpa field alasan)

urutan dokumen (CO/invoice):
  subtotal − diskon item − diskon dokumen + biaya eksternal = DPP
  DPP + PPN = total tagihan
  total tagihan − PPh23(jasa) = net diterima (proyeksi; realisasi saat bayar)
```

## SECTION 8 — DISKON, BIAYA EKSTERNAL, ESTIMASI

### 8.1 Diskon (item · amount · qty)
**Dipakai di layar:** tier table (item), panel Diskon Dokumen (amount), rule (qty).
```pseudo
disc_item   : per baris tier, fix di inkuiri
disc_amount : dokumen, fix di inkuiri, dibawa ke CO
disc_qty    : occasional — aktif bila Σtonase ≥ ambang (m_discount_qty_rule)
```

### 8.2 Biaya Eksternal
**Dipakai di layar:** `inquiry_new`, `inquiry_revise` (card Biaya Eksternal); dikelola di CO.
- Trucking/ekspedisi/jasa luar yang sudah pasti → dicatat sejak inkuiri → masuk DPP (kena PPN).

### 8.3 Estimasi Kecepatan & Due-date
**Dipakai di layar:** `inquiry_new`, `inquiry_revise` (card Estimasi).
```pseudo
estimate ← historical_speed(similar) × qty     # similar: tipe+finishing+luas+layout+gramatur mirip
estimate ← override(role IN {COSTING,DESIGN,SALES}, reason)   # tercatat
IF estimate > due_date:
    mark_special_color(inquiry)                # aplikasi TAK blok
    require_verification(OPS_MANAGER, PPIC)    # non-aplikasi (verbal/meeting)
IF no_history: estimate WAJIB manual (tak boleh kosong)
```

## SECTION 9 — VERSIONING REVISE / RECALC
**Dipakai di layar:** `inquiry_revise`, `inquiry_recalc`.
**Kondisi:** order dari master product existing.
```pseudo
load_from_master(product_id, mode):
    deep_copy(m_product → m_product_parts → _material(per layer) + _process(branch/flow)
              + m_product_type(dieline_spec, param, size))
    IF mode=RECALC: lock(parts, dims, process); allow(substitute_equivalent_material)
    IF mode=REVISE: allow(parts, material, process)   # spec boleh berubah
    parent_product_id ← product_id ; revision_no++
```
| Sumber | REVISE | RECALC |
|---|:--:|:--:|
| m_product_parts | editable | locked |
| _material (layer) | editable | substitusi setara |
| _process (branch) | editable | locked |
| costing | recalc | recalc (fokus) |
- REVISE artwork → prove ulang, due-date reset. RECALC → prove tidak ulang, due-date tetap.
- Costing → `i_inquiry_costing` (MTO). Harga versi lama immutable.

## SECTION 10 — DIELINE ENGINE (DSL)
**Dipakai di layar:** semua layar dengan preview dieline (`inquiry_new/revise`, `inquiry_imposition`).
Renderer berbasis DATA (`TMDieline`): dieline_spec = `{canvas, flat, vars, elements[]}` di `m_product_type`.
```text
1. Expression evaluator: tokenize→RPN→eval (max/min/sqrt/round, PI)
2. Scope: dimensi + alias (A=length,B=width,C=height,G=glue,TT=top_tuck…) + vars
3. Primitif: line/rect/circle/tuck/dust/glue/bleed/foldlines/holes/text (k=CUT/CREASE/BLEED/HOLE)
4. flat{w,h} → bentangan & luas ke COSTING (satu sumber kebenaran shape↔harga)
```
- Tipe box baru = tambah data DSL, tanpa ubah kode renderer. Roadmap: flat→3D (Pacdora).

---
---

# ══════════════════════════════════════════════════════
# BAGIAN C — STATE, FORM & BUTTON (UI/UX)
# ══════════════════════════════════════════════════════

> Berlaku untuk **semua form** di sistem (kontrak universal), bukan hanya inquiry.

## SECTION 11 — FORM STANDARD & STATE

### 11.1 Aksi Form Standard
| Aksi | Fungsi | Tersedia |
|---|---|---|
| Add New | buat record (DRAFT) | semua form |
| Edit/Save | ubah (hanya DRAFT) | semua form |
| Delete | hapus (HANYA DRAFT) | semua form |
| Confirm | kunci → CONFIRMED (stat_confirm=1) | semua form |
| Cancel | batalkan → CANCELLED (stat_cancel=1, hanya bila confirmed) | semua form |
| Revise Doc | buat versi/revisi | modul tertentu (inquiry, quotation) |

### 11.2 State & Field Penanda
| State | Penanda | Tombol tampil | Editable |
|---|---|---|---|
| DRAFT | stat_confirm=0 | Edit·Save·Delete·Confirm | ✓ read-write |
| CONFIRMED | stat_confirm=1 | **Cancel** (Save/Edit/Delete ditiadakan) | ✗ locked |
| CANCELLED | stat_confirm=1 + stat_cancel=1 | — | ✗ |
| DONE | approved (stat_approved=1) | — | ✗ APPROVED |

**Aturan kunci:** `stat_cancel=1` HANYA bila `stat_confirm=1`. Delete milik DRAFT, Cancel milik CONFIRMED. DONE = hasil approval.

### 11.3 Logging Universal (wajib tiap form)
```pseudo
add_new : create_log=NOW; create_by=user; stat_confirm=0
save    : REQUIRE stat_confirm=0 ; update_log=NOW; update_by=user
delete  : REQUIRE stat_confirm=0
confirm : REQUIRE stat_confirm=0 & valid ; stat_confirm=1; confirm_log=NOW
cancel  : REQUIRE stat_confirm=1 ; stat_cancel=1; cancel_log=NOW
approve : REQUIRE stat_confirm=1 & role ; stat_approved=1; approve_log=NOW → DONE
```

## SECTION 12 — SUB-STATE MONITORING (Board of Management)

Backbone (§11) = teknis. Sub-state = tahap bisnis untuk monitoring manajemen.

**Sub-state INQUIRY** (dari `inquiry_hub` + rancangan):
| Sub-state | Backbone | Sumber |
|---|---|---|
| DRAFT | DRAFT | ✅ html |
| WAITING_COST · SUBMITTED | CONFIRMED | ✅ html |
| PRICE_CALCULATED · PRICE_APPROVED · QUOTED | CONFIRMED | 🆕 |
| PROVING · PROVE_APPROVED | (→DONE) | 🆕 |
| CUSTOMER_APPROVED / CONVERTED | DONE | 🆕 |
| CANCELLED / LOST | CANCELLED | 🆕 |

**Sub-state COSTING** (dari `product_costing`): DRAFT · CALCULATED · PENDING_APPROVAL · PUBLISHED · FAILED (semua ✅ html).

`inquiry_state_log`: inquiry_id · from_state · to_state · changed_at · changed_by · duration → sumber report (§14).

## SECTION 13 — PEMETAAN BUTTON ↔ STATE (per layar)

| Layar | DRAFT | CONFIRMED | DONE |
|---|---|---|---|
| inquiry_new/revise/recalc | Save·Confirm·Delete | Cancel · **Convert to Quotation** | — |
| inquiry_costing_pricing | Save·Calculate·Submit | (Direksi) Approve/Reject | Published |
| inquiry_quotation | Save·Confirm·add-inquiry-line | Cancel·Send | — |
| inquiry_proof | Add version·Save | Approve (fisik) | — |

**Convert to Quotation:** tombol **selalu aktif** di layar inquiry; membuka pilihan quotation baru / gabung ke draft existing (1 quotation : N inquiry).

---
---

## SECTION 14 — REPORT UNTUK BOARD OF MANAGEMENT

Dari `inquiry_state_log` + data transaksi, otomatis:
| Report | Isi | Untuk |
|---|---|---|
| Pipeline Inquiry | jumlah per state, nilai potensi | GM, Direksi |
| Cycle Time | lama per tahap (bottleneck) | Ops Manager |
| Conversion Rate | % jadi CO; % LOST + alasan | Sales Manager |
| Costing Turnaround | lama HPP dihitung & approve | Ops |
| Prove Delay | tertahan di PROVING, sebab | Ops, Sales |
| Estimasi > Due-date | order warna khusus, status verifikasi | Ops, PPIC |
| Win/Loss by segment | konversi per demografis | Sales Manager, Direksi |

Otomatis dari data operasional (tanpa input manual), actionable (tunjuk bottleneck).

---

## SECTION 15 — NOTICE
- Blocker aktif: Tier 1 §6.2 (B1,B2,B3,B4,B6,B9).
- **T2-B1:** granularitas sub-state (perlu "menunggu PO" terpisah?).
- **T2-B2:** retensi `inquiry_state_log` & agregasi historis.
- **T2-B3:** hak akses report BoM (Sales Manager vs Direksi beda kedalaman?).
- **T2-B4:** dualisme `i_inquiry_part` vs `i_inquiry_design*` — ke mana material-layer & process-branch ditulis? (klarifikasi sebelum Tier 3).
- DDL semua ini → Tier 3.

---
> **Peta bagian:** A (§3–4) alur · B (§4–10) special case per layar · C (§11–13) state/form/button · §14 report · §15 notice.
