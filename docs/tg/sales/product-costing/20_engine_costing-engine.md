# Kartu ENGINE: Costing Engine (rumus HPP dan harga per tier)

> DRAFT KELOMPOK C (2026-10-16), menunggu persetujuan Jo. Sumber: `app/Services/Costing/CostingEngine.php` dibaca penuh (496 baris). Tes dijalankan sesi ini: `CostingEngineTest` (3 lulus, 38 assert), `CostingExcelParityTest` (7 lulus, 47 assert), `CostingLegacyParityTest` (4 lulus, 21 assert), `CostingInstantModeTest` (2 lulus, 14 assert). Bagian setelah penanda johiwan-only = angka/rumus rahasia (A.8-A.9). Bagian atas hanya alur.

- **Nama kanonik**: Costing Engine (Pro-Cost). **Kelas**: `App\Services\Costing\CostingEngine`. **Registry**: key `costing`, mode `calc`, grup Pro-Cost. **Modul/sub-modul**: Sales > Inquiry & Quotation > Pro-Cost.
- **Status beku**: ProCost = zona beku (flow/data). Dua jalur input (Mode 1 Integrasi, Mode 2 Instant) sengaja memakai engine yang SAMA (`docs/claude_vital_flows.md`).
- **Sinonim**: hitung HPP, Hitung, ladder harga, price tier, `CostingEngine::run`.
- **BUKAN sama dengan**: Costing Advisor (mengisi kotak, kartu `10_engine_costing-advisor.md`); Reverse Costing (`30_engine_reverse-costing.md`).

## Pemicu (tidak ada background / jadwal)
Tidak ada di `routes/console.php`, Jobs, Observers. FAKTA, semua pemanggil `new CostingEngine`:
| Pemicu | Berkas:baris |
|---|---|
| Mode 1 (Integrasi): tombol **Hitung** | `resources/views/pages/inquiry/costing.blade.php:535` dalam `hitung()` (`:529`), tombol `:816`; `hitung()` juga dipanggil otomatis setelah part ditambah dari layout (`:326`), setelah ganti layout (`:371`) dan `setPaperIndex` (`:525`). Sesudah `run`, `CostingAdvisor::learn` dipanggil (`:536`). |
| Mode 2 (Instant): tiap perubahan isian memanggil `recalc()` | `resources/views/pages/inquiry/costing-instant.blade.php:149` (`recalc`, dipanggil `:175, :185, :197, :218, :298, :334, :350, :369, :379` dst.); tidak memanggil `learn`. Tidak berjalan bila tidak ada part. |
| Pengisi data dummy waktu migration | `database/migrations/2026_09_18_000007_seed_procost_dummy_data.php:127,180` |
Tidak dipanggil oleh Advisor, Quotation, atau Reverse (FAKTA, grep).

## Alur (kembaran teks dari diagram `../img/costing-engine.svg`)
1. Ambil qty tier utama (`is_primary`, kalau tidak ada: tier pertama) sebagai qty dasar.
2. Untuk tiap part, pilih aturan menurut tipe part: DC, DC+SF, DC+SHEET, KRAFT = keluarga offset; SHEET, SHEET_DIGITAL = bergelombang; tipe lain melempar kesalahan `part_type_code '...' belum diimplementasi CostingEngine`.
3. Offset: kertas, proses dasar (cetak, plong, lem, lain-lain), coating per lapisan, laminasi/SF EF (hanya DC+SF dan DC+SHEET), tooling per order (film, plate, pisau).
4. Bergelombang: geometri lembar (manual atau diturunkan dari dimensi + flute + lebar mesin), harga lembar dengan diskon/surcharge per supplier, ongkos proses.
5. Roll-up ke header dokumen, dibobot qty per set.
6. Untuk tiap tier harga: tooling dibagi qty tier, toleransi (aturan beda per tipe part), pembulatan, margin, PPN.
7. Semua dalam satu transaksi di `tm2sdi`; status dokumen menjadi CALCULATED.
**Titik keputusan officer (A.7)**: engine tidak mengubah isian; ia hanya menghitung dari kotak yang diisi officer atau Advisor.

