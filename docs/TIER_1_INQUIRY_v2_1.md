# TIER 1 — INQUIRY · SYSTEM FLOW & PROCESS MATRIX
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## SECTION 1 — IDENTITAS DOKUMEN

| | |
|---|---|
| **Dokumen** | Tier 1 — Inquiry · System Flow & Process |
| **Versi** | 2.1 |
| **Riwayat** | v1.0 alur inti Stage 1 · v2.0 integrasi case (jasa, bahan customer, tooling, pajak, diskon, CO multi-item) · **v2.1 restrukturisasi 7 section** |
| **Status** | AKTIF — acuan alur untuk Tier 2/3/4 |
| **Pembaca** | Sales · Officer · Manager · Sistem Analis |
| **Aktor** | Sales · Product Design · Costing Officer · Direksi · Customer/Prospek |
| **Goal** | Menetapkan alur & aturan proses Inquiry dari permintaan customer sampai terbit PO/CO — termasuk penetapan harga rahasia (costing), sifat pekerjaan (produksi penuh vs jasa), pajak/diskon, dan penyiapan item untuk Customer Order |
| **Dua dokumen inti** | INKUIRI (IQ, Sales) → HPP (Costing) |
| **📌 Baca juga (perhatian khusus)** | **[FORECAST_ENGINE_spec.md](FORECAST_ENGINE_spec.md)** — Demand & Material Forecast Engine (persiapan bahan baku & product forecast MTS berbasis waktu/season/demografis). Relevan untuk perencanaan setelah ≥1 tahun data. |
| **Output** | Item ter-approve → CUSTOMER ORDER → Stage 2 |

---

## SECTION 2 — SCOPE & RUANG LINGKUP

### 2.1 Modul yang dicakup
Modul **Inquiry** (Stage 1) — titik lahir seluruh order. Menjadi sumber kebenaran untuk spesifikasi, harga, sifat pekerjaan, dan syarat komersial yang diwariskan ke Customer Order, Produksi, dan Finance.

### 2.2 Fitur di dalam scope

> **Kolom Status:** ✅ konsep matang di HTML lama · 🆕 rancangan baru.
> **Kolom di Mockup:** status implementasi di file HTML `inquiry_new.html` per update terakhir — ✔ sudah ada card/field · ◑ sebagian · ✗ belum.

| # | Fitur | Status | Di Mockup (new) | Ringkas |
|---|---|:--:|:--:|---|
| F1 | Inkuiri tiga tipe | ✅ | ✔ | NEW · RECALC · REVISE (3 file terpisah) |
| F2 | Tier qty berjenjang | ✅ | ✔ | multi-tier, 1 UTAMA wajib (+ kolom diskon item) |
| F3 | HPP per tier (costing) | ✅ | ✔ (di costing) | rahasia; ladder HPP→margin→PPN |
| F4 | Sifat pekerjaan (job_nature) | 🆕 | ✔ | PRODUCT · JASA (turunan material_source) |
| F5 | Sumber bahan (material_source) | 🆕 | ✔ | OWN→CO PRODUCT · CUSTOMER→CO JASA (+panel bahan customer) |
| F6 | Tooling lifecycle | 🆕 | ✔ | card status plat/pisau/film + biaya baru (line) |
| F7 | Imposisi & routing | 🆕 | ◑ | dieline ✔ (TMDieline); routing detail ✗ (di imposition) |
| F8 | Prove warna | 🆕 | ✗ | belum jadi card di inquiry; ada di layar proof terpisah |
| F9 | Pajak (PPN/PPh) | 🆕 | ✔ | skema PPN/non-PPN + PPh 23 (jasa) |
| F10 | Diskon | 🆕 | ✔ | item (tier) + amount (dokumen) + qty note |
| F11 | Quotation + Nota | ✅ | ◑ | nota ✔; quotation perlu upgrade multi-item |
| F12 | Penyiapan item CO | 🆕 | ◑ | di layar CO (bukan inquiry); tombol convert ✗ |
| F13 | Biaya eksternal | 🆕 | ✔ | card tabel trucking/ekspedisi (add/delete) |
| F14 | Relasi dokumen (FK) | 🆕 | ✔ | comment header + §3.4 |
| F15 | Estimasi kecepatan | 🆕 | ✔ | card kecepatan + override + status vs due-date |
| F16 | Preview dieline (DSL) | ✅ | ✔ | TMDieline render dari dieline_spec; flat→costing |
| F17 | Ukuran Pasaran + Galeri | ✅ | ✔ | auto-fill dimensi dari m_product_type |
| F18 | Multi-part | ✅ | ✔ | Body/Lid/Partisi, tiap part punya dieline & bahan |
| F19 | Template Dieline | ✅ | ◑ | slot m_dieline_template (kosong, menyusul) |
| F20 | Form standard & state | 🆕 | ✔ | Add/Save/Confirm/Cancel + state DRAFT→CONFIRMED |
| F21 | Convert to Quotation | 🆕 | ✗ | tombol di tiap layar inquiry → buat quotation (usulan) |

