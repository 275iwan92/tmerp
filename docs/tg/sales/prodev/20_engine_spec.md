# Kartu ENGINE: Spec Engine (BOP, BOM, Ink)

> DRAFT KELOMPOK C (2026-10-16), menunggu persetujuan Jo. Sumber: `app/Services/ProDev/SpecEngine.php` dibaca penuh (1248 baris). Tes yang dijalankan: `WoBomFromInquiryTest` (1 lulus, 60 assert). Contoh angka dihitung sendiri dari data `ddt*` lewat `forWorkOrder` (tanpa menulis DB) dan dicek tangan.

- **Nama kanonik**: Spec Engine. **Kelas**: `App\Services\ProDev\SpecEngine`. **Registry**: key `spec`, mode `suggest`, grup Pro-Dev. **Modul/sub-modul**: Sales > Inquiry & Quotation > Pro-Dev (hilir: Production > Work Order).
- **Status beku**: ProDev = zona beku. Patch `processOfBom` sudah diterapkan atas izin Jo 14 Okt (lihat ATTENTION-ENGINE-01, status SELESAI di sana). Jangan ubah alur tanpa izin Jo.
- **Sinonim**: engine BOP BOM, Bill of Process, Bill of Material, Ink Configuration, "engine ProDev".
- **BUKAN sama dengan**: `ImpositionEngine` (geometri, kartu `10_engine_imposition.md`); **BUKAN** `CostingEngine` (harga).

## Pemicu (tidak ada background / jadwal)
Dicari di `routes/console.php` (tidak ada), `app/Jobs` (tidak ada), Observers (tidak ada). FAKTA, semua pemanggil:
| Pemicu | Method | Berkas:baris |
|---|---|---|
| ProDev: panel BOM/Ink/BOP dihitung ulang (JS `w.call('generateSpec', ...)`) | `generate` | `resources/views/pages/production/prodev.blade.php:347` (JS `:2279`); route v2 `routes/v2.php:1667-1675` (`Prodev::CALLS`: `generateSpec`, `saveLayout`) |
| ProDev: tombol Save / Confirm (`saveLayout`) | `generate` lalu `persist` | `prodev.blade.php:316-318` |
| ProDev: dropdown pencarian bahan | `stockFor` | `prodev.blade.php:373` |
| ProDev: data awal layar | `saved`, `tierQty` | `prodev.blade.php:673-674` |
| Pembuatan WO dari jadwal kirim CPDEL | `forWorkOrder` | `WoGenerationService.php:105` (`generateFromDelivery`) |
| Tombol PROCESS di PPIC (susun ulang operasi) | `forWorkOrder` | `WoGenerationService.php:190` (`rebuildOperations`), `:263` (`engineBomCount`), `:279` (`regenerateBom`); controller `PpicController.php:444` (`processWo`) |
| Pembuat data dummy Master Card | `persist` (lewat `saveLayout`) | `app/Services/MasterCard/MasterCardDummyEngine.php:396` |
| Pustaka `x-mp::prodev.workbench` (devlib.test) | tidak memanggil | `packages/mockup-parts/resources/assets/js/components/prodev/workbench.js:38` hanya mengembalikan data contoh (`this.data.spec`) |
`ProductMasterSync` hanya menyebut engine ini di komentar (FAKTA, grep: tidak ada pemanggilan).

