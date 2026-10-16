# Kartu ENGINE: Costing Advisor

> CONTOH FASE 1 (2026-10-16), menunggu persetujuan Jo. Sumber: `app/Services/Costing/CostingAdvisor.php` dibaca penuh; tes `CostingAdvisorTest` dijalankan (5 lulus, 24 assert). Bagian setelah penanda johiwan-only = angka/rumus rahasia (A.8-A.9).

- **Nama kanonik**: Costing Advisor. **Kelas**: `App\Services\Costing\CostingAdvisor`. **Registry**: key `costing-advisor`, mode `suggest`. **Modul/sub-modul**: Sales > Inquiry & Quotation > Pro-Cost.
- **Status beku**: ProCost = zona beku (flow/data). Engine ini hanya menyarankan (A.7).

## Pemicu (tidak ada background / jadwal)
Dicari di `routes/console.php` (tidak ada), Jobs (tidak ada folder), Observers (hanya 2 lain), dan semua pemanggil. FAKTA: hanya dari layar `pages::inquiry.costing` (`resources/views/pages/inquiry/costing.blade.php`):
| Pemicu | Method | Baris |
|---|---|---|
| Dokumen DRAFT/CALCULATED dibuka | `prefill` | 118 |
| Tombol/aksi "advise" | `prefill` | 220 |
| Officer mengubah isian | `markTouched`, `valueToRestore` | 415-505 |
| Tombol Calculate | `CostingEngine::run` lalu `learn` | 536 |
| Layar render (hanya yang boleh lihat rumus) | `report`, `recsFor`, `missing` | 171, 163, 766 |

## Alur (kembaran teks dari diagram `../img/costing-advisor.svg`)
1. Dokumen costing dibuka -> `prefill` -> untuk tiap **kotak kosong**:
   - gramatur: BOM Inquiry; bila kosong, template; lalu gramatur AKTIF terdekat di master.
   - geometri (plano, UP, ukuran cetak): bila layout ProDev ada, dipakai apa adanya; bila tidak, simulasi `ImpositionEngine` (baca saja) atas semua plano aktif, ditandai ESTIMATE.
   - tarif (`TARIF`): urutan prioritas = riwayat (STAT/ML) > template tarif > konstanta/master.
   - margin & toleransi per tier: riwayat > konstanta.
2. Tiap nilai yang diisi dicatat di `iqp_costing_recommendation` (sumber, metode, jumlah sampel).
3. Officer mengubah kotak -> `markTouched` -> `is_override`; nilai 0 = `zero_intent`. Override dan nol sengaja TIDAK ditimpa lagi.
4. Kotak dikosongkan tak sengaja -> `valueToRestore` mengembalikan nilai saran.
5. Calculate -> `CostingEngine` menghitung -> `learn` menyimpan isian final (+ qty, tipe part) sebagai bahan belajar.
6. `report` menyusun Laporan Hitung; langkah rumus disembunyikan bila penelusuran tidak sama dengan hasil tersimpan.

**Titik keputusan officer (A.7)**: setiap saran bisa diubah; engine tidak menimpa override.

## Input / Output
- **Input**: `iqp_costing` + `iqp_costing_part` + `iqp_costing_price_tier`; `iqp_costing_recommendation` (riwayat); `prod_part_template`, `mat_grammage`, `mat_plano_size`, `prod_part_type`; `sys_constant` grup COSTING (`MARGIN_DEFAULT_PCT`, `TOLERANCE_DEFAULT_PCT`, `ADVISOR_MIN_SAMPLE_STAT`, `ADVISOR_MIN_SAMPLE_ML`, `FILM_*`, `PLATE_PER_COLOR`, `KNIFE_FACTOR_DEFAULT`, `PPN_INPUT_MATERIAL_DIVISOR`); konteks Inquiry/ProDev lewat `ProCostContextService`.
- **Output (menulis ke DB)**: `iqp_costing_part`, `iqp_costing_price_tier` (hanya kotak kosong), `iqp_costing_recommendation` (satu baris per kotak).
- **Hanya ditampilkan**: array Laporan Hitung (langkah, pembanding, catatan, simulasi).