**Ringkasan status mockup `inquiry_new.html`:** sebagian besar fitur (F1,F2,F4,F5,F6,F9,F10,F13,F15,F16,F17,F18,F20) **sudah ada card/field**. Yang **belum**: F8 (prove — ada di layar terpisah), F21 (tombol convert to quotation). F7 & F11 & F12 & F19 sebagian (◑).
**REVISE:** F4,F5,F6,F9,F10,F13,F15,F20 ✔. **RECALC:** F6,F9,F10 ✔ (spec locked, F4/F5/F13/F15 tak relevan).

### 2.3 Di luar scope (dokumen lain)
Customer Order detail (Stage 2), Produksi/SPK, Dispatch/DO, Finance/Invoice, Inventory/Receiving — hanya disinggung sebagai tujuan warisan data, dibahas di stage/tier masing-masing.

### 2.4 Konvensi Penomoran Dokumen (selaras lintas stage)

Nomor dokumen mengikuti pola tunggal agar **nyambung dari Inquiry sampai CO** dan mudah dilacak:

```text
[PREFIX][YY][NNNNN]
  PREFIX = kode dokumen (2 huruf)
  YY     = 2 digit tahun
  NNNNN  = 5 digit counter, RESET setiap tahun
```

| Dokumen | Prefix | Contoh | Keterangan |
|---|---|---|---|
| Inquiry | `IQ` | `IQ2600001` | counter reset tiap tahun |
| Quotation | `QT` | `QT2600001` | — |
| Customer Order | `CO` | `CO2600001` | pola acuan (sudah berjalan) |

**Aturan:**
- Counter per dokumen berjalan sendiri, reset 1 Januari tiap tahun.
- Format konsisten di semua stage → memudahkan pelacakan silang (Inquiry → Quotation → CO).
- Nomor bersifat immutable setelah terbit; versi/revisi dokumen dilacak lewat `revision_no`, bukan mengubah nomor.

### 2.5 Klasifikasi Data (CRITICAL — dasar seluruh visibilitas)
**SISI A — COSTING ROOM** *(Costing Officer + Direksi SAJA)*: seluruh HPP, bucket Material/Process/Tooling/Coating, margin per tier, toleransi, PPN mode, rounding, tooling/bahan, handling fee, spoilage, calc-trace, per-part = **RAHASIA DAGANG**.
`─── GARIS DEKLASIFIKASI: hanya Direksi menembus via APPROVE ───`
**SISI B — SALES FLOOR** *(Sales + Operational)*: HANYA `qty | harga/pc excl | harga/pc incl | valid until` + penanda komersial ringkas. Sebelum approve = **BUTA harga**.

---

## SECTION 3 — ALUR PROSES & ALUR INFORMASI

### 3.1 Dua sisi costing (aliran informasi)
- **Sisi #1 MAKE-TO-ORDER** → dari INKUIRI Sales, untuk 1 customer
- **Sisi #2 MAKE-TO-STOCK** → forecast produk stok, tanpa Sales/customer
- Keduanya → **APPROVAL DIREKSI wajib** sebelum harga sah.

### 3.2 Alur proses utama (make-to-order)

```text
Sales terbit INKUIRI (+ tier qty, pilih UTAMA)
  → set job_nature (PRODUCT / JASA) — turunan otomatis dari material_source
  → set material_source (OWN / CUSTOMER); bila CUSTOMER → spec bahan + ETA kirim WAJIB
  → set tax_scheme (PPN/NON_PPN + alasan) + pph_applicable (jasa→1)
  → set diskon item (opsional, FIX di sini)
  → Pemohon PROSPEK / CUSTOMER existing?
  → Jenis: BARU / RECALC / REVISE
  → [Design] Imposisi (BARU & REVISE wajib; RECALC reuse layout)
  → [Design] Cek TOOLING (plat/pisau/film) di m_tooling_asset
       ADA → cost 0 (repeat) · AUS/TIDAK_ADA → LINE tooling baru (biaya sekali)
  → [Costing] HPP per TIER
       FULL   → Material + Process + Tooling + Coating → HPP/pc
       JASA_* → (Material=0) + Process + Tooling + Coating + Handling + Spoilage → HPP/pc
       → +toleransi% → rounding → ÷(1−margin%) → excl → (−diskon item) → PPN → incl
       → published_flag=0 (Sales belum lihat)
  → APPROVAL DIREKSI = EVENT PUBLISH → published_flag=1 → tier ke Sisi B
  → [Sales] Quotation (tier ter-publish; qty UTAMA; + diskon, PPN/non-PPN, PPh info, line tooling)
  → QUOTATION + NOTA INQUIRY (sepasang) → kirim (Email/Print/WA, tercatat)
  → CUSTOMER SETUJU HARGA?
       Tidak → ├─ ubah bahan/size/qty → VERSI BARU (RECALC/REVISE)
               └─ harga FIX (Direksi kunci) → terima? Tidak→LOST · Ya→lanjut
       Ya → lanjut
  → PROVE (warna/diecut) — v1,v2,v3 (mockup = bukti presisi, tak memulai jam)
  → APPROVAL customer (prove FISIK, tanda tangan basah → arsip; entry ke sistem)
       ═══ MULAI 14 HARI KERJA dari TANGGAL APPROVAL FISIK ═══
  → PO customer masuk → Prospek→Customer (term & limit)
  → OUTPUT: item → CUSTOMER ORDER (1 CO gabung banyak item; customer & lokasi sama)
```

