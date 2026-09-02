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

**KOREKSI PENTING (keputusan user 2026-09-02, DIKUNCI):** Master Card
**BUKAN layar input** — ini **hasil PRINT OUT** dari data yang dikerjakan
di layar ProDev, persis pola dokumen cetak lain di aplikasi ini (Nota
Quotation, Nota CO, dst — lihat `docs/printnotastandard.md`, komponen
reusable `resources/views/components/print/*`). Alurnya:

```
Inquiry Product ──(spec dasar: dimensi, qty, customer)──► ProDev Submodule
                                                              │
                                          Officer INPUT + MENATA desain
                                          di sini, dibantu TOOLS & ENGINE
                                          dengan PREVIEW ON SCREEN (§4.3)
                                                              │
                                                    ┌─────────┴─────────┐
                                                    ▼                   ▼
                                          Tabel data (boleh jadi   Tombol "Cetak
                                          Master Data tm2mst,      Master Card"
                                          REUSABLE lintas Inquiry) (dokumen resmi
                                                                   utk approval)
```

Jadi **layar ProDev = tempat kerja** (form input + canvas tools interaktif
+ upload file, lihat §4.2b), **Master Card = tombol cetak** dari data yang
sama — sama seperti CO Entry punya data + tombol cetak Surat Jalan, bukan
2 layar terpisah yang masing-masing minta input sendiri.

**REVISI KEDUA (keputusan user 2026-09-02, DIKUNCI) — datanya BUKAN tabel
baru berdiri sendiri, tapi PERLUASAN grup tabel Master Product yang SUDAH
ADA** (`prod_product`/`prod_part`, domain `tm2mst`), termasuk **urutan
proses (routing)** jadi bagian resmi skema Master Product, bukan cuma
teks di `vw_master_card.urutan_pekerjaan`. Ini justru LEBIH konsisten
dengan arsitektur "Master netral, single source of truth" CLAUDE.md §2 —
Master Card bukan entity baru, ini **profil teknis lengkap dari
`prod_part` yang sudah ada**, yang selama ini cuma diisi kolom identitas +
akuntansi (lihat §2, `ProdProduct`/`ProdPart` fillable sekarang MINIM
sekali — tidak ada 1 pun kolom teknis produksi).

**Temuan penting saat investigasi**: `sys_app_menu` **SUDAH PUNYA** slot
`PROD_CARD` ("Master Card & Layout") sejak lama, sengaja **disembunyikan**
(`activate=false`) via migration `2026_08_28_000026_hide_master_card_layout_rename_crew.php`
dengan komentar eksplisit *"tinggal dinyalakan lagi kapan pun (mis. saat
Master Card mulai digarap)"* — momen itu SEKARANG. Tidak perlu bikin menu
baru dari nol, tinggal aktifkan slot yang sudah disiapkan.

### 4.2 Skema Tabel: Perluasan Grup Master Product

Dibongkar langsung dari 5 file riil `docs/attachments/MC-*.xlsx` (bukan
tebakan), dipetakan ke KOLOM BARU yang perlu ditambah ke `prod_part`
(via ALTER, bukan tabel terpisah) + 3 tabel anak baru (routing, layout,
attachment) — **✅ = tambah kolom baru ke `prod_part`**, **cek dulu** =
mungkin sudah ada jalan lain (mat_grammage dsb), perlu verifikasi sebelum
duplikasi:

| Field Master Card (form riil) | Contoh nilai riil | Rencana kolom |
|---|---|---|
| Kode Item (kode mnemonic pendek) | `MKR-TTP ALS ECLS`, `SJJ-OSM N100 CV DS3146` | ✅ `prod_part.mastercard_code` (beda dari `part_code` internal yg sudah ada) |
| No. Desain | `DS003047` | ✅ `prod_part.design_no` |
| Nama Barang, Nama Pelanggan, Dimensi P/L/T | — | Sudah ada semua: `part_name`, `product.customer_id`, dimensi di `prod_product.cm_length/width/height` (perlu cek: dimensi di Master Card level PART atau PRODUCT — data riil nunjuk ke level produk jadi, kemungkinan tetap di `prod_product`) |
| **Kode Pisau** (nama/referensi fisik pisau pond) | `PISAU ALSAMSI TOP ISI 4 MATA` | ✅ `prod_part.die_code` (text) |
| **Ukuran Pisau** (dimensi fisik pisau — beda dari ukuran cetak!) | `83,4 x 70,1 cm` | ✅ `prod_part.die_w_cm`/`die_h_cm` — **gap nyata, belum ada sama sekali di skema manapun** |
| Material + Kualitas (gsm) | `DUPLEX IKA DP`, `350 gsm` | Cek dulu: link ke `mat_grammage`/`mat_raw` (tm2mst, sudah ada di Costing) via FK, JANGAN duplikasi teks bebas |
| Ukuran Bahan (raw sheet sblm potong) | `84,5 x 72 cm` | ✅ `prod_part.sheet_raw_w_cm`/`sheet_raw_h_cm` — gap nyata |
| Ukuran Cetak | `84,5 x 72 cm` | ✅ `prod_part.print_area_w_cm`/`print_area_h_cm` |
| Jenis Finishing (bisa multi: Laminating + UV + Hotprint) | `Laminating Doff, UV Spot, Hot Print Gold Puyeh` | ❌ **BUKAN kolom teks** — tabel anak baru **`prod_part_finishing`** (keputusan user 2026-09-02, §4.2a), 1 baris per jenis finishing, siap dikaitkan biaya/urutan proses masing-masing |
| Bentuk Sambungan (Single/Double Join) | `Double Join` | ✅ `prod_part.join_type` |
| **Isi dalam Satu Cetakan = hasil UP** | `4 UP`, `1/2 UP`, `1 SET`, `8 UP` | Bukan kolom manual — **hasil `ImpositionEngine`**, disimpan di tabel anak `prod_part_layout` (§4.2a), bukan diketik officer |
| Warna Pertama/Kedua (Unit 1-4 = CMYK/spot) | `Black/Cyan/Magenta/Yellow` | ✅ `prod_part.color_set_1`/`color_set_2` (JSON array) |
| **Urutan Pekerjaan (Routing)** | `Potong → Cetak → QC → Laminating → Plong → Kupas → Lem → Hitung → Pack` | ❌ **BUKAN kolom teks** — tabel anak baru **`prod_part_process`** (§4.2a), 1 baris per langkah, `seq` + FK `process_id` (`prod_process`, sudah ada tapi baru lookup flat) + `machine_id`/notes opsional. Ini yang ditekankan user barusan: routing harus jadi DATA terstruktur, bukan teks, supaya `WoGenerationService` bisa baca langsung |
| ACC Email / ACC Digital / Box Polos / Di ACC Oleh / Tgl ACC | tanggal+nama approver per channel | Perluas `prod_mastercard_signoff` (sudah ada) — cek granularitas cukup atau perlu kolom channel (EMAIL/DIGITAL/BOX_POLOS) |
| Pack Qty | `10 pcs/pack`, `600 pcs/pack` | ✅ `prod_part.pack_qty` |