## Ambang statistik / ML (FAKTA dari kode, baris 312-332)
- < `ADVISOR_MIN_SAMPLE_STAT` (bawaan 5) riwayat serupa (tipe part sama, costing lain): tidak ada saran riwayat.
- >= 5: median nilai final officer -> sumber `STAT`.
- >= `ADVISOR_MIN_SAMPLE_ML` (bawaan 30) dan qty > 0: tetangga terdekat per qty (k = 3..7), dipakai hanya bila galat leave-one-out lebih kecil daripada median -> sumber `ML`.
- Yang memutuskan: officer.

## Kasus tepi
- Saran bernilai 0 -> kotak dibiarkan kosong tanpa penanda.
- Tarif tooling bernilai 0 di template = "pakai default konstanta", bukan "tanpa tooling".
- Tanpa tipe part, riwayat kosong (tidak ada saran STAT/ML).
- Hanya keluarga DC (`DC`, `DC+SF`, `DC+SHEET`, `KRAFT`) mendapat gramatur/geometri otomatis.

## Pemakai
`pages::inquiry.costing` (Mode 1), komponen `x-costing.src` dan `x-costing.field` (penanda sumber). Mode Instant: DARI DOKUMEN (REMARK kode: "costing-instant belum disambung"), belum dicek ulang.

## Butir terbuka
Lihat `docs/ENGINE-ATTENTION.md` (status tetap di sana, tidak disalin). Temuan baru: REMARK menulis Mode #2 "belum disambung", sedangkan teks registry menyebut Mode 2 "typed by the officer"; salah satu usang.

## Tes
`tests/Feature/CostingAdvisorTest.php` (5 tes), `CostingAdvisorScreenTest.php`.

<!-- johiwan-only -->

## Contoh angka (rahasia; diverifikasi lewat `CostingAdvisorTest`, 5/5 lulus 2026-10-16)
Semua angka di bawah adalah assert tes, bukan tebakan.
1. **Default**: costing baru tipe DC, qty 1.000: margin 18 (`MARGIN_DEFAULT_PCT`), toleransi 5 (`TOLERANCE_DEFAULT_PCT`), Ongkos Cetak/RIM 750.000 dan QC/PC 50 (template KALENDER), `i_film_faktor` 0,25 (konstanta, karena template menandai 0 = default), `i_dc_persen_override` 5,3 (gramatur Duplex 250). Tes: `test_prefill_fills_empty_boxes_with_margin_default_and_template_rates`.
2. **Statistik**: 5 costing lama (KRAFT) bermargin akhir 20, 22, 24, 26, 28 -> median 24 -> costing baru mendapat margin 24, sumber `STAT`, `sample_count` 5. Tes: `test_statistics_replace_default_after_enough_history_then_ml_threshold`.
3. **Override**: officer mengubah margin 18 -> 22 dan menolkan Ongkos Cetak/RIM; `prefill` ulang tidak mengubah keduanya; `valueToRestore` untuk yang dinolkan = null. Tes: `test_officer_override_and_zero_are_never_overwritten`.
4. **Pemulihan**: kotak Ongkos Cetak/RIM dikosongkan -> dipulihkan 750.000; Hot Print (template 0) -> null. Tes: `test_blank_box_is_restored_to_suggested_value`.

### Rumus rantai harga (salinan `CostingEngine::calculateTier` di `chain()`, baris 517-550; dijelaskan agar laporan bisa menjelaskan)
Per part: `gross = c_total_part_cost_pc x qty_per_set + c_tooling_cost_order / qty`; bila PPN: `net = gross - inputBahan x credit`, `credit = 1 - 1/PPN_INPUT_MATERIAL_DIVISOR`; toleransi `tt` = % part atau tier: part biasa `tolNet += net + gross x tt`, part SHEET `tolNet += net / (1 - tt)`. Dasar = pembulatan (`rounding_mode`, `rounding_step`) atas `tolNet`; `dpp = dasar / (1 - margin)`; harga akhir = `dpp x (1 + ppn%)` bila PPN.
Contoh angka rantai dari data ddt*: BELUM ditulis (butuh satu kasus tes baru; usul Fase 4, tunggu izin Jo).
