# Kartu konsep: Antarmuka ProDev (sisi Sales)

> Kelompok B, 2026-10-16, menunggu persetujuan Jo. Hanya batas Inquiry <-> ProDev: apa yang diterima ProDev dari Inquiry, apa yang dikembalikan, dan tanda `stat_confirm_prodev`. Mesin hitung (SpecEngine, ImpositionEngine) bukan isi kartu ini; lihat butir terbuka di `docs/ENGINE-ATTENTION.md` (ATTENTION-ENGINE-01) dan kartu ENGINE kelompok engine bila sudah ada. FAKTA = dilihat sendiri (DB mockup, kode dengan baris). Klaim dokumen lama diverifikasi di bagian "Cek klaim dokumen lama".

- **Jenis dokumen**: kartu konsep (antarmuka). **Modul/sub-modul**: Sales > Inquiry & Quotation > Pro-Dev (menu: Pro-Dev New, Pro-Dev Revise, Pro-Dev Manual, Master Product Market, Master Card Collection).
- **Nama kanonik**: Antarmuka ProDev = `InquiryStage::openInProdev` + `InquiryWriter::sendToProDev` (masuk), `SpecEngine::persist` + layout `prod_layout_group` + `InquiryStage::syncProdev` (keluar), tanda `iqp_inquiry.stat_confirm_prodev`.
- **Sinonim**: Kirim ke ProDev, Mode #1 (integrasi), part master dari Inquiry, Confirm ProDev.
- **BUKAN sama dengan**: ProDev Mode #2 (Pro-Dev Manual: part master tanpa Inquiry, `Prodev::parts` mengecualikan part yang punya `iqp_part.prod_part_id`); Master Card Collection.

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Pintu masuk | Grid Pro-Dev New/Revise (satu baris = satu inquiry NEW/REVISE yang `stat_confirm_sales=1`, tidak batal). Buka -> `GET /prodev/open/{inquiry}` -> `InquiryStage::openInProdev`. Tombol "Kirim ke ProDev" di form Sales sudah dihapus (A.20). | FAKTA, `routes/v2.php:1654-1663`, `InquiryStage.php:73-87`, `ProdevNewGrid.php` |
| Gerbang | Hanya NEW/REVISE yang Confirm Sales; RECALC tidak lewat ProDev. | FAKTA, `InquiryStage.php:20,75`; `Sales.php:249` |
| Apa yang dibuat saat dibuka | Untuk tiap part komponen tanpa `prod_part_id`: `sendToProDev` membuat Master Product DRAFT (`ensureDraftProduct`: `product_code='DRAFT-<id_inquiry>'`; REVISE = turunan dari product induk, `revision_no`+1), membuat `prod_part` (`part_code='P<id_part>'`, P/L/T dalam cm = mm/10, `activate`), menulis `iqp_part.prod_part_id`; menyalin `pack_qty`, `cfg_color`, finishing, dan artwork (sisi luar) sebagai `prod_part_attachment`. | FAKTA, `InquiryWriter.php:97-155`, `IqpInquiry.php:132-154` |
| Yang TIDAK disalin saat dibuka | `part_type`, `dieline_spec`, `flat_w/h`, `joint`, bahan, `qty_per_sku`. Bahan, finishing, proses subkon dibaca LANGSUNG dari Inquiry oleh SpecEngine bila part tertaut (`$iqp`); ukuran sobekan dari `iqp_part.flat_w x flat_h` bila `die_w_cm/die_h_cm` kosong. `part_type` dan `dieline_spec` baru tersalin penuh ke master saat WIN (`ProductMasterSync::part`). | FAKTA, `InquiryWriter.php:109-119`; `SpecEngine.php:137-221,376-396`; `ProductMasterSync.php:61-78` |
| Yang dibaca ProDev dari Inquiry | Bahan (`iqp_part_material`), finishing (`iqp_part_finishing`), proses subkon (`iqp_part_process`), qty tier utama (`iqp_tier`), `pack_qty`, `cfg_color`, flat, dieline (panel "Part & Dieline"), warna artwork. | FAKTA, `SpecEngine.php` (`finishings`, `inputMaterials`, `tierQty`, `subconMap`, `blankArea`), `Data/Prodev.php:106-136` |
| Yang dikembalikan | "Simpan 2 arah": BOP, BOM, tinta ditulis ke master (`prod_part_process/_material/_ink`) DAN ke Inquiry (`iqp_part_process/_material/_ink`), dengan penanda `source` ENGINE / OVERRIDE / MANUAL; hasil officer (OVERRIDE) tidak ditimpa engine. Bahan pilihan Sales (`MANUAL`) di Inquiry DIPERBARUI di tempat (`rawmat_id`, `rawmat_name`, gramatur, ukuran), baris lain ditulis ulang. Dua koneksi dibungkus transaksi bersarang, bukan transaksi lintas-DB. | FAKTA, `SpecEngine.php:1103-1247` |
| Dieline dua arah | Panel "Part & Dieline" mengubah `dieline_spec`, flat, dan artwork di `iqp_part` dan `prod_part` sekaligus (A.17); ukuran kepala Master Product ikut bila produk ACTIVE. | FAKTA, `PartDieline.php:29-53` |
| Layout | Simpan layout: master `prod_layout_group` (+ `prod_layout_group_part`, 60 baris: CONFIRMED 39, DRAFT 21) DAN snapshot `iqp_layout` per part Inquiry (28 baris, kolom `stat_confirm`). | FAKTA, `show columns`, query; `pages/production/prodev.blade.php:43-49,225-295` (komentar dan kode) |
| Tanda `stat_confirm_prodev` | Dihitung, bukan diklik: `InquiryStage::syncProdev` = 1 bila SEMUA part non-rakitan punya layout terbaru berstatus CONFIRMED; turun ke 0 hanya selama belum ada `iqp_costing`. Dipicu event `ProdLayoutGroup::saved` (`AppServiceProvider.php:75` memanggil `InquiryStage::listen`) dan rute panggilan workbench. | FAKTA, `InquiryStage.php:23-50,99-105`, `routes/v2.php:1666-1673` |
| Pemakai hilir | ProCost mengambil layout ProDev (`ProCostContextService`: `prod_layout_group`, BOM, BOP, tinta, finishing; baca-saja); tombol Confirm workbench menuju `procost?part=`. | FAKTA, `ProCostContextService.php:23-40`, `prodev.blade.php` (parameter `costingUrl`) |
| Sebaran tanda (mockup) | `stat_confirm_prodev=1`: 15 inquiry NEW (13 CONVERTED + 2 SUBMITTED). REVISE (2) belum Confirm ProDev. | FAKTA, query |