### 4.2a Tabel Anak Baru

1. **`prod_part_process`** (routing/urutan proses, PALING PENTING sesuai
   penekanan user) — `id_part` FK ke `prod_part`, `seq` (urutan), `process_id`
   FK ke `prod_process` (lookup existing), `machine_id` FK opsional ke
   `prod_machine`, `notes`. Dipakai ulang sbg TEMPLATE saat WO Generation
   (`WoGenerationService`) — bukan diketik ulang tiap kali. **SELALU per
   part** (tiap part definisi routing-nya sendiri, tidak digabung — beda
   dari layout di poin 2).
2. **`prod_layout_group`** (REVISI 2026-09-02, lihat §4.2b) — 1 Master
   Card = 1 baris di sini, BUKAN 1:1 ke `prod_part`. `id_product` FK,
   `mastercard_code`, plano size/method/up_count/efficiency_pct/
   `layout_detail` JSON, `status` (DRAFT/CONFIRMED). **1 group bisa
   menaungi 1 ATAU LEBIH part** — keputusan gabung/pisah adalah hasil
   ENGINE (§4.2b), bukan aturan tetap di skema.
   + **`prod_layout_group_part`** (junction): `id_layout_group` FK,
   `part_id` FK, `qty_up_in_group` (berapa banyak part ini muncul dalam 1
   plano gabungan itu). Menggantikan rencana `prod_part_layout` di draf
   sebelumnya (SALAH, ditulis 1:1 per part — dikoreksi user).
3. **`prod_part_attachment`** — upload file JPEG/PDF/AI (Adobe
   Illustrator): bisa nempel ke `part_id` (dieline/artwork 1 part) ATAU
   ke `id_layout_group` (preview gabungan hasil nesting) — 2 kolom FK
   nullable, isi salah satu. Pola upload ikuti yang sudah established di
   Master Employee (`WithFileUploads` Livewire trait,
   `->store("prodev/{id}/attachment", 'public')`, lihat
   `resources/views/pages/master/employee.blade.php:499`).
   **[KEPUTUSAN 2026-09-02]** AI file **TIDAK wajib** disertai PDF export
   — kalau preview AI sulit (memang tidak bisa inline browser, bukan
   format native web), **cukup JPEG** yang jadi sumber preview visual.
   Jadi: JPEG = wajib ada kalau mau preview visual (dipakai `<img>`),
   AI/PDF = opsional, boleh nempel sbg referensi/download tambahan tanpa
   perlu preview.
4. **`prod_part_finishing`** (keputusan user 2026-09-02 §7 poin 5) — jenis
   finishing sbg data terstruktur, bukan teks bebas: `id_part` FK, `seq`,
   `finishing_type` (Laminating Doff/Glossy, UV Spot, Hot Print, Varnish,
   Emboss, dst — kemungkinan perlu lookup `ref_finishing_type` sendiri
   drpd enum hardcode, banyak variasi dari data riil §4.2), `notes`, kolom
   biaya opsional (nanti dipakai Costing) — persis pola `prod_part_process`
   tapi utk finishing.
5. **`mat_flap_rule`** (keputusan user 2026-09-02 §7 poin 8, `tm2mst`) —
   tabel referensi rentang lidah valid struktural, pola sama
   `MatFluteAllowance`: `material_type`/`grammage_range`/`glue_type` →
   `flap_min_mm`/`flap_max_mm`. **BUTUH DATA RIIL dari user** (aturan
   lem/struktur per material CV. Telasih Makmur) sebelum diisi — dipakai
   `prod_part.flap_min_mm`/`flap_max_mm` (§4.3a) sbg lookup default,
   officer tetap bisa override per part kalau ada kasus khusus.

**Temuan penting**: `Kode Pisau` (nama/rujukan pisau, string) dan
`Ukuran Pisau` (dimensi fisik pisau pond, BEDA dari ukuran cetak — pisau
biasanya lebih besar dari area cetak krn ada margin gripper) adalah 2
**field terpisah** di form asli. `vw_master_card` baru punya yang pertama.
Ini konfirmasi ada gap kolom nyata, bukan cuma gap layar.

