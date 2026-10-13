# ATTENTION — ENGINE (jangan terlewat)

> Jo 2026-10-13: *"Beri remark dan ATTENTION di md agar TIDAK TERLEWAT buat ENGINE nya"*, *"kita bahas engine nya di another task, cukup di sini ambil output nya"*.
> Berkas ini = daftar hal yang MENUNGGU pembahasan engine. Form WO / PPIC hanya MEMBACA output engine apa adanya; tidak ada engine yang dibangun atau diubah dari daftar ini.
> Aturan baca: **FAKTA** = terbukti di kode / data ddt* (berkas:baris). **DUGAAN** = belum terbukti. Status semua butir: **TERBUKA** (menunggu keputusan Jo / task engine).
> Setiap berkas PPIC yang menyentuh sebuah butir memuat REMARK "ATTENTION-ENGINE-nn: lihat docs/ENGINE-ATTENTION.md".

Pemilik engine: **ProDev** (BOP/BOM, `SpecEngine`) · **ProCost** (Material Planning / Reserve / Booking / PR-PO) · **Solver PPIC** (`ScheduleSolver`, `PlanLoader`, `GateService`).

---

## ATTENTION-ENGINE-01 — Patch `SpecEngine.php` DIPARKIR (ProDev, zona beku)
- **Apa:** 5 perubahan di `app/Services/ProDev/SpecEngine.php` (119 baris diff) diparkir sebagai patch, `SpecEngine.php` dikembalikan ke HEAD. Patch: `storage/app/private/parked/specengine_processOfBom_2026-10-13.patch` (folder diabaikan git; JANGAN dihapus). Terapkan kembali: `git apply storage/app/private/parked/specengine_processOfBom_2026-10-13.patch`.
- **Isi tiap hunk (FAKTA, dari patch):**
  1. `@@ -1,5 +1,15 @@` blok REMARK 6 butir di atas berkas (teks saja).
  2. `@@ -937 @@ ... saved()`: saringan sumber bahan `where('pm.source','<>','ENGINE')` -> `whereNotIn('pm.source',['ENGINE','INQUIRY'])`; kolom `pm.category_id`, `pm.role` ikut dibaca.
  3. `@@ -960 @@` jalur kategori: bahan yang belum dipilih (`material_id` kosong, hanya kategori + gsm + ukuran dari Inquiry) diberi NAMA dari jalur `mat_category` ("Paper › Duplex › 310gr") agar barisnya tidak hilang; `role` cadangan `KERTAS PLANO` / `SHEET` bila `rawmat_type` kosong.
  4. `@@ -974 @@` metode baru `processOfBom(array $bom, array $bop)`: menautkan tiap baris BOM ke proses BOP yang memakainya (aturan material mengikuti aturan proses terdekat di atasnya; plano -> langkah BOP pertama; sheet laminasi -> langkah "LEM LAMINASI BAHAN").
  5. `@@ -1007`, `@@ -1018 @@ forWorkOrder()`: bila layout ProDev belum ada (`upCount` 0) dipakai 1 up per lembar (peringatan "Layout ProDev belum ada: 1 up per lembar diasumsikan") dan `layout` null; BOM override kosong `[]` tidak lagi menutup BOM engine (`?: null`) agar BOM tidak kosong.
- **Fitur layar yang BERGANTUNG pada patch (FAKTA):** nama bahan di BOM saat bahan belum dipilih; kolom **Process** di tab Bill of Material (urutan baris BOM per proses); tidak adanya BOM kosong untuk part dengan bahan dari Inquiry. Tanpa patch: BOM bisa kosong untuk part itu (placeholder "Material belum ditentukan di Inquiry / ProDev" ditulis PPIC), kolom Process "—".
- **Tes yang bergantung pada patch (FAKTA):** `WoBomFromInquiryTest` (gagal di HEAD: "BOM is empty" untuk iqp_part 1500) — ditahan dari commit bersama patch.
- **AKIBAT TANPA PATCH (FAKTA, diukur 13 Okt dengan SpecEngine HEAD vs patch, `SpecEngine::forWorkOrder` untuk tiap WO aktif di ddt*):** dari 13 WO aktif, SpecEngine HEAD mengembalikan **0 baris BOM untuk 9 WO** (WO2600272, 273, 274, 282, 283, 285, 286, 288, 289) dan 5 baris untuk 4 WO (271, 281, 284, 287; bahan kertas "belum dipilih" bernama generik); dengan patch: **11-12 baris** untuk 9 WO tadi (kertas "Paper › Duplex › 310gr", sheet, tinta, laminasi, UV, foil, pack) dan 5 baris untuk 4 WO lainnya. `WoGenerationService::regenerateBom` (dipanggil tombol PROCESS) MENGHAPUS semua baris material WO lalu menulis output engine; dengan engine HEAD, PROCESS pada 9 WO itu mengganti 11 baris dengan 1 baris placeholder "Material belum ditentukan di Inquiry / ProDev" (kecuali edit officer / substitusi yang cocok baris yang sama, yang tidak ada lagi). **Keputusan Jo diperlukan:** (a) terapkan patch SpecEngine (task engine), atau (b) PPIC tidak boleh menghapus baris BOM bila engine mengembalikan kosong (perlindungan di sisi PPIC; belum dibuat).
- **Sisi PPIC (FAKTA):** `WoGenerationService::writeBom` membaca `process_id` dari baris BOM apa adanya (`$b['process_id'] ?? null`); tidak memanggil `processOfBom`. Saat patch kembali, engine perlu mengisi `process_id` per baris BOM (di `forWorkOrder`) agar kolom Process hidup lagi.
- **Pemilik:** ProDev · **Dampak ke layar:** tab BOM Form WO (nama bahan, kolom Process).