### 3.3 Alur informasi (siapa lihat apa)
| Tahap | Sisi A (Costing/Direksi) | Sisi B (Sales/Ops) |
|---|---|---|
| Inkuiri dibuat | lihat sifat & spec | lihat sifat & spec (bukan harga) |
| HPP dihitung | lihat penuh | **buta harga** |
| Direksi approve | lihat penuh | tier harga terbuka |
| Quotation | lihat penuh | lihat tier + komersial |

### 3.4 RELASI DOKUMEN — INQUIRY → QUOTATION → CO (SECTION KHUSUS)

> Section ini menetapkan rantai relasi antar-dokumen secara eksplisit agar **Tier 3 mudah menjabarkan FK & kardinalitas**. Ini fondasi integritas data lintas stage.

#### 3.4.1 Rantai & kardinalitas

```text
   INQUIRY (N) ────< QUOTATION (1) ────1:1──── CUSTOMER ORDER (1)
        │                  │                          │
  produk/spec/harga   konsolidasi           eksekusi order
  per inquiry         multi-item            (delivery, pajak dok)

  Inquiry → Quotation : MANY-TO-ONE   (beberapa inquiry product = baris quotation)
  Quotation → CO      : ONE-TO-ONE    (1 quotation → tepat 1 CO)
  Prove → Inquiry     : ONE-TO-ONE    (1 proof PDF final per inquiry)
```

**Titik konsolidasi multi-item = QUOTATION** (bukan CO). Beberapa inquiry product digabung menjadi baris-baris quotation. CO kemudian 1:1 mengeksekusi quotation tersebut.

#### 3.4.2 Tabel relasi (acuan Tier 3)

| Relasi | Kardinalitas | FK penghubung | Keterangan |
|---|---|---|---|
| Inquiry → Quotation line | 1 inquiry : 1 quotation-line | `quotation_line.inquiry_id` | tiap inquiry product = 1 baris quotation |
| Quotation → Inquiry (agregat) | 1 quotation : N inquiry | (via quotation_line) | 1 quotation gabung banyak inquiry |
| Quotation → CO | 1 : 1 | `co.quotation_id` (UNIQUE) | satu quotation jadi satu CO |
| CO → Quotation line → item | 1 : N | (turun dari quotation_line) | item CO = baris quotation |
| Prove → Inquiry | 1 : 1 | `prove.inquiry_id` (UNIQUE) | proof PDF melekat per inquiry |
| Inquiry ← CO (jejak balik) | — | `inquiry.co_id` (via quotation) | inquiry tahu masuk CO mana |

#### 3.4.3 Aturan relasi (locked)

1. **Konsolidasi di quotation:** beberapa inquiry product (customer & lokasi kirim sama) digabung jadi baris-baris **satu quotation**.
2. **Quotation → CO one-to-one:** satu quotation menghasilkan tepat satu CO; `co.quotation_id` UNIQUE.
3. **Item CO = baris quotation** (yang berasal dari inquiry). Nilai qty/harga/diskon item = snapshot, fix.
4. **Prove one-to-one ke inquiry:** tiap inquiry punya tepat satu proof final (PDF). Repeat order = inquiry baru → proof baru.
5. **Penelusuran lewat FK**, bukan pencocokan nomor. Nomor selaras (§2.4) hanya untuk keterbacaan manusia.
6. **Guard gabung:** inquiry hanya bisa masuk quotation bila customer & lokasi kirim sama dengan baris lain.

#### 3.4.4 Contoh alur nomor

```text
IQ2600001 (Teh Celup)  ─┐
IQ2600002 (Box Snack)  ─┼─► QT2600007 ──1:1──► CO2600011
IQ2600003 (Sleeve)     ─┘   (3 baris)          (3 item)
   3 inquiry              1 quotation          1 customer order
   3 proof (1:1)          konsolidasi          eksekusi
```

---

## SECTION 4 — CRITICAL POINT & ATURAN PENDUKUNG

### 4.1 Critical point alur
- **CP1 — Deklasifikasi harga:** hanya event PUBLISH (approve Direksi) membuka harga ke Sisi B. Sebelum itu Sales buta harga.
- **CP2 — Mulai jam produksi:** 14 hari kerja dihitung dari **tanggal approval prove FISIK** (tanda tangan basah), bukan tanggal entry/PO/mockup.
- **CP3 — Sumber bahan menentukan sifat CO & struktur biaya:** OWN → CO PRODUCT (material penuh) · CUSTOMER → CO JASA (material=0 + handling/spoilage + PPh 23). Salah set = sifat order, harga, dan pajak salah sejak awal.
- **CP4 — Tooling aus = biaya baru:** repeat order dengan plat/pisau/film aus memunculkan line tooling terpisah — tidak boleh disembunyikan di harga/pc.
- **CP5 — Item fix di inkuiri:** qty/harga/diskon item ditetapkan di inkuiri; Customer Order tak boleh mengubahnya.
- **CP6 — Estimasi kecepatan vs janji kirim:** estimasi produksi (dari histori) memberi Sales dasar janji tanggal. Guard: estimasi ≤ due-date; bila estimasi melebihi janji 14 HK → red flag sejak inquiry, bukan pas produksi.

