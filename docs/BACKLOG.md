# BACKLOG — SEMUA YANG SUDAH DIRENCANAKAN TAPI BELUM DIKERJAKAN
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-09-02 (permintaan user: "catat dulu dalam satu file... semua
> apa yang harusnya di kerjakan dan belum di kerjakan"). **Satu file untuk
> SEMUA item tertunda lintas modul** — bukan cuma Fase 2 (Report System),
> tapi juga item lama dari sesi-sesi sebelumnya yang belum pernah
> dikumpulkan di satu tempat.
>
> **Sumber**: gabungan dari (a) `docs/*.md` (bagian "yang sengaja belum
> dikerjakan"/GAP di tiap standar & spec Tier), dan (b) memori sesi Claude
> Code sebelumnya (`project_*` notes). Item dari sumber (b) **belum
> di-verifikasi ulang ke kode terkini** — beberapa mungkin sudah
> terselesaikan tanpa tercatat di sini (sudah ditandai kalau saya temukan
> indikasi itu saat menyusun file ini), jadi **cek kondisi kode dulu**
> sebelum mulai kerjakan item apa pun dari sini, jangan langsung percaya.
>
> **Cara pakai**: kalau mau kerjakan 1 item, cari section-nya, baca
> rujukan filenya, verifikasi masih relevan, baru mulai. Kalau sudah
> selesai, HAPUS dari section asalnya di sini dan catat lengkap di
> **[`docs/LOG_JOB_DONE.md`](LOG_JOB_DONE.md)** (file terpisah, bukan
> ditulis di dua tempat) — supaya file ini tetap ramping, cuma isi item
> yang MASIH terbuka.

---

## 0. PRODUCT DEVELOPMENT — FOLLOW-UP FASE 1 (2026-09-02)

Fase 1 ProDev SUDAH SELESAI & LIVE (lihat `docs/LOG_JOB_DONE.md` tanggal
2026-09-02 "ProDev Fase 1 TUNTAS", spesifikasi lengkap
`docs/inquiry_product_costing/productdevelopment_tier1.md`). Item di bawah
ini ada di katalog tools §4.3 dokumen itu tapi TIDAK termasuk checklist
"Fase 1 SELESAI kalau" eksplisit — follow-up, bukan blocker:

- **Tool #10 — Intra-Product Part Combiner** (prioritas tertinggi dari sisa
  katalog, per urutan kerja resmi tier1 §7 poin 1): coba semua kombinasi
  gabung/pisah part dalam 1 `prod_product`, ranking by biaya kertas+plate
  total, hasilkan `prod_layout_group` yang menaungi >1 part. Perlu algoritma
  2D packing heterogen (rectangle berbeda ukuran dalam 1 plano) — beda &
  lebih kompleks dari `sweepFlap`/`sweepInterlock` (homogen, 1 ukuran
  diulang). Baca §4.2b tier1 doc dulu (aturan gabung/pisah = keputusan
  engine, bukan aturan tetap skema).
- **Tool #2 — Pcs→Plano (Best Sheet Finder)**: dari ukuran dus, cari otomatis
  plano standar (`mat_plano_size`) mana yang waste/biaya kertas termurah.
  Relatif sederhana — loop `ImpositionEngine::sweepFlap()`/`sweepInterlock()`
  utk TIAP baris `mat_plano_size` (bukan cuma yang dipilih officer di layar
  ProDev sekarang), lalu ranking gabungan across-plano. Layar ProDev
  (`resources/views/pages/production/prodev.blade.php::runEngineTingkat1/2()`)
  sudah punya semua building block, tinggal loop tambahan.
- **Tool #4 — Die-Rule Estimator**: hitung total meter lari pisau
  potong+lipat+perforasi dari geometri part (pakai `die_w_cm`/`die_h_cm`/body
  dims yg sudah ada di `prod_part`) → langsung jadi biaya tooling pengganti
  `i_pisau_faktor` manual di Costing sekarang.
- **Alokasi biaya proporsional `prod_layout_group` multi-part** (§4.2b/§5.1
  tier1 doc): CostingEngine belum punya logic ini — baru relevan setelah
  Tool #10 bisa menghasilkan group berisi >1 part (sekarang tiap group cuma
  1 part krn combiner belum ada). Formula sudah didesain di dokumen
  (proporsional berdasar luas area × `qty_up_in_group`), tinggal
  diimplementasi begitu Tool #10 jadi.
