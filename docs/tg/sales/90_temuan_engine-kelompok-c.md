# Temuan lintas engine, kelompok C (Imposition, Spec, Usage Rate, Dieline, Costing Engine, Reverse Costing, Material Planning)

> DRAFT 2026-10-16, menunggu persetujuan Jo. Hanya DICATAT, tidak ada yang diperbaiki. Detail per engine ada di kartu masing-masing (bagian "Temuan"). Label FAKTA = dilihat langsung sesi ini (kode, query `ddt*`, tes dijalankan).

## A. Verifikasi daftar engine terhadap registry
FAKTA: `EngineRegistry` memuat 60 key. Yang relevan untuk Sales: `dieline`, `imposition`, `spec`, `usage-rate`, `routing`, `costing`, `costing-advisor`, `reverse-costing`, `material-planning`, `booking-maintenance`, `cpdel`, `cpdel-promise`, `flow`, `wo-generation`. Semua method yang didaftarkan untuk engine kelompok C ada di kelas (dicocokkan satu per satu); kekurangannya pada method publik yang TIDAK terdaftar (Spec: `processOfBom`; Material Planning: 15 method; Dieline: ekspor `eval/scope/STROKE`).

| # | Temuan | Bukti |
|---|---|---|
| 1 | **`EngineCatalogTest::test_every_engine_class_is_registered` GAGAL sekarang**: kelas `App\Services\MasterCard\MasterCardDummyEngine` bernama `*Engine` tetapi belum didaftarkan di registry. Engine ini membangun data dummy berlevel (Inquiry, ProDev, ProCost, Quotation, CO) dari PDF Master Card; hulu Sales. Kartunya tidak ditulis (di luar cakupan dan hanya `ddt*`), perlu didaftarkan atau dikecualikan oleh Jo. | tes dijalankan 2026-10-16: 5 lulus, 1 gagal |
| 2 | **Routing Template Engine tidak terhubung ke ProDev.** Registry (`EngineRegistry.php:240`) menulis pemakai "Pro-Dev (BOP)". Pemanggil `RoutingTemplateService` hanya `routing-template.blade.php:143,152` (layar Setup) dan tes `MachineProcessCapabilityTest`; `SpecEngine`/`WoGenerationService` tidak memanggilnya. Tabel `prod_routing_template` kosong di DB mockup (0 baris). Karena itu kartunya TIDAK ditulis. | grep `app resources routes packages`; query |
| 3 | **Tiga arti angka "UP/cut/isi/up_count"**: `iqp_costing_part.i_cut_per_plano` = lembar cetak per plano; `i_isi_per_lb` = UP per lembar cetak (`ProCostContextService.php:111-113`); `iqp_layout.up_count` dan `prod_layout_group.up_count` dipakai SpecEngine sebagai UP per lembar cetak (`sheetsPerPlano` dipaksa 1, `SpecEngine.php:1059`) tetapi `MaterialPlanningEngine.php:552-554` menamainya "UP per plano"; `ReverseCostingService.php:125` mengisi `up_count` dari `i_cut_per_plano` saja. Selama 1 plano = 1 lembar cetak ketiganya sama; berbeda bila tidak. DUGAAN, perlu keputusan Jo tentang istilah baku. | kode, nomor baris di kartu |
| 4 | **Nilai gap 3 / gripper 12 / margin 8 mm ditulis literal** di `inquiry/imposition.blade.php:45-50` dan `CostingAdvisor.php:276`, padahal konstanta `PRODEV_GAP_MM`, `PRODEV_GRIPPER_MM`, `PRODEV_MARGIN_MM` ada (nilai sama sekarang). | grep |
| 5 | **Dieline: tiga salinan kode** (JS `resources/js`, JS `packages/mockup-parts`, port PHP `TmDieline`). Diuji sesi ini: 27 tipe aktif, hasil `flat` dan `svg` identik byte untuk byte (dims uji tunggal). Tidak ada penjaga otomatis untuk perubahan di masa depan selain 3 kasus tes unit. | node v24 vs PHP; `TmDielineTest` |
| 6 | **Usage Rate Engine: klaim registry tidak sesuai kode** (apply=false tetap menulis kolom usulan; usulan tetap ditulis di bawah minimum sampel; `rate_source` MANUAL tidak melindungi aturan ber-`auto_apply`). Tanpa tes. Tidak ada WO COMPLETED di DB mockup. | `UsageRateEngine.php:53-89`; query |
| 7 | **Costing Engine tidak menjaga status**: `run` menulis `CALCULATED` tanpa memeriksa `PUBLISHED` (`CostingEngine.php:59`); `hitung()`/`recalc()` tidak memanggil `editable()`. Risiko, BELUM diuji. | kode |
| 8 | **Reverse Costing**: tanda `stat_confirm_sales` dan `stat_confirm_prodev` langsung 1 padahal layout ProDev DRAFT; `flat_w/h` diisi dimensi produk; commit dua DB berurutan tanpa pemulihan. | `ReverseCostingService.php:94-95,146-148,187-188` |
| 9 | **Registry vs kode (teks)**: Imposition "dipakai ProDev workbench" (tidak; hanya ProDev lama yang tidak ditaut menu) dan "Costing Engine (flat area)" (tidak); Spec `writes` tanpa `iqp_part_*` dan "source per row" (kunci tidak ada) dan "Master Product sync" (tidak ada pemanggilan); Costing `reads` memuat `mat_coating_rate` (tidak dibaca); Material Planning "from the moment costing has calculated" (kode: published). | kartu masing-masing |
| 10 | **Tidak ada tes** untuk Usage Rate; tes Imposition tidak mencakup `run`/sweep/`cells`; tes Spec hanya `forWorkOrder`; tidak ada tes yang membandingkan HPP Mode 1 vs Mode 2. | grep `tests/` |
| 11 | **Nomor baris di ENGINE-ATTENTION bergeser** (tidak diubah): butir 02 `SpecEngine.php:768` kini 778, `MaterialPlanning.php:180` kini 184; butir 04 `SpecEngine::toStockUom :727` kini 737. Baris `MaterialPlanningEngine.php:684` dan `:922`, `PlanningRun.php:52`, `MaterialShortage.php:66` masih benar. | grep |
| 12 | **`SpecEngine::saved('bop')` menganggap `source = INQUIRY` sebagai baris non-ENGINE** (`:943`), sedangkan bahan menyaring INQUIRY (`:950`). Di `ddt*`: 104 baris `prod_part_process` ber-source INQUIRY. Disengaja atau tidak: BELUM diketahui. | kode; query |