## ATTENTION-ENGINE-02 — Dua definisi "Available" (view vs PPIC)
- **FAKTA:** `tm2inv.v_rm_available.qty_available` = on hand dikurangi booking **Confirmed** saja (`stat_confirm=1`). PPIC (`App\Services\Scheduling\Plan\AvailableStock::of`, dipakai `Ppic::stockOf` dan `GateService::ownLines`) = on hand dikurangi sisa booking **semua tahap** (Draft/PRE/B2/Held/Confirmed; keputusan Jo 06:31Z), minimum 0, hold kadaluwarsa tidak dihitung.
- **Pembaca lama yang MASIH memakai angka view (FAKTA):** `app/Services/Planning/MaterialPlanningEngine.php:684` (netting) dan `:922` (pilih lokasi); `app/Support/V2/Data/MaterialPlanning.php:180`; `app/Support/V2/Board/Widgets/MaterialShortage.php:66`; `app/Console/Commands/PlanningRun.php:52`; `app/Services/ProDev/SpecEngine.php:768` (status stok BOM ProDev).
- **Akibat (FAKTA turunan):** bahan yang sama bisa tampak Available di layar Material Planning / ProDev dan lebih kecil di BOM Form WO bila ada booking Draft/PRE ber-`qty_book > 0`. Data ddt* sekarang: 227 baris booking, 0 punya `qty_book > 0`, jadi belum ada selisih.
- **DUGAAN:** draft dari banyak CO berbeda dihitung engine terhadap stok yang sama (netting hanya mengurangi Confirmed), sehingga jumlah sisa Draft bisa melebihi on hand.
- **Pemilik:** ProCost (Material Planning / Booking) · **Dampak:** angka Available tidak seragam antar layar.

## ATTENTION-ENGINE-03 — Release tidak membuat booking; dua WO melepas stok yang sama
- **FAKTA:** `GateService::release` (stored ?? available) dan `GateService::ownLines`: Release hanya menentukan gate (Release > Available = BLOCK; Required - Release > 0 = WAIT "Buy"). Tidak ada penulisan ke `rm_booking*`. Tes penjaga: `WoEngineVerifyTest::test_known_gap_release_does_not_book_stock_two_work_orders_count_it_twice`.
- **Keputusan Jo 05:04Z:** reserve / booking adalah ENGINE ProCost; PPIC tidak membangun reservasi.
- **Yang perlu dibahas di task engine:** cek gudang, usulan material pengganti yang lebih lebar, PR dan PO dari Purchase, booking saat Release.
- **Pemilik:** ProCost · **Dampak:** Release = Available bisa dua kali untuk bahan yang sama.

## ATTENTION-ENGINE-04 — Satuan Required vs stok tanpa konversi
- **FAKTA:** `Ppic::bomSheet`, `AvailableStock::of`, `GateService::ownLines` membandingkan `prd_wo_material.qty_required` (uom baris BOM: LBR / M / KG) langsung dengan `qty_onhand` (uom stok). Konversi hanya ada di ProDev: `SpecEngine::toStockUom` (`SpecEngine.php:727`).
- **Pemilik:** ProDev / ProCost · **Dampak:** Purchase / Available tidak sebanding bila satuan beda.