- **DC+SHEET/DC+SF formula** (`CostingEngine::calculateDcSheetPart()`) masih
  DITANDAI belum diverifikasi ke Excel/sumber asli (lihat docblock class) —
  kalau ada Excel riil kasus DC+SHEET/DC+SF, cross-check dulu sebelum dipakai
  harga produksi sungguhan.

---

## 1. FASE 2 — SISTEM REPORT 4 LEVEL x BIDANG (permintaan user 2026-09-02)

> **UPDATE sore 2026-09-02**: sebagian besar keputusan desain di section
> ini **SUDAH DIKUNCI** lewat diskusi lanjutan dgn user (bukan lagi
> "terbuka") — 3 dokumen jadi rujukan lengkap, JANGAN baca cuma section
> ini: **`docs/reportstandard.md`** (dokumen INDUK — 4 level, matrix
> level x bidang §2.3, mekanisme kode supervisor §3, keputusan chart),
> **`docs/topmanagementreport_tier1.md`** (katalog laporan detail level
> Top Management per domain), **`docs/security_brief.md`** §6 (sisi
> akses/menu-beda-per-level dari model yang sama). Ringkasan di bawah ini
> CUMA POINTER, bukan isi lengkap.

**Model final**: 2 sumbu independen — **Level** (`sec_group.mgmt_level`
1-5: Staff/Supervisor/Manager/GM/Direksi — kolom SUDAH ADA, komentar DB-nya
sendiri sudah define skala ini) x **Bidang** (Sales/Purchasing/Inventory/
Production/Finance & Accounting/HR). GM = grup `OPS_MGR` yang sudah ada
(BUKAN grup baru). Level 1-3 terkurung 1 bidang, Level 4 (Top Management,
Direksi+GM sekaligus) lintas SEMUA bidang. Detail penuh tiap sel matrix
(bidang x level, status ADA/belum per domain): `reportstandard.md` §2.3.

**Yang SUDAH diputuskan** (bukan lagi open item):
- Akses per level → reuse `mgmt_level` (BUKAN dimensi baru).
- Library grafik → **Chart.js via CDN**.
- Kode otorisasi Supervisor (bukan bagian katalog awal, muncul dari
  penjelasan user 2026-09-02) → alur "Supervisor generate kode sekali-pakai,
  Data Entry ketik utk buka akses edit sementara" — detail teknis
  (durasi, tabel/session, audit trail) **masih perlu didetailkan** saat
  mulai implementasi, tapi POLA-nya sudah dikunci.
- Menu beda per level → **kebutuhan BARU dikonfirmasi**, ternyata BUTUH
  perluasan skema (`sys_app_menu` belum punya gating per-item by
  `mgmt_level`, cuma warisan dari modul induknya) — lihat
  `security_brief.md` §6.2, JANGAN diimplementasi sepihak (ubah tabel
  menu inti, dampaknya ke semua menu existing).

**Yang MASIH terbuka** (`topmanagementreport_tier1.md` §5, `reportstandard.md` §5):
1. Lokasi menu laporan (grup "Laporan Eksekutif" lintas-modul vs tersebar).
2. Engine pivot/agregasi — **dicek 2026-09-02**: `HasCustomizableGrid`
   (grid custom view/sort/group-by yang sudah ada) TERNYATA group-by-nya
   cuma VISUAL, BUKAN agregasi SUM/COUNT — TIDAK bisa langsung jadi
   fondasi, harus dibangun baru.
3. ~~Win-rate Sales butuh kolom baru `iqp_inquiry`~~ **SELESAI 2026-09-02**:
   kolomnya (`sub_state` CONVERTED/LOST) TERNYATA sudah ada sejak awal,
   tinggal titik tulisnya — layar `sales/win-rate` sudah live, lihat
   `docs/LOG_JOB_DONE.md`.