## Cek klaim dokumen lama (diverifikasi 2026-10-16)
| Klaim (sumber) | Hasil |
|---|---|
| "ProDev Mode #1: LINK INI BELUM ADA, 0 jalur kode bikin `prod_part` dari `iqp_part`" (`claude_vital_flows.md`, status 2026-09-03) | **USANG/SALAH hari ini.** Jalurnya ada: `sendToProDev`, `openInProdev`, rute `prodev.open`. 35 dari 42 `iqp_part` sudah punya `prod_part_id`. |
| "Tipe Produk dipindah dari per-Part ke level Inquiry (Header)" (`claude_vital_flows.md`) | **SEBAGIAN.** Dipilih di header, tetapi tersimpan per part di `iqp_part.dieline_spec.type_code` (`InquiryWriter.php:254-258`); `iqp_inquiry` tidak punya kolom tipe produk (`show columns`). |
| "Tiap Part gerbang di Peran Part (Body/Lid/...)" (`claude_vital_flows.md`) | **Hanya gerbang layar.** `part_role` ada di komponen `InquiryPart.php:9,24,97`, tetapi tidak ada kolom dan tidak ditulis `InquiryWriter`/`InquiryPayload`; tidak tersimpan. |
| "`iqp_costing.calc_type` hardcode `'NEW'`" (`claude_vital_flows.md`) | **Data tidak lagi begitu**: 25 NEW, 1 RECALC di `iqp_costing`. Kodenya BELUM dibaca ulang. |
| "`prod_type` 63 (28 aktif); `prod_routing_template/step` 0 baris; `prod_bop_rule` 26 baris dengan `part_type` semua kosong; `mat_join_type` 4" (`handoff-prodev-session.md` §4, `engine-process-template-per-tipe.md` §6) | **BENAR**: query 2026-10-16 memberi 63/28, 0/0, 26/26 kosong, 4 (STITCHING_SINGLE/DOUBLE, GLUE_SINGLE/DOUBLE). |
| "`SpecEngine` menurunkan `NEEDS_GLUE` dari teks `joint_type` (pola `glue|lem|lock bottom|crash|auto bottom`)" | **BENAR** (`SpecEngine.php:43,95`). **Tambahan**: kolom `prod_part.joint_type` tidak ditulis kode v2 mana pun (hanya migration seeder, form v1 lama `old_prodev`); dari 35 part tertaut Inquiry, 0 punya `joint_type` terisi, sehingga `NEEDS_GLUE` selalu salah untuk part yang lahir dari Inquiry. Inquiry sendiri menyimpan sambungan sebagai `iqp_part.join_type_id` (rakitan; 8 part master punya `join_type_id`). |
| "`prod_routing_template`/`RoutingTemplateService` tidak dipakai SpecEngine, hanya dirujuk EngineRegistry" | **BENAR** untuk kelas service: hanya `EngineRegistry.php:40,219,238` (dan dirinya sendiri) yang memakainya; model/grid/halaman v1 `routing-template` memakai tabel, bukan service. `SpecEngine.php:190-193` hanya membaca `prod_bop_rule`. |
| "BOM kosong BOX BTP OMEGAMOTO SPOTLIGHT (IQ2690068, 100 pcs): flat 740 x 650, pack 100, DC+SHEET, `iqp_layout` belum ada, `join_type_id` kosong" | **BENAR di DB**: `iqp_part` 1668: flat 740 x 650, `pack_qty` 100, `DC+SHEET`, `join_type_id` NULL, `iqp_layout` 0, tidak ada `prod_layout_group` untuk `prod_part` 516; `die_w_cm/die_h_cm` NULL. **Tambahan**: part master 516 sudah punya BOP 9 baris, BOM 2, tinta 2 (tersimpan), jadi "BOM kosong" tergantung layar mana yang dilihat Jo. |
| "lembar cetak = qty_in langkah BOP pertama berunit LBR; tanpa up/canvas jadi '-'" | **BENAR dengan koreksi**: bukan langkah pertama, melainkan baris BOP PERTAMA yang berunit `LBR` dan `qty_in` terisi; bila tidak ada -> NULL (`SpecEngine.php` fungsi `firstSheets`). |
| "Jalur WO (`forWorkOrder`) punya cadangan 1 up + peringatan; jalur layar belum" | **BENAR**: `forWorkOrder` mengganti `upCount=0` dengan 1 dan menambah peringatan `ups` (`SpecEngine.php:1042-1093`); `generate` memakai `max(0, upCount)` tanpa cadangan (baris 59-70). |
| "`iqp_part.part_type` hanya DC 25, DC+SHEET 13, DC+SF 3, SHEET 1" | **BENAR** (42 baris; 6 di antaranya rakitan). |
| "Master Card dummy 15 kartu hanya 5 tipe" | **BENAR**: `prod_master_card` 15 baris, 5 `type_code` (RSC_2PC 5, RTE_BOX 6, TRAY_LID_DW 2, LOCK_BOTTOM_2PC 1, BUKU 1). |
| "`inquiry-new.blade.php:68-70` menyatakan `type_code` disimpan ke `iqp_part.part_type`" | **Label layar menyesatkan** (sudah dicatat di kartu tipe produk): nilai di `iqp_part.dieline_spec.type_code`. |

