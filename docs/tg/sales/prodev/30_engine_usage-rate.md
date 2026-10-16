# Kartu ENGINE: Usage Rate Engine

> DRAFT KELOMPOK C (2026-10-16), menunggu persetujuan Jo. Sumber: `app/Services/ProDev/UsageRateEngine.php` dibaca penuh (202 baris), `RefreshUsageRates.php`, `bop-rule.blade.php:250-290`. Tidak ada tes untuk engine ini (grep `tests/`). Engine **tidak dijalankan** di sesi ini karena `refresh()` selalu menulis ke `prod_bop_rule` (lihat Temuan 1); tidak ada contoh angka dari data.

- **Nama kanonik**: Usage Rate Engine. **Kelas**: `App\Services\ProDev\UsageRateEngine`. **Registry**: key `usage-rate`, mode `suggest`, grup Pro-Dev. **Modul/sub-modul**: Sales > Pro-Dev (bahan baku aturan BOP/BOM); data sumbernya Production.
- **Status beku**: ProDev beku (flow/data). Engine ini mengubah nilai master aturan, bukan flow.
- **Sinonim**: takaran pemakaian, usulan waste, belajar dari produksi, "auto apply".
- **BUKAN sama dengan**: `CostingAdvisor` (belajar dari riwayat costing officer; kartu `../product-costing/10_engine_costing-advisor.md`) dan `SpeedTargetEngine` (kecepatan mesin).

## Pemicu
FAKTA:
| Pemicu | Berkas:baris |
|---|---|
| Jadwal bulanan tgl 1 pukul 02:00, tanpa tumpang tindih | `routes/console.php:18` -> perintah `prodev:usage-rates` (`app/Console/Commands/RefreshUsageRates.php`, bawaan 3 bulan ke belakang, opsi `--dry`) |
| Tombol segarkan usulan (hanya admin: `canEdit` = `is_admin`) | `resources/views/pages/production/bop-rule.blade.php:256` (`refreshSuggestions`, selalu `apply=true`) |
| Tombol terapkan usulan satu aturan (bukan memanggil engine, menyalin kolom usulan) | `bop-rule.blade.php:261-280` |
Layar BOP Rule di menu mockup berstatus tersembunyi (`PROD_MST_BOP_RULE`, `hide_in_menu`=1, target `v2:prod-bop-rule`).

## Alur (kembaran teks, tanpa diagram: tidak ada percabangan selain auto-apply)
1. Ambil semua aturan aktif `prod_bop_rule` (+ nama proses).
2. Pemakaian bahan: untuk WO `COMPLETED` sejak tanggal mulai, jumlahkan `prd_wo_material.qty_required` (rencana) per `material_role`; pemakaian nyata = `tm2inv.v_inv_wo_reconcile.qty_net_used` untuk WO dan bahan yang sama (query terpisah lintas DB).
3. Waste proses: dari `prd_wo_operation_output` dan `_defect` per nama langkah WO, dipetakan ke nama proses master lewat kecocokan kata (`stripos`).
4. Per aturan hitung usulan takaran dan usulan waste (rumus di bawah). Aturan tanpa usulan dilewati.
5. **Selalu** tulis kolom usulan (`rate_suggested`, `waste_suggested`, `suggest_sample`, `suggest_at`) ke aturan.
6. Hanya bila `apply` dan aturan `auto_apply = 1` dan sampel >= `PRODEV_RATE_MIN_SAMPLE` (5): usulan langsung menimpa `usage_rate`/`waste_pct`, `rate_source` = AUTO, `updated_by` = `usage_rate_engine`.
7. Selain itu officer/admin memutuskan di layar BOP Rule (tombol terapkan usulan).
**Titik keputusan officer (A.7)**: aturan ber-`auto_apply` adalah satu-satunya jalur penimpaan otomatis.