4. Refresh data — real-time vs snapshot periodik.
5. **Katalog laporan Production** — `topmanagementreport_tier1.md` TIDAK
   cakup domain ini (cuma Sales/Procurement-Inventory/Finance/HR) — kalau
   Production jadi pilot, katalognya harus ditulis dulu dari nol.

**Koreksi atas catatan lama**: `topmanagementreport_tier1.md` §4.1 bilang
"laporan pipeline Customer Order belum bisa dibangun — CO belum ada layar
sama sekali" (per 2026-08-27) — **ini sudah TIDAK BENAR**, `co/entry.blade.php`
+ route `co.entry` sudah live. Laporan pipeline CO kemungkinan sudah bisa
dibangun, re-scope kalau diminta.

**Pilot pertama BELUM ditunjuk** ("saya tentukan sendiri nanti", user
2026-09-02) — **belum ada 1 layar report/kode-supervisor pun dibangun**,
JANGAN mulai modul/bidang mana pun tanpa arahan eksplisit.

---

## 2. FASE 1 (2026-09-02) — SISA YANG SENGAJA DITUNDA

Dari kerja Configuration/branding hari ini (`docs/configurationstandard.md`,
`docs/admindashboardstandard.md` §10-12) — sisa yang genuinely masih terbuka
(item selesai sudah dipindah ke History Pengerjaan):

- **Email template** (`resources/views/emails/portal/verify-email.blade.php`)
  — SENGAJA tetap hardcode "TELASIH MAKMUR" (bukan lupa): ini email portal
  karir ke CALON KARYAWAN eksternal, representasi NICKNAME company (sama
  alasan `layouts::portal.blade.php`), bukan APP_NAME aplikasi internal.
- **`sys_constant_category`** baru punya 5 kategori (Branding/Finance/
  Production/Procurement/Inventory) — kategori lain (mis. HRM, kalau nanti
  ada konstanta khusus HR) belum ditambahkan, tambah manual saat perlu.
- **Lapis kosmetik tema dashboard Filament** — mekanismenya sudah benar
  (lihat History Pengerjaan), TAPI belum ada screenshot visual browser
  asli (cuma verifikasi via HTTP kernel) — kalau mau yakin 100% tampilannya
  benar, perlu login browser manual & lihat langsung tiap mode.

---

## 3. SALES / INQUIRY / CUSTOMER ORDER (tm2sdi)

- **Marketing Management** (CLAUDE.md §2, ditambahkan 2026-08-30) — modul
  BARU, belum ada 1 baris kode/dokumen Tier pun. Cakupan yang dideskripsikan
  user: log aktivitas sales (email/kirim quotation/kunjungan per customer)
  + tracking supply chain customer reguler/berkala. **Belum di-scoping
  detail** — kalau mulai dikerjakan, treat seperti modul baru (baca/tulis
  Tier doc dulu, jangan langsung bikin skema dari 1 paragraf deskripsi).
- **Manual input Customer Order & Product Costing** — user minta CO dan
  Costing bisa diisi manual (bukan cuma hasil engine/turunan Inquiry).
  Costing Hub saat ini engine-driven penuh. CO sudah ada layarnya
  (`co/entry.blade.php`) tapi belum jelas apakah "manual input" di sini
  maksudnya field tambahan atau alur terpisah — perlu klarifikasi ulang
  scope-nya sebelum eksekusi (catatan lama 2026-08-27, kemungkinan sudah
  berubah konteksnya sejak CO dibangun).
---

## 4. PROCUREMENT (tm2pur)

- **Purchase Receive — 3 alur, baru 1 yang ada**: PV/SPB saat ini cuma
  cover "Receiving dari PO" (supplier vs PO). **Belum dibangun**:
  "Receiving dari Customer" (consignment, customer kirim bahan sendiri,
  butuh stok terpisah/`ownership_type` — skema belum diputuskan) dan
  "Receiving dari Subkontraktor" (SFG kembali dari pihak ketiga, masuk
  `sfg_stock`). Detail lengkap: `docs/SIDEBAR_MENU_STANDARD.md` §6/§6a.
