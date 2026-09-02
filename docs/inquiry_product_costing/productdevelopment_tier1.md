# Product Development — Sub-Modul Baru (Tier 1: Arsitektur & Scope)

> **Status: DESAIN, BELUM DIKERJAKAN.** Dokumen ini murni hasil riset & sintesis
> (permintaan user 2026-09-02, "baca 2 folder lalu diskusikan") — bukan rencana
> yang sudah disetujui utk langsung dieksekusi. Baca §7 (Pertanyaan Terbuka)
> sebelum mulai coding apa pun dari dokumen ini.
>
> **Sumber**: `docs/imposition_nesting/*` (riset software prepress industri),
> `docs/inquiry_product_costing/gemini_spark_productcosting.md` (reverse-
> engineering `calc_cogs()` V1), `docs/attachments/MC-*.xlsx` (5 Master Card
> RIIL yang dipakai lapangan), `docs/attachments/mockup_mastercard.jpg`
> (mockup layar target), kode V2 existing (`ImpositionEngine.php`,
> `CostingEngine.php`, `vw_master_card`), + riset web software industri
> (Esko Phoenix, Heidelberg Prinect Signa Station Packaging Pro, Insoft Imp,
> Kodak Pandora — lihat sitasi di §4.3).

---

## 1. Kenapa 3 Sub-Modul, Bukan 1 Alur Lurus

User (2026-09-02) menegaskan ada **3 sub-modul terpisah** dalam alur "dari
Inquiry sampai harga jadi":

```
Inquiry Product  →  Product Development  →  Product Costing
(komersial/intake)   (teknis/rekayasa)       (kalkulasi HPP)
```

**Product Development BUKAN bagian dari Inquiry atau Costing** — ini
sub-modul sendiri, dengan tanggung jawab: menerjemahkan spesifikasi produk
mentah (dari Inquiry) menjadi **desain teknis produksi yang lengkap**
(dieline, imposition/nesting, pisau pond, urutan proses) yang dituangkan ke
**Master Card**. Master Card ini lalu jadi INPUT bagi Costing (utk hitung
HPP) dan bagi Production (utk WO/routing) — bukan sebaliknya.

Peran "Product Development" (Desainer/Prepress) muncul eksplisit di
`mockup_mastercard.jpg` sbg salah satu dari 4 penandatangan Master Card
(Desainer/Product Development, Sales Officer, Quality Control, Kepala
Pabrik) — bukan istilah yang saya karang, ini sudah ada di alur kerja nyata
perusahaan.

---

## 2. Peta Kondisi Saat Ini (Kode V2) vs Arsitektur Baru

| Kapabilitas | Lokasi SEKARANG | Masalah |
|---|---|---|
| Imposition sederhana (grid/rotate/mix, hitung UP) | `App\Services\Imposition\ImpositionEngine.php`, layar `pages::inquiry.imposition`, tabel `iqp_layout` — nempel di **Inquiry** | Cuma 1 dari banyak tool prepress, dan nempel ke Inquiry padahal ini kerjaan teknis Product Development, bukan komersial |
| Tampilan Master Card | View `tm2mst.vw_master_card` (SQL view, sudah ada di DB), dibaca READ-ONLY di tab "Master Card" pada **WO Detail** (`production/wo-entry.blade.php`) | Cuma tampil pasif SETELAH WO dibuat (production, hilir) — bukan tempat Master Card **dibuat/didesain** (harusnya di hulu, sebelum Costing) |
| Kalkulasi HPP | `App\Services\Costing\CostingEngine.php`, layar `pages::inquiry.costing`, tabel `iqp_costing`/`iqp_costing_part` — nempel di **Inquiry** juga | Cuma 2 dari 6 tipe part diimplementasi (DC, SHEET_DIGITAL); field imposition (`i_cut_per_plano`, `i_isi_per_lb`) diisi **manual** oleh user costing, padahal seharusnya bisa ditarik otomatis dari hasil Product Development |
| Approval Master Card | Tabel `tm2mst.prod_mastercard_signoff` (role_name → sign) | Ada tapi terpisah dari layar Master Card sendiri (krn Master Card belum punya layar sendiri) |

**Kesimpulan**: fondasi data (`iqp_layout`, `vw_master_card`,
`prod_mastercard_signoff`, `mat_plano_size`) sudah lumayan lengkap dan
match ke field-field Master Card asli (lihat §4.2) — yang belum ada adalah
**satu layar terpadu tempat proses ini benar-benar dikerjakan** dengan tools
interaktif + preview visual, bukan cuma perhitungan kotak-kosong di
background.