## Input / Output
- **Input**: `iqp_costing`, `iqp_costing_part` (+ `iqp_costing_part_coating`), `iqp_costing_price_tier`; master `mat_grammage`, `mat_sheet_rate`, `mat_sheet_discount_tier`, `mat_sheet_supplier`, `mat_flute_allowance`, `prod_part_type`; `sys_constant` grup Costing (nama saja: `RIM_SHEETS`, `PLANO_REF_AREA`, `CUT_PER_PLANO_DEFAULT`, `PCS_PER_LB_DEFAULT`, `FILM_FACTOR_DEFAULT`, `FILM_RATE_DEFAULT`, `PLATE_PER_COLOR`, `KNIFE_RATE_DC`, `KNIFE_RATE_KRAFT`, `KNIFE_FACTOR_DEFAULT`, `PPN_INPUT_MATERIAL_DIVISOR`, `SHEET_MACHINE_WIDTH_CM`, `SHEET_TRIM_GAP_CM`, `SHEET_DISCOUNT_MIN_/MAX_<supplier>`, `OB_DISCOUNT_RM_MIN/MAX`).
- **Output (menulis DB)**: `iqp_costing_part` (kolom `c_*` dan `description_calc`), `iqp_costing_part_coating` (`c_cost_per_lc`, `c_cost_per_pc`), `iqp_costing` (total header dan `doc_status`), `iqp_costing_price_tier` (kolom `c_*` dan `price_per_pcs`). Registry tidak menyebut `iqp_costing_part_coating` dan membaca `mat_coating_rate` yang tidak dibaca kode (lihat Temuan 2).
- **Siapa boleh menimpa**: setiap Hitung menimpa semua kolom `c_*`; kolom `i_*` (isian officer) tidak diubah.

## Kasus tepi
- Tipe part tidak dikenal: `RuntimeException`; layar menampilkannya sebagai `engineError` (tes `test_engine_rejects_unimplemented_part_type`).
- Diskon/surcharge sheet di luar batas supplier: kesalahan dengan pesan batas (`guardDiscount`).
- Tier diskon sheet tidak ada untuk running meter: kesalahan, officer isi manual.
- `qty_pcs` tier 0 dipakai minimum 0,0001 (hindari bagi nol).
- KRAFT tidak menghitung coating; DC+SF/DC+SHEET sajalah yang menghitung laminasi dan SF EF.
- Tidak ada penjaga status: `run` selalu menulis `doc_status = 'CALCULATED'` (baris 59), walau dokumen sebelumnya PUBLISHED. Penjagaan hanya ada di tombol (`@disabled($ro)`); `hitung()` sendiri tidak memeriksa. Belum diuji apakah panggilan langsung bisa menurunkan status PUBLISHED (lihat Temuan 3).

## Pemakai (relasi)
- **sumber**: Inquiry (BOM, part), ProDev (layout, via `ProCostContextService`), Advisor (kotak terisi), master tarif/bahan, konstanta Costing.
- **dipakai_oleh**: layar costing Mode 1 dan Mode 2, Quotation (harga published), Customer Order (snapshot item quotation). Ladder harga di `iqp_costing_price_tier` dibaca Advisor untuk Laporan Hitung.
- **mengalir**: tanda `stat_confirm_procost` di `iqp_inquiry` (A.20).

## Butir terbuka
Lihat `docs/ENGINE-ATTENTION.md` (tidak ada butir khusus Costing Engine; dibaca penuh). Catatan lokal: tidak ada tes yang membandingkan Mode 1 dan Mode 2 untuk geometri yang sama (klaim "HPP harus sama" di registry/vital flows hanya terjaga karena keduanya memakai engine ini).

## Temuan (dicatat, tidak diperbaiki)
1. Registry `methods` hanya `run` (benar: method lain `protected`/`private`).
2. Registry `reads` memuat `mat_coating_rate`; kode tidak membacanya (grep `CostingEngine.php`): indeks coating diambil dari `iqp_costing_part_coating.index_coating`. Kolom tulis `iqp_costing_part_coating` tidak ada di `writes`.
3. `run` tidak memeriksa `doc_status` dan menulis `CALCULATED` tanpa syarat; `hitung()` (Mode 1) dan `recalc()` (Mode 2) tidak memanggil `editable()` seperti `updateHeader`. Risiko turun status; BELUM diuji.
4. Komentar migration `2026_09_02_000028` dan `000029` menyebut `CostingEngine::calculateDcSheetPart()`; method itu tidak ada lagi (kini `calculateOffsetPart`). Hanya komentar.
5. Tes `test_engine_rejects_unimplemented_part_type` memakai kode BOGUS; tipe part resmi di master `prod_part_type` BELUM dibandingkan dengan `match` di `run` (SHEET_DIGITAL ada di data ddt* 1 baris; KRAFT, DC+SHEET, SHEET tidak muncul di data ddt*, hanya di tes).

## Tes
`tests/Feature/CostingEngineTest.php`, `CostingExcelParityTest.php`, `CostingLegacyParityTest.php`, `CostingInstantModeTest.php` (semua membersihkan datanya sendiri).

<!-- johiwan-only -->

## RAHASIA: rumus dan angka (Costing dan Direksi saja)

