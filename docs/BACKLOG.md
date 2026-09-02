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
> selesai, PINDAHKAN (bukan hapus) ke bagian "SELESAI" paling bawah tiap
> section supaya riwayatnya tetap ada.

---

## 1. FASE 2 — SISTEM REPORT 4 LEVEL (permintaan user 2026-09-02)

Kerangka konsepnya sudah ditulis di `docs/reportstandard.md` (skeleton,
BUKAN implementasi) — 4 level (Data Entry Officer/Supervisor/Manager/Top
Management) + graphics report.

**Temuan penting saat menyusun file ini**: sudah ada dokumen perencanaan
report yang JAUH lebih detail dari `reportstandard.md`, ditulis 2026-08-27,
sempat terlewat karena namanya beda ("Report" vs "Report 4 Level") —
**`docs/topmanagementreport_tier1.md`**. Isinya katalog laporan LENGKAP per
domain (Sales & Margin, Procurement & Inventory, Finance, HR) dengan
dimensi/ukuran/sumber data/jenis grafik untuk masing-masing, ditulis dengan
prinsip desain **Summary (pivot+grafik) → Detail (drill-down)**. Ini secara
efektif **katalog konten untuk level "Top Management"** dari 4 level yang
diminta user — jangan bikin ulang dari nol, extend/rujuk dokumen itu.

**6 keputusan terbuka di `topmanagementreport_tier1.md` §5** (WAJIB
dikonfirmasi user sebelum mulai bangun layar sungguhan):
1. Lokasi menu — grup sidebar baru "Laporan Eksekutif" lintas-modul, atau
   tersebar per modul dengan badge?
2. Kontrol akses per level — reuse `sec_group.mgmt_level` yang sudah ada
   (dipakai role-gate PPIC Production Scheduling) atau bikin dimensi baru?
3. Engine pivot — komponen generik dipakai ulang (dimensi/ukuran
   dikonfigurasi) atau layar custom per laporan? Terkait ke item
   "grid custom view/sort/group-by" (§4 di bawah).
4. Library grafik — **belum ada preseden chart di project ini sama
   sekali** (dicek 2026-09-02, tidak ada Chart.js/ApexCharts/dsb.
   terpasang).
5. Win-rate Sales butuh kolom baru di `iqp_inquiry` (status menang/kalah)
   yang belum ada — keputusan skema tersendiri, bukan cuma laporan.
6. Refresh data — real-time query langsung atau snapshot periodik?

**Koreksi atas catatan lama**: `topmanagementreport_tier1.md` §4.1 bilang
"laporan pipeline Customer Order belum bisa dibangun — CO belum ada layar
sama sekali" (per 2026-08-27) — **ini sudah TIDAK BENAR**, `co/entry.blade.php`
+ route `co.entry` sudah live (dipakai/diedit di sesi-sesi setelahnya
termasuk hari ini). Laporan pipeline CO kemungkinan sudah bisa dibangun,
re-scope kalau diminta.

**Belum ada 1 layar report/dashboard grafik pun dibangun** (per audit
2026-09-02) — baik untuk 4-level system maupun katalog top-management di
atas. Ini murni perencanaan, eksekusi menyusul.

---

## 2. FASE 1 (2026-09-02) — SISA YANG SENGAJA DITUNDA

Dari kerja Configuration/branding hari ini (`docs/configurationstandard.md`,
`docs/admindashboardstandard.md` §10-12):

- ~~**~97 atribut `#[Title(...)]`** — SELESAI 2026-09-02, lihat "SELESAI"
  di bawah (solusi: str_replace di layout, bukan edit 97 file).~~
- ~~**PDF footer** — SELESAI 2026-09-02, baca `Brand::name()`.~~
- **Email template** (`resources/views/emails/portal/verify-email.blade.php`)
  — SENGAJA tetap hardcode "TELASIH MAKMUR" (bukan lupa): ini email portal
  karir ke CALON KARYAWAN eksternal, representasi NICKNAME company (sama
  alasan `layouts::portal.blade.php`), bukan APP_NAME aplikasi internal.
