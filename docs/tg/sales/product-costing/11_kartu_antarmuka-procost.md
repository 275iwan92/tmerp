# Kartu konsep: Antarmuka ProCost (sisi Sales)

> Kelompok B, 2026-10-16, menunggu persetujuan Jo. Hanya batas Inquiry/ProDev <-> ProCost: apa yang diterima ProCost, apa yang diberikan ke Sales, dan tanda `stat_confirm_procost`. Rumus dan engine costing: lihat `10_engine_costing-advisor.md` dan butir terbuka di `docs/ENGINE-ATTENTION.md`. HPP, margin, dan rumus harga TIDAK ada di bagian atas kartu ini (A.8-A.9); angka harga hanya setelah penanda johiwan-only. FAKTA = dilihat sendiri (DB mockup, kode dengan baris).

- **Jenis dokumen**: kartu konsep (antarmuka). **Modul/sub-modul**: Sales > Inquiry & Quotation > Pro-Cost.
- **Nama kanonik**: Antarmuka ProCost = `ProCostContextService` (masuk), `iqp_costing.published_flag` + `InquiryStage::syncProcost` (tanda), `TierPricingResolver` (keluar ke Sales), `ReverseCostingService` (balik dari costing INSTANT).
- **Sinonim**: Pro-Cost Mode #1 (Integrasi / Ambil Data), Publish costing, deklasifikasi harga, Ready to Quote.
- **BUKAN sama dengan**: Costing Instant (mode manual, `iqp_costing.mode='INSTANT'`, tanpa Inquiry); engine Costing Advisor.

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Meja ProCost | Inquiry masuk meja ProCost bila `stat_confirm_sales` = 1, tidak batal/LOST, dan (jenis NEW/REVISE) `stat_confirm_prodev` = 1; RECALC langsung. Grid ProCost memakai kode modul `COSTING` (meja `PROCOST`). | FAKTA, `Sales.php:249-250,326-330`; `CostingListGrid.php:22-25,38-41` |
| Isi tabel (mockup) | `iqp_costing` 26 baris: DRAFT 4, CALCULATED 9, PUBLISHED 13; `mode` INTEGRATED 17 (semua tertaut Inquiry NEW), INSTANT 9 (`inquiry_id` NULL, 0 published); `calc_type` NEW 25, RECALC 1. `iqp_costing_price_tier` 37 baris. `inquiry_id` bersifat UNIK (satu costing per inquiry). | FAKTA, `show columns` + query 2026-10-16 |
| Konteks baca-saja untuk ProCost | Per part costing: `iqp_part` (+ BOM `bomMaterials`, proses, finishing, tinta), `prod_part` (+ proses, finishing), layout ProDev (`prodev_part_id`/`prod_layout_group_id` tersimpan di part costing, atau layout CONFIRMED terbaru), geometri hasil layout ("lembar cetak per plano", "isi per lembar", ukuran cetak), artwork. BOP: dari ProDev bila ada, selain itu dari Inquiry. | FAKTA, `ProCostContextService.php:26-47,115-150` |
| Qty | Tier costing menunjuk tier Inquiry: `iqp_costing_price_tier.tier_id` -> `iqp_tier.id_tier`. Data: 0 tier published yang tidak tertaut ke tier inquiry yang sama; 0 costing published tanpa tier harga. | FAKTA, query 2026-10-16 |
| Publish | "PUBLISH DIREKSI = deklasifikasi harga dari Sisi A (rahasia) ke Sisi B (Sales)": hanya bila `doc_status = PENDING_APPROVAL`; gerbang admin atau grup `DIREKSI`; menulis `doc_status=PUBLISHED`, `published_flag`, `is_approved`, `approved_by/_at` dan `iqp_inquiry.sub_state='PRICE_CALCULATED'`. | FAKTA, `pages/inquiry/costing.blade.php:554-584` |
| Tanda | `stat_confirm_procost` DIHITUNG: 1 bila ada `iqp_costing.published_flag=1` untuk inquiry itu; dipicu event `IqpCosting::saved` (`InquiryStage::listen`). Konsistensi data: 0 inquiry bertanda tanpa costing published, 0 costing published tanpa tanda. | FAKTA, `InquiryStage.php:59-70,99-105`; `AppServiceProvider.php:75`; query |
| Keluar ke Sales | Hanya bila costing published: `TierPricingResolver::rows` mengisi harga per tier (`price_excl`, `price_incl`) dan menandai tier termurah; bila belum published SEMUA kolom harga NULL. Pemakai: Nota Inquiry, Quotation (`availableInquiries` mensyaratkan `published_flag`), grid "Ready to Quote". | FAKTA, `TierPricingResolver.php:13-40`, `QuotationBuilder.php:46-57,149-154`, `Sales.php:237,285` |
| Yang tampak di worklist Sales | Hanya status dokumen costing (`NOT STARTED` bila belum ada) dan tahap (`Pro-Cost`, `Ready to Quote`); tidak ada angka biaya. | FAKTA, `Sales.php:274,280-287` |
| Akses rumus | Rincian rumus dan HPP hanya untuk login pada konstanta `PROCOST_FORMULA_LOGINS`; nilai di `sys_constant` mockup `johiwan,wiwik2`. | FAKTA, `FormulaAccess.php:16-26`; query |
| Reverse | Costing INSTANT -> Inquiry NEW baru + Master Product DRAFT + part + `prod_layout_group` DRAFT + `iqp_tier`; costing berubah menjadi INTEGRATED. 0 kali dipakai di data (`reversed_at` kosong, 0 inquiry `source='REVERSE'`). | FAKTA, `ReverseCostingService.php:44-197`; query |