### Rumus part offset (`calculateOffsetPart`, baris 74-201)
Satuan: Rp per PC kecuali disebut; `rim = RIM_SHEETS` (500), `planoRef = PLANO_REF_AREA` (8611).
- `cut` = `i_cut_per_plano` bila > 0, kalau tidak `CUT_PER_PLANO_DEFAULT` (1). `isi` = `i_isi_per_lb` bila > 0, kalau `PCS_PER_LB_DEFAULT` (1). (Arti: cut = lembar cetak per plano; isi = UP per lembar cetak.)
- `areaPlano = panjang_plano_cm * lebar_plano_cm`. `areaCetak = uk_cetak_p * uk_cetak_l` bila keduanya > 0; kalau tidak `areaPlano/cut` dan flag `UK_FALLBACK`.
- **Kertas duplex** (harga list duplex > 0 atau `paper_kind = DUPLEX`): `harga = override bila > 0 sebaliknya duplex_price_list`; `dcf = 1 + (i_dc_persen_override bila > 0, kalau tidak duplex_dc_pct)`; `kertas = (areaPlano/planoRef) * harga * dcf / rim / cut / isi`. **Kertas per kg** (ivory/kraft): `(areaPlano/10000) * harga * (gramatur/1000) / cut / isi`. Tanpa grammage atau harga <= 0: kertas 0 dan flag `PAPER_GAGAL`.
- `cetak = ongkos_cetak_rim / rim / cut / isi`; `plong = ongkos_plong_lb / isi`; `lem = ongkos_lem_pc`; `lain = hotprint + kupas + qc_pack + kirim + packing` (semua per PC).
- **Coating** (bukan KRAFT): tiap lapisan `perLc = areaCetak * index_coating`, `perPc = perLc/isi` (tidak dibagi cut); `coating = jumlah perPc`.
- **Laminasi (DC+SF, DC+SHEET)**: `J36 = pakai_laminasi * lam_sheet_flat_pc`; `sfRate = override bila > 0 kalau tidak index_substance dari sheet rate`; `J37 = (sfRate>0) * (areaCetak/10000) * sfRate / isi`; `ukLam = luas sheet bila i_lam_pakai_ukuran_sf dan luas > 0 kalau tidak areaCetak`; `J38 = bila ada tarif cm2: pakai_laminasi*ukLam*rateSh/isi + sfAktif*ukLam*rateSf/isi, kalau tidak i_ongkos_laminasi_pc`.
- **Tooling per order**: `film = faktorFilm^2 * areaCetak * rateFilm` (faktor bawaan 0,25; rate bawaan 55); `plate = (ongkos plate bila > 0 kalau PLATE_PER_COLOR) * jumlah_warna`; `pisau = faktorPisau * rate` (rate: override, lalu `prod_part_type.pisau_rate_rp`, lalu `KNIFE_RATE_DC` atau `KNIFE_RATE_KRAFT`; faktor bawaan 0,25); `tooling = film + plate + pisau`.
- `proses = cetak + plong + lem + lain + J36 + J37 + J38`; `total_part_pc = kertas + proses + coating`; `jumlah_cetak_lc = qty/(cut*isi)`.

### Rumus part bergelombang (`calculateSheetPart`, 204-246; geometri 261-342)
- `proses = print*2 + diecut + slitter + slotter + join + partisi + layer + pisau + kupas + qc + kirim + packing`; `total = biaya_lembar + proses`; tooling 0.
- Geometri otomatis (SHEET_DIGITAL): `panjang = ((P+L)*2 + total_panjang_flute + lidah_lem)/10`; `lebar = (L+T+total_lebar_flute+3)/10`; `isiTrim = max(floor((lebarMesin - gap)/lebar), 1)`; `trim = ceil((lebar*isiTrim + gap)/5)*5`; running meter `rm = (qty/isiPerLb)/isiTrim*(panjang/100)`; `biaya_lembar = (trim*panjang/10000) * index_substansi / isiTrim * (1 - diskon)`. Manual: `trim`, `isiTrim`, `panjang` diketik.
- Diskon = isian officer bila supplier sama, kalau tidak lookup tier (`MatSheetDiscountTier::lookup`), dijaga batas `SHEET_DISCOUNT_MIN/MAX_<supplier>` (fallback `OB_DISCOUNT_RM_MIN/MAX`).

### Roll-up header (413-429)
`bahan = (kertas + biaya_lembar) * qty_per_set`; `proses = proses_part * qty_per_set`; `finishing = coating * qty_per_set`; `tooling = jumlah tooling order`.