### 4.2 Aturan umum (locked)
1. Harga **BERVERSI** — hitung ulang = versi baru, harga lama utuh
2. RECALC = harga ulang (spec sama) · REVISE = ubah spec (imposisi ulang)
3. Semua harga wajib approval Direksi (MTO & MTS)
4. Approval Direksi = deklasifikasi (publish tier ke Sisi B)
5. Tier qty = input Sales; tepat 1 UTAMA; qty minim tampil
6. Margin boleh beda per tier; snapshot param tiap versi WAJIB (audit)
7. Due date 14 HK dari approval prove FISIK
8. Prove warna WAJIB; mockup opsional
9. Prove menggantung → follow-up wajib (sebab molor tercatat)
10. Quotation & Nota Inquiry sepasang seversi
11. Prospek → Customer resmi (term & limit) sebelum PO diproses
12. **job_nature** ditetapkan di inkuiri, konsisten sepanjang dokumen
13. **material_source menentukan sifat CO:** OWN → **CO PRODUCT** (jual barang) · CUSTOMER → **CO JASA** (jual jasa). CUSTOMER ⇒ HPP material=0, handling+spoilage wajib, bahan via Receiving non-costed (consignment), komponen jasa kena PPh 23
14. **Tooling ber-umur**; order pertama=pembuatan, repeat=hanya bila aus; biaya tooling baru selalu line terpisah
15. **Pajak per item:** PPN/non-PPN; PPh 23 hanya atas jasa (dipotong customer); **non-PPN dianggap include dalam harga** (tanpa field alasan)
16. **Diskon:** item (fix inkuiri) · amount dokumen (fix inkuiri) · qty (occasional, aktif bila lewat ambang tonase). Semua kurangi DPP
17. **Item CO fix dari inkuiri** — CO hanya delivery + pajak/diskon dokumen
18. **CO multi-item:** 1 CO = 1 customer + 1 lokasi + 1 PO; completion & invoice per-CO; DO tarik per-item
19. **Relasi terlacak (FK) — lihat §3.4:** Inquiry→Quotation many-to-one (konsolidasi multi-item di quotation), Quotation→CO one-to-one, Prove→Inquiry one-to-one. Penelusuran lewat FK, bukan pencocokan nomor manual
20. **Estimasi kecepatan produksi** dari histori produk sejenis (per pcs/lembar); overridable oleh Costing/Design/Sales (tercatat); jadi dasar Sales janji kirim. Guard: estimasi ≤ due-date (bila lebih = red flag sejak inquiry)

---

## SECTION 5 — PEMBAHASAN DETAIL & KHUSUS

### 5.1 Dokumen INKUIRI (ditulis Sales)
**Header dasar:** `no_inkuiri`(auto, format `IQ[YY][NNNNN]` — lihat §2.4) · tanggal · sumber · PIC Sales · customer/calon · prospek? · `deadline_quotation`(min +14 HK) · `nama_produk_draft`(tanpa karakter khusus) · catatan_sales
**Header komersial (v2, 🆕):** `job_nature` · `material_source`(+spec bahan customer & ETA bila CUSTOMER) · `tax_scheme`(PPN/NON_PPN — non-PPN dianggap include dalam harga, tanpa field alasan) · `pph_applicable` · `ppn_percent`(11) · `pph_percent`(2)
**Tier qty:** `seq · qty · UTAMA(radio) · target_harga(opsional) · diskon_item(opsional) · catatan` — breakpoint input Sales; diskon item FIX di sini.

**Fitur form yang SUDAH ADA di `inquiry_new.html` (✅):**
- **Pilih Tipe Produk + Galeri** (`m_product_type`) → menampilkan katalog tipe (Card Box, Pouch, Sleeve, dll) dengan foto & deskripsi.
- **Ukuran Pasaran** — dropdown ukuran umum per tipe → **auto-fill dimensi** (P×L×T + parameter) → dieline langsung tergambar.
- **Multi-part** — 1 produk bisa banyak part (Body, Lid, Partisi…), tiap part punya tipe, dimensi, dieline, bahan sendiri.
- **Tipe Part** (`ptd`): Paper / DC / DC+SF / SHEET dll → menentukan struktur biaya di costing.
- **Template Dieline** — slot dropdown (`m_dieline_template`, saat ini kosong; auto-fill flap menyusul saat katalog siap).
- **Bahan (Material)** per part — pilih dari master `m_raw_material` (ketik cari; jenis & brand dari master).
- **File Diecut** — slot upload file vektor dieline/pisau (terpisah dari preview DSL).
- **Bentangan (flat)** — Flat W/H/Area auto dari dieline DSL, mode **Auto (hitung sistem)** atau manual override.

### 5.2 Sifat pekerjaan — sumber bahan menentukan sifat CO (khusus)

**Prinsip inti:** `material_source` adalah penentu apakah order menjadi **CO PRODUCT** atau **CO JASA**.