## Alur (kembaran teks dari diagram `../img/spec-engine.svg`)
1. Siapkan konteks: finishing (dari `iqp_part_finishing`, atau `prod_part_finishing` bila tanpa Inquiry = Mode #2), bahan pilihan Sales, aturan aktif `prod_bop_rule` (`part_type` kosong atau sama dengan tipe part), keputusan layout (sheet, plano, mesin, UP, qty).
2. Tentukan fakta bersyarat: `PLANO_NEEDS_CUT`, `MULTI_LAYER`, `NEEDS_GLUE`.
3. **Ink**: dari notasi warna `a/b` (depan/belakang) dan analisa artwork, buat baris tinta (CMYK bila >= 4 unit, sisanya spot).
4. **BOM**: baris KERTAS PLANO (selalu), lapisan SHEET/SF (atau "None"), lalu baris dari aturan yang punya `material_pattern`.
5. **BOP**: tiap aturan yang punya proses menjadi langkah (STANDARD bila kondisi cocok; FINISHING sekali per finishing yang dipilih). Langkah bersebelahan dengan `merge_group` sama digabung. Nama proses, status in-house dan target jam diisi (`decorate`).
6. **Rantai qty MUNDUR** dari qty order (`chain`): tiap langkah menerima keluaran langkah berikutnya + waste + toleransi. Lembar pertama yang masuk = `printSheets`.
7. **Kebutuhan BOM** dihitung dari `printSheets` (`figures`) dan dibandingkan dengan stok gudang.
8. Override officer (bagian `bop`/`bom`/`inks` yang diedit) menggantikan hasil engine di bagian itu saja.
9. Hanya `persist` yang menulis. Bagian yang sudah punya baris non-ENGINE tidak ditimpa (dilaporkan sebagai `skipped`).
**Titik keputusan officer (A.7)**: tiap bagian bisa di-override dan dikembalikan ("reset") ke hasil engine.

## Input / Output
- **Input (dibaca)**: `tm2mst.prod_bop_rule`, `prod_process`, `prod_machine` (+ `max_speed_per_hour`), `prod_part_process_machine_rate`, `prod_part_finishing`, `ref_finishing_type`, `prod_part_material`, `prod_part_ink`, `prod_part_process`, `mat_raw`, `mat_category`, `sup_supplier`; `tm2sdi.iqp_part` (+ `iqp_part_finishing`, `_material`, `_process`, `_ink`), `iqp_tier`, `iqp_layout`, `do_delivery`; `tm2mst.prod_layout_group` (+ `_part`); `tm2pur.pr_requisition`; `tm2inv.v_rm_available`; `tm2sys.sys_constant` `RIM_SHEETS` (bawaan 500). Tabel dan kolom ini dibaca langsung dari kode (SpecEngine.php:148-1051).
- **Output (nilai kembali)**: `generate` -> `inputs`, `bop`, `bom`, `inks`, `colors`, `override`, `warnings`. `forWorkOrder` -> `bop`, `bom` (tiap baris + `process_id`), `warnings`, `layout`.
- **Output (menulis DB)**: hanya `persist`: `tm2mst.prod_part_process`, `prod_part_material`, `prod_part_ink`; dan, bila ada Inquiry, `tm2sdi.iqp_part_process`, `iqp_part_material`, `iqp_part_ink` (SpecEngine.php:1153-1247). Registry hanya menyebut tabel `tm2mst` (lihat Temuan 3).
- **Siapa boleh menimpa**: `source` ENGINE ditimpa engine; OVERRIDE / MANUAL tidak ditimpa engine, hanya lewat "reset" officer (`deleteNonEngine`, `:1140`).

## FORMULA
Satuan: LBR = lembar cetak, PCS, M, KG. Semua dari kode; rumus bukan harga (aman di bagian umum).
1. **Qty order**: `ctx.orderQty` bila ada, kalau tidak qty tier utama Inquiry (`tierQty`: `iqp_tier` urut `is_primary` turun lalu `tier_seq`) (baris 69, 198-208).
2. **Rantai BOP** (`chain`, baris 330-372), untuk `i` dari langkah terakhir ke pertama:
   - `cut` = indeks langkah PLONG terakhir (nama proses diawali "PLONG", tanpa melihat besar huruf) atau langkah terakhir bila tidak ada. Langkah `i <= cut` dalam LBR, sesudahnya PCS.
   - `out` = `need` bila PCS; `ceil(need/up)` bila LBR (null bila `up` kosong).
   - `waste = ceil(out * waste_pct/100)`, `tol = ceil(out * tolerance_pct/100)`, `in = out + waste + tol`.
   - `need` untuk langkah sebelumnya = `in * up` bila LBR, `in` bila PCS.
   - `est_hours = round(in / target_qty_per_hour, 1)`.
   - `waste_pct`/`tolerance_pct` kosong dihitung 0.
   - Langkah yang digabung (`merge_group`): waste dan toleransi **dijumlah** (baris 270-271).
3. **printSheets** = `qty_in` langkah pertama ber-uom LBR (`firstSheets`).
4. **BOM per lembar** (`figures`, baris 689-698), `per`:
   - PLANO: `1 / sheetsPerPlano`
   - SHEET_LENGTH: `sheetW/100 * rate` (M per lembar)
   - SHEET_AREA: `sheetL * sheetW / 10000 * rate / 1000` (KG per lembar; rate g/m2)
   - INK_COVERAGE: `coverage_cm2_sheet / 10000 * rate / 1000` (KG per lembar)
   - PER_PCS: `rate * up / 1000`
   - PER_PACK: `rate * up / packQty` (hanya tampilan, lihat langkah 5)
   - basis lain (mis. AREA_UNKNOWN): kosong, officer isi manual.
5. **Kebutuhan**: `need = per * printSheets`; untuk PER_PACK: `ceil(orderQty/packQty) * rate`; bila uom LBR dibulatkan ke atas (`ceil`); disimpan 4 desimal.
6. **Ink**: `area_cm2 = die_w_cm * die_h_cm`, kalau kosong `flat_w * flat_h / 100` (flat dalam mm). `coverage_cm2 = pct/100 * area` (2 desimal); `coverage_cm2_sheet = coverage_cm2 * up` (1 desimal). Notasi `a/b` ditolak bila bukan pola digit/digit (warning).
7. **Status stok**: `CUKUP` bila `avail >= need` (setelah konversi ke satuan stok), `KURANG`, `TIDAK ADA`, `CEK SATUAN`, `BELUM ADA ITEM`. Konversi hanya: sama, LBR->RIM (`/RIM_SHEETS`), KG->G/GR (`*1000`), M->CM (`*100`), M->ROLL (panjang dari nama item). Lainnya null (lihat ATTENTION-ENGINE-04).

**Contoh angka (data ddt*, dihitung `forWorkOrder`, qty WO 1.000)**
- Part 532 (tipe DC, UP 3, isi pack 100, BOP dari aturan): Potong Plano -> Cetak -> QC -> Laminating -> Plong -> Kupas & Hitung -> Pack. Dari belakang: Pack out 1.000 in 1.000; Kupas & Hitung waste 0,5% = ceil(5) = 5, in 1.005; Plong out `ceil(1005/3)` = 335, waste 1,5% = ceil(5,025) = 6, toleransi 0,5% = ceil(1,675) = 2, in 343; Laminating out `ceil(343*3/3)` = 343, waste 4, tol 2, in 349; QC out 349, waste 2, in 351; Cetak out 351, waste 11, tol 4, in 366; Potong Plano out 366, waste 2, in 368. Hasil engine sama persis (368 LBR masuk), jadi `printSheets` = 368.
- Part 462 (DC, UP 4, plano 845x720 mm, pack 400, BOP berasal dari baris tersimpan MANUAL tanpa waste): `printSheets` = `ceil(1000/4)` = 250 LBR. BOM: KERTAS PLANO 250 LBR (per 1, `perPlano` 1); PLASTIK LAMINASI `SHEET_LENGTH`: 72/100*1 = 0,72 M/lembar * 250 = 180 M; LEM `SHEET_AREA`: 84,5*72/10000*10/1000 = 0,006084 KG/lembar * 250 = 1,521 KG; TALI RAFIA `PER_PACK`: `ceil(1000/400)=3` * rate 3 = 9 M. Semua cocok hasil engine. Tinta dan varnish/foil kosong karena "Artwork belum dianalisa" dan basis `AREA_UNKNOWN`.

## Kasus tepi
- Layout ProDev belum ada (`upCount` 0): `forWorkOrder` memakai 1 UP dan peringatan "Layout ProDev belum ada: 1 up per lembar diasumsikan" (baris 1066-1090).
- BOM override kosong `[]` tidak menutup BOM engine (`?: null`, baris 1080).
- Bahan belum dipilih: baris kertas bernama `(kertas belum dipilih di Inquiry)`; di `saved()` diberi nama dari jalur kategori ("Paper › Duplex › 350gr") (baris 954-963).
- Aturan finishing tanpa proses: peringatan "belum punya aturan proses"; langkah tidak dibuat.
- Baris `source` INQUIRY pada `prod_part_process` diperlakukan `saved()` sebagai non-ENGINE (baris 943), padahal untuk bahan disaring (baris 950). Di ddt*: 104 baris INQUIRY, 89 ENGINE, 33 MANUAL.
- Bagian `inks` memakai kunci unik `pass_seq-unit_no`; unit dibatasi 1-12.

## Pemakai (relasi)
- **sumber**: Inquiry (finishing, bahan, notasi warna, pack, qty), layout ProDev, aturan `prod_bop_rule` (layar Setup > BOP Rules).
- **dipakai_oleh**: ProDev (tab BOM/Ink/BOP), pembuatan dan penyusunan ulang WO (PPIC), Master Card (dummy). **hilir**: `prd_wo_operation`, `prd_wo_material` (via `WoGenerationService`), costing (BOP/BOM sebagai konteks baca saja lewat `ProCostContextService`).
- **mengalir**: tanda `stat_confirm_prodev` dihitung ulang setelah layout disimpan (`routes/v2.php:1672`, `InquiryStage::syncProdevForParts`).

## Butir terbuka
ATTENTION-ENGINE-01 (patch SpecEngine, SELESAI 14 Okt), ATTENTION-ENGINE-02 (dua definisi Available; `stockFor` memakai `v_rm_available`), ATTENTION-ENGINE-04 (satuan Required vs stok tanpa konversi; konversi terbatas di `toStockUom`). Status tetap di `docs/ENGINE-ATTENTION.md`, tidak disalin. Nomor baris yang disitir butir 02 dan 04 (`:768`, `:727`) sudah bergeser (kini `stockFor` baris 771-791, `toStockUom` baris 737).

## Temuan (dicatat, tidak diperbaiki)
1. Registry (`EngineRegistry.php:156`) tidak mencantumkan method publik `processOfBom` (baris 1007), padahal dipakai `forWorkOrder`.
2. Registry langkah 4 menulis "Returns the result with a source per row: ENGINE, OVERRIDE, MANUAL". FAKTA: baris hasil `generate` tidak punya kunci `source`; sumber hanya ada per bagian di `saved()` (`'source' => OVERRIDE|MANUAL`).
3. Registry `writes` hanya `tm2mst.prod_part_*`; kode juga menulis `tm2sdi.iqp_part_process`, `_material`, `_ink`. Registry `reads` tidak memuat banyak tabel (lihat Input).
4. Registry `used` menulis "Master Product sync"; tidak ada pemanggilan (hanya komentar di `ProductMasterSync.php`).
5. `deleteNonEngine` (baris 1149) memanggil `where('source', 'OVERRIDE', 'OVERRIDE')` untuk `bom`; bekerja karena Laravel menukar operator tidak valid menjadi nilai, tetapi sulit dibaca.
6. Setiap Save ProDev memanggil `persist`: baris ENGINE dihapus dan ditulis ulang (id baru) di dua database dalam transaksi bersarang (`prod_part_*` di `tm2mst`, `iqp_part_*` di `tm2sdi`, dua koneksi, bukan satu transaksi terdistribusi). Gagal di tengah dapat meninggalkan satu sisi lebih baru.
7. Tidak ada tes khusus `generate`/`persist`/`saved`/`processOfBom`; hanya `forWorkOrder` lewat `WoBomFromInquiryTest` dan `WoRowsTest`.
8. `PROCESS_INKS` memakai hex literal (`#1a1a1a`, dst.) di kode, bukan token; hanya data tampil.

## Tes
`tests/Feature/WoBomFromInquiryTest.php` (1 tes), `tests/Feature/WoRowsTest.php` (memakai `forWorkOrder`, baris 164). Tes ini membersihkan datanya sendiri.