### Ladder per tier (`calculateTier`, baris 443-495)
Per part: `toolPc = tooling_order/qty`; `kotor = total_part_pc*qty_per_set + toolPc`; `bahanMasuk = (kertas+biaya_lembar+J36+J37)*qty_per_set`; `bersih = kotor - bahanMasuk*kredit` bila PPN, kredit = `1 - 1/PPN_INPUT_MATERIAL_DIVISOR`, kalau NON_PPN `bersih = kotor`; `t = min(tol part ?? tol tier, 99,9999)/100`.
- Part offset: `tolKotor += kotor*(1+t)`; `tolBersih += bersih + kotor*t`. Part SHEET/SHEET_DIGITAL: `tolKotor += kotor/(1-t)`; `tolBersih += bersih/(1-t)`.
- Pembulatan `round(v)`: `UP/CEILING` = `ceil(v/step)*step`; `DOWN/FLOOR`; `NEAREST`; selain itu (NONE) tanpa bulat; `step <= 0` tanpa bulat.
- `m = min(margin_pct, 99,9999)/100`; `mPpn = min(margin_ppn_pct ?? margin_pct, 99,9999)/100`.
- `setelahMargin = round(tolKotor)/(1-m)`; `dpp = bila PPN: round(tolBersih)/(1-mPpn), kalau tidak setelahMargin`; `harga_per_pcs = dpp`; `harga_incl_ppn = dpp*(1+ppn_pct/100)` bila PPN.
- Disimpan: `c_tooling_pc`, `c_hpp_pc` (= kotor), `c_ppn_input_credit_pc`, `c_after_tolerance_pc`, `c_after_round_pc`, `c_after_margin_pc`, `c_after_ppn_pc`, `price_per_pcs`.

### Nilai konstanta (DB mockup, dibaca 2026-10-16)
`RIM_SHEETS` 500; `PLANO_REF_AREA` 8611; `CUT_PER_PLANO_DEFAULT` 1; `PCS_PER_LB_DEFAULT` 1; `FILM_FACTOR_DEFAULT` 0,25; `FILM_RATE_DEFAULT` 55; `PLATE_PER_COLOR` 90.000; `KNIFE_RATE_DC` 900.000; `KNIFE_RATE_KRAFT` 1.500.000; `KNIFE_FACTOR_DEFAULT` 0,25; `PPN_INPUT_MATERIAL_DIVISOR` 1,1; `SHEET_MACHINE_WIDTH_CM` 180; `SHEET_TRIM_GAP_CM` 2.

### Contoh angka (hitungan ulang dari asersi `CostingEngineTest::test_engine_calculates_dc_part_and_price_ladder`, 3/3 lulus 2026-10-16)
Kasus: 1 part DC, grammage Duplex 300gsm (harga list 12.500 seperti asersi tes), plano 65 x 100 cm, cut 2, isi 4, 4 warna, ongkos cetak/rim 200.000, plong/lb 5.000, lem 300, faktor pisau 0,5, satu coating Water Base (indeks 0,035), uk. cetak kosong; tier qty 1.000, toleransi 5%, margin 20%, bulat NEAREST step 100, PPN 11%.
- `areaPlano` 6.500; `areaCetak` 3.250 (fallback = 6.500/2); `dcf` 6,3 (1 + 5,3).
- kertas = 6.500/8.611 * 12.500 * 6,3 / 500 / 2 / 4 = **14,8611**; cetak 200.000/500/2/4 = **50**; plong 5.000/4 = **1.250**; lem **300**; coating 3.250*0,035/4 = **28,4375**.
- film = 0,25^2 * 3.250 * 55 = **11.171,875**; plate = 90.000*4 = **360.000**; pisau = 0,5*900.000 = **450.000**; tooling order **821.171,875**; per PC (qty 1.000) **821,1719**.
- total part per PC = 14,8611 + 50 + 1.250 + 300 + 28,4375 = **1.643,2986**; HPP/PC (tambah tooling) = **2.464,4705**.
- toleransi aditif lalu bulat: `round(2.464,4705*1,05/100)*100` = **2.600**; setelah margin: 2.600/0,8 = **3.250**.
- Jalur PPN: bersih = 2.464,4705 - 14,8611*(1 - 1/1,1) = **2.463,1194**; `dpp = round((2.463,1194 + 2.464,4705*0,05)/100)*100/0,8` = **3.250**; incl. PPN = 3.250*1,11 = **3.607,50**.
Kasus lain yang dijaga tes (angka dari tes, bukan dihitung ulang di sini): `CostingInstantModeTest` mereproduksi HPP/PC DC+SF 1.487,4648 (toleransi 0,0005) dan angka itu tetap sama setelah Reverse. Paritas Excel/legacy: lihat tes Parity.