### 4.2c Konstanta Engine — SUDAH DIBUAT di `sys_constant` (2026-09-02)

Keputusan user: 3 angka placeholder (batas upload, estimasi hemat
interlock, lidah minimum) **WAJIB gampang di-adjust admin TANPA deploy
kode** — bukan hardcode PHP. Sudah dieksekusi (bukan cuma rencana),
migration `2026_09_02_000020_seed_prodev_engine_constants.php`, tampil
otomatis di layar Configuration (`category_code='PRODUCTION'`,
`constant_group='PRODEV'`, pola persis sama dgn konstanta `COSTING` yang
sudah dipakai `CostingEngine.php` via `SysConstant::number()`):

| `constant_key` | Nilai awal | Catatan |
|---|---|---|
| `PRODEV_UPLOAD_MAX_MB` | 25 | Batas upload JPEG/PDF/AI (§4.2a poin 3) — user: "start with 25MB dulu" |
| `PRODEV_INTERLOCK_SAVING_MIN_PCT` | 12 | Estimasi hemat Interlocking Nesting, batas bawah (§4.3 Tool #3) — angka riset luar, BUKAN resmi Esko (§7 poin 7), user: "pakai saja 12-22% nanti kalau kurang efektif ditentukan lagi" |
| `PRODEV_INTERLOCK_SAVING_MAX_PCT` | 22 | Pasangan di atas, batas atas |
| `PRODEV_FLAP_MIN_MM` | 20 (2cm) | Lidah minimum DEFAULT/FALLBACK global (§4.3a, §7 poin 8) — user tegaskan **seharusnya bergantung panjang & lebar tutup** (formula, bukan angka flat), 2cm cuma titik awal sampai `mat_flap_rule` (§4.2a poin 5) terisi & logic formulanya ditulis |

Dipakai via `SysConstant::number('PRODEV_FLAP_MIN_MM', 20)` dst — pola
identik yang sudah dipakai `CostingEngine.php` (`RIM_SHEETS`,
`PLANO_REF_AREA`, dst), tidak perlu mekanisme baru.

### 4.2b Keputusan Gabung vs Pisah = Output ENGINE (klarifikasi user 2026-09-02)

**Masalah di sistem manual lama**: Master Card bisa berisi 1 part atau
gabungan >1 part TANPA aturan konsisten — kadang gabung, kadang pisah,
tergantung kebiasaan desainer saat itu. Ini bukan sekadar soal tampilan,
tapi **soal ekonomi**: gabung 2 part (mis. outer box + inner box) dalam 1
plano bisa menghemat kertas/plate/make-ready dibanding cetak terpisah —
TAPI tidak selalu, tergantung ukuran & sisa area masing-masing part.

**Aturan yang DIKUNCI**:
- **1 `iqp_inquiry` = 1 `prod_product`** (sudah tervalidasi ke kode:
  `iqp_inquiry.parent_product_id` FK tunggal ke `prod_product`, bukan
  many-to-many — arsitektur existing SUDAH benar, tidak perlu diubah).
- **1 `prod_product` = bisa multi `prod_part`** (juga sudah existing,
  `prod_part.product_id` FK).
- **Tiap `prod_part` WAJIB punya definisi sendiri yang jelas** (dimensi,
  material, dst — via kolom §4.2) — identitas part TIDAK PERNAH digabung/
  dikaburkan, apa pun hasil keputusan layout.
- **Penyusunan dieline/layout (gabung atau pisah) adalah keputusan
  ProDev**, dibantu ENGINE yang membandingkan skenario: (a) tiap part 1
  plano sendiri-sendiri, vs (b) kombinasi part mana digabung ke plano
  mana — lalu **rekomendasikan yang paling ekonomis** (biaya kertas +
  plate + make-ready terendah). Officer tetap bisa override rekomendasi
  engine.

**Konsekuensi ke Costing (§5)**: kalau 2+ part berbagi 1 `prod_layout_group`
(1 plano gabungan), biaya kertas/plate plano itu HARUS dialokasikan
proporsional ke tiap part (mis. berdasar luas area yang dipakai per part
dalam layout, via `qty_up_in_group` × luas part ÷ total luas terpakai) —
**bukan tiap part dihitung seolah-olah punya plano sendiri-sendiri**, itu
akan melipatgandakan biaya kertas secara keliru. Ini butuh logic baru di
`CostingEngine.php`, belum ada sama sekali sekarang (engine sekarang
selalu asumsi 1 part = 1 plano independen).

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
| 10 | **Intra-Product Part Combiner** (BARU, klarifikasi user 2026-09-02, §4.2b) | Beda dari Tool #5 (gang-run LINTAS order/produk) — ini gang-run ANTAR-PART dalam 1 produk yang SAMA (mis. outer+inner box 1 order). Coba semua kombinasi gabung/pisah part dalam `prod_product` yang sama, ranking by biaya kertas+plate total, hasilkan 1/lebih `prod_layout_group` | ❌ Belum — ini akar masalah yang diangkat user (kelemahan sistem manual: gabung/pisah tanpa aturan), **prioritas tertinggi** dari 10 tool ini | Konsep gabungan Esko Phoenix combination-run + Insoft Imp cost-based imposition, diterapkan ke skala intra-product bukan lintas-order |

**Catatan jujur**: Tool #3 (Interlocking), #6, #8 (CAD import bentuk asli,
bukan bounding-box) itu levelnya signifikan lebih rumit — butuh representasi
GEOMETRI POLYGON asli (bukan cuma W×H persegi panjang), rendering SVG/canvas
interaktif, dan kemungkinan library geometri (clipping/rotation polygon).
Ini BUKAN 1-2 hari kerja kalau mau serius — realistis dikerjakan bertahap.
Tool #10 lebih sederhana dari itu (masih bounding-box W×H, "cuma" kombinasi
gabung/pisah + bandingkan total biaya) — realistis jadi titik mulai paling
masuk akal krn langsung jawab masalah inti yang diangkat user.