## Relasi
- **sumber**: Inquiry (Confirm Sales), master tipe/BOP rule, mesin dan proses (REAL, tidak disentuh).
- **dipakai_oleh**: ProCost (konteks baca-saja), Master Product/WIN, PPIC/WO (`forWorkOrder`), Master Card (a).
- **mengalir**: Inquiry -> `prod_part`/layout/BOP/BOM/tinta -> kembali ke Inquiry (`iqp_part_*`) -> tanda `stat_confirm_prodev` -> ProCost. A.20.
- **zona beku**: ProDev dan Inquiry Product. Kartu ini hanya mencatat; tidak ada yang diubah.

## Temuan (dicatat, tidak diperbaiki)
1. **`NEEDS_GLUE` bergantung pada kolom tanpa penulis** (lihat tabel klaim). Ini menguatkan butir keputusan Jo #5 di `handoff-prodev-session.md` §6 dengan bukti kode + data.
2. **ProDev menimpa pilihan bahan Sales** (`rawmat_id`, `rawmat_name`, ukuran) di `iqp_part_material` lewat Save (`SpecEngine.php:1216-1221`); A.17 hanya mengizinkan ProDev mengubah dimensi, flat, artwork. Apakah penimpaan bahan itu disengaja BELUM diputuskan.
3. **Dua penyimpanan layout** (`prod_layout_group` master dan `iqp_layout` snapshot) dengan pembaca berbeda: tanda ProDev membaca master (`InquiryStage.php:23-35`), `forWorkOrder` dan `PlanFacts` membaca `iqp_layout` dahulu (`SpecEngine.php:1042-1047`, `PlanFacts.php:27`). Jika keduanya berbeda hasilnya berbeda; konsistensi keduanya tidak diperiksa.
4. **`sendToProDev` tidak menyalin `part_type`**: 2 dari 35 part master tertaut punya `part_type` NULL padahal Inquiry `DC`. Dampak ke `MULTI_LAYER` (`SpecEngine.php:94`) belum dibuktikan.
5. **Dua inquiry bertanda ProDev tanpa part master** (`IQ2600001`, `IQ2600002`): lihat kartu Inquiry, temuan 2.
6. **Simpan lintas-DB tidak atomik**: `persist` menumpuk transaksi `tm2mst` dan `tm2sdi` (`SpecEngine.php:1128`); kegagalan di antara commit bisa membuat master dan Inquiry berbeda. Tidak dibuktikan terjadi.
## Sudah ada? Dicari di mana, hasil
Dicari: `sendToProDev`, `openInProdev`, `prod_part_id`, `stat_confirm_prodev`, `iqp_layout`, `prod_layout_group`, `joint_type`, `routing_template` di `app/`, `routes/`, `resources/`, `packages/`, `database/`; tabel terkait di `ddtsdi`/`ddtmst`. Hasil: seluruh antarmuka SUDAH ADA. Tidak ada tabel atau kolom baru yang diperlukan untuk antarmuka ini; usulan "resep per tipe" di `engine-process-template-per-tipe.md` adalah urusan engine, bukan kartu ini.