## Relasi
- **sumber**: Inquiry (spec, tier qty), ProDev (layout, BOP, BOM), master tarif dan konstanta COSTING (milik kartu ProCost lain).
- **dipakai_oleh**: Quotation (harga per tier), Nota Inquiry, CO (toleransi qty dari `tolerance_pct` tier), Win-Rate.
- **mengalir**: costing PUBLISHED -> baris Quotation (`unit_price` = harga tier; snapshot) -> CO. Sebelum publish, Sales tidak melihat angka; sesudah publish melihat harga, bukan HPP/margin (A.8). Persen toleransi dibekukan ke CO.
- **zona beku**: ProCost dan Quotation. Hanya dicatat.

## Temuan (dicatat, tidak diperbaiki)
1. **Reverse menulis tanda ProDev langsung.** `ReverseCostingService.php:94-95` menetapkan `stat_confirm_sales=true` DAN `stat_confirm_prodev=true`, sedangkan layoutnya dibuat `DRAFT` (baris 126). A.20 menetapkan tanda ProDev dihitung dari layout CONFIRMED. `syncProdev` baru menurunkannya bila belum ada costing, padahal costing sudah ada, jadi tanda tetap 1 walau layout DRAFT. Belum pernah terjadi di data (jalur 0 kali dipakai).
2. **Satu kolom, dua arti.** `iqp_tier.price_excl/_incl` dibaca Nota Inquiry sebagai "Target customer", tetapi satu-satunya penulis yang ditemukan adalah Reverse yang mengisinya dengan harga hasil costing (`ReverseCostingService.php:170-171`). Lihat kartu `../inquiry-product/11_kartu_inquiry-dan-part.md` temuan 5.
3. **Daftar login rumus tidak sama dengan ringkasan aturan.** `docs/ringkas/globaluserrules.md` (A.8) menulis "rumus ProCost hanya login `johiwan`"; konstanta mockup = `johiwan,wiwik2`; komentar kode menyebut tiga orang (johiwan, Wiwik Ardiatno, Carrij Suhendra, yang ketiga belum punya akun). Mana yang berlaku perlu ditegaskan Jo. Hanya dicatat.
4. **Dua status publish.** `doc_status` mengenal `PENDING_APPROVAL` (syarat publish, `costing.blade.php:569`), tetapi data hanya DRAFT, CALCULATED, PUBLISHED; jalur tombol `PENDING_APPROVAL` tidak tampak di data. Bukan kesalahan; belum teruji dengan data.
5. **Reverse bukan atomik lintas-DB** (diakui di komentar kode, baris 37-40): dua koneksi, commit berurutan.
6. **`Screens.php:40` mendaftarkan layar `sales-price` ("Prices from Pro-Cost")** tetapi tidak ada berkas `resources/views/v2/screens/sales-price.blade.php` dan tidak ada item menu `v2:sales-price`; entri daftar tampaknya usang (belum dicek apakah dipakai tempat lain).
7. **Klaim `claude_vital_flows.md` "ProCost Mode #1 sudah ada via `pickLayoutGroup()`" TERBUKTI**: method ada di `pages/inquiry/costing.blade.php:352` dan menarik geometri lewat `ProCostContextService::layoutGeometry` (baris 259-272).

## Sudah ada? Dicari di mana, hasil
Dicari: `published_flag`, `stat_confirm_procost`, `TierPricingResolver`, `ProCostContextService`, `price_per_pcs`, `canViewSalesPrice` di `app/`, `resources/`, `routes/`, `packages/`; tabel `iqp_costing*` di `ddtsdi`. Hasil: seluruh antarmuka SUDAH ADA; tidak ada tabel baru yang diperlukan. Visibilitas harga di Quotation: lihat kartu Quotation temuan 3.

<!-- johiwan-only -->

## Contoh angka alur harga (rahasia; dari DB mockup 2026-10-16)
- Tier published `IQ99KSP001`, qty 30.000: `price_per_pcs` 2.491,7647; `c_after_ppn_pc` 2.765,8588. `IQ99MC0001`, qty 20.000: 1.395,2941 / 1.548,7765. `IQ99MC0002`, qty 15.000: 2.525,8824 / 2.803,7294.
- Baris Quotation memakai `price_per_pcs` (excl. PPN) sebagai `unit_price`: 24 dari 24 baris sama persis (tidak ada yang diedit). PPN dihitung ulang oleh Quotation dari `TaxRate::ppn()`, tidak diambil dari `c_after_ppn_pc`.
- Rantai HPP -> margin -> harga ada di `10_engine_costing-advisor.md` (bagian rahasia); kartu ini tidak membaca kolom HPP atau margin.