## B. Layanan hulu/hilir Sales yang BUKAN `*Engine` (tidak terdaftar, tanpa kartu di kelompok ini)
FAKTA (dibaca sebagian): `ProCostContextService` (pemetaan layout ProDev ke kotak costing: cut, isi, ukuran cetak; dipanggil `costing.blade.php:150,262`, `CostingAdvisor.php:242`), `ArtworkAnalyzer` (analisa warna artwork di server, bahan Ink Configuration), `ProcurementBridge` (BOM kurang -> PR; langkah BOP subkon -> PR + surat jalan), `PartDieline` (simpan dieline dan artwork ProDev), `ProductMasterSync`, `InquiryStage`, `InquiryWriter`, `QuotationBuilder`. Kandidat kartu "layanan" bila Jo menghendaki; tidak ditulis di sini.

## C. Engine di luar cakupan yang berbatasan dengan Sales (hanya ditautkan)
`cpdel`, `cpdel-promise` (kartu `order/20_engine_co-order-builder.md`, `order/21_engine_cpdel-promise.md` oleh kelompok lain), `flow` (approval CO), `wo-generation` (CPDEL -> WO; memanggil `SpecEngine::forWorkOrder`), `booking-maintenance` (kartu belum ada; disebut di `order/30_engine_material-planning.md`).

## D. Yang tidak bisa diverifikasi di sesi ini
- Layar tidak dibuka di browser (aturan sesi): perilaku visual layar dan tombol hanya dibaca dari kode.
- `UsageRateEngine::refresh` tidak dijalankan (menulis `prod_bop_rule`); tidak ada contoh angka.
- `inkLines`, `standardLines`, `summarize`, `frozenLines` di `MaterialPlanningEngine` tidak dibaca; `MrpBuyTest` tidak dijalankan.
- Isi `docs/guides/sales/*` tidak dibaca.
- Pembanding tangan untuk `sweepInterlock` dan sebagian `combinePair` tidak dilakukan.
