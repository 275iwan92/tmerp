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
| Jenis Finishing (bisa multi: Laminating + UV + Hotprint) | `Laminating Doff, UV Spot, Hot Print Gold Puyeh` | ✅ `prod_part.finishing_notes` (text) — ATAU child table `prod_part_finishing` kalau mau tiap finishing punya biaya/urutan sendiri (lebih tepat kalau finishing juga masuk hitungan Costing per-item, bukan cuma catatan) |
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
   `resources/views/pages/master/employee.blade.php:499`) — **AI file
   TIDAK bisa di-preview inline browser** (bukan format native web), cuma
   disimpan+bisa didownload; JPEG/PDF bisa preview inline
   (`<img>`/embed PDF).

**Temuan penting**: `Kode Pisau` (nama/rujukan pisau, string) dan
`Ukuran Pisau` (dimensi fisik pisau pond, BEDA dari ukuran cetak — pisau
biasanya lebih besar dari area cetak krn ada margin gripper) adalah 2
**field terpisah** di form asli. `vw_master_card` baru punya yang pertama.
Ini konfirmasi ada gap kolom nyata, bukan cuma gap layar.

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

**Tingkat 2 — Interlock/"69" Pattern (LEBIH RUMIT, butuh geometri
tambahan + algoritma baru, bukan sekadar extend yang ada)**