- **`StockConfirmService`** (auto Confirm→Stock IN) — trigger legacy SQL
  (`trg_rm_stock_confirm`/`trg_fg_stock_confirm`/`trg_sfg_stock_confirm`,
  ditemukan di dump lama) belum di-port jadi PHP Service. **Cek dulu**
  apakah bagian ini sudah tercakup oleh Stock In/Out yang sekarang live —
  kemungkinan sebagian sudah, catatan sumbernya sebelum Stock In dibangun.
- **12 dari 18 kategori Level-1 `mat_category`** masih kosong default COA
  account (`default_coa_account_id`/`default_stock_asset_account_id`) —
  layar isinya **SUDAH ADA** (`master/mat-category.blade.php`, bagian
  "Default Akuntansi" muncul otomatis utk kategori Level 1, dicek ulang
  2026-09-02 — sempat salah dicatat seolah belum dibangun). Kategori kosong:
  Gasoline, Solasi, Plastik, CTP Plate, Pack Material, Stationery/ATK, 2×
  Spare Part (Machine/Vehicle), Tools/Equipment, Laptop/Desktop/Networking,
  Jasa/Service, Peralatan Kebersihan — murni tinggal user isi manual per
  kategori (keputusan akun, bukan kode), akun GR/IR universal (813) sudah
  terisi semua.

---

## 5. INVENTORY (tm2inv)

- Sebagian besar sudah live (Stock In/Out/Return/Adjustment+Approval,
  3 kelas RM/FG/SFG). Yang tersisa mengikuti dari gap Procurement di atas
  (Purchase Receive 3-alur → tujuan akhirnya nulis ke `sfg_stock`).

---

## 6. PRODUCTION SCHEDULING / PPIC (tm2prd)

Modul paling matang cakupannya tapi masih banyak sisa — daftar dari
`project_production_scheduling_spec` (memori sesi, verifikasi ulang ke kode
sebelum eksekusi):

- `MachineCalendarService` masih jam tetap 08:00-20:00 Senin-Sabtu, belum
  sadar `emp_shift_roster` untuk kapasitas MESIN (baru dipakai utk validasi
  crew manual).
- Rate Band untuk task `resource_type=MANUAL` masih throw error (belum
  ditangani).
- `RateBandResolver` masih pakai qty target operasi utk hitung rate
  historis, bukan qty_good hasil Output (F15) yang sekarang sudah ada
  datanya real.
- `SchedulingRuleEngine::applySequencing()` masih 1-rule-only.
- Decision Feedback calibration loop masih sebatas log, belum ada loop
  belajar sungguhan.
- MTS Forecast → WO auto-replenishment generator belum ditulis (entry +
  accuracy tracking sudah ada, trigger generate WO-nya belum).
- **Simulation Sandbox penuh** ("Submit ke Master Schedule", Tier1 §5A.1 —
  tarik CO ke sandbox → PDR suggest → review → Submit) belum ada sama
  sekali — Worklist WO + WO Detail sudah jadi fondasi natural utk ini.
- WO-part convergence (1 operasi hilir dilayani >1 WO, mis. Badan+Tutup
  ketemu di Packing) — skema saat ini 1 `iqp_part` = 1 `prd_wo`, belum
  bisa model ini, butuh desain tabel baru (`prd_wo_convergence` atau
  sejenis).
- `iqp_part` → `prod_part` (master part reusable lintas-order) belum
  ter-link — `prod_part` sendiri ternyata tidak pernah terisi.
- Reroute-Subkontrak di dialog "Sisip Hot Job" masih cuma tampil opsi,
  belum bisa benar-benar di-apply (butuh generate PO Subkontrak otomatis
  dari situ, beda dari alur Breakdown→Subkontrak yang sudah ada).
- Qty Kebutuhan di tab Bill of Material WO Detail belum dikalikan faktor
  Imposition (`qty_factor`/`i_cut_per_plano`) — masih qty order mentah.
- `prod_part_artwork` kosong di semua baris — Master Card tab tidak bisa
  tampilkan foto produk/dieline asli, masih placeholder.
- **F10-F14 & F18-F20** (MRP netting, Material Substitution/Cutting Yield
  engine, Time-Phased Procurement, Lead Time Historical Tracking, Customer
  Interruption Profile reporting) — semua secara eksplisit ditunda user,
  belum digarap sama sekali.