```text
material_source = OWN       → perusahaan sediakan bahan → CO PRODUCT
                             (jual barang jadi: material + proses + tooling + coating)

material_source = CUSTOMER  → customer sediakan bahan   → CO JASA
                             (jual jasa saja: material=0 + proses + tooling + coating + handling)
```

Turunan `job_nature` mengikuti sumber bahan (**hanya 2 nilai**: PRODUCT / JASA):
| material_source | job_nature | Material | Process | Tooling | Coating | Handling | Sub-jenis (deskriptif) |
|---|---|:--:|:--:|:--:|:--:|:--:|---|
| **OWN** | **PRODUCT** | ✓ | ✓ | ✓ | ✓ | – | cetak penuh, bahan kita |
| **CUSTOMER** | **JASA** | **0** | ✓ | ✓ | ✓ | ✓ | jasa cetak (customer bawa kertas) |
| **CUSTOMER** | **JASA** | **0** | ✓(plong) | ✓(pisau) | – | ✓ | jasa diecut/potong |
| **CUSTOMER** | **JASA** | **0** | sesuai | sesuai | sesuai | ✓ | jasa lain (laminasi/coating saja) |

> `job_nature` cukup **2 nilai** (PRODUCT/JASA). Ragam jasa (cetak/diecut/finishing) adalah **deskripsi pekerjaan**, bukan nilai enum terpisah — dicatat di keterangan/spec, bukan mengubah tipe.

**Konsekuensi:**
- **OWN → CO PRODUCT:** harga = jual barang; revenue penuh; PPN barang; tidak kena PPh.
- **CUSTOMER → CO JASA:** harga = jual jasa (material=0 + handling/spoilage); bahan customer masuk consignment (non-aset, via Receiving non-costed); komponen jasa **kena PPh 23** (dipotong customer).

### 5.3 Imposisi & urutan proses (khusus — routing bervariasi)

**Imposisi** menentukan susunan berapa *up* di plano → mengalir ke HPP (cut/plano, isi/lb). Mengubah imposisi mengubah harga.

**Preview dieline (SVG) di NEW & REVISE — 📌 PERHATIAN KHUSUS: metode DSL, bukan geometri hardcode.**

Sistem sudah mengimplementasikan renderer dieline **berbasis DSL** (`TMDieline`, teruji di `inquiry_new.html`). Prinsipnya: **dieline didefinisikan sebagai DATA, bukan kode** — disimpan di `m_product_type.dieline_spec`. Menambah tipe box baru = menambah data DSL, bukan menulis program.

Arsitektur (ringkas, detail teknis di Tier 2 §perhatian khusus):
1. **Expression evaluator** — string ekspresi (`"W"`, `"A+B"`, `"max(8,(b-a)/6)"`) dieval jadi angka (tokenize → RPN → eval; fungsi max/min/sqrt/round, konstanta PI).
2. **Scope** — dimensi part + **alias** (A=length, B=width, C=height, G=glue_flap, TT=top_tuck, DF=dust_flap, BT=bottom_tuck…) + `vars` turunan.
3. **Primitif gambar** (tipe elemen): `line · rect · circle · tuck · dust · glue · bleed · foldlines · holes · text` — tiap primitif meng-eval ekspresi jadi koordinat SVG.
4. **`dieline_spec`** = `{ canvas{w,h}, flat{w,h}, vars{}, elements[] }`. `flat{w,h}` → **bentangan & luas ke costing**.

Warna garis: CUT (potong) · CREASE (rill/lipat) · BLEED · HOLE — standar prepress.

**Alur di form:** pilih tipe produk → (opsional Ukuran Pasaran auto-fill dimensi) → isi P×L×T + parameter → dieline tergambar live + flat/luas terhitung → mengalir ke HPP. Berlaku NEW & REVISE; RECALC tidak (bentuk sama).

**Warna Pantone → CMYK:** spec warna cetak pakai kode Pantone C (coated, offset) + mapping CMYK indikatif; proof warna tetap acuan final.

**Roadmap:** dieline (DSL) → mockup **3D** (gaya Pacdora) sebagai tahap lanjut. Referensi eksternal metode: Templatemaker (dieline parametrik) & Pacdora (dieline→3D).

**Urutan proses (routing):** standar Lampiran A hanya **ACUAN AWAL** yang disarankan engine. Urutan riil **sangat bervariasi** karena layer finishing:
- **Coating multi-layer & berulang** — bisa 1–3 layer (WB/UV/varnish), kombinasi, dan berulang di titik berbeda → menambah/menggeser step.
- **Laminasi** — bisa sheet, kombinasi dengan corrugated (titik MERGE), memindahkan posisi step.
- **Kombinasi finishing** — emboss, hot-print/foil, spot-UV di atas laminasi, dll — masing-masing menyisipkan step dengan urutan bergantung teknik.

**Prinsip:** engine menyarankan urutan standar sesuai `part_type`; **Product Designer menyesuaikan** (drag/insert/skip) mengikuti kebutuhan finishing riil. Yang dijaga hanya **guard urutan wajib** (mis. COATING sebelum LAMINASI, PLONG butuh PISAU, CETAK butuh PLATE) — selebihnya fleksibel. Pola: *"engine suggests, officer confirms"*.