---

## 3. Sub-Modul 1: Inquiry Product (existing, scope TETAP)

Tidak berubah dari `inquiryproduct_tier1.md`/`tier2.md` yang sudah ada —
tetap fokus komersial: data customer, spesifikasi produk mentah (dimensi,
qty, target harga), quotation. **Imposition Engine dipindah KELUAR dari
sini** ke Product Development (lihat §6 utk detail perubahan yang perlu
didiskusikan — BUKAN keputusan final).

---

## 4. Sub-Modul 2: Product Development (BARU — inti dokumen ini)

### 4.1 Kenapa Perlu Jadi Sub-Modul Sendiri

- **Aktor beda**: Inquiry dikerjakan Sales/CS, Costing dikerjakan tim
  Costing, tapi **desain teknis (dieline, imposition, pisau, routing)
  dikerjakan Desainer/Prepress** — kompetensi & tools yang sama sekali
  beda, pantas jadi layar/peran sendiri.
- **Master Card adalah dokumen resmi** (ada kolom approval multi-role: ACC
  Email, ACC Digital, Box Polos, Di ACC Oleh — lihat data riil §4.2) — ini
  bukan sekadar hasil hitungan yang numpang tampil di tab lain.
- **1 Master Card dipakai ULANG** lintas banyak Inquiry/CO di masa depan
  (kalau customer order ulang produk yang sama, desainnya sudah ada, tinggal
  dipanggil) — ini natural jadi Master Data-nya sendiri, bukan child dari 1
  Inquiry doang (beda dari `iqp_layout` sekarang yang 1:1 ke `iqp_part`).

### 4.2 Master Card = Output Utama

Dibongkar langsung dari 5 file riil `docs/attachments/MC-*.xlsx` (bukan
tebakan) + divalidasi silang ke `vw_master_card` (kolom yang SUDAH ada di
DB ditandai ✅, yang BELUM ada ditandai ❌):

| Field Master Card (form riil) | Contoh nilai riil | Kolom `vw_master_card`? |
|---|---|---|
| Kode Item (kode mnemonic pendek) | `MKR-TTP ALS ECLS`, `SJJ-OSM N100 CV DS3146` | ✅ `kode_item` |
| No. Desain | `DS003047` | ✅ `no_desain` |
| Nama Barang | `ALSAMSI EXCLUSIVE TOP` | ✅ `nama_barang` |
| Nama Pelanggan | `PAK MUKRI`, `PT. SELATAN JADI JAYA` | ✅ `nama_pelanggan` |
| Dimensi P/L/T | `24,8 / 19 / 3,4 cm` | ✅ `dimensi` (perlu cek: 1 kolom gabungan atau perlu dipecah 3?) |
| **Kode Pisau** (nama/referensi fisik pisau pond) | `PISAU ALSAMSI TOP ISI 4 MATA` | ✅ `kode_pisau` |
| **Ukuran Pisau** (dimensi fisik pisau, beda dari ukuran cetak!) | `83,4 x 70,1 cm` | ❌ **TIDAK ADA** di view — gap nyata |
| Material + Kualitas (gsm) | `DUPLEX IKA DP`, `350 gsm` | Sebagian (lihat `mat_grammage` di Costing, belum tentu sinkron ke sini) |
| Ukuran Bahan (raw sheet sblm potong) | `84,5 x 72 cm` | ❌ **TIDAK ADA** — cuma `ukuran_cetak` |
| Ukuran Cetak | `84,5 x 72 cm` | ✅ `ukuran_cetak` |
| Jenis Finishing (bisa multi: Laminating + UV + Hotprint) | `Laminating Doff, UV Spot, Hot Print Gold Puyeh` | ✅ `jenis_finishing` |
| Bentuk Sambungan (Single/Double Join) | `Double Join` | ✅ `bentuk_sambungan` |
| **Isi dalam Satu Cetakan = hasil UP** | `4 UP`, `1/2 UP`, `1 SET`, `8 UP` | ✅ `isi_satu_cetakan` — **ini persis output `ImpositionEngine::run()['up']`**, konfirmasi imposition MEMANG harus masuk sini |
| Warna Pertama/Kedua (Unit 1-4 = CMYK/spot) | `Black/Cyan/Magenta/Yellow` | ✅ `warna_cs`/`warna_ks` |
| **Urutan Pekerjaan (Routing)** — persis proses produksi step-by-step | `Potong → Cetak Lithrone G40 → QC → Laminating → Plong → Kupas → Lem → Hitung → Pack` | ✅ `urutan_pekerjaan` — **ini bisa jadi basis routing WO otomatis**, saat ini `WoGenerationService` belum baca dari sini |
| ACC Email / ACC Digital / Box Polos / Di ACC Oleh / Tgl ACC | tanggal+nama approver per channel | Sebagian via `prod_mastercard_signoff` (perlu cek granularitasnya cukup atau tidak) |
| Pack Qty | `10 pcs/pack`, `600 pcs/pack` | ✅ `pack_qty` |

