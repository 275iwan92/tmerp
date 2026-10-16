# Kartu ENGINE: Imposition Engine

> DRAFT KELOMPOK C (2026-10-16), menunggu persetujuan Jo. Sumber: `app/Services/Imposition/ImpositionEngine.php` dibaca penuh (472 baris); tes `ImpositionEngineProDevToolsTest` dijalankan (7 lulus, 19 assert). Angka contoh dihitung sendiri lewat tinker (`--env=mockup`, tanpa menulis DB) dan dicek tangan.

- **Nama kanonik**: Imposition Engine. **Kelas**: `App\Services\Imposition\ImpositionEngine`. **Registry**: key `imposition`, mode `suggest`, grup Pro-Dev. **Modul/sub-modul**: Sales > Inquiry & Quotation > Pro-Dev.
- **Status beku**: ProDev = zona beku (flow/data). Engine ini hitungan murni, tidak menulis DB sendiri (FAKTA: tidak ada `DB::`/`save` di berkas).
- **Sinonim**: UP, nesting, tata letak plano, layout cetak, TEGAK/PUTAR/CAMPUR.
- **BUKAN sama dengan**: nesting manual di layar ProDev baru (digambar di JS `prodev.blade.php`, bukan engine ini; lihat Temuan 1). **BUKAN** `ProCostContextService::layoutGeometry` (itu pemetaan hasil layout ke kotak costing).

## Pemicu (tidak ada background / jadwal)
Dicari di `routes/console.php` (tidak ada), `app/Jobs` (tidak ada), Observers (tidak ada), seluruh `app/`, `resources/views`, `packages`. FAKTA, semua pemanggil `new ImpositionEngine` / `app(ImpositionEngine::class)`:
| Pemicu | Method | Berkas:baris | Catatan |
|---|---|---|---|
| Layar v1 Imposition per Inquiry dibuka / ukuran diubah | `run`, `cells` | `resources/views/pages/inquiry/imposition.blade.php:67` (recompute), `:118` (usulan semua plano aktif) | rute `inquiry.imposition` (`routes/web.php:150`); tautan masuk dari form v1 `form.blade.php:2621`. Menu v2 `SALES_IMPO` menunjuk `v2:prodev`, bukan layar ini. |
| Pratinjau bahan pengganti di Form WO | `run` | `resources/views/pages/production/wo-entry.blade.php:439` | hanya tampil, tidak menyimpan |
| Costing Advisor, bila layout ProDev belum ada | `run` | `app/Services/Costing/CostingAdvisor.php:276` | baca saja, ditandai ESTIMATE (lihat kartu Costing Advisor) |
| Hitung "cut efektif" group multi-part | `applyFlap` | `app/Models/Master/ProdLayoutGroup.php:88` (dari `ProCostContextService.php:139`, `costing.blade.php:268`) | hanya ukuran bentangan, bukan `run` |
| ProDev LAMA (diarsipkan) | semua tool (`run`, `sweepFlap` baris 622, `sweepInterlock` 673, `bestSheet` 726, `dieRuleMeterLari` 771, `combinePair` 854, `cells`, `applyFlap`) | `resources/views/pages/production/old_prodev.blade.php` | rute `production.old-prodev` (`routes/web.php:339`) hidup, tetapi komentar `MenuRoutes.php` dan isi `sys_app_menu` mockup tidak menautkannya |

**Tidak memanggil engine ini** (FAKTA, grep): layar ProDev aktif `pages::production.prodev` (menu `v2:prodev`, `v2:prodev-new`, `v2:prodev-revise`): nesting digambar dan dihitung di JS di klien, tombol Save hanya menyimpan hasilnya (`saveLayout`). `CostingEngine` juga tidak memanggilnya.

## Alur (kembaran teks dari diagram `../img/imposition.svg`)
1. `run(plano, flat, gap, gripper, margin)` menghitung area pakai = plano dikurangi margin dan gripper.
2. Hitung susunan TEGAK (flat apa adanya) dan PUTAR (flat diputar 90 derajat); yang UP-nya lebih banyak menang (seri = TEGAK).
3. Coba CAMPUR: blok utama TEGAK, lalu sisa lebar diisi blok PUTAR; dipakai hanya bila total UP lebih banyak DAN blok putar tidak kosong.
4. Hitung efisiensi dan waste; kembalikan `up`, `method`, `layout` (kolom, baris, ukuran per blok).
5. `cells` mengubah `layout` menjadi koordinat tiap sel (mm dari kiri-atas plano) untuk gambar.
6. Alat tambahan (semua memanggil `run` berulang): `sweepFlap` (variasi panjang lidah), `sweepInterlock` (lidah + geser "69"), `bestSheet` (semua plano aktif), `combinePair` (dua part sekaligus), `dieRuleMeterLari` (meter lari pisau).
7. Hasil berupa saran; officer memutuskan (A.7). Engine tidak menyimpan.