### 5.4 Tooling lifecycle (khusus)
Plat/pisau/film = aset ber-umur (dihitung impresi). Status: ADA · ADA_WARNING(≥85%) · AUS · TIDAK_ADA. Repeat order cek status; AUS/TIDAK_ADA → line tooling baru (biaya sekali, terpisah). Umur diisi dari produksi, di-reset saat tooling dibuat ulang.

### 5.5 Pajak (khusus — arah berbeda)
- **PPN menambah** tagihan (perusahaan setor ke negara).
- **PPh 23 mengurangi** kas diterima (customer memotong, kita terima bukti potong = kredit pajak); **hanya atas komponen jasa**.
- Non-PPN dianggap **include dalam harga** — tanpa field alasan terpisah (keputusan perusahaan).

### 5.6 Diskon (khusus — tiga jenis)
| Jenis | Level | Waktu | Aturan |
|---|---|---|---|
| Diskon item | baris | fix inkuiri | atas harga item |
| Diskon **amount** | dokumen | fix inkuiri, dibawa CO | nominal/% atas subtotal |
| Diskon **qty** | dokumen | occasional | aktif bila Σ tonase ≥ ambang |

### 5.7 Biaya eksternal / pihak ketiga (khusus)

Biaya dari pihak ketiga yang **sudah ditentukan/diketahui sejak awal** — mis. trucking/ekspedisi dengan tarif tetap, jasa luar (finishing outsource, sablon khusus) — dapat dicatat **sejak inkuiri**, tidak harus menunggu CO.

| Aspek | Ketentuan |
|---|---|
| Waktu catat | boleh sejak inkuiri (bila sudah pasti), atau ditambahkan di CO |
| Jenis | trucking · ekspedisi · jasa luar · lainnya |
| Perlakuan pajak | **masuk DPP → kena PPN** (bukan reimbursement murni) |
| Aliran | inkuiri → quotation (tampil sbg biaya) → CO (level dokumen) |
| Bedanya dari handling fee | handling fee = internal (penanganan bahan customer); biaya eksternal = tagihan pihak ketiga yang diteruskan |

**Catatan:** bila biaya eksternal bersifat *reimbursement murni* (ditalangi & ditagih apa adanya tanpa markup), perlakuan pajaknya bisa berbeda — flag jenis biaya (taxable vs reimbursement) disiapkan untuk fleksibilitas (lihat blocker terkait di §6).

### 5.8 Prove fisik & relasi ke inquiry (khusus)

Approval prove = fisik, tanda tangan basah, diarsip. Officer entry ke sistem dengan **tanggal approval fisik** (memulai due-date clock) terpisah dari tanggal entry. Approval per-separasi (CMYK+PMS). Scan arsip wajib.

**Relasi PROVE ↔ INQUIRY = one-to-one:**
- Tiap inquiry punya **tepat satu proof final** (bukan per master product). Proof melekat ke inquiry, bukan produk.
- Versi v1/v2/v3 = **log anak** di bawah proof; versi final ditunjuk snapshot di header inquiry.
- **Repeat order (RECALC/REVISE) = inquiry baru → proof baru** (proof ulang, versi baru sendiri). Tidak reuse proof lama.
- **Bentuk proof:** file **PDF** artwork siap cetak (dengan crop/registration mark, color bar per separasi, info trim & design number) — contoh: layout KAYS N200 (design number DS003479).

**Design number** (mis. DS003479) = identitas artwork/proof. Saat ini bisa manual (dari sistem lama); target otomatisasi minimal — cukup jadi **atribut proof + FK ke inquiry**, tanpa tabel master berlebih.

### 5.9 Penyiapan item Customer Order (khusus)

Item ter-approve = fix (qty/harga/diskon item snapshot inkuiri). **Penggabungan multi-item terjadi di QUOTATION** (lihat section khusus §3.4): beberapa inquiry product milik customer & lokasi kirim sama digabung jadi baris-baris satu quotation. Quotation kemudian **1:1 menjadi CO**.

Di CO, item = baris quotation (asal inquiry). CO hanya atur delivery (full/partial + tanggal) + diskon amount/qty dokumen + pajak dokumen + biaya eksternal — **tidak mengubah** qty/harga/diskon item.

- **co_item.kind** (PRODUCT/JASA) diwarisi dari `inquiry.material_source` (OWN→PRODUCT, CUSTOMER→JASA) — satu CO bisa campuran item product & jasa.
- Jejak relasi lengkap & FK: lihat **§3.4** (section khusus relasi dokumen).

### 5.10 Estimasi Kecepatan Produksi & Janji Kirim (khusus)

Sistem menghitung **estimasi lama pengerjaan** produk dari **histori produk sejenis** — agar Sales bisa menjanjikan kapan bisa selesai/kirim dengan dasar nyata, bukan tebakan.

**Sumber & satuan:**
- Basis = histori produk sejenis (part_type / material / proses mirip).
- Satuan estimasi: **per pcs** dan/atau **per lembar cetak** — dikalikan qty order → total waktu pengerjaan.
- Memperhitungkan urutan proses (routing) & jumlah layer finishing (makin banyak coating/laminasi, makin lama).