### 4.3a Desain Algoritma ENGINE (klarifikasi user 2026-09-02)

User kasih contoh konkret: lidah (flap) 5cm → cuma dapat 1 UP; lidah
dipendekkan ke 2,5cm → bisa 2 UP (rotate) atau malah 3 UP (kombinasi 1
horizontal + 2 vertical via rotate 90°); atau pola "69" (2 unit diputar
180° saling mengunci, sebagian pindah posisi). Harapannya ENGINE "sepintar
AI" — paham trade-off ini sendiri, bukan officer coba-coba manual.

Ini **BUKAN 1 masalah, tapi 2 tingkat kesulitan berbeda** — saya pisahkan
supaya jujur soal mana yang realistis dikerjakan cepat vs mana yang
riset/effort besar:

**Tingkat 1 — Parametric Sweep (REALISTIS, extension langsung dari
`ImpositionEngine` yang sudah ada, TIDAK butuh data geometri baru selain
1 rentang angka)**

Prinsip: lidah bukan angka TETAP, tapi **variabel dalam rentang yang masih
valid secara struktural** (`flap_min_mm` s.d. `flap_max_mm` — d ibawah
`flap_min` sambungan lem tidak kuat, di atas `flap_max` boros bahan/tidak
sesuai desain awal). Engine tinggal **loop nilai lidah dalam rentang itu**,
tiap nilai hitung ulang pakai `ImpositionEngine::run()` yang SUDAH ADA
(sudah otomatis coba TEGAK/PUTAR/CAMPUR tiap panggilan), lalu ranking hasil
by UP count:

```
untuk flap in range(flap_min_mm, flap_max_mm, step=5mm):
    dim_efektif = body_dim + flap   // tergantung flap_side (atas/bawah/kiri/kanan)
    hasil = ImpositionEngine::run(plano, dim_efektif, ...)   // sudah coba 3 orientasi
    simpan {flap, hasil.up, hasil.method, hasil.efficiency}

urutkan semua hasil by up DESC, efficiency DESC
tampilkan TOP 3-5 ke officer dengan preview visual tiap opsi
officer PILIH salah satu (Engine suggests, Officer confirms — pola yang
SUDAH terkunci di CLAUDE.md §4 utk scheduling/routing, dilanjutkan di sini)
```

Ini persis menjawab contoh user (lidah 5cm→1up vs 2,5cm→2-3up) — TINGGAL
loop nilai lidah, tidak perlu algoritma baru sama sekali. **Field baru yang
dibutuhkan** di `prod_part`: `flap_side` (enum TOP/BOTTOM/LEFT/RIGHT),
`flap_length_mm` (nilai desain saat ini), `flap_min_mm`/`flap_max_mm`
(rentang yang masih valid struktural).

**Tingkat 2 — Interlock/"69" Pattern, DIREVISI 2026-09-02: SHIFT jadi
lever UTAMA, flap-shrink jadi lever SEKUNDER**

Klarifikasi user: dari kasus-kasus yang sudah dia lihat, **kebanyakan
solusi interlock adalah GESER posisi** (translasi kiri-kanan/atas-bawah
unit yang diputar 180°, PANJANG LIDAH TETAP), bukan mengecilkan lidah —
tapi **ADA JUGA** kasus yang memang perlu dikecilkan. Jadi algoritmanya
perlu 2 variabel independen yang dicoba BERSAMAAN, bukan cuma 1:

1. **`shift_offset_mm`** — geser unit kedua sepanjang sisi yang
   berhimpitan (variabel UTAMA, sesuai observasi user) — dicoba dalam
   rentang `[0, body_length]`, step tertentu.
2. **`flap_length`** — dalam rentang `[flap_min_mm, flap_max_mm]`
   (variabel SEKUNDER dari Tingkat 1, tetap dicoba tapi bukan fokus
   utama).

```
untuk shift in range(0, body_length_mm, step=5mm):
    untuk flap in range(flap_min_mm, flap_max_mm, step=5mm):
        pitch = body_length - overlap(shift, flap, flap_width_mm, flap_offset_mm)
        hasil = hitung_up(plano, body_dim, pitch, ...)  // coba row-interlock & col-interlock, x rotate 90°
        simpan {shift, flap, hasil.up, hasil.efficiency}

urutkan semua hasil by up DESC, efficiency DESC
tampilkan TOP 3-5 ke officer dgn preview visual (termasuk yg shift-only,
flap tetap — sesuai pola yg PALING SERING user lihat)
```