## Input / Output
- **Input**: `tm2mst.prod_bop_rule` (`usage_rate`, `usage_basis`, `waste_pct`, `auto_apply`, `rate_source`, `material_role`, `process_id`), `prod_process.process_name`; `tm2prd.prd_wo` (`wo_status`, `updated_at`), `prd_wo_material` (`material_role`, `qty_required`, `material_id`), `prd_wo_operation` (`process_name`), `prd_wo_operation_output` (`qty_good`), `prd_wo_operation_output_defect` (`qty`); `tm2inv.v_inv_wo_reconcile` (`qty_net_used`); `sys_constant` `PRODEV_RATE_MIN_SAMPLE`.
- **Output (menulis)**: `tm2mst.prod_bop_rule` (kolom usulan, dan nilai takaran/waste hanya jalur auto-apply). Nilai kembali: `rules`, `applied`, `sample_total`, `from`, `note`.

## FORMULA
1. Takaran usulan = `round(usage_rate * pemakaian_nyata / pemakaian_rencana, 4)`, hanya bila rencana > 0 dan nyata > 0 dan aturan punya `material_role` dan `usage_rate` (baris 53-59). Sampel = jumlah WO selesai dengan peran itu.
2. Waste usulan (%) = `round(defect / (good + defect) * 100, 2)`, hanya bila `good + defect > 0` (baris 61-67). Sampel = jumlah WO yang punya langkah itu.
3. Sampel aturan = maksimum dari kedua sampel. Hasil WO = `COUNT(DISTINCT wo_id)`.
4. Auto-apply: `apply && auto_apply && sampel >= PRODEV_RATE_MIN_SAMPLE` (bawaan 5, DB mockup 5).
5. Kecocokan nama proses: nama proses master pertama yang menjadi potongan teks label WO (urutan `pluck`, tanpa urutan terjamin).
Tidak ada contoh angka: tidak ada WO `COMPLETED` di DB mockup (status WO ada: DRAFT 51, SCHEDULED 4, CANCELLED 10; FAKTA, query 2026-10-16).

## Kasus tepi
- Tanpa WO selesai: `rules` kosong dan `note` "Belum ada data pemakaian nyata ..." (baris 107).
- Sampel di bawah minimum tetap menulis usulan, hanya tidak diterapkan otomatis.
- Label langkah WO yang tidak mengandung nama proses master dipakai apa adanya sebagai kunci, jadi tidak pernah cocok dengan aturan.
- DB mockup: 26 aturan aktif, 0 ber-`auto_apply`, semua `rate_source` MANUAL (FAKTA, query), jadi engine hanya akan menulis kolom usulan.

## Pemakai (relasi)
- **sumber**: Production (WO, output, defect), Inventory (rekonsiliasi pemakaian). **hilir**: `prod_bop_rule` yang dibaca `SpecEngine` untuk takaran bahan dan waste BOP/BOM. Dengan begitu perubahan di sini ikut memengaruhi BOM Inquiry/ProDev berikutnya dan WO berikutnya (bukan dokumen lama: snapshot).

## Butir terbuka
Tidak ada butir ATTENTION-ENGINE khusus engine ini (dibaca penuh).

## Temuan (dicatat, tidak diperbaiki)
1. Registry (`EngineRegistry.php:197`) menulis `apply` "false = only show them". FAKTA kode: `refresh()` menulis kolom usulan ke `prod_bop_rule` (baris 89) untuk setiap aturan berusulan, walau `apply = false` (perintah `--dry`). Yang tidak terjadi hanya penimpaan takaran/waste.
2. Registry menulis "Below the minimum sample nothing is proposed". FAKTA: usulan tetap ditulis; minimum hanya menahan auto-apply.
3. Registry menulis "Values typed by an officer are never overwritten". FAKTA: kolom `rate_source` dibaca (baris 40) tetapi tidak dipakai dalam keputusan; yang menahan hanya `auto_apply = 0`. Aturan ber-`auto_apply` yang pernah diketik officer dapat ditimpa.
4. `processWaste` tidak menyaring `wo_status = COMPLETED` (baris 158-164), sedangkan `materialUsage` dan `woCount` menyaring. Waste dihitung dari semua WO sejak tanggal mulai, termasuk yang belum selesai.
5. Tombol terapkan usulan (`bop-rule.blade.php:261-280`) menandai `rate_source = AUTO` walau dilakukan manual oleh admin.
6. Tidak ada tes.

## Tes
Tidak ada.