**Override (tiga role boleh menyesuaikan):**
| Role | Alasan override |
|---|---|
| **Product Costing Officer** | tahu beban biaya & kompleksitas riil |
| **Product Design Officer** | tahu kerumitan artwork/finishing aktual |
| **Sales** | tahu urgensi & negosiasi dengan customer |

Estimasi engine = **saran awal**; override tercatat (siapa, kapan, alasan) untuk audit & pembelajaran.

**Hubungan dengan due-date (PENTING — jangan tertukar):**
```text
Estimasi kecepatan  = perhitungan KAPASITAS riil (berapa lama produk ini dikerjakan)
Due-date 14 HK      = JANJI kontraktual (mulai dari approval prove, angka tetap)

Bila estimasi > due-date:
  → aplikasi TIDAK memblok order
  → order ditandai WARNA KHUSUS (mudah terlihat semua pengguna)
  → butuh VERIFIKASI non-aplikasi: Operational Manager & PPIC (verbal/meeting)
  → bila valid, order lanjut dengan penanda tetap terlihat
```

**Manfaat ke Sales:** dengan estimasi per pcs/lembar, Sales dapat memberi customer **perkiraan tanggal selesai/kirim** yang realistis saat penawaran — memperkuat kepercayaan & mencegah over-promise.

> Catatan: estimasi ini berbeda & melengkapi **Production Capacity Engine** (net rate vs effective rate). Estimasi inquiry = cepat/kasar dari histori untuk janji Sales; capacity engine = detail untuk penjadwalan produksi aktual.

### 5.11 Sumber Data REVISE & RECALC (khusus — pemetaan tabel)

REVISE & RECALC **keduanya berasal dari master product `m_product`** — tetapi produk bukan satu tabel datar; ia **hierarki berlapis**. Menarik "produk asal" berarti menarik seluruh rantai:

**Tabel sumber (dari struktur `tmmst` asli):**
```text
m_product (header)                          product_code, name, customer_id, qty_limit, cfg_parts_id, category
  └─< m_product_parts (per part)            parts_code, parts_name, part_type, qty_per_sku, cfg_color, cfg_ink
        ├─< m_product_parts_material        layer_seq, rawmat_id, gram_weight, flute_type, size_p/l, is_primary
        └─< m_product_parts_process         branch_code(MAIN/L/R/BODY/LID), step_seq, flow_type(LINEAR/SPLIT/MERGE),
                                            process_id, machine_id, qty_factor
Referensi tipe (dieline & dimensi):
  m_product_type + _param + _size + _size_val   (dieline_spec DSL, parameter, ukuran pasaran)
Konfigurasi (template opsional):
  m_product_cfg_parts / _detail · m_product_cfg_color · m_product_cfg_ink
```

**Minimal 4 tabel inti:** `m_product` → `m_product_parts` → (`m_product_parts_material` + `m_product_parts_process`), plus `m_product_type*` untuk dieline/dimensi.

**Apa yang boleh berubah — REVISE vs RECALC:**
| Tabel sumber | REVISE | RECALC |
|---|:--:|:--:|
| `m_product` (header/produk) | tetap (produk sama) | tetap |
| `m_product_parts` (ukuran/warna part) | **boleh ubah** | terkunci |
| `m_product_parts_material` (bahan/gramatur/layer) | **boleh ubah** | hanya **substitusi setara** |
| `m_product_parts_process` (proses/branch/flow) | **boleh ubah** (bila proses berubah) | terkunci |
| Costing (harga) | recalc | **recalc (fokus)** |
| Tabel costing tujuan | `i_inquiry_costing` (MTO) | `i_inquiry_costing` (MTO) |

**Catatan penting struktur:**
- **Material per-layer** (`layer_seq`) — satu part bisa banyak lapisan bahan (mis. duplex + SF). REVISE menyentuh layer; RECALC hanya substitusi setara per layer.
- **Proses ber-branch & flow** (`branch_code`, `flow_type` SPLIT/MERGE, `qty_factor`) — menangkap anomali seperti **BELAH PLANO** (SPLIT) & **laminasi menyatu** (MERGE). Ini sumal untuk urutan produksi.
- **Costing tujuan = `i_inquiry_costing`** (bukan `i_stock_costing`) karena REVISE/RECALC make-to-order (ada customer). `i_stock_costing` khusus MTS.
- Versioning: produk hasil revisi menunjuk asal via kolom `parent_*` (lineage), `revision_no` naik.

---

## SECTION 6 — NOTICE: BELUM DIBAHAS / AKAN DATANG

### 6.1 Keputusan yang SUDAH ditetapkan (tidak lagi blocker)
- **B5 — Kepemilikan tooling:** ✅ milik **perusahaan** (investasi order; perusahaan tanggung saat aus).
- **B7 — Non-PPN:** ✅ dianggap **include dalam harga**, **tanpa** field alasan/dasar.
- **B8 — Guard routing:** ✅ mengikuti alur di layar Product Designer (urutan proses & finishing). Detail teknis dijabarkan di Tier 2 §7; bila ada celah, dibahas saat Tier 2.
- **B10 — Definisi "produk sejenis" (estimasi kecepatan):** ✅ satu tipe (mis. Paper+SF) dengan **layer finishing sama**, **luas lembar cetak** tidak jauh beda, **bentuk layout mirip**, **gramatur kertas mirip**.
- **B11 — Estimasi > due-date:** ✅ butuh **verifikasi non-aplikasi** oleh Operational Manager & PPIC (verbal/meeting). Bila valid → order **ditandai warna khusus** agar mudah terlihat semua pengguna. (Aplikasi tidak memblok; hanya menandai & meminta verifikasi manusia.)