- **Maintenance submodule** (`PROD_MTN_SCH`/`PROD_MTN_PREV`) — di luar
  cakupan modul Production Scheduling menurut Tier1 §6 sendiri, belum ada
  spec sama sekali.
- `PROD_MON_WO` sekarang sudah kepakai (Screen #3 Monitoring Dashboard) —
  **BUKAN lagi item terbuka**, catatan lama yang bilang ini ambigu sudah
  usang.

---

## 7. FINANCE & ACCOUNTING (tm2acc)

- **Aset Tetap (Fixed Asset)** — modul **belum ada sama sekali** (dicek
  2026-09-02: cuma akun COA "ASET TETAP PABRIK"/"Akumulasi Penyusutan
  Mesin" di `fin_account`, bukan tabel register). Menu placeholder
  `ACC_FIXEDASSET` (NOT_BUILT) sudah ditaruh di grup ACC (migration
  `2026_09_02_000019`), permintaan user. Butuh desain dulu sebelum
  dibangun: skema register (kode aset, tgl/nilai perolehan, metode &
  umur penyusutan, lokasi/departemen pemegang, link ke akun aset vs
  akumulasi penyusutan di COA), siapa yang input, apakah auto-jurnal
  penyusutan bulanan (mirip `JournalPostingService` PV) atau manual.
- **Auto-jurnal Delivery/Invoice** — PV & Stock Out(RM) sudah auto-posting
  (`JournalPostingService`), tapi Delivery(DO)/Invoice sengaja ditunda
  karena butuh mekanisme snapshot Costing V2 di titik shipment (Keputusan B
  `FIN_TIER_1.md`: COGS diakui saat DO, bukan saat Stock-Out RM). **DO
  sekarang sudah ada layarnya** (`dispatch/do/entry.blade.php`, live sejak
  2026-08-28) — prasyarat "DO belum ada" di catatan lama SUDAH TERPENUHI,
  tapi status mekanisme snapshot Costing V2 itu sendiri **belum
  dikonfirmasi ada atau tidak** — cek dulu sebelum mulai.
- ~~Outstanding PV report~~ **SELESAI 2026-09-02** — keputusan desain diambil
  otonom (flag simpel per-PV, opsi paling sederhana dari 3 yg diajukan,
  konsisten gaya Outstanding PR/PO existing yg header-level). Lihat
  `docs/LOG_JOB_DONE.md`.
- **Payroll (tm2pyr) — SELURUH MODUL belum dibangun** (bukan cuma
  enkripsi). CLAUDE.md §2 sendiri menandai ini requirement yang belum
  diimplementasikan sama sekali. Termasuk di dalamnya: enkripsi data
  gaji at-rest (algoritma/key management/kolom mana yang kena — belum ada
  keputusan desain apa pun, jangan mulai tanpa konfirmasi).
- **12 kategori `mat_category` tanpa default COA** — sama item dengan §4
  Procurement (mempengaruhi Finance juga karena ini soal akun jurnal),
  layarnya sudah ada, tinggal user isi.

---

## 8. HRM / RECRUITMENT (tm2hrm, tm2hrr)

- **`org_position` masih ada posisi HR Manager/Dept Head yang tidak
  ter-mapping** ke pegawai asli — approval Recruitment saat ini jalan
  lewat tabel terpisah (`rcr_approver_assignment`, role→user langsung)
  sebagai workaround, bukan lewat `org_position` yang seharusnya jadi
  sumber kebenaran jangka panjang. Perlu HR data-cleanup tersendiri, di
  luar cakupan Recruitment itu sendiri.
- **Flutter mobile channel** untuk Portal Rekrutmen — dicatat sebagai
  rencana di CLAUDE.md §2, backend Firestore sudah siap dipakai bareng,
  tapi kanal HP-nya sendiri belum dibangun sama sekali.
- **Master Komponen / arah final Master Card** — pertanyaan lama (Jo,
  2026-08-18) soal di mana data product/part/material/proses seharusnya
  diinput (WO vs Costing). **DITUNDA (keputusan user 2026-09-02)**: akan
  dibahas nanti bersamaan modul **Sales — Product Costing**, bukan ditutup
  dari sisi WO Detail Master Card saja. Jangan asumsikan tab Master Card
  (2026-08-28) sudah jadi jawaban final sebelum diskusi itu terjadi.

---

## 9. MIGRASI FILAMENT `/admin` (lintas-modul)

Baru HRM (2 Resource + 1 Page) yang dipindah sebagai bukti pola —
**Sales/Procurement/Inventory/Production/Finance/9 layar Recruitment
lain/Setup masih 100% di shell lama.** Pola & 2 gotcha crash yang sudah
terpecahkan (generator `--cluster` crash, tipe union `$navigationGroup`)
didokumentasikan di `docs/admindashboardstandard.md` §6 — dipakai ulang
begitu modul berikutnya digarap. **Jangan mulai modul berikutnya tanpa
arahan eksplisit user soal modul mana & kapan** (aturan yang sudah ada di
`admindashboardstandard.md` §12, dipertegas ulang di sini).

---

## 10. TEKNIS / UI STANDAR — TECHNICAL DEBT

- **Halaman transaksi iframe** (Inquiry, Stock In, Quotation, dst) —
  belum ikut sistem tema `sys_theme` (themestandard.md §6), masih warna
  Tailwind literal.
- **Rename `is_active`→`activate` global** — sudah selesai di semua DB
  KECUALI `sec_user.active` (kolom legacy terpisah, sengaja tidak diubah,
  BUKAN item terbuka — dicatat di sini supaya tidak disangka lupa).
- **WYSIWYG template-editor untuk Nota/print** — ide sempat dilontarkan
  saat print/nota component diekstrak (2026-08-28), sengaja ditunda
  (keputusan setingkat modul baru, belum diminta).
- **QUEUE item #9 lama — VERIFIKASI SELESAI 2026-09-02, hasilnya SETENGAH
  usang, setengah MASIH nyata terbuka**: item ini dulu 2 bagian jadi 1
  ("refactor dropdown avatar jadi data-driven dari `sys_user_menu`" +
  "modal picker tema di atasnya"). Bagian **picker tema SUDAH selesai**
  (`themestandard.md`, dibangun 2026-08-31) — TAPI implementasinya lewat
  grid HARDCODE di dropdown avatar, BUKAN lewat `sys_user_menu`. Dicek
  langsung: tabel `tm2sys.sys_user_menu` **memang ada & berisi 10 baris**,
  tapi `main-menu.blade.php` **tidak pernah membacanya sama sekali** (0
  referensi) — dropdown avatar masih 100% tombol Blade hardcode. Jadi
  bagian "refactor jadi data-driven" dari item #9 **belum pernah
  dikerjakan**, tabelnya nganggur berisi data yang tidak dipakai apa pun.
  File `QUEUE.md` sumber aslinya sudah tidak ada lagi (digantikan
  `BACKLOG.md` ini). **Keputusan menyusul**: apakah refactor ini masih mau
  dikerjakan (buat dropdown data-driven) atau `sys_user_menu` dianggap
  usang & boleh diabaikan/dihapus — belum diputuskan, jangan asumsikan.

---

## 11. KEBIJAKAN BESAR — DIPUTUSKAN TAPI BELUM DIEKSEKUSI

- **Semua menu aplikasi jadi Bahasa Inggris** (keputusan user 2026-08-29,
  bukan cuma grup HRM) — belum dieksekusi. Butuh rencana tersendiri
  (kemungkinan per-modul: pilih 1 modul, translate `sys_app_menu.label`
  lewat migration, verifikasi, `npm run build`, lanjut modul berikutnya).
  **Jangan lakukan mass-rename sebagai efek samping tugas lain** — hanya
  item BARU yang sudah pakai Inggris sejak keputusan ini (mis. "Help &
  Support", "Configuration" yang dibangun hari ini), label lama tetap
  Indonesia sampai giliran modul itu tiba.

---

## 12. TAMBAHAN DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Hasil sisir 107 file Tier/standar yang belum saya baca sendiri. **3 item
yang kedengarannya kritis SUDAH DICEK LANGSUNG ke DB dan TERNYATA SUDAH
BERES** (dokumen sumbernya `openingapp_tier1-4.md`, jelas ditulis sebelum
banyak sesi berikutnya): `sec_group_permission` sekarang 14 baris (bukan 0
— B-OA2 resolved), `sys_login_hero` tabelnya sudah ada (B-OA5 resolved),
akun admin bisa login normal (B-OA12 kemungkinan resolved juga, sudah
diverifikasi hari ini via login `johiwan`/`admin`). **Pelajaran: dokumen
`openingapp_tier*.md` secara umum sudah usang berat** — kalau baca dokumen
itu, JANGAN percaya "blocker"-nya tanpa cek ulang ke DB dulu.

### 12.1 Komponen UI — batas yang SENGAJA (bukan gap nyata)

Ganttboard/Kanban/Nota komponen semuanya punya daftar "yang sengaja tidak
ditangani" (auto-assign resource, validasi konflik, zoom dinamis, drag
lintas-board, reorder manual kartu, dll) — ini keputusan desain sadar,
BUKAN kerjaan tertunda, jangan dikira backlog. Yang beneran masih terbuka:
- `spreadsheetgridstandard.md` §8 — tanpa arrow-key nav, tanpa multi-cell
  select, tanpa indikator dirty per-sel, tanpa undo/redo.
- `griduistandard.md` §10 — beberapa filter dropdown (tipe/kategori/
  divisi/dimensi/sembunyikan-nonaktif) pernah DIHAPUS dari beberapa
  toolbar grid, belum dikembalikan — butuh keputusan user eksplisit kalau
  mau balik.
- `printnotastandard.md` §8 — ide "edit isi cetak lewat GUI" (WYSIWYG,
  sudah dicatat di §10 backlog ini juga) masih sekadar ide.

### 12.2 Inquiry/CO — keputusan bisnis masih menggantung

`inquiryproduct_tier1.md` §6.2 & `tier2.md` §15 — sederet keputusan bisnis
BELUM dikunci (bukan soal teknis, soal kebijakan): granularity bahan
customer-supplied (all-or-nothing vs per-material), basis handling-fee,
kebijakan waste bahan customer, definisi umur/impresi maksimal tooling,
invoice-per-CO vs split, pajak external-cost. Plus 2 dokumen terkait yang
belum pernah dibangun sama sekali: `FORECAST_ENGINE_spec.md` (forecast
demand, butuh ≥1 tahun data) dan `SALES_INITIATIVE_note.md` (modul
"jual kapasitas nganggur" — masih verbal, belum ada sistem).

### 12.3 Procurement/Finance — subsistem yang belum ada sama sekali

- **Purchase Invoice (SUPPLIER_DP/SUPPLIER_BILL)** — belum dibangun,
  butuh tabel delta `fin_invoice_line` yang belum ada.
- **Seluruh subsistem AR/AP Finance** (Invoice Entry+print, e-Faktur,
  Billing Status, Payment Record, AR/AP Aging, Statement of Account,
  Kwitansi, GL Trial Balance dashboard) — belum ada 1 layar pun,
  Journal Entry sendiri sudah live (Cash & Bank + auto-posting).
- **Partner Account auto-fill** (`financeaccounting_delta_partner_category_coa_v2.md`
  §3.3) — mekanisme Observer-nya **secara eksplisit diblokir**, menunggu
  konfirmasi state `fin_partner_account` di `tm2acc` — cek dulu sebelum
  lanjut, mungkin sudah tidak relevan.
- Blanket PO/kontrak jangka panjang, Supplier performance rating — "belum
  dibutuhkan"/"menyusul", tidak ada urgensi tercatat.

### 12.4 Delivery/Dispatch — 8 item eksplisit di luar scope v1

`dispatch_tier1.md` §8: picking list granular (per-lokasi/barcode),
Packing List (koli/tonase/m³), Load Planner, Route Planner, Shipment
Manifest, approval 3-pihak perubahan harga (kolom sudah disiapkan, TANPA
flow aktif), FG Stock Out otomatis saat SHIPPED (butuh keputusan valuasi
AVG/FIFO dulu), Proof of Delivery digital (foto/e-signature — v1 masih
tanda tangan fisik), Delivery Return (retur pasca-SHIPPED), Invoicing/
Billing (AR-lite, Modul 6 terpisah).

### 12.5 HR — Attendance & Payroll, gap teknis konkret

- **DELTA-01**: `emp_shift` butuh dimensi lokasi/gedung baru untuk pola
  shift Satpam (S1-S5) — skema saat ini tidak punya konsep gedung.
- **DELTA-03**: business unit ke-3 (Indostry Packaging) belum punya kode
  dokumen huruf (setelah TLS/TDP) — perlu diputuskan.
- Semua perhitungan Rupiah (lembur, denda, potongan, uang makan/roti,
  prorata gaji, slip gaji) tetap domain **Payroll**, konsisten dengan §7
  di atas (modul Payroll belum dibangun sama sekali).
- Koneksi real-time mesin fingerprint (SDK/ADMS) — ditunda ke v2, v1
  cuma berbasis file-export.
- GPS/selfie mobile clock-in — tidak diminta, tidak dibangun.

### 12.6 Recruitment — 4 item tambahan di luar yang sudah tercatat §8

- Kandidat WNA (bukan WNI) butuh kunci dedup alternatif (paspor, bukan
  KTP) — **belum didesain**.
- Kebijakan retensi/purge/anonimisasi data kandidat lama (kepatuhan PDP)
  — belum ada keputusan sama sekali.
- App Flutter (kanal ke-2 portal, Android+iOS) — belum mulai, effort
  setingkat proyek terpisah.
- Publikasi ke Google Play ($25)/App Store ($99/th) + privacy policy —
  dicatat eksplisit sebagai tugas OPERASIONAL user, bukan sesuatu yang
  bisa dikerjakan Claude Code — jangan lupa saat rilis.

### 12.7 Checklist master lintas-modul (`checklistmockuperp.md`)

File ini sendiri berfungsi sebagai daftar centang ⬜/🟡 raksasa. Layar yang
masih ⬜ (belum dibangun) di luar yang sudah tercatat di section lain file
ini: Prove Warna (versioning v1/v2/v3), Nota Quotation & Nota CO print,
Nota Alokasi/Label Rak Inventory, Period Manager (buka/tutup periode stok
— `PeriodService::assertOpen()` belum dipanggil di mana pun), FIFO/LIFO
valuation (`StockValuationService`, saat ini cuma AVG), Forecast Engine &
Flow Engine (state-machine) mockup produksi lama (kemungkinan besar sudah
digantikan oleh `production_scheduling_tier*.md` yang jauh lebih baru —
cek dulu sebelum kerjakan, potensi duplikat). ~~print PR/PO/PQ~~ **SEMUA
SELESAI 2026-09-02** (lihat `docs/LOG_JOB_DONE.md` — `pur/po/print.blade.php`,
`pur/pr/print.blade.php`, `pur/pq/print.blade.php`). Sisa: Setup > Audit
Trail Viewer & generic Lookup Master maintainer (sebagian sudah tercakup
Configuration hari ini, sebagian belum).

### 12.8 Catatan konsistensi (bukan actionable, cuma disclosure)

Beberapa dokumen lama (`masteremployee_tier1.md`, `TIER_1_ATTENDANCE.md`)
masih bilang Recruitment/Payroll/Attendance "ditunda total", padahal
dokumen lain yang lebih baru (`recruitment_tier1-4.md`, dst) menunjukkan
Recruitment sudah dibuka lagi & dibangun penuh sejak keputusan
"DEC-SCOPE-OPEN" 2026-08-26. Dicatat apa adanya sesuai sumbernya, bukan
diselaraskan paksa — kalau baca dokumen lama soal 3 modul ini, cek dulu
tanggal & dokumen yang lebih baru sebelum percaya frasa "ditunda".

---

## HISTORY PENGERJAAN

Dipindah ke **[`docs/LOG_JOB_DONE.md`](LOG_JOB_DONE.md)** (file terpisah,
2026-09-02) supaya file ini tetap ramping. Item selesai dihapus dari
section angka di atas dan dicatat lengkap di sana, BUKAN di sini.