- ~~**`fin_exchange_rate`** — SELESAI 2026-09-02, layar CRUD "Master Kurs"
  (`master/exchange-rate.blade.php`) dibangun, pola spreadsheet-editable +
  versioned (1 baris aktif per currency).~~
- ~~**`geo_country.flag_emoji`** — SELESAI 2026-09-02, semua 250 baris
  terisi (dihitung dari `iso_code` via tabel referensi ISO 3166-1 alpha-3→
  alpha-2 + rumus Unicode, bukan tebakan).~~
- ~~**Lapis kosmetik tema dashboard Filament** — DIPERDALAM 2026-09-02:
  ditemukan & diperbaiki BUG ARSITEKTURAL nyata (personalisasi per-user
  ternyata tidak jalan sama sekali krn salah tempat, lihat "SELESAI" +
  `admindashboardstandard.md` §10.2). Masih BELUM ada screenshot visual
  browser asli (cuma verifikasi mekanisme via HTTP kernel).~~
- **`sys_constant_category`** baru punya 5 kategori (Branding/Finance/
  Production/Procurement/Inventory) — kategori lain (mis. HRM, kalau nanti
  ada konstanta khusus HR) belum ditambahkan, tambah manual saat perlu.

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
- **Win-rate Sales** — butuh field baru di `iqp_inquiry` (status
  menang/kalah eksplisit), lihat §1 poin 5 di atas.

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
- **13 kategori `doc_rmprefix1`** belum punya default COA account
  (`default_stock_asset_account_id`/`default_gr_ir_account_id`) — baru 5
  kategori (Kertas/Board, Tinta/Chemical, + GR/IR universal) yang di-seed;
  sisanya (Gasoline, Solasi, Plastik, CTP Plate, Pack Material, ATK, 2×
  Spare Part, Tools, Laptop/IT, Jasa, Peralatan Kebersihan) butuh review
  manual user per kategori, bukan tebakan.

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

- **Auto-jurnal Delivery/Invoice** — PV & Stock Out(RM) sudah auto-posting
  (`JournalPostingService`), tapi Delivery(DO)/Invoice sengaja ditunda
  karena butuh mekanisme snapshot Costing V2 di titik shipment (Keputusan B
  `FIN_TIER_1.md`: COGS diakui saat DO, bukan saat Stock-Out RM). **DO
  sekarang sudah ada layarnya** (`dispatch/do/entry.blade.php`, live sejak
  2026-08-28) — prasyarat "DO belum ada" di catatan lama SUDAH TERPENUHI,
  tapi status mekanisme snapshot Costing V2 itu sendiri **belum
  dikonfirmasi ada atau tidak** — cek dulu sebelum mulai.