### 6.2 Keputusan yang MASIH menggantung (blocker) + saran
- **B1 — Granularitas bahan customer:** all-or-nothing (flag header) vs per-material (kertas customer, tinta kita). **Saran:** mulai flag header (mayoritas kasus), tapi siapkan kolom `material_source` di level material sejak Tier 3 agar tak perlu ALTER besar bila nanti butuh campuran.
- **B2 — Basis handling fee:** % proses / flat per pc / % estimasi nilai bahan. **Saran:** default % dari nilai proses (paling adil terhadap kompleksitas), overridable per order.
- **B3 — Kebijakan waste bahan customer:** ganti / lapor / toleransi allowance. **Saran:** tetapkan allowance standar (mis. ±3%); di atas itu wajib lapor & kesepakatan tertulis dengan customer — cegah dispute.
- **B4 — Umur maksimal tooling & definisi impresi:** per lembar plano vs per pcs; angka max per jenis plat. **Saran:** impresi = **per lembar plano** (standar offset), bukan per pcs jadi; max per jenis plat jadi parameter master (CTP aluminum ~100–250k tergantung mesin).
- **B6 — Invoice per-CO vs item macet:** bila 1 item prove-nya telat, item siap ikut tertahan. **Saran:** sediakan mekanisme *split CO* (keluarkan item macet jadi CO turunan) agar item siap bisa ditagih — tapi ini keputusan bisnis Finance, perlu konfirmasi.
- **B9 — Biaya eksternal: taxable vs reimbursement.** Default masuk DPP (kena PPN). **Saran:** sediakan flag jenis biaya (taxable/reimbursement) untuk fleksibilitas, default taxable.

### 6.3 Akan dibahas di tier/stage lain
- **Tier 2:** state machine detail (+ state form & monitoring), pseudocode engine, urutan pajak presisi, tooling function, **logging & form lifecycle**, **report analisa Board of Management**.
- **Tier 3:** DDL (`job_nature`, `tax_scheme`, `m_tooling_asset`, `co_header/co_item`, `m_discount_qty_rule`, kolom log & stat).
- **Tier 4:** UI toggle, panel tooling, breakdown pajak, kontrol delivery CO, **penanda warna khusus (estimasi>due-date), dashboard monitoring**.
- **Stage 2+:** Customer Order detail, Receiving consignment, DO cross-CO, Invoice per-CO, PPh realisasi di pembayaran.
- **📌 Dokumen terpisah (perhatian khusus):** **[FORECAST_ENGINE_spec.md](FORECAST_ENGINE_spec.md)** — forecast **2 view** (KIRI: waktu + event dari `m_season_calendar` → produk tema event laris; KANAN: per area yang pernah order), semua dari average tahun sebelumnya. Prototype: `forecast_engine.html`. Fondasi: `customer_demografis.html` (tagging). Butuh data ≥1 th. Blocker: FE-B1…FE-B6.
- **📌 Modul baru diusulkan (belum ada sistem baku):** **[SALES_INITIATIVE_note.md](SALES_INITIATIVE_note.md)** — penjualan inisiatif internal (supply-driven): jual sisa order (overrun), slow-moving material, idle machine. Selama ini verbal. **Saran: modul terpisah dari Inquiry** (arah aliran terbalik: dorongan internal, bukan permintaan customer), yang men-generate lead → dikonversi jadi inquiry normal saat ada peminat.

---

## SECTION 7 — ROLE MATRIX

| Aksi | Sales | Design | Costing | Direksi | Customer |
|---|:--:|:--:|:--:|:--:|:--:|
| Terbit Inkuiri + tier + job_nature + pajak/diskon item | **C** | – | – | – | – |
| Isi spec bahan customer (material_source=CUSTOMER) | **C** | R | – | – | – |
| Imposisi (BARU/REVISE) | – | **C** | R | – | – |
| Cek/registrasi status tooling | – | **C** | R | – | – |
| Hitung HPP per tier (+handling/tooling baru) | – | – | **C** | – | – |
| Baca margin/toleransi/HPP/handling/tooling | ✗ | ✗ | **✓** | **✓** | ✗ |
| **Approve = Publish harga** | – | – | – | **A** | – |
| Buat Quotation + Nota | **C** | – | – | R | – |
| Setuju/tolak harga | – | – | – | – | **A** |
| Approve Prove (tanda tangan fisik) | – | R | – | – | **A** |
| Entry approval prove ke sistem | **C** | R | – | – | – |
| Kirim PO | – | – | – | – | **C** |
| Rakit CO multi-item (delivery + pajak dokumen) | **C** | – | R | R | – |

C=Create · A=Approve · R=Review · ✓/✗=akses baca

---
> **Lanjut:** Tier 2 (Business Logic) · Tier 3 (Database) · Tier 4 (UI/UX).