## ATTENTION-ENGINE-05 — Purchase belum membuat PR
- **FAKTA:** `Purchase = max(0, Required - Release)` (`GateService::purchase`) hanya angka tampilan dan alasan gate WAIT; tidak ada kode yang membuat PR/PO dari kolom ini (`PpicController::bomCells` hanya menulis `qty_purchase`). "Create PR" direncanakan nanti.
- **Pemilik:** ProCost (PR/PO) · **Dampak:** kolom Purchase belum memicu pembelian.

## ATTENTION-ENGINE-06 — Gate: lubang pemeriksaan
- **FAKTA (Required 0):** `GateService::ownLines` hanya memeriksa `qty_required > 0` (`GateService.php:118`); baris Required 0 (area belum terhitung: foil, UV) tidak pernah menahan.
- **FAKTA (artwork):** `GateService::apply` menulis `gate_artwork = READY` tanpa syarat (`GateService.php:181`); tidak ada pemeriksaan artwork (tes penjaga `test_known_gap_artwork_gate_is_never_evaluated`).
- **FAKTA (tooling):** tooling tidak terdaftar di master = READY dengan catatan (`GateService.php:159`).
- **DUGAAN:** saringan sheet/gsm tidak punya data cukup untuk beberapa bahan (belum diverifikasi agen ini); libur / shift mesin belum diaudit dengan kasus uji (tidak ada tes khusus kalender); Colour Sequence tanpa kasus uji khusus.
- **Pemilik:** solver PPIC (`GateService`) · **Dampak:** WO bisa tampil READY padahal artwork/tooling/bahan belum siap.

## ATTENTION-ENGINE-07 — Solver: serial, tanpa kapasitas untuk operasi tanpa mesin; belum ada pembagian proses
- **FAKTA (kapasitas):** `ScheduleSolver::placeOutsideMachines` (`ScheduleSolver.php:382-395`) tidak memanggil `occupy()`: operasi tanpa mesin (SUBCON, QC, HITUNG, PACK) tidak menempati kapasitas, jadi bar bertumpuk dalam satu baris proses di grup SUBCON pada Gantt (QC · subcon, dst).
- **FAKTA (crew):** `CrewAvailabilityChecker` hanya dipakai `PdrEngine` + `CompositePriorityScorer` (engine lama, `SchedulingServiceProvider.php:42-53`), BUKAN `ScheduleSolver` / `AutoPlanRunner`; bentrok orang tidak dicek di jadwal sekarang.
- **FAKTA (pembagian proses ke beberapa mesin = "Tahap 2"):** belum ada struktur (tidak ada parent_operation_id / qty bagian); `WoGenerationService::rebuildOperations` (`WoGenerationService.php:225-239`) MENGHAPUS baris operasi yang tidak cocok langkah BOP (kecuali CONFIRMED / sudah jalan / punya output) — baris bagian tambahan akan hilang saat PROCESS bila tidak ditangani; solver menjadwalkan baris ber-`seq_no` sama secara serial.
- **Pemilik:** solver PPIC · **Dampak:** Gantt grup SUBCON menumpuk; pembagian proses belum bisa.

## ATTENTION-ENGINE-08 — Poin terbuka lain (menunggu Jo / task engine)
- HOT PRINT / FOIL STAMPING: `prod_process.inhouse = 0` padahal punya mesin mampu di `prod_process_machine` (**FAKTA**: 1 mesin mampu untuk HOT PRINT di ddt*; engine memperlakukan kind SUBCON → baris SUBCON di Gantt). Perlu keputusan master (DATA REAL, jangan diubah tanpa izin).
- Sisa operasi WO 274 / 273 / 283 / 286 yang masih DRAFT (WO ber-status Applied dengan operasi Draft tersisa; belum diputuskan dikunci) — **FAKTA** dari catatan Jo/koordinator; Jo belum menjawab.
- Arti **SPILL** di perhitungan waste/spill (dari daftar koordinator; **belum diverifikasi** agen ini ke kode).
- Dua jalur POST (Post to Production vs Create WO), qty Bad QC, P18, P19/P20, P22 — butir dari daftar koordinator; **belum diverifikasi** agen ini; tandai DUGAAN sampai ditelusuri di task engine.
- **Pemilik:** campuran · **Dampak:** lihat catatan masing-masing.

---

## Cara memakai daftar ini
1. Sebelum menyentuh engine (ProDev / ProCost / solver), baca butir terkait.
2. Selesai satu butir: ubah status menjadi SELESAI + tanggal + commit, jangan hapus butirnya.
3. Remark `ATTENTION-ENGINE-nn` di kode menunjuk ke berkas ini; jangan hapus remark itu sampai butir selesai.