- **Outstanding PV report** — user minta diingatkan, sengaja ditunda
  sampai Stock Out dibangun (**Stock Out SUDAH live sejak 2026-08-22** —
  item ini sudah UNBLOCKED secara prasyarat). Masih butuh 1 keputusan
  desain sebelum bisa dibangun: bagaimana cara track serah-terima barang
  dari gudang ke departemen peminta setelah PV di-posting (3 opsi pernah
  diajukan: kolom qty-per-line spt `c_qty_received`, dokumen baru "Bukti
  Serah Terima Barang", atau flag simpel per-PV — user belum memilih).
  Menu placeholder `PROC_MON_PV` (NOT_BUILT) sudah ada slotnya.
- **Payroll (tm2pyr) — SELURUH MODUL belum dibangun** (bukan cuma
  enkripsi). CLAUDE.md §2 sendiri menandai ini requirement yang belum
  diimplementasikan sama sekali. Termasuk di dalamnya: enkripsi data
  gaji at-rest (algoritma/key management/kolom mana yang kena — belum ada
  keputusan desain apa pun, jangan mulai tanpa konfirmasi).
- **13 kategori `doc_rmprefix1` tanpa default COA** — sama item dengan §4
  Procurement (mempengaruhi Finance juga karena ini soal akun jurnal).

---

## 8. HRM / RECRUITMENT (tm2hrm, tm2hrr)

- **Recruitment L8 (Sync Log)** — sengaja dilewati saat modul dibangun
  (2026-08-29), belum ada; L1-L9 lain (minus L8) sudah live.
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
  diinput (WO vs Costing) kemungkinan **sudah terjawab implisit** oleh
  pembangunan WO Detail tab Master Card (2026-08-28, baca dari
  `vw_master_card` + berbagai tabel existing, field kosong tampil
  "— belum diisi" apa adanya) — verifikasi ke Jo apakah ini sudah
  dianggap cukup atau masih ada yang kurang sebelum menutup item ini.

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

- **`class="grid"` + `grid-cols-N` Tailwind mentah tanpa `.two`/`.three`**
  — ditemukan di puluhan file Blade lain (FORM_UI_STANDARD.md §2), belum
  diaudit penuh. Kebanyakan kebetulan aman (`grid-cols-4` cocok dgn
  default), tapi rapuh kalau default `.grid` berubah nanti.
- **Halaman transaksi iframe** (Inquiry, Stock In, Quotation, dst) —
  belum ikut sistem tema `sys_theme` (themestandard.md §6), masih warna
  Tailwind literal.
- **Rename `is_active`→`activate` global** — sudah selesai di semua DB
  KECUALI `sec_user.active` (kolom legacy terpisah, sengaja tidak diubah,
  BUKAN item terbuka — dicatat di sini supaya tidak disangka lupa).
- **WYSIWYG template-editor untuk Nota/print** — ide sempat dilontarkan
  saat print/nota component diekstrak (2026-08-28), sengaja ditunda
  (keputusan setingkat modul baru, belum diminta).
- **QUEUE item #9 lama (theme picker penuh via `sys_user_menu`)** —
  memori 2026-08-20 bilang ini masih pending, TAPI sistem tema (matrix
  4 mode x 3 corak, picker di dropdown avatar) sudah selesai dibangun
  2026-08-31 (`themestandard.md`) — **kemungkinan besar sudah usang/
  terselesaikan**, verifikasi dulu sebelum menganggap ini masih terbuka.

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
- `sidebarmenustandard.md` §1 — grup Sales masih pakai label lama
  "Monitoring & Laporan" (belum diseragamkan ke "Laporan"), sengaja belum
  disentuh, butuh konfirmasi user (label user-visible).

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

- **`sys_holiday`** (tabel hari libur nasional) **tidak ada sama sekali**
  di database manapun — jadi blocker nyata untuk aturan lembur H-7/H-1 di
  modul Attendance.
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
cek dulu sebelum kerjakan, potensi duplikat), print PR/PO/PQ, Setup >
Audit Trail Viewer & generic Lookup Master maintainer (sebagian sudah
tercakup Configuration hari ini, sebagian belum).

### 12.8 Catatan konsistensi (bukan actionable, cuma disclosure)

Beberapa dokumen lama (`masteremployee_tier1.md`, `TIER_1_ATTENDANCE.md`)
masih bilang Recruitment/Payroll/Attendance "ditunda total", padahal
dokumen lain yang lebih baru (`recruitment_tier1-4.md`, dst) menunjukkan
Recruitment sudah dibuka lagi & dibangun penuh sejak keputusan
"DEC-SCOPE-OPEN" 2026-08-26. Dicatat apa adanya sesuai sumbernya, bukan
diselaraskan paksa — kalau baca dokumen lama soal 3 modul ini, cek dulu
tanggal & dokumen yang lebih baru sebelum percaya frasa "ditunda".

---

## SELESAI (dipindahkan dari section di atas, bukan dihapus)