## Input / Output
- **Input**: argumen method (tanpa membaca DB), dalam mm: `planoW, planoH, flatW, flatH, gap, gripper, margin`; sweep: `flapMin, flapMax, step, flapSide` (TOP/BOTTOM/LEFT/RIGHT); `bestSheet`: koleksi `mat_plano_size` (diberikan pemanggil; engine sendiri tidak query). Nilai bawaan layar: `sys_constant` `PRODEV_GAP_MM`=3, `PRODEV_GRIPPER_MM`=12, `PRODEV_MARGIN_MM`=8, `PRODEV_FLAP_MIN_MM`=20 (FAKTA, DB mockup), tetapi engine tidak membacanya sendiri.
- **Output (hanya nilai kembali)**: `up`, `method` (TEGAK/PUTAR/CAMPUR), `efficiency_pct`, `waste_pct`, `layout`, `area_w`, `area_h`; sweep menambah `flap_length_mm`, `shift_offset_mm`, `flat_w`, `flat_h`; `bestSheet` menambah `plano_size_id`, `plano_name`, `cost_metric`, `gross_sheets`; `combinePair` memberi `recommend` (COMBINED/SEPARATE/NONE), `combined`, `separate`, `saving_pct`.
- **Siapa menulis hasilnya**: pemanggil. Layar v1 menulis `iqp_layout` (`imposition.blade.php:75-82`: `method`, `up_count`, `efficiency_pct`, `waste_pct`, `layout_detail`), hanya bila `stat_confirm` belum 1 (`:63`). ProDev aktif menulis `prod_layout_group` dan `iqp_layout` dari hasil JS-nya sendiri.

## FORMULA
Satuan: mm, kecuali `up` (buah per lembar) dan persen. Tanpa pembulatan kecuali disebut.
1. `areaW = planoW - 2*margin`; `areaH = planoH - gripper - 2*margin` (baris 11-12).
2. `fit(avail, item, gap) = max(0, floor((avail + gap) / (item + gap)))` (baris 458); `n = fit(areaW, itemW) * fit(areaH, itemH)` (baris 461-471). Item dengan sisi <= 0 memberi n = 0.
3. TEGAK = `n(flatW, flatH)`; PUTAR = `n(flatH, flatW)`; `up` = yang besar, bila sama TEGAK (baris 17).
4. CAMPUR: `mainCols = fit(areaW, flatW)`, `usedW = mainCols*(flatW+gap) - gap`, `restW = areaW - usedW - gap`, `mainRows = fit(areaH, flatH)`; `rotCols = fit(restW, flatH)`, `rotRows = fit(areaH, flatW)`; total = `mainCols*mainRows + rotCols*rotRows`; menggantikan hasil sebelumnya bila `total > up` DAN `rotCols*rotRows > 0` (baris 27-46).
5. `efficiency = up * flatW * flatH / (areaW * areaH) * 100`, dijepit 0-100 dan dibulatkan 2 desimal; `waste = 100 - efficiency` dijepit sama (baris 48-56). Catatan: dasar efisiensi = area pakai (bukan seluruh plano).
6. `applyFlap`: LEFT/RIGHT menambah lidah ke lebar, selain itu ke tinggi (baris 166-172).
7. `sweepFlap`: untuk `flap = flapMin ... flapMax` (kelipatan `step`, toleransi +0,0001) hitung `run` pada bentangan `applyFlap`; urut UP turun lalu efisiensi turun; ambil `topN` (bawaan 5). `flapMax < flapMin` atau `step <= 0` memberi daftar kosong.
8. `sweepInterlock`: sama, tetapi untuk tiap `flap` dicoba `shift = 0 ... flap` (kelipatan `shiftStep`) dan bentangan efektif memakai `flap - shift` (baris 206). Penulis kode sendiri menyatakan model ini perkiraan, bukan spesifikasi presisi (komentar baris 181-189); shift 0 = hasil `sweepFlap`.
9. `bestSheet`: per plano ambil hasil `sweepFlap` teratas (UP >= 1). Metrik "termurah": bila `targetQty > 0`: `ceil(targetQty/up) * (planoW*planoH)`; bila tidak: `planoW*planoH / up`. Urut naik (baris 264-279). Harga bahan tidak dipakai (asumsi bahan sama di semua plano).
10. `combinePair`: area dibagi dua kolom vertikal, kolom A selebar `x` (digeser `splitStep`, bawaan 5), B sisanya; `upA`/`upB` = maksimum dua orientasi; `sets = min(upA/qtyPerSkuA, upB/qtyPerSkuB)`; simpan `x` dengan `sets` terbesar. `area_per_set = planoArea / sets`. Pembanding terpisah: `planoArea/soloUpA*qtyA + planoArea/soloUpB*qtyB`. `saving_pct = (1 - gabung/pisah)*100` (baris 309-391). `recommend` = COMBINED hanya bila gabung < pisah; NONE bila tidak ada `x` yang memuat keduanya.
11. `dieRuleMeterLari`: potong = `(cols+1)*gridH + (rows+1)*gridW` (pisau bersama antar sel), dengan `gridW = cols*iw + (cols-1)*gap`, dst; untuk CAMPUR dua grid dijumlah. Lipat = `up * panjangGarisLipat` (lebar badan bila lidah TOP/BOTTOM, tinggi bila LEFT/RIGHT). Perforasi = 0 (komentar: belum ada sumber data). Hasil = `(potong+lipat)/1000` meter, 4 desimal.