**Temuan penting**: `Kode Pisau` (nama/rujukan pisau, string) dan
`Ukuran Pisau` (dimensi fisik pisau pond, BEDA dari ukuran cetak — pisau
biasanya lebih besar dari area cetak krn ada margin gripper) adalah 2
**field terpisah** di form asli. `vw_master_card` baru punya yang pertama.
Ini konfirmasi ada gap kolom nyata, bukan cuma gap layar.

### 4.3 TOOLS & ENGINE Catalog (dengan PREVIEW ON SCREEN)

Digabung dari `docs/imposition_nesting/*` + riset software industri
(dicek 2026-09-02, lihat sitasi tiap software). **Semua tool WAJIB preview
visual di layar** (bukan cuma angka) — ini poin eksplisit user.

| # | Tool | Fungsi | Status di V2 | Rujukan industri |
|---|---|---|---|---|
| 1 | **Plano→Pcs** (Up & Cut Optimizer) | Dari ukuran plano tersedia, cari layout UP terbanyak (tegak/putar/campur) | ✅ **SUDAH ADA** (`ImpositionEngine.php`) — tinggal dipindah rumahnya + preview-nya diperkaya | Formula grid/rotate = "Modul A" `prepress_imposition_packaging_guide.md` |
| 2 | **Pcs→Plano** (Best Sheet Finder) | Kebalikannya: dari ukuran dus, CARI OTOMATIS plano standar (61×86/65×100/79×109/70×100 dst — sudah ada tabel `mat_plano_size`) mana yg waste/biaya kertas termurah | ❌ Belum — perlu loop Tool #1 utk tiap baris `mat_plano_size` lalu ranking hasilnya | Konsep sama dgn Esko Phoenix "AI analysis of design... calculates most efficient way to produce" ([esko.com/phoenix](https://www.esko.com/en/products/phoenix)) |
| 3 | **Interlocking Nesting** | Utk dus tuck-flap/auto-lock: putar 180° copy kedua supaya lidah saling kunci, hemat 12-22% vs grid biasa | ❌ Belum — beda algoritma dari grid biasa, perlu tau bentuk flap dari dieline | Esko ArtiosCAD "Nested Tumble/180° Rotation... Common Knife" (`prepress_imposition_packaging_guide.md` §1B) |
| 4 | **Die-Rule Estimator** | Hitung total meter lari pisau potong+lipat+perforasi dari geometri dieline → langsung jadi biaya tooling (bukan faktor manual spt `i_pisau_faktor` sekarang) | ❌ Belum — `CostingEngine.php` sekarang pakai `i_pisau_faktor * pisauRate` (manual), bukan dihitung dari geometri | `prepress_imposition_packaging_guide.md` §3.4 |
| 5 | **Multi-Job Gang-Run** | Gabung beberapa Master Card/SPK beda ukuran (kertas sama) dlm 1 plano cetak, hemat plate CTP+make-ready | ❌ Belum — butuh query lintas-Master Card yg qty-nya kecil & material sama | Esko Phoenix "combination runs 3 SKUs or 300 SKUs" · Insoft Imp "No-Mix-Gang" (pisah per section, hindari campur job saat finishing) — [insoftautomation.com](https://insoftautomation.com/latest-update/) |
| 6 | **Auto-populate & conflict resolve** | Auto isi cavity die berdasar qty, auto deteksi&selesaikan overlap bleed | ❌ Belum | Esko Phoenix "auto-populates die cavities... identifies and resolves bleed overlaps automatically" |
| 7 | **3D/Visual simulation per-copy** | Preview visual tiap unit di lembar (bukan cuma bounding box kotak) sebelum di-approve | Sebagian (`ImpositionEngine::cells()` sudah kasih koordinat per-cell utk render, tapi masih kotak polos, belum bentuk dieline asli) | Heidelberg Signa Station Packaging Pro "reliable visual monitoring as 3D simulation for each copy" |
| 8 | **CAD import** (dieline dari software desain) | Import bentuk dieline asli (bukan cuma bounding-box W×H) supaya nesting/interlock presisi | ❌ Belum — `iqp_part.flat_w`/`flat_h` cuma 2 angka, tidak ada bentuk polygon | Heidelberg "import and processing of all common CAD data formats" · Esko ArtiosCAD (native) |
| 9 | **Auto-trigger dari MIS/order data** | Layout otomatis ke-generate begitu data order masuk, tanpa staff harus buka tool manual | ❌ Belum (relevan kalau Tool #2/#5 sudah jalan, baru worth diotomatisasi) | Kodak Pandora "SmartLayout... automatically build layouts via your MIS system" |

**Catatan jujur**: Tool #3 (Interlocking), #6, #8 (CAD import bentuk asli,
bukan bounding-box) itu levelnya signifikan lebih rumit — butuh representasi
GEOMETRI POLYGON asli (bukan cuma W×H persegi panjang), rendering SVG/canvas
interaktif, dan kemungkinan library geometri (clipping/rotation polygon).
Ini BUKAN 1-2 hari kerja kalau mau serius — realistis dikerjakan bertahap.

### 4.4 Data Flow In/Out

```
Inquiry Product (iqp_part: flat_w/flat_h, qty, spec kasar)
        │
        ▼
Product Development
  Input: spec dari Inquiry + (opsional) dieline/CAD
  Proses: Tools #1-#9 di atas → interaktif, preview visual
  Output: MASTER CARD (kode_item, kode_pisau+ukuran_pisau, material,
          ukuran_bahan, ukuran_cetak, isi_satu_cetakan/UP, warna,
          urutan_pekerjaan/routing) — REUSABLE, bukan cuma milik 1 Inquiry
        │
        ├──────────────► Product Costing
        │                 Mode MANUAL: officer isi i_cut_per_plano/
        │                 i_isi_per_lb/dst sendiri (spt sekarang)
        │                 Mode AMBIL DATA: tarik otomatis dari Master
        │                 Card (isi_satu_cetakan → i_isi_per_lb, dst)
        │
        └──────────────► Production (WO Generation)
                          Routing (urutan_pekerjaan) → jadi dasar WO steps
                          Kode Pisau/Ukuran Pisau → validasi mesin/tooling
```

---

## 5. Sub-Modul 3: Product Costing (existing engine, tambah 2 mode)

### 5.1 Mode Manual vs Mode Ambil Data

Instruksi eksplisit user: **HARUS 2 mode**, bukan cuma auto-pull:

- **Mode Manual**: officer Costing isi sendiri semua parameter
  (`i_cut_per_plano`, `i_isi_per_lb`, dll) — ini yang SUDAH ADA sekarang di
  `costing.blade.php`. Tetap dipertahankan utk kasus: quick quote tanpa
  Product Development dulu, atau produk one-off yang tidak perlu Master
  Card permanen.
- **Mode Ambil Data**: kalau Inquiry punya Master Card terkait (dari
  Product Development), field-field imposition (`isi_satu_cetakan` →
  `i_isi_per_lb`, dst) **ditarik otomatis**, officer tinggal cek/override
  kalau perlu. Ini butuh field baru di `iqp_costing_part` (link ke Master
  Card id) + toggle UI "Manual / Ambil dari Master Card".

**Belum diputuskan** (perlu didiskusikan, BUKAN saya putuskan sendiri):
apakah toggle ini per-part atau per-costing (1 costing bisa campur part
manual + part ambil-data)? Kemungkinan besar per-part, konsisten dgn
`iqp_costing_part` yang sudah granular per part.

### 5.2 Formula Gap yang Sudah Terisi dari `gemini_spark_productcosting.md`

Sudah dibahas di respons sebelumnya (ringkas ulang di sini krn relevan ke
scope dokumen ini):

- **DC formula CostingEngine.php TERVALIDASI** — cocok byte-presisi dgn
  dokumen baru, TODO lama "wajib diverifikasi" di komentar kelas bisa
  ditutup.
- **4 tipe part belum diimplementasi** (DC+SHEET, DC+SF, KRAFT, SHEET
  polos) sekarang ADA formulanya lengkap (§II/§IV `gemini_spark_productcosting.md`)
  siap diimplementasi kalau diminta.
- **3 bug V1 lama** (formula RM tidak konsisten di 3 tempat, query agregasi
  salah, jebakan format persen) — V2 sepertinya sudah kebal, tapi belum
  ada test eksplisit yang membuktikan ini utk tipe part yang belum
  diimplementasi.

---

## 6. Perubahan pada Kode Existing yang Perlu Didiskusikan

**BUKAN keputusan final** — ini opsi yang perlu di-ACC user dulu:

1. **Pindah Imposition dari Inquiry ke Product Development?** Kalau ya:
   `iqp_layout` (1:1 ke `iqp_part`, nempel 1 Inquiry) kemungkinan perlu jadi
   tabel Master Data baru (mis. `mat_master_card` + child layout/routing di
   `tm2mst`, REUSABLE lintas Inquiry) — bukan cuma pindah lokasi file/layar,
   tapi perubahan skema data (dari "milik 1 part" jadi "master data
   independen yang di-link ke part").
2. **Master Card jadi Master Data baru di `tm2mst`** (sejalan prinsip
   arsitektur CLAUDE.md §2 — "master netral, single source of truth") —
   perlu skema tabel baru (bukan cuma view `vw_master_card` yang sekarang
   READ-ONLY dari sumber lain, jadi tabel yang benar2 diinput lewat layar
   Product Development).
3. **`WoGenerationService` baca routing dari Master Card** — perubahan ke
   Production module, di luar scope Product Development sendiri tapi
   konsekuensi lanjutannya.

Ini semua **perubahan arsitektural** (skema data, bukan cuma tambah layar)
— sesuai CLAUDE.md §7.1, ini kelas keputusan yang WAJIB dikonfirmasi user
dulu, bukan dijalankan diam-diam.

---

## 7. Pertanyaan Terbuka / Keputusan yang Dibutuhkan

1. **Prioritas tool** — dari 9 tool §4.3, mana yang mau dikerjakan duluan?
   Realistis bertahap: Tool #1 (pindah rumah) → #2 (Pcs→Plano, ekstensi
   natural dari #1) → #4 (Die-Rule, dampak langsung ke HPP) → baru yang
   lebih rumit (#3/#6/#8, butuh representasi geometri).
2. **Skema Master Card final** — apakah jadi tabel Master Data baru di
   `tm2mst` (rekomendasi saya, sejalan arsitektur), atau tetap sekitar view
   `vw_master_card` yang ada + di-upgrade jadi read-write?
3. **Sumber `Kode Item`** (kode mnemonic spt `MKR-TTP ALS ECLS`) — apakah
   ini digenerate otomatis dari aturan tertentu (customer+produk+desain),
   atau selalu manual diketik desainer? Perlu tahu polanya dari lebih
   banyak contoh sebelum bisa auto-generate.
4. **Toggle Manual/Ambil-Data di Costing** — per-part atau per-costing
   (§5.1)?
5. **Sumber dokumen** (`imposition_nesting`, `gemini_spark_productcosting.md`,
   `mockup_mastercard.jpg`) — dari riset user sendiri atau AI lain (Gemini)?
   Perlu tahu tingkat kepercayaan formulanya, terutama klaim hemat 12-22%
   dari Interlocking Nesting (ini angka umum industri, belum tentu pas
   dgn mesin/spesifikasi CV. Telasih Makmur — jangan dipakai mentah-mentah
   sbg target performa).

---

## Sumber Riset Web (dicek 2026-09-02)

- [Phoenix AI Planning & Imposition Software](https://www.esko.com/en/products/phoenix) — Esko
- [Rethinking Print Layouts: Esko Phoenix](https://www.esko.com/en/blog/rethinking-print-layouts-smarter-planning-imposition-with-esko-phoenix)
- [Prinect Signa Station Packaging Pro](https://www.heidelberg.com/global/en/software/workflow/prinect_modules/packaging_workflow_1/prinect_signa_station_packaging_pro/product_information_113/prinect_signa_station_packaging_pro.jsp) — Heidelberg
- [InSoft Automation — Imp](https://insoftautomation.com/) · [Latest Update (No-Mix-Gang)](https://insoftautomation.com/latest-update/)
- [Packaging Layout Automation (PLA) — Pandora 11.0](https://workflowhelp.kodak.com/display/PANDORA110/Packaging+Layout+Automation+(PLA)+Software+User+Guide) — Kodak