Pola "69" = 2 unit diputar 180° satu sama lain sehingga lidah unit kedua
"masuk" ke celah kosong di sebelah badan unit pertama — jaraknya (pitch)
antar unit jadi LEBIH PENDEK dari body+lidah biasa. Ini beda dari Tingkat 1
krn butuh tau **bentuk lidah itu sendiri** (bukan cuma panjangnya):
lebar lidah (`flap_width_mm`) dan seberapa jauh lidah itu dari sudut
(`flap_offset_mm`) — model bentuk part disederhanakan jadi **badan persegi
+ 1 persegi kecil (lidah) nempel di 1 sisi**, BUKAN import polygon dieline
penuh (itu Tool #8, jauh lebih besar effort-nya).

```
pitch_interlock = body_length - overlap
overlap = min(flap_length - clearance_minimum, ruang_notch_tersedia)
// ruang_notch_tersedia dihitung dari flap_width & flap_offset unit tetangga

coba pola interlock arah baris DAN arah kolom, masing2 x rotate 90°
bandingkan up count vs hasil Tingkat 1, menang yg UP-nya lebih banyak
```

**Rekomendasi urutan kerja**: Tingkat 1 dulu (cepat, langsung pakai engine
existing, jawab 80% kasus yg user contohkan) → baru Tingkat 2 kalau
Tingkat 1 belum cukup memuaskan hasil nyata di lapangan. Tingkat 2 BUTUH
data baru (`flap_width_mm`, `flap_offset_mm`) yang belum tentu gampang
diisi officer tanpa training tambahan (perlu ukur dari dieline asli).

**Soal batas `flap_min_mm`/`flap_max_mm`**: ini ATURAN TEKNIK (kekuatan
lem/struktur, beda per material/jenis lem), bukan angka bebas. Ada
preseden di kode: `MatFluteAllowance` (`app/Models/Master/MatFluteAllowance.php`)
sudah jadi tabel serupa utk geometri corrugated (dipakai `CostingEngine.php`
SHEET_DIGITAL, sudah diverifikasi byte-presisi ke Excel asli) — bisa
dicontoh polanya bikin tabel sejenis utk aturan lidah, ATAU lebih simpel:
officer isi `flap_min_mm`/`flap_max_mm` manual per part (compromise MVP,
tidak butuh tabel referensi baru dulu). **Ini salah satu pertanyaan di
§7.**

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

1. **Prioritas tool** — dari 10 tool §4.3, mana yang mau dikerjakan duluan?
   Realistis bertahap: Tool #1 (pindah rumah + skema baru) → **Tingkat 1
   ENGINE §4.3a** (parametric flap sweep, langsung jawab contoh user) →
   #10 (Intra-Product Combiner) → #2 (Pcs→Plano) → #4 (Die-Rule) → baru
   yang lebih rumit (Tingkat 2 ENGINE/interlock, #3/#6/#8).
2. ~~Dimensi Master Card di level Part atau Product?~~ **[TERJAWAB
   2026-09-02, §4.2b]**.
3. ~~Sumber `Kode Item`?~~ **[TERJAWAB 2026-09-02]**: ditunda, pakai
   nomor urut sementara (PK `id` auto-increment yang sudah ada, mis.
   tampilkan `MC-{id}` apa adanya) — pola penamaan mnemonic final
   diputuskan belakangan, TIDAK memblokir pengembangan sekarang.
4. **Toggle Manual/Ambil-Data di Costing** — per-part atau per-costing
   (§5.1)?
5. **Finishing jadi kolom teks atau tabel anak?** (§4.2 baris Jenis
   Finishing) — tergantung apakah tiap jenis finishing butuh biaya/urutan
   sendiri di Costing/routing, atau cukup catatan deskriptif saja.
6. **Batas upload file** (§4.2a) — ukuran maks, apakah AI wajib disertai
   PDF export (utk preview) atau cukup file AI mentah tersimpan?
7. **Sumber dokumen** (`imposition_nesting`, `gemini_spark_productcosting.md`,
   `mockup_mastercard.jpg`) — dari riset user sendiri atau AI lain (Gemini)?
   Perlu tahu tingkat kepercayaan formulanya, terutama klaim hemat 12-22%
   dari Interlocking Nesting (ini angka umum industri, belum tentu pas
   dgn mesin/spesifikasi CV. Telasih Makmur — jangan dipakai mentah-mentah
   sbg target performa).
8. **[BARU] `flap_min_mm`/`flap_max_mm` (§4.3a) — dari tabel aturan
   teknik (pola `MatFluteAllowance`) atau diisi manual per part oleh
   officer?** Tabel referensi lebih konsisten/aman (tidak bisa asal isi
   angka salah), tapi butuh data riil dari user (aturan lem/struktur per
   material — belum ada di dokumen manapun yang saya baca) sebelum bisa
   dibangun. Manual per part lebih cepat dibangun tapi rawan human-error.
9. **[BARU] Berapa banyak contoh kasus flap/interlock riil dibutuhkan
   sebelum ENGINE Tingkat 1 dianggap cukup teruji?** — beda dari
   `CostingEngine.php` SHEET_DIGITAL yang sudah diverifikasi byte-presisi
   ke 1 Excel asli, engine imposition ini BELUM ada data pembanding riil
   sama sekali — perlu tau apakah ada dokumen/kasus serupa
   `docs/attachments/` yang bisa jadi bahan verifikasi sebelum dipakai
   produksi sungguhan.

---

## Sumber Riset Web (dicek 2026-09-02)

- [Phoenix AI Planning & Imposition Software](https://www.esko.com/en/products/phoenix) — Esko
- [Rethinking Print Layouts: Esko Phoenix](https://www.esko.com/en/blog/rethinking-print-layouts-smarter-planning-imposition-with-esko-phoenix)
- [Prinect Signa Station Packaging Pro](https://www.heidelberg.com/global/en/software/workflow/prinect_modules/packaging_workflow_1/prinect_signa_station_packaging_pro/product_information_113/prinect_signa_station_packaging_pro.jsp) — Heidelberg
- [InSoft Automation — Imp](https://insoftautomation.com/) · [Latest Update (No-Mix-Gang)](https://insoftautomation.com/latest-update/)
- [Packaging Layout Automation (PLA) — Pandora 11.0](https://workflowhelp.kodak.com/display/PANDORA110/Packaging+Layout+Automation+(PLA)+Software+User+Guide) — Kodak