- **§2 poin 1 — ~97 judul tab browser jadi dinamis (2026-09-02).** Solusi:
  bukan edit 97 file `#[Title('X — TELASIH MAKMUR ERP')]` satu-satu (yang
  tidak mungkin krn PHP attribute wajib constant expression) — literal
  "TELASIH MAKMUR"/"TELASIH MAKMUR ERP" di dalam string attribute yang
  SUDAH ADA dijadikan placeholder, di-cari-ganti SEKALI di titik render
  `<title>` (`layouts::shell`/`guest`/`app`, `str_replace([...], [Brand::name(),...], $title)`).
  0 dari 97 file individual disentuh. `main-menu.blade.php` sedikit beda
  pola (`'TELASIH MAKMUR — ERP'`, bukan `'X — TELASIH MAKMUR ERP'`) —
  diseragamkan ke `'Home — TELASIH MAKMUR ERP'` supaya konsisten. Detail:
  `docs/configurationstandard.md` §5.
- **Koreksi nama company (2026-09-02)**: `org_company.company_name` dulu
  "CV Telasih Makmur Group" (salah) → **"CV. Langgeng Jaya Makmur"** (nama
  legal terdaftar sebenarnya). "Telasih Makmur" tetap dipakai sebagai
  NICKNAME company di permukaan customer-facing (portal karir, dst) —
  TIDAK dihapus, cuma diklarifikasi bukan nama legal. APP_NAME ("ERP TM",
  identitas aplikasi internal) tidak terpengaruh, konsep terpisah.
- **§2 — PDF footer dinamis** (2026-09-02): `exports/generic-grid-pdf.blade.php`
  sekarang baca `Brand::name()`.
- **§2 — Master Kurs (`fin_exchange_rate`) dapat layar CRUD** (2026-09-02):
  `resources/views/pages/master/exchange-rate.blade.php`, pola spreadsheet-
  editable sama spt Master Currency, versioned (1 baris `activate=1` per
  currency, baris lama otomatis nonaktif saat baris baru diaktifkan).
  **Bonus temuan**: menu "Master Currency" (`MD_CURRENCY`) ternyata
  **link mati sejak dibuat 2026-08-30** (tidak pernah dipetakan ke route di
  `main-menu.blade.php`) — diperbaiki bersamaan.
- **§2 — `geo_country.flag_emoji` terisi semua 250 baris** (2026-09-02):
  DIHITUNG, bukan ditebak — `iso_code` (ISO 3166-1 alpha-3, sudah ada)
  dipetakan ke alpha-2 lewat tabel referensi ISO baku, lalu emoji dihitung
  via rumus Unicode Regional Indicator Symbol (deterministik, 0 karakter
  emoji diketik manual). 0 baris NULL tersisa.
- **§2 — BUG ARSITEKTURAL Filament ditemukan & diperbaiki (2026-09-02,
  temuan terbesar hari ini)**: saat verifikasi lebih dalam lapis kosmetik
  tema (5 mode x 4 halaman, bukan cuma 1 seperti sebelumnya), ketahuan
  personalisasi aksen/mode dashboard **TIDAK PERNAH benar-benar jalan**
  sejak dibangun kemarin — `AdminPanelProvider::panel()` cuma dieksekusi
  SEKALI saat boot (`Filament::registerPanel()` via `app()->resolving()`),
  JAUH SEBELUM auth resolve utk request itu, jadi `Auth::user()` di dalam
  `panel()` SELALU null. Semua user sebenarnya melihat aksen/mode DEFAULT
  yang sama, walau preferensi tersimpan benar di DB & form Save tidak
  error — nyaris lolos tak ketahuan kalau cuma dites 1 mode. **Diperbaiki**:
  personalisasi dipindah total ke render hook (`filament.partials.theme-matrix.blade.php`,
  HEAD_END) yang TERBUKTI jalan per-request — aksen via `Color::hex()`
  dipanggil ulang di situ (override CSS var `--primary-*` via cascade
  urutan render), dark/light via script inline set `localStorage`+toggle
  class `.dark` langsung. Diverifikasi ulang 20 kombinasi (5 mode x 4
  halaman), 0 gagal. **Pelajaran dicatat** di `admindashboardstandard.md`
  §10.2 utk Resource/Page Filament lain: jangan pernah panggil
  `Auth::user()`/logic per-user di dalam `PanelProvider::panel()`.