**Contoh angka (dihitung dan dicek tangan, plano 79x109 = 790x1090, gap 3, gripper 12, margin 8)**
| Flat (mm) | Hasil `run` | Cek tangan |
|---|---|---|
| 300 x 200 | 10 UP, TEGAK, efisiensi 72,99% | areaW 774, areaH 1062; kolom floor(777/303)=2, baris floor(1065/203)=5 = 10; 10*60.000/821.988 = 72,99% |
| 250 x 100 | 30 UP, TEGAK, 91,24% | 3 kolom x 10 baris; 750.000/821.988 |
| 128 x 102 | 58 UP, CAMPUR, 92,12% | TEGAK 5x10 = 50, PUTAR 7x8 = 56; campur 5x10 + 1x8 = 58 > 56; `offX` 655 |
| 780 x 300 | 2 UP, PUTAR, 56,94% | (hasil engine, tidak dicek tangan) |
| 2000 x 2000 | 0 UP, TEGAK, 0% | tidak muat |
- `sweepFlap`, badan 200x150, lidah TOP 20-50 langkah 5, 3 teratas: lidah 40 = 20 UP PUTAR 92,46%; lidah 35 = 20 UP 90,03%; lidah 30 = 20 UP 87,59%. Cek tangan lidah 40: bentangan 200x190, PUTAR 4 kolom x 5 baris = 20, 20*38.000/821.988 = 92,46%. Dua lainnya tidak dicek tangan.
- `combinePair` (plano 790x1090; A 300x200 qty 1, B 150x100 qty 1; step 10): COMBINED, `x` 610, 10 UP A + 10 UP B, 10 set per lembar, hemat 16,67% (hasil engine; tes mengasersi hanya COMBINED dan saving > 0).
- `dieRuleMeterLari` grid 2x3, 100x80, 6 UP, gap 3, lidah TOP, badan 100x80 = 2,15 m (sama dengan tes).

## Kasus tepi
- Flat 0 atau lebih besar dari plano: `up` 0, efisiensi 0, `method` tetap TEGAK (FAKTA, dijalankan). Pemanggil harus memeriksa `up < 1`.
- `restW` negatif: `fit` memberi 0, tidak ada blok putar.
- Hasil tidak mempertimbangkan arah serat, ukuran mesin cetak, atau minimum gigitan selain `gripper` yang diberikan pemanggil.
- Hasil seri antar lidah diurut stabil menurut urutan kemunculan (PHP `usort` stabil).

## Pemakai (relasi)
- **sumber**: `prod_part` / `iqp_part` (bentangan, lidah), `mat_plano_size` (plano). **dipakai_oleh**: layar v1 Imposition, Form WO (pratinjau), Costing Advisor, ProDev LAMA. **hilir**: `iqp_layout`, `prod_layout_group` (diisi pemanggil); Costing membaca `up_count`/layout lewat `ProCostContextService`. Konsep terkait: Tipe produk, `../inquiry-product/20_engine_dieline.md` (sumber bentangan).

## Butir terbuka
`docs/ENGINE-ATTENTION.md` tidak memuat butir untuk engine ini (dibaca penuh; status tetap di sana, tidak disalin).

## Temuan (dicatat, tidak diperbaiki)
1. Registry menulis pemakai "Pro-Dev workbench (Imposition tab)" (`EngineRegistry.php:148`). FAKTA: layar ProDev aktif tidak memanggil engine; hanya ProDev lama yang tidak ditaut menu. Teks registry menyesatkan.
2. Registry menulis pemakai "Costing Engine (flat area per part)". FAKTA: `CostingEngine` tidak memanggil engine ini. Komentar `ImpositionEngine.php:164` merujuk `IqpCostingPart::allocateProportionalCost()` yang tidak ada (grep); yang ada `ProdLayoutGroup::effectiveCutFor` (memakai `applyFlap`).
3. Nilai gap 3 / gripper 12 / margin 8 ditulis literal di `imposition.blade.php:45-50` dan `CostingAdvisor.php:276` (dan `old_prodev` lewat konstanta), padahal ada konstanta `PRODEV_GAP_MM`, `PRODEV_GRIPPER_MM`, `PRODEV_MARGIN_MM` (nilai sama sekarang). Bila konstanta diubah, tiga pemanggil tidak ikut.
4. Tes tidak mencakup `run` (dasar TEGAK/PUTAR/CAMPUR), `sweepFlap`, `sweepInterlock`, `cells`, `applyFlap` secara langsung (hanya tiga alat: `dieRuleMeterLari`, `bestSheet`, `combinePair`).
5. Tiap alat sweep hanya dipanggil dari ProDev lama; bila ProDev lama dibuang, lima method menjadi tanpa pemanggil. Keputusan Jo.

## Tes
`tests/Unit/ImpositionEngineProDevToolsTest.php` (7 tes; `dieRuleMeterLari` x2, `bestSheet` x2, `combinePair` x3).