Field geometri part yang dibutuhkan sama seperti draf sebelumnya
(`flap_width_mm`, `flap_offset_mm`) — model **badan persegi + 1 persegi
kecil (lidah) nempel di 1 sisi**, BUKAN import polygon dieline penuh
(itu Tool #8, effort jauh lebih besar).

**Soal "ENGINE dengan AI" — pendapat saya (diminta menimbang, bukan
sekadar setuju)**: masalah ini ("geser + kadang kecilkan, dalam rentang
terbatas, cari kombinasi terbaik") secara teknis adalah **masalah
optimasi geometri/kombinatorial klasik**, BUKAN kasus yang butuh Machine
Learning:
- Esko sendiri ("AI-driven" di marketing mereka) intinya tetap algoritma
  pencarian/heuristik di baliknya, bukan neural network yang belajar dari
  data training — dicek langsung ke dokumentasi ArtiosCAD (§7 poin 7),
  tidak ada indikasi model ML di balik fitur nesting-nya.
- **Belum ada data training** (Anda sendiri bilang "contoh kasus belum
  saya dapat") — ML butuh puluhan-ratusan contoh riil berlabel sebelum
  bisa diandalkan; loop pencarian 2-variabel (`shift` × `flap`) di atas
  **tidak butuh data training sama sekali**, langsung hitung matematis.
- **Explainability**: kalau engine salah pilih layout (berdampak ke harga
  jual), loop pencarian bisa dijelaskan persis ("shift 15mm + flap 25mm
  menang krn UP=6 vs alternatif UP=4") — model ML "black box" jauh lebih
  susah diaudit officer/Direksi saat harga dipertanyakan.

**Rekomendasi saya**: bangun sbg **algoritma pencarian deterministik**
(loop 2-variabel di atas, "terasa pintar" krn eksplorasi kombinasi yang
manusia malas coba manual, TAPI bukan ML) — BUKAN model AI/ML
sungguhan. Kalau nanti sudah terkumpul banyak data trial riil (officer
pilih opsi mana dari rekomendasi top-3/5 tiap kali), BARU pertimbangkan
lapisan ML KECIL di atasnya (murni utk ranking "opsi mana yg biasanya
dipilih officer", bukan menggantikan perhitungan geometri) — itu pun
baru masuk akal setelah ada riwayat data, bukan sekarang. **Ini rekomendasi
saya, keputusan akhir tetap di Anda** — kalau tetap mau explore arah ML
sekarang, saya perlu tahu dulu infrastruktur/budget training data yang
tersedia sebelum bisa kasih rencana konkret.

**Rekomendasi urutan kerja**: Tingkat 1 dulu (cepat, langsung pakai engine
existing) → Tingkat 2 (loop shift+flap gabungan) begitu field geometri
(`flap_width_mm`, `flap_offset_mm`) siap diisi officer (perlu training
singkat cara ukur dari dieline).

**Soal batas `flap_min_mm`/`flap_max_mm`** [KEPUTUSAN 2026-09-02]: ini
ATURAN TEKNIK (kekuatan lem/struktur, beda per material/jenis lem), bukan
angka bebas — user pilih **tabel referensi baru `mat_flap_rule`**
(§4.2a poin 5), pola sama `MatFluteAllowance`
(`app/Models/Master/MatFluteAllowance.php`, sudah jadi preseden tabel
serupa utk geometri corrugated, dipakai `CostingEngine.php` SHEET_DIGITAL
yg sudah diverifikasi byte-presisi ke Excel asli). **BUTUH DATA RIIL dari
user** (aturan lem/struktur per material CV. Telasih Makmur) sebelum tabel
ini terisi benar — lihat §7 poin 8, jadi prasyarat sebelum ENGINE Tingkat 1
dipakai kalkulasi harga produksi sungguhan (boleh mulai development dgn
angka placeholder dulu).

### 4.4 Data Flow In/Out

```
Inquiry Product (iqp_part: flat_w/flat_h, qty, spec kasar)
        │
        ▼
Product Development  (layar kerja, prod_part diperluas + 3 tabel anak)
  Input: spec dari Inquiry + upload JPEG/PDF/AI (prod_part_attachment)
  Proses: Tools #1-#9 §4.3 → interaktif, preview visual live di layar
  Output: prod_part (kolom teknis terisi) + prod_part_process (routing
          terstruktur) + prod_layout_group (hasil ENGINE §4.3a: pilihan
          flap/orientasi terbaik, bisa gabung >1 part) — REUSABLE,
          bukan cuma milik 1 Inquiry
        │
        ├─ tombol "Cetak Master Card" → dokumen PDF resmi (§4.1),
        │  komponen reusable spt Nota lain, utk approval multi-role
        │
        ├──────────────► Product Costing
        │                 Mode MANUAL: officer isi i_cut_per_plano/
        │                 i_isi_per_lb/dst sendiri (spt sekarang)
        │                 Mode AMBIL DATA (default/diutamakan, §5.1):
        │                 auto-calculate dari prod_layout_group — TIDAK
        │                 butuh officer Costing terpisah, rumus di-hidden
        │
        └──────────────► Production (WO Generation)
                          prod_part_process (routing terstruktur, bukan
                          teks) → jadi dasar WO steps langsung
                          die_code/die_w_cm/die_h_cm → validasi mesin/tooling
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
- **Mode Ambil Data** (klarifikasi user 2026-09-02 — ini yang DIUTAMAKAN,
  bukan cuma opsi tambahan): kalau Inquiry punya `prod_layout_group`
  terkait (dari Product Development), SEMUA parameter imposition
  (`isi_satu_cetakan` → `i_isi_per_lb`, ukuran plano/cetak, dst) **ditarik
  otomatis dan HPP langsung ke-KALKULASI** — rumus di-hidden dari officer,
  bukan sekadar auto-fill field yang lalu masih perlu di-"Hitung HPP"
  manual. **Tujuan eksplisit user**: pekerjaan yang tadinya butuh 2 orang
  (1 officer ProDev + 1 officer Costing terpisah) jadi bisa dikerjakan
  **1 orang saja** — begitu layout dikonfirmasi di ProDev, HPP sudah jadi,
  officer yang sama tinggal lanjut isi margin/tolerance/tier harga (bagian
  yang MEMANG masih perlu keputusan manusia, bukan bisa diotomasi).

**Belum diputuskan** (perlu didiskusikan, BUKAN saya putuskan sendiri):
apakah toggle ini per-part atau per-costing (1 costing bisa campur part
manual + part ambil-data)? Kemungkinan besar per-part, konsisten dgn
`iqp_costing_part` yang sudah granular per part. Juga: kalau 1
`prod_layout_group` menaungi >1 part (§4.2b), CostingEngine WAJIB alokasi
biaya plano proporsional per part — bukan cuma "tarik data", ada logic
kalkulasi baru yang perlu ditulis.

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

**Sebagian sudah DIKUNCI user (2026-09-02), sebagian masih opsi:**

1. **[DIKUNCI] Skema data = perluasan `prod_product`/`prod_part`** (bukan
   tabel Master Data baru terpisah) + 3 tabel anak baru
   (`prod_part_process`, `prod_part_layout`, `prod_part_attachment`) — lihat
   §4.2/4.2a. `vw_master_card` (view lama) kemungkinan besar PENSIUN
   digantikan query langsung ke `prod_part`+relasinya, atau di-rewrite jadi
   view di atas skema baru — perlu dicek dulu apa saja yang masih bergantung
   ke view lama sebelum diubah/dihapus.
2. **[DIKUNCI] Imposition pindah dari Inquiry ke Product Development** —
   `iqp_layout` (1:1 ke `iqp_part`, nempel 1 Inquiry) digantikan
   `prod_layout_group` (+ junction `prod_layout_group_part`, di `tm2mst`,
   REUSABLE lintas Inquiry, bisa menaungi >1 part — §4.2b). Perlu keputusan
   teknis: `iqp_layout` yang sudah ada datanya (kalau ada) di-migrasi atau
   dibiarkan legacy?
3. **[BELUM DIPUTUSKAN] `WoGenerationService` baca routing dari
   `prod_part_process`** — perubahan ke Production module, di luar scope
   Product Development sendiri tapi konsekuensi lanjutannya. `prd_wo`
   sekarang 1:1 ke `iqp_part` (bukan `prod_part` langsung) — perlu jalur
   penghubung yang jelas (`iqp_part` snapshot dari `prod_part` yang mana),
   lihat gap sudah dicatat di `production_scheduling_wo_generation_delta.md`.
4. **[BELUM DIPUTUSKAN] Aktivasi menu `PROD_CARD`/`PROD_LAYOUT`** — tinggal
   flip `activate=true` + arahkan route ke layar ProDev baru (bukan bikin
   kode menu baru), tapi perlu dipastikan dulu layar barunya sudah siap
   sebelum menu di-unhide (supaya tidak nunjuk ke halaman kosong).

Ini semua **perubahan arsitektural** (skema data, bukan cuma tambah layar)
— sesuai CLAUDE.md §7.1, item yang masih "BELUM DIPUTUSKAN" WAJIB
dikonfirmasi user dulu sebelum dieksekusi.

---

## 7. Pertanyaan Terbuka / Keputusan yang Dibutuhkan

Sebagian besar sudah dijawab user via pertanyaan pilihan-ganda
2026-09-02 sore — dicatat di sini, coretan (~~...~~) = sudah selesai:

1. ~~Prioritas tool?~~ **[TERJAWAB]**: user SETUJU urutan yang diusulkan
   — Tool #1 (pindah rumah + skema baru) → **Tingkat 1 ENGINE §4.3a**
   (parametric flap sweep) → #10 (Intra-Product Combiner) → #2
   (Pcs→Plano) → #4 (Die-Rule) → baru yang lebih rumit (Tingkat 2
   ENGINE/interlock, #3/#6/#8). **Ini urutan kerja resmi kalau eksekusi
   dimulai.**
2. ~~Dimensi Master Card di level Part atau Product?~~ **[TERJAWAB
   2026-09-02, §4.2b]**.
3. ~~Sumber `Kode Item`?~~ **[TERJAWAB 2026-09-02]**: ditunda, pakai
   nomor urut sementara (PK `id` auto-increment yang sudah ada, mis.
   tampilkan `MC-{id}` apa adanya) — pola penamaan mnemonic final
   diputuskan belakangan, TIDAK memblokir pengembangan sekarang.
4. ~~Toggle Manual/Ambil-Data di Costing — per-part atau per-costing?~~
   **[TERJAWAB]**: **per-part**, konsisten dgn `iqp_costing_part` yang
   sudah granular per part — 1 costing boleh campur part manual + part
   ambil-data.
5. ~~Finishing jadi kolom teks atau tabel anak?~~ **[TERJAWAB]**: **tabel
   anak** — jadi `prod_part_finishing` (bukan `finishing_notes` teks di
   §4.2), 1 baris per jenis finishing, siap dikaitkan ke biaya/urutan
   proses masing-masing di Costing/routing nanti.
6. ~~Batas upload file? AI wajib disertai PDF export?~~ **[TERJAWAB
   2026-09-02]**: batas **25MB** (`PRODEV_UPLOAD_MAX_MB`, §4.2c — riset
   saya ke kebijakan upload printer packaging lain menghasilkan range
   25-100MB, user pilih titik awal 25MB). AI **TIDAK wajib** disertai PDF
   — kalau preview-nya sulit, **cukup JPEG** sbg sumber preview visual
   (§4.2a poin 3), AI/PDF opsional sbg referensi/download saja.
7. ~~Sumber dokumen & kepercayaan angka 12-22%?~~ **[TERJAWAB 2026-09-02,
   DIPERKUAT dgn riset lanjutan]**: dikonfirmasi dari Gemini (diminta
   riset ke Esko). Cross-check langsung ke `docs.esko.com` — **konsep
   mekanismenya valid** (ArtiosCAD Layout Rubber tool memang punya
   rotasi/tumbling + hitung efisiensi), tapi angka 12-22% ITU SENDIRI
   TIDAK ditemukan sbg 1 statistik resmi tunggal. **Riset putaran ke-2**
   (sisa waktu sblm task 15:45): ketemu **studi kasus RIIL Esko Phoenix**
   (folding carton converter, 3000+ SKU/bulan) — items per sheet naik
   **2,1 → 2,5**, setara **~16-19% penghematan material** (dihitung dari
   angka itu, bukan diklaim langsung oleh Esko sbg %). Plus 1 sumber
   independen (industri die-cutting web-fed): **hingga 18%** pengurangan
   board dari nesting+eliminasi gripper margin. **2 sumber independen
   riil mendarat di ~16-19%, PAS di tengah 12-22%** — menaikkan
   kepercayaan range ini jadi estimasi wajar (bukan lagi angka tanpa
   dasar sama sekali), meski tetap BUKAN jaminan utk mesin/spesifikasi
   Telasih Makmur sendiri. `PRODEV_INTERLOCK_SAVING_MIN/MAX_PCT`
   (§4.2c) tetap dipakai, **adjust nanti kalau trial nyata beda** —
   bukan angka final. Sources: [Esko Phoenix Case Study](https://www.esko.com/en/resources/large-folding-carton-converter-phoenix).
8. ~~`flap_min_mm`/`flap_max_mm` — tabel referensi atau manual per
   part?~~ **[TERJAWAB]**: **tabel referensi** (pola `MatFluteAllowance`)
   — `mat_flap_rule` (§4.2a poin 5). Riset saya ke Esko/standar industri
   **tidak ketemu 1 angka mm universal yang bisa dipercaya** (proprietary
   tiap converter) — 1 angka yang sempat ketemu ("1.5× tebal board") GAGAL
   diverifikasi ulang ke sumber aslinya (dicoba fetch 2×, gagal), jadi
   TIDAK dipakai. **Cek ulang ke 5 file MC-*.xlsx juga TIDAK BISA dipakai**
   — `Ukuran Pisau` di situ adalah ukuran 1 PLAT PISAU utk SEMUA up
   sekaligus (mis. 4-UP), bukan 1 unit+lidah, jadi tidak bisa dihitung
   mundur tanpa menebak susunan barisnya. Keputusan user: **seharusnya
   FORMULA bergantung panjang & lebar tutup** (bukan 1 angka flat per
   material) — belum diimplementasi, `PRODEV_FLAP_MIN_MM=20` (2cm, §4.2c)
   jadi fallback global SEMENTARA sampai formula & `mat_flap_rule` siap.
   **Angka riil dari tim produksi Telasih Makmur sendiri masih jadi
   prasyarat** sebelum dipakai kalkulasi harga sungguhan.
9. ~~Berapa banyak contoh kasus riil dibutuhkan sebelum engine teruji?~~
   **[TERJAWAB 2026-09-02, sebagian]**: belum ada contoh kasus siap
   (beda dari `CostingEngine.php` SHEET_DIGITAL yg sudah diverifikasi ke
   1 Excel asli) — user akan **trial ENGINE langsung sampai match dgn
   keputusan ProDev officer riil** (bukan verifikasi di depan pakai
   dataset). Observasi user yang PENTING & sudah masuk desain (§4.3a
   Tingkat 2 direvisi): **kebanyakan kasus = GESER posisi (shift), bukan
   kecilkan lidah** — cuma sebagian yang genuinely butuh flap dikecilkan.
   Ini kenapa Tingkat 2 sekarang loop 2 variabel (`shift_offset_mm` +
   `flap_length`) bersamaan, bukan cuma flap seperti draf awal.
10. **[BARU] "ENGINE dengan AI"?** User usul pakai AI/ML utk masalah ini
    (scope terbatas ke imposition/nesting saja). **Rekomendasi saya:
    TIDAK** — ini masalah optimasi geometri klasik (2 variabel, bisa
    dihitung langsung), bukan kasus yang butuh ML (blm ada data training,
    ML "black box" susah diaudit utk keputusan yg pengaruhi harga jual).
    Alternatif yang saya usulkan: algoritma pencarian deterministik yg
    "terasa pintar" (eksplorasi kombinasi byk) tapi tetap bisa dijelaskan
    — detail alasan di §4.3a. **Keputusan akhir tetap di user** — ini
    baru pendapat saya, belum final.

---

## Sumber Riset Web (dicek 2026-09-02)

- [Phoenix AI Planning & Imposition Software](https://www.esko.com/en/products/phoenix) — Esko
- [Rethinking Print Layouts: Esko Phoenix](https://www.esko.com/en/blog/rethinking-print-layouts-smarter-planning-imposition-with-esko-phoenix)
- [Prinect Signa Station Packaging Pro](https://www.heidelberg.com/global/en/software/workflow/prinect_modules/packaging_workflow_1/prinect_signa_station_packaging_pro/product_information_113/prinect_signa_station_packaging_pro.jsp) — Heidelberg
- [InSoft Automation — Imp](https://insoftautomation.com/) · [Latest Update (No-Mix-Gang)](https://insoftautomation.com/latest-update/)
- [Packaging Layout Automation (PLA) — Pandora 11.0](https://workflowhelp.kodak.com/display/PANDORA110/Packaging+Layout+Automation+(PLA)+Software+User+Guide) — Kodak

**Ronde 2 (verifikasi klaim §7 poin 6-8):**

- [ArtiosCAD Layout Rubber tool](https://docs.esko.com/docs/en-us/artioscad/14.1/userguide/en-us/common/ac/topic/UG4_Manufacturing_id1286088UNG28.html) — Esko, konfirmasi rotasi/tumbling + efficiency %, **TIDAK ada angka hemat 12-22%**
- [instantprint file size guide](https://www.instantprint.co.uk/printspiration/print-design-tips/file-type-and-size-guide), [PakFactory artwork guide](https://pakfactory.com/guide-to-preparing-files-for-print) — dasar range 25-100MB batas upload
- Pencarian glue flap minimum mm (Sttark, Bailipack, JohnsByrne, 3M/OZPak spec) — **TIDAK ketemu angka mm universal yang bisa diverifikasi**, dicatat sbg temuan negatif (bukan diabaikan)

**Ronde 3 (perkuat estimasi 12-22%, 2026-09-02 sore):**

- [Esko Phoenix Case Study — Large Folding Carton Converter](https://www.esko.com/en/resources/large-folding-carton-converter-phoenix) — studi kasus RIIL, items/sheet 2,1→2,5 (≈16-19% hemat material), sumber paling kuat yang ditemukan sejauh ini
- Industri die-cutting web-fed (hingga 18% pengurangan board dari nesting+eliminasi gripper margin) — sumber independen ke-2, rentang serupa

**Ronde 4 (persiapan ENGINE + UI/UX, 2026-09-02 sore, sblm eksekusi otomatis):**

- **Alokasi biaya gang-run/sheet gabungan** (relevan §4.2b/§5.1 — CostingEngine perlu logic baru utk `prod_layout_group` yg menaungi >1 part): norma industri adalah **alokasi PROPORSIONAL** — setup/make-ready cost (yang jadi biaya utama dibagi, BUKAN cuma biaya kertas) displit ke semua job yg berbagi 1 lembar, bukan dibebankan penuh ke 1 pihak ("every customer on the sheet splits that one-time cost, so nobody pays for the full setup alone" — [Gang run printing, Wikipedia](https://en.wikipedia.org/wiki/Gang_run_printing) & [Formax Printing](https://www.formaxprinting.com/blog/printing-lingo-what-is-a-gang-run-or-combination-run)). **Konfirmasi**: pendekatan "alokasi proporsional per part berdasar luas area" yang sudah didesain di §4.2b/§5.1 SEJALAN dgn norma industri, bukan pendekatan yang mengada-ada.
- **FEFCO** (European Federation of Corrugated Board Manufacturers) — badan standar RESMI utk parameter glue flap per kode box style (mis. FEFCO 0201, 0501, tiap kode punya "Glue Flap Parameters" sendiri — lihat [EngView FEFCO 0501](https://www.engview.com/online_help/7.3/packaging_library/en/Corrugated_Standards_Library/FEFCO_Standards_Library/F_0500/0501/0501_Glue_Flap_Parameters.htm)/[0201](https://www.engview.com/online_help/7.3/packaging_library/en/Corrugated_Standards_Library/FEFCO_Standards_Library/F_0200/0201/0201_Glue_Flap_Parameters.htm)). Angka mm eksak tidak ke-fetch penuh (dokumen berbayar/butuh akses terstruktur), TAPI ini **sumber otoritatif yang benar** utk `mat_flap_rule` (§4.2a poin 5) ke depannya — LEBIH BAIK dari blog generik yang sudah dicoba Ronde 2. Rekomendasi: kalau isi `mat_flap_rule` nanti, cek FEFCO code box style yg dipakai Telasih Makmur dulu, baru cari parameter glue flap resminya (via EngView atau FEFCO langsung), bukan tebak dari blog.
- **Algoritma packing 2D** (validasi pendekatan Tingkat 1/2 §4.3a): riset akademik ([Frontiers in Mechanical Engineering review](https://www.frontiersin.org/journals/mechanical-engineering/articles/10.3389/fmech.2022.966691/full)) konfirmasi heuristik **Bottom-Left (BL)** & **corner-occupying search** sbg pendekatan standar utk 2D rectangle packing — **loop pencarian deterministik yang sudah didesain (bukan ML) SEJALAN dgn praktik akademik/industri**, bukan pendekatan naif.
- **UI/UX referensi Esko** (permintaan user: "esko punya alternatif UI/UX boleh dipakai"): dari deskripsi Phoenix/ArtiosCAD (User Guide resmi, PDF terlalu besar utk di-fetch penuh — >10MB) & modul "Layouts in canvases" — pola workspace-nya: **canvas multi-part** (grup part berdasar jenis substrat/material) + **panel parameter** di sisi + **preview layout visual** langsung di canvas yang sama. Ini **KONFIRMASI struktur layar ProDev yang sudah direncanakan** (§4.2a: form input di kiri + preview visual live di kanan, mirip `imposition.blade.php` existing) — TIDAK perlu redesign, cukup pastikan preview canvas cukup besar/jadi fokus utama layar (bukan form yang dominan), krn itu yang ditekankan Esko jg.
