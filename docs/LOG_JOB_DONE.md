# LOG JOB DONE — RIWAYAT ITEM YANG SUDAH DIKERJAKAN
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dipisah dari `docs/BACKLOG.md` 2026-09-02 (permintaan user: "pindahkan ke
> file LOG Job Done md file, untuk yang sudah sehingga file backlog md
> mengecil") — supaya `BACKLOG.md` tetap ramping, cuma isi item yang MASIH
> terbuka. File ini murni arsip/riwayat, urutan dari **terbaru di atas**.
>
> **Cara pakai**: setiap kali 1 item di `BACKLOG.md` selesai dikerjakan,
> HAPUS dari `BACKLOG.md` dan catat lengkap di sini (bukan disalin ke
> dua-duanya).

---

## 2026-09-05 — Dokumentasi massal per-layar, flow modul, database, katalog UI/UX

Permintaan user (BACKLOG.md §13 lama, dieksekusi penuh hari ini setelah
"kerjakan tanpa saya... Do it now" + "Auto Run even run out of tokens"):
4 kategori dokumen baru, ditulis murni dari BACA KODE SUNGGUHAN (bukan
didikte spek duluan) — tujuan eksplisit user: "agar saya bisa benahi isi
md file jika ternyata menyimpang".

**Hasil**: 157 file baru + 1 index master.
- **127 file `docs/screens/screen_*.md`** — 1 per layar Livewire SFC
  (`resources/views/pages/**`, 119 file) + Resource/Page Filament
  (`app/Filament/**`, 8 file). Isi: Field & Kolom, Aksi/Method, Tabel &
  Model, Alur/Business Logic, Catatan gap.
- **12 file `docs/flows/flow_*.md`** — 1 per modul (System&Setup, Careers
  Portal Publik, Recruitment Internal, Inquiry Product, Sales Order &
  Delivery, Finance&Accounting, HRM, Inventory, Master Data, Production
  Scheduling, Procurement, Admin Dashboard Filament) — tabel "tahap->
  layar->aktor" tiap modul.
- **12 file `docs/database/db_*.md`** — 1 per koneksi tm2* (mst/sdi/sys/
  pur/inv/prd/acc/hrr/hrm/pyr/old/crp), daftar tabel+kolom dari DDL dump
  (grep+sed CREATE TABLE, skip INSERT data) atau migration kalau dump tak
  tersedia/stale.
- **6 file `docs/uistandard/uistandard_component_*.md`** — katalog ~45
  komponen Blade reusable (`resources/views/components/**`) dgn props+
  contoh pakai nyata, melengkapi (bukan duplikat) 7 dokumen standar
  konsep yang sudah ada.
- **`docs/index_master.md`** — index semua 157 file di atas + pointer ke
  85 dokumen Tier/standar lama.

**Konvensi**: prefix `screen_`/`flow_`/`db_`/`uistandard_`, underscore
konsisten (bukan hyphen — dinormalisasi manual di akhir, 28 file sempat
salah pakai hyphen dari sub-agent).

**Metodologi**: 14 sub-agent paralel (Agent tool, background) dibagi per
modul/kategori — 3 gelombang kena rate-limit sesi Claude (bukan bug kode)
dan di-resume dgn instruksi presisi file mana yang sudah/belum selesai;
sisa gap kecil (13 file: 1 auth, 1 WO detail besar, 3 PR procurement, 5
flow, 5 database hrm/hrr/pyr/old/crp) ditutup manual langsung oleh sesi
utama, bukan sub-agent, setelah retry ke-2 sub-agent juga kena rate limit.

**3 temuan penting selama proses** (dicatat di file terkait, BUKAN
diperbaiki di sesi ini — murni tugas dokumentasi):
1. `co/list.blade.php::confirmOrder()` kemungkinan bypass auto-generate
   CPDEL yang ada di `co/entry.blade.php::confirmCo()` — potensi
   inkonsistensi, lihat `screen_co_list.md`.
2. Dump DDL `docs/ddl/tm2hrr.sql` **STALE** (0 tabel) padahal modul
   Recruitment live dgn 18+ tabel nyata (sumber kebenaran: migration
   `2026_08_29_000003_create_recruitment_tm2hrr_schema.php`) — disarankan
   refresh dump.
3. Database `tm2hrm` fisik ada (koneksi `hrm` terdefinisi) tapi **0 model**
   Eloquent memakainya — semua fitur HRM (org_*/emp_*) berjalan di tm2mst.

Lihat `docs/index_master.md` untuk peta lengkap.

---

## 2026-09-03 (lanjutan — panel referensi ProDev + guard integritas data)

User menunjuk gap nyata: link Mode #1 (Inquiry->ProDev) yang dibangun sesi
sebelumnya cuma bikin KONEKSI (`iqp_part.prod_part_id`), tidak pernah
TAMPILKAN datanya ke officer ProDev. Plus 2 pertanyaan tajam soal integritas
data yang berujung ke 2 guard baru:

- **Panel "📥 Referensi Inquiry Asal"** (read-only) di layar ProDev —
  reverse-lookup `iqp_part` yang `prod_part_id`-nya nunjuk ke part aktif,
  tampilkan Inquiry No/Customer/Tipe Produk/semua dim param (label dari
  `prod_type_param`)/Flat W-H/Qty per Set. TIDAK PERNAH bisa diedit dari
  sini (prinsip snapshot/beku) — kalau ProDev butuh angka beda, isi field
  miliknya sendiri (`prod_part`, kolom terpisah total).
- **Filter status diperbaiki**: hanya Inquiry CONFIRMED & masih hidup
  (`whereNotIn sub_state ['DRAFT','CANCELLED','LOST']`, pola PERSIS sama
  dengan `costing-list.blade.php`/`imposition-list.blade.php`, bukan
  reinvent). Bug ditemukan: `stat_confirm` tetap `true` selamanya walau
  Inquiry belakangan di-CANCEL/LOST (yang berubah cuma `sub_state`) — cek
  `stat_confirm` saja tidak cukup.
- **Tombol "Kirim ke ProDev"** sekarang wajib `stat_confirm=true` (dulu
  cukup part tersimpan) — guard di UI dan server-side.
- **Guard baru `unlockLayout()`**: tolak buka kunci layout yang SUDAH
  ditarik Costing (`IqpCostingPart.prod_layout_group_id`) — sebelumnya
  tanpa syarat sama sekali, risiko HPP yang sudah dihitung (bahkan sudah
  masuk Quotation ke customer) diam-diam basi tanpa peringatan siapa pun.
- **Audit menyeluruh batas antar-tahap** (diminta user setelah menegur
  "kenapa saya yang beritahu, harusnya kamu sarankan"): dikonfirmasi SEMUA
  4 batas pipeline (Inquiry->ProDev, ProDev->ProCost, ProCost->Quotation,
  Quotation->CO) sekarang konsisten gate confirmed/published — 3 dari 4
  sudah benar sejak awal (bukan buatan sesi ini), cuma Inquiry->ProDev yang
  genuinely bolong sampai diperbaiki hari ini.
- **Insiden data test bocor ke DB nyata**: sisa script tinker testing
  (7 baris lintas 5 tabel) sempat ketinggalan di database dev SUNGGUHAN,
  ditemukan user LANGSUNG di layar ProDev (chip pencarian). Sudah di-scan
  penuh & dibersihkan, diverifikasi ulang bersih. Root cause: 1 script
  crash di tengah (helper assertion Livewire dipanggil dari tinker mentah,
  bukan test PHPUnit sungguhan) sebelum sempat jalankan kode cleanup-nya
  sendiri.

Test baru/diperbarui: `InquirySendToProdevTest` (+2 skenario: guard
confirm, panel referensi confirmed vs cancelled), `ProdevUnlockLayoutGuardTest`
baru. Full suite: 103 test, 94 passed, 9 skipped (Firebase, tidak terkait), 0 gagal.

---

## 2026-09-03 (lanjutan — Mode #1 InqProd/ProDev, picker visual, formula hide)

- **Popup picker visual Tipe Produk (InqProd, ala Pacdora).** Tombol 🖼️ baru
  di sebelah dropdown "Tipe Produk" (`inquiry/form.blade.php`) buka modal
  grid kartu, tiap kartu render thumbnail dieline ASLI (reuse
  `TMDieline.svg()` yang sudah ada, bukan aset gambar baru) dgn dims default
  dari preset ukuran pertama/param default. Pilih kartu = trigger event
  'change' di `<select>` yang sama (`pickType()` di Alpine,
  `dieline-part.js`) — 100% reuse `selectPartType()` Livewire existing,
  dropdown teks lama TETAP ada tak disentuh, semua field dimensi tetap
  full-editable sesudahnya. `[x-cloak]{display:none!important}` ditambah ke
  app.css (dipakai modal ini). Smoke-tested via `Livewire::test()`.
- **Link Mode #1 Inquiry(BARU) → ProDev, gap kritis dari CLAUDE.md §4
  VITAL.** Tombol "🏭 Kirim ke ProDev" per part (muncul setelah part
  tersimpan). `sendToProDev()`: auto-buat `prod_product` DRAFT kalau
  `iqp_inquiry.parent_product_id` masih kosong, prod_part baru di bawahnya
  seed `cm_length`/`cm_width`/`cm_height` dari
  `iqp_part.dieline_spec['dims']` (mm→cm) kalau ada — field teknis ProDev
  lain (die_w_cm, flap, dst) sengaja dibiarkan kosong, itu memang tugas
  ProDev. Idempoten (panggil ulang tak bikin dobel). Kolom baru
  `iqp_part.prod_part_id` (referensi by-ID lintas-DB, bukan FK fisik).
  Route `production.prodev` diubah terima `{part?}` di URI (dulu query
  string yg tak nyampai ke Livewire mount()). Test baru
  `InquirySendToProdevTest` (termasuk cek idempoten).
- **Sembunyikan formula/breakdown HPP ProCost, cuma login johiwan.**
  `costing.blade.php` dapat `canSeeFormula()` (`Auth::user()->login ===
  'johiwan'`, literal per instruksi user, bukan is_admin/grup). Digate:
  breakdown Bahan/Proses/Coating/Tooling per part + kolom HPP/pc di Ladder
  Harga per Tier (tampil 🔒 utk user lain). SENGAJA TIDAK digate: input
  margin%/toleransi%/rounding + harga jual final (Harga Jual/pc, Incl PPN)
  — kalau ikut disembunyikan, staf Costing lain tak bisa kerja sama sekali.
  Lebih ketat dari aturan lama CLAUDE.md §4 "Costing rahasia Sisi A"
  (grup Costing+Direksi umum, di sini 1 login spesifik).

---

## 2026-09-03 (ProDev §0 follow-up — instruksi user "kenapa tidak diselesaikan, coba selesaikan")

- **R30 — Tool #2 (Pcs→Plano/Best Sheet Finder).** `ImpositionEngine::bestSheet()`
  baru — loop SEMUA `mat_plano_size` aktif (bukan cuma yang dipilih officer
  di dropdown), tiap plano dicoba `sweepFlap()` sendiri (top-1), ranking
  lintas-plano by biaya: `targetQty` diisi → Gross Sheets × luas plano
  (formula Modul B `docs/imposition_nesting/prepress_imposition_packaging_guide.md`,
  akurat memperhitungkan sisa di lembar terakhir); kosong → fallback luas/pc
  (proxy krn `mat_plano_size` sendiri tak simpan harga, asumsi material sama
  di semua opsi). UI: tombol "▶ Cari Plano Termurah" di layar ProDev, reuse
  penuh render candidate card yang sudah ada (shape hasil identik
  `sweepFlap()` + extra key), `pickCandidate()` disesuaikan pakai
  `plano_size_id` milik kandidat (bisa beda dari dropdown) alih-alih selalu
  `selectedPlanoId`.
- **R30 — Tool #4 (Die-Rule Estimator).** `ImpositionEngine::dieRuleMeterLari()`
  baru — cutting rule pakai prinsip **Common Knife** (Esko ArtiosCAD: garis
  potong internal grid dipakai BERSAMA 2 sel bertetangga, formula
  `(cols+1)×gridH + (rows+1)×gridW`, bukan jumlah perimeter tiap unit yang
  dobel-hitung garis bersama) + crease rule per-unit (garis lipat
  badan↔lidah). Perforasi sengaja 0 (tidak ada data source geometri
  perforasi di skema manapun, tidak ditebak). Method MIX (grid utama+rotasi)
  dihitung 2 grid terpisah dijumlah, TANPA optimasi Common Knife di batas
  antar-blok (overestimate kecil, didokumentasikan sbg batasan sadar).
  Auto-dipanggil tiap `pickCandidate()` (bukan langkah manual terpisah),
  hasil tersimpan `prod_layout_group.die_rule_meter` (kolom baru, migration
  `2026_09_03_000008_add_prodev_tools_columns`).
- **R30 — Tool #10 (Intra-Product Part Combiner), MVP 2-part.**
  `ImpositionEngine::combinePair()` baru — BUKAN general heterogeneous
  bin-packing (NP-hard, di luar scope), tapi **guillotine vertical-split**
  (perluasan LANGSUNG dari metode CAMPUR yang sudah ada di `run()`): area
  usable dibagi 2 kolom via sweep X, tiap X dihitung up count masing² part
  (coba 2 orientasi). Keputusan pakai `qty_per_sku` (ProdPart, field lama yg
  ternyata belum pernah dipakai) sbg bobot rasio kebutuhan riil antar-part
  dlm 1 set produk — metrik "sets per sheet" = MIN(up_A/qtyA, up_B/qtyB),
  dibandingkan `area_per_set` gabung vs pisah (baseline: tiap part dapat
  plano sendiri). **Bug ditemukan & diperbaiki saat testing**: sweep awalnya
  mulai dari `bodyWA` mentah (asumsi non-rotasi), part yang cuma muat kalau
  diputar 90° (mis. lebih lebar dari areaW tapi lebih pendek dari areaH)
  ter-skip dari eksplorasi — diperbaiki pakai `min(bodyW,bodyH)` sbg starting
  point sweep (regression test ditambahkan). UI: card baru "🧩 Tool #10 —
  Gabung Part dalam Produk Sama" (pilih part pasangan dari produk yang sama,
  bandingkan GABUNG vs PISAH, tombol "Terapkan" bikin `prod_layout_group`
  BARU menaungi 2 part — tidak menimpa group solo existing masing² part,
  riwayat solo tetap ada). Layout gabungan tetap lewat alur konfirmasi yang
  sama (`confirmLayout()`, generik utk semua method) sebelum tampil di
  picker Costing.
- **R30 — Alokasi biaya proporsional multi-part (§4.2b).**
  `ProdLayoutGroup::effectiveCutFor(ProdPart $part)` baru — derivasi
  matematis: "cut efektif" = totalLuasTerpakaiGroup ÷ luasPartSendiri (proxy
  independen dari up_count, terbukti aljabar) supaya formula CostingEngine
  **existing** (`paperCostPc = ... / cut / isi`) TIDAK PERLU diubah sama
  sekali, otomatis menghasilkan alokasi proporsional by luas yang benar.
  Group solo (1 part) fallback ke `up_count` apa adanya (perilaku lama
  identik, tak ada regresi). `costing.blade.php::pickLayoutGroup()` diperluas
  terima `$prodPartId` opsional — WAJIB diisi kalau group Tool #10 (>1 part),
  guard menolak (session flash error) kalau belum dipilih; UI dropdown baru
  "Part dalam Layout Gabungan" muncul otomatis saat group multi-part.
  `i_pisau_faktor` juga auto-terisi dari `die_rule_meter` (Tool #4) dgn
  alokasi proporsional serupa. **Bug ditemukan & diperbaiki saat testing**:
  guard "wajib pilih part" awalnya TIDAK PERNAH ke-trigger krn fallback
  `$group->parts->first()` dipakai bahkan saat `$prodPartId` null — diperbaiki
  supaya fallback cuma jalan utk group solo. Kolom baru
  `iqp_costing_part.prodev_part_id` (migration
  `2026_09_03_000009_add_prodev_part_id_to_iqp_costing_part`, referensi
  by-ID lintas-DB tm2sdi→tm2mst, BUKAN FK fisik, murni utk picker UI sticky).
  Diverifikasi end-to-end via `Livewire::test()` + tinker sintetis (guard
  ditolak tanpa prodPartId, alokasi dgn prodPartId=A dan =B, angka cocok
  derivasi manual). Full test suite 93/100 lolos (7 gagal `CareersPortalTest`
  krn Firebase Emulator tidak menyala, tidak terkait perubahan ini).

---

## 2026-09-03 (lanjutan — instruksi user langsung, item tambahan)

- **R29 — Default periode Accounting/Stock Opname/Payroll di `sys_constant`.**
  Permintaan user: tahun buku accounting 1 Jan-31 Des
  (`FISCAL_YEAR_START/END_MONTH/DAY`, kategori FINANCE), stock opname tiap
  tanggal 1 (`STOCK_OPNAME_DAY_OF_MONTH`, kategori INVENTORY), gajian
  bulanan tiap akhir bulan (`PAYROLL_MONTHLY_CUTOFF_DAY='END_OF_MONTH'`,
  disimpan sbg teks krn 28-31 dinamis per bulan, bukan angka tetap), gajian
  mingguan tiap Jumat (`PAYROLL_WEEKLY_CUTOFF_DOW=5`, kategori PAYROLL).
  Migration `2026_09_03_000007_seed_default_period_constants`. **Ini murni
  konfigurasi NILAI**, BUKAN membangun `PeriodService`/engine buka-tutup
  periode (item itu terpisah, masih 0%, lihat BACKLOG.md §12.7). Diverifikasi
  via `SysConstant::text()`/`number()` di tinker.
- **Worktree cleanup.** 2 folder `.claude/worktrees/festive-feynman-7f63e0`
  dan `sleepy-solomon-81458e` (sisa sesi agent lain, ditemukan sesi
  sebelumnya) dicek: tidak ada perubahan uncommitted, commit HEAD
  masing-masing sudah ada di `origin/main` dan
  `origin/claude/exciting-hypatia-1d0d5a` — aman dihapus via
  `git worktree remove` (bukan `rm -rf` manual, supaya registry git ikut
  bersih), termasuk sisa `node_modules` yang nyangkut di salah satunya.

---

## 2026-09-03 (lanjutan diskusi backlog — instruksi user langsung per item)

- **R27 — Dropdown avatar jadi data-driven dari `sys_user_menu`.** BACKLOG
  §10 "QUEUE item #9" akhirnya tuntas. `sys_user_menu` sudah ada 10 baris
  sejak lama tapi isinya usang (pra-QC-001/002/003, pra-grup Configuration
  2026-09-03) dan `main-menu.blade.php` tidak pernah membacanya (0
  referensi, dropdown 100% hardcode Blade). Migration
  `2026_09_03_000004_make_avatar_dropdown_data_driven` truncate+reseed 8
  item flat akurat (Chat/Notif/AI drawer, My Security, Edit profil/Ubah
  password, Pengaturan Setup, Bantuan & Support) + kolom `visibility` baru
  (`all`/`employee_linked`/`non_employee_linked`/`setup_non_admin`) karena
  kondisi tampil aslinya (employee_id terisi/tidak, canSetup tanpa
  is_admin) tak tertangkap `admin_only` saja. Model baru
  `App\Models\Sys\SysUserMenu`. `main-menu.blade.php` dapat computed
  property `userMenuItems()` (filter visibility + group by section) dan
  loop `@foreach`/`@switch` generik menggantikan blok hardcode lama; 2 item
  bespoke (My Security route params, Bantuan & Support window.open+ctx
  dinamis) tetap special-case by `code`. **Keputusan scope sadar**: widget
  Configuration (Theme matrix 4x3/Bahasa/User Configuration nested) TETAP
  hardcode — terlalu bespoke (loop custom `$this->themeMatrix`, bukan
  label+aksi flat) utk direpresentasikan sbg baris generik, jadi bukan
  "setengah dikerjakan", ini keputusan yang disengaja. Diverifikasi via
  `Livewire::test()`: akun `johiwan` (employee_id=1) → My Security tampil,
  Edit profil/Ubah password tersembunyi; akun `rina` (employee_id null) →
  sebaliknya. `MainMenuShellSmokeTest` (3 test) tetap lolos. Tidak perlu
  `npm run build` (semua class yang dipakai sudah ada di app.css sebelumnya,
  tak ada class Tailwind baru).
- **R28 — Shell responsive di layar sempit (qc_feedback.md, 2026-08-30).**
  Instruksi user "jalankan jika tidak mengubah layout yang sekarang". Root
  cause: `.shell` flex row dgn `aside` 262px FIXED + tanpa `@media`
  breakpoint sama sekali → di layar <768px sidebar mendorong topbar
  (termasuk avatar dropdown) keluar viewport, secara visual/interaktif tak
  terjangkau. Fix di-scope MURNI di dalam `@media (max-width: 767px)` baru
  (`resources/css/app.css`, ditaruh setelah blok `.shell` selesai): `aside`
  jadi `position: fixed` (overlay di atas konten, z-index 60, box-shadow)
  alih-alih ikut flex row, `.shell .main` dapat `width: 100%`. TIDAK ada
  class/state Alpine baru — reuse toggle ☰ (`nosidebar`) yang SUDAH ADA
  sebelumnya (`tb-btn @click="nosidebar = !nosidebar"`, CSS
  `.shell.nosidebar aside{width:0}` sudah lama ada), cuma nambah `init()`
  di `app-shell.js` supaya nilai awal `nosidebar` TERTUTUP saat page load
  pertama di layar sempit (`window.matchMedia('(max-width: 767px)')`).
  Desktop (>=768px) tidak tersentuh sama sekali — semua rule baru murni di
  dalam media query yang tadinya tidak ada. `npm run build` dijalankan &
  diverifikasi masuk ke CSS/JS terkompilasi (grep `767px`/`matchMedia`).
  **Batasan**: verifikasi visual browser (login) TIDAK dilakukan Claude
  Code (kredensial tidak boleh diotomatisasi) — user disarankan cek
  langsung di HP/window sempit sebelum dianggap final. Tidak ada backdrop
  dismiss-on-outside-click (tap ☰ lagi utk tutup) — minimal viable, bisa
  disempurnakan kalau ada QC lanjutan.

---

## 2026-09-03 (kerja langsung sesuai instruksi user, di luar sesi autonomous)

- **§6 Production — `RateBandResolver` prioritas qty_good F15 di atas qty
  target.** Sebelumnya rate historis dihitung dari `prd_wo_operation.qty`
  (rencana/target operasi) — SALAH kalau hasil aktual beda dari rencana
  (reject/waste). Sekarang: `historicalRate()` eager-load relasi
  `outputs()` (`prd_wo_operation_output`, F15 Production Output Recording),
  pakai `SUM(qty_good)` aktual per baris kalau ADA, fallback ke qty target
  HANYA kalau genuinely 0 baris F15 tercatat (data legacy/gap pencatatan,
  bukan kasus normal). Diverifikasi 2 skenario via tinker sintetis (insert
  5 baris `DONE` dlm transaction, di-rollback): (a) ada F15 qty_good=100 x
  5, qty target=1000 x5, durasi 2 jam/baris → rate = 50/jam (qty_good yang
  dipakai, BUKAN 500/jam dari qty target) ✅; (b) tanpa F15 sama sekali →
  fallback qty target = 500/jam ✅. Plus 6 test `ProductionWoGenerationTest`
  tetap lolos. `git commit`-nya sempat tertinggal uncommitted di working
  tree sesi lain (`tmerp-b5`) — diverifikasi ulang & di-commit sesi ini
  atas permintaan user "commit all... update backlog dulu".
- **Sidebar Sales: "Imposition Engine"→"Pro-Dev", "Product Costing"→
  "Pro-Cost".** Permintaan user (screenshot sidebar) — menu setelah
  Inquiry Product harus Pro-Dev, bukan Imposition Engine lama. `SALES_IMPO`
  dialihkan dari `inquiry.imposition.list` ke layar ProDev workbench baru
  (`production.prodev`), sesuai `productdevelopment_tier1.md` §6 poin 2
  ("Imposition pindah dari Inquiry ke Product Development"). **Gap nyata
  ditemukan & diperbaiki sekalian**: `SALES_STAFF`/`SALES_SPV` TIDAK punya
  akses modul PROD sama sekali — kalau cuma ganti label tanpa ini, klik
  "Pro-Dev" akan 403. Menu duplikat `PROD_CARD`/`PROD_LAYOUT` (grup
  Production) disembunyikan (nunjuk layar sama).
- **Dropdown profil direstrukturisasi + sidebar "Configuration"→"Settings".**
  Permintaan user "agar menghemat menu": grup baru collapsible
  "Configuration" di dropdown avatar menggabung Tema (matrix 4x3, makan
  byk ruang vertikal) + Bahasa + Portal Karyawan (dilabeli ulang **"User
  Configuration"**, termasuk judul modal+iframe title) + Aktivitas saya —
  default tertutup, klik utk buka. My Security & Bantuan/Support TETAP di
  luar grup. Sidebar kiri: "Security" tidak berubah, "Configuration"→
  **"Settings"** (`sys_app_menu.SET_CONFIG.label`, route/kode tidak
  berubah) — nama "Configuration" dibebaskan drpd dobel makna dgn grup
  dropdown baru.
- **7 akun contoh UAT lintas 4 level + panel referensi di layar login.**
  Permintaan user: `operator`(L1, grup SALES_STAFF)/`supervs`(L2, grup
  SALES_SPV)/`mgr-sal`,`mgr-pur`,`mgr-inv`,`mgr-acc`(L3, 1 per bidang, 4
  grup BARU `MGR_SALES`/`MGR_PUR`/`MGR_INV`/`MGR_ACC` krn belum ada
  sebelumnya)/`direksi`(grup DIREKSI existing) — semua password
  `contoh!23`. Panel collapsible "🧪 Akun Uji Coba (UAT)" ditambahkan ke
  `login.blade.php` (ada catatan wajib dicopot sebelum rilis produksi).
  Diverifikasi: 7x password-check OK, end-to-end HTTP kernel `operator` →
  `/production/prodev` = 200 (sebelumnya 403 sebelum fix modul PROD di
  atas).
- **Backup dokumentasi**: reorganisasi `docs/` (DDL→`docs/ddl/`, mockup
  dipluralkan `docs/mockups/`, file inquiry/costing dikumpulkan ke
  `docs/inquiry_product_costing/`) + referensi baru (5 Master Card riil
  `docs/attachments/MC-*.xlsx`, Blueprint arsitektur, materi persiapan
  modul Payroll/Time Attendance `docs/payroll_timeattendance/` — Tier1-4 +
  11 mockup HTML + DDL, **belum dipakai/diverifikasi**, CLAUDE.md tetap
  melarang mulai implementasi Payroll tanpa keputusan desain eksplisit).

## 2026-09-03 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #14)

- **§12.7 — Cetak Label Rak (Warehouse) dibangun.** Item `checklistmockuperp.md`
  "[P] Nota Alokasi / Label Rak — sengaja ditunda, diprioritaskan Form/Grid
  dulu" (Form+Grid Warehouse sudah lama live) — Label Rak dikerjakan (Nota
  Alokasi TERPISAH, maknanya sendiri sudah tidak jelas pasca-Inventory
  ledger jadi, dicatat perlu klarifikasi ulang bukan dikerjakan asal tebak).
  Layar baru `master/warehouse/print-labels.blade.php`: grid kartu label
  kecil siap gunting per `wh_loc` aktif (kode lokasi besar, row-col-stack,
  nama lokasi), 4 kolom, print CSS `@media print` dgn `@page A4`. SENGAJA
  TIDAK pakai komponen `resources/views/components/print/*` (dirancang utk
  1 dokumen formal header+parties+items+signatures, bentuk beda total dari
  lembar label). Tombol "🏷 Cetak Label Rak" baru di toolbar
  `master/warehouse/entry.blade.php` (hanya muncul saat edit gudang
  existing). Diverifikasi `Livewire::test()` (gudang riil dgn 3 lokasi ->
  200) + HTTP kernel 200. `npm run build` dijalankan.
  **Koreksi sekalian**: `PeriodService` yang disebut BACKLOG lama TERNYATA
  tak pernah dibuat sama sekali (0 file), bukan cuma "belum dipanggil" --
  catatan lama diperbaiki.

## 2026-09-03 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #13)

- **§6 Production — Reroute-Subkontrak "Sisip Hot Job" kini bisa benar-benar
  di-apply (bukan cuma tampil opsi).** Sebelumnya `applyHotJobOption()`
  langsung toast "belum aktif" kalau opsi ke-3 (subkontrak) dipilih. Fix:
  1. `SubcontractPoService` direfactor -- logic pembuatan PR+PO diekstrak
     jadi `createPo()` privat generik (TIDAK terikat `PrdBreakdownEvent`),
     dipakai `createFromBreakdown()` (existing) DAN `createFromReroute()`
     (baru). Beda dari Breakdown: opsi "Reroute subkontrak" (InterruptionOptionGenerator
     opsi ke-3, "tidak ada job lain tergeser") mengalihkan HOT TASK itu
     sendiri ke subkontraktor, BUKAN menggeser task lain di antrean --
     antrean mesin existing sama sekali tak disentuh.
  2. **Keputusan arsitektural otonom** (dicatat jelas, dinilai low-risk/
     reversibel): `PrdWoOperation::TRANSITIONS` ditambah `PENDING`/`ASSIGNED`
     → `SUBCONTRACT` (sebelumnya CUMA reachable dari `BREAKDOWN`, state
     machine dari Tier2 §2) -- reroute hot job task belum pernah jalan sama
     sekali, tak ada insiden fisik yang perlu di-link, beda skenario dari
     breakdown mesin rusak.
  3. UI `scheduling-board.blade.php`: pilih opsi subkontrak + klik "Terapkan"
     -> sub-form vendor/estimasi biaya/tanggal kirim-kembali muncul inline
     (pola sama persis form Breakdown->Subkontrak di `wo-entry.blade.php`,
     termasuk gate `isOpsManagerOrAbove()`) -> "Terbitkan PO Subkontrak"
     panggil `createFromReroute()`.
  Diverifikasi `Livewire::test()` end-to-end (transaction rollback,
  0 data tertinggal): generate 3 opsi (opsi ke-3 `requires_subcontract_po`
  benar), pilih+Terapkan -> sub-form muncul (bukan langsung apply), isi
  vendor+estimasi+tanggal -> Terbitkan -> PO SUBCONTRACT terbit + operation
  transisi ke status SUBCONTRACT, sesuai ekspektasi penuh. `npm run build`
  dijalankan.

## 2026-09-03 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #12)

- **§6 Production — `SchedulingRuleEngine::applySequencing()` tak lagi
  1-rule-only.** Sebelumnya `$rules->first()` -- rule SEQUENCING ke-2/ke-3
  dst utk resource yang sama diam-diam diabaikan. Fix: SEMUA rule aktif
  diterapkan, urutan `rule_id` ASC (rule dibuat lebih dulu = kriteria
  UTAMA, berikutnya = tie-breaker) via chained stable sort (dijalankan
  MUNDUR -- tie-breaker duluan, utama terakhir -- supaya multi-key sort
  benar; usort/uasort PHP 8+ terjamin stabil). `prd_scheduling_rule` tak
  punya kolom priority eksplisit, urutan pembuatan dipakai sbg proxy
  (1 rule = perilaku identik sebelumnya, backward-compatible). Diverifikasi
  via tinker (2 rule sintetis dlm transaction rollback + 4 operation
  in-memory qty berduplikat): hasil urutan 3,2,4,1 -- persis sesuai
  ekspektasi qty ASC utama + seq_no DESC tie-break.

## 2026-09-03 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #11)

- **§6 Production — Master Card tab WO Detail kini tampilkan artwork
  nyata (bukan placeholder statis).** `wo-entry.blade.php` sebelumnya
  SELALU render "Artwork belum tersimpan" tanpa 1 pun query ke DB (murni
  dekoratif). Fix: 3 computed baru -- `prodPartForArtwork()` (jembatan
  iqp_part->prod_part via `product_id`+`part_code` teks, pola SAMA yg
  sudah dipakai file ini utk lookup rate mesin/karyawan, BUKAN bikin pola
  baru), `officialArtwork()` (query nyata ke `prod_part_artwork`, tabelnya
  sendiri masih 0 baris tapi sekarang genuinely reachable), `prodevArtwork()`
  (fallback ke `prod_part_attachment` JPEG dari ProDev Fase 1 kalau artwork
  resmi belum ada, dgn badge peringatan "belum resmi"). Diverifikasi
  `Livewire::test()`: kasus tanpa data (null aman, tak crash) + kasus
  dgn attachment ProDev sintetis dlm transaction rollback (fallback
  terdeteksi benar). `npm run build` dijalankan.

## 2026-09-03 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #10)

- **§6 Production — `MachineCalendarService` kini sadar hari libur & shift
  per-mesin.** 2 perbaikan:
  1. `isWorkingDay()` cek `sys_holiday` (dibangun 2026-09-02, sebelumnya
     "belum ada 1 modul pun yang membacanya") -- hari libur nasional
     otomatis dilewati saat backward-schedule, bukan cuma Minggu.
  2. `subtractWorkingTime()` terima parameter `machineId` opsional
     (`BackwardSchedulingService` kirim `$task->machine_id`, null utk task
     MANUAL). Kalau diisi: jam kerja hari itu diambil dari
     `emp_shift_roster` (siapa dijadwalkan di mesin ini tanggal itu) +
     `emp_shift_detail` (jam mulai/selesai shift per hari-dlm-minggu) —
     union rentang terluas kalau >1 shift di-roster hari yg sama
     (disederhanakan sengaja, tak hitung gap antar-shift presisi). Mesin
     yang TAK PERNAH pakai fitur roster (0 baris histori) fallback ke jam
     default global (backward-compatible, tidak break scheduling existing).
     Mesin yang PUNYA histori roster tapi kosong di 1 tanggal spesifik =
     dianggap hari libur mesin itu (staffing-driven, bukan asumsi).
  Diverifikasi 4 skenario via tinker (2 dgn transaction rollback): (a) jalur
  lama tanpa machineId tetap identik (08:00-20:00, backward-compat), (b)
  hari libur sintetis terlewati benar, (c) window sempit 09:00-11:00 dari
  roster terpakai persis, (d) hari tanpa roster utk mesin yg PUNYA histori
  roster ditolak (didorong ke hari kerja sebelumnya) — semua sesuai ekspektasi.

## 2026-09-03 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #9)

- **§4 Procurement — `StockConfirmService` TERNYATA sudah selesai**, koreksi
  catatan lama. Item BACKLOG bilang "belum di-port jadi PHP Service" dan
  minta dicek dulu — dicek: `App\Services\Inventory\StockPostingService.php`
  docblock-nya sendiri eksplisit "Port dari trigger legacy
  trg_rm_stock_confirm/trg_fg_stock_confirm/trg_sfg_stock_confirm", RM/FG/SFG
  lengkap + auto-jurnal Stock Out. Catatan lama ditulis SEBELUM Stock In/Out
  dibangun (2026-08-22), sudah usang total. Murni koreksi dokumentasi, tidak
  ada kode ditulis.

## 2026-09-03 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #8)

- **§12.7 — Cetak Nota Customer Order (CO) dibangun.** Quotation print sudah
  ada duluan, CO belum — pola identik (komponen
  `resources/views/components/print/*`). `co/print.blade.php`: header+
  parties (customer+alamat kirim)+rincian item+totals (pakai
  `CoOrder::totals()` yang sudah ada, termasuk PPh23 jasa)+signatures. Gate
  cetak: `doc_status=CONFIRMED` saja (CO cuma 2 status total). Tombol
  "🖨 Cetak Nota" baru di toolbar `co/entry.blade.php`. Diverifikasi
  `Livewire::test()` (CO CONFIRMED->200; tak ada CO DRAFT di DB utk uji
  redirect langsung, tapi gate logic identik 4 print page lain yg sudah
  diverifikasi sebelumnya). `npm run build` dijalankan.

## 2026-09-02/03 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #7)

- **§6 Production — Rate Band `resource_type=MANUAL` tak lagi selalu throw.**
  `RateBandResolver::resolveManual()` baru: histori Production Output
  (`prd_wo_operation` DONE/QC_PASSED) difilter `resource_type=MANUAL`+
  `process_name` (rate = properti proses, sama spirit jalur MACHINE yang
  properti mesin — BUKAN per-operator individual, itu tetap gap terbuka,
  butuh desain agregasi rate crew multi-orang yang belum diputuskan).
  `BackwardSchedulingService::resolveRateForTask()` pakai ini utk task
  manual, ganti exception permanen sebelumnya. Diverifikasi: tanpa histori
  -> exception informatif (bukan diam-diam salah hitung), dgn histori
  sintetis dlm transaction rollback -> rate terhitung benar (100qty/2jam x6
  data poin = 50/jam, sesuai).
- **BUG NYATA ditemukan & diperbaiki saat investigasi test tak
  terkait**: `production/monitoring-dashboard.blade.php` (Screen #3 MPS
  Kanban Board) rusak TOTAL sejak refactor date-picker custom 2026-09-02 --
  tag `<x-form.date-picker ...>` di header dibuka TANPA `/>` atau penutup,
  bikin seluruh konten setelahnya (termasuk link "MPS - Gantt Chart") ikut
  jadi slot content komponen itu, Blade compiler kehilangan jejak nesting
  @if/@foreach di bawahnya -> "unexpected end of file" tiap kali layar ini
  dirender (test smoke-check `test_screen3_monitoring_dashboard_renders`
  gagal). Fix: tutup tag `/>`. Sekaligus perbaiki 2 inline `@php(...)`
  tepat setelah `@foreach` (gotcha terdokumentasi
  `feedback_livewire_wirekey_inline_php_gotcha`, walau bukan akar masalah
  di kasus ini) jadi pemanggilan method langsung inline. Grep dilakukan ke
  seluruh `resources/views` cari pola tag serupa -- cuma 1 kecocokan lain
  (`inquiry/form.blade.php:1266`), dicek TERNYATA closed dgn benar
  (`x-slot:label`, false positive). Semua 6 test `ProductionWoGenerationTest`
  lolos setelah fix (sebelumnya 5/6). `npm run build` dijalankan.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #6)

- **§6 Production — Qty Kebutuhan BOM WO Detail diperbaiki.**
  `production/wo-entry.blade.php::billOfMaterialRows()` sebelumnya
  `qty_kebutuhan = qty WO` mentah (1:1, asumsi 1 lembar bahan baku = 1 pcs
  jadi) — salah utk part yang di-imposition >1 UP per lembar (mis. 500 WO
  dgn 8 UP/lembar harusnya butuh 63 lembar, bukan 500). Fix: dibagi
  `up_count` dari `iqp_layout` CONFIRMED milik `source_iqp_part_id`
  (fallback up_count=1 kalau imposition belum dikonfirmasi -- tetap qty
  mentah, bukan crash/kosong). Diverifikasi `Livewire::test()`: kasus
  unconfirmed (fallback benar, 500 tetap 500) + kasus confirmed dipaksa
  dalam transaction rollback (500/8 dibulatkan ke atas = 63, sesuai
  ekspektasi). Bukan MRP/qty_factor per proses penuh (tetap gap terbuka,
  BACKLOG §6 F10), tapi jauh lebih presisi dari sebelumnya.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #5)

- **§12.7 — Cetak PR & PQ dibangun, melengkapi PO** (jadi 3/3 dokumen
  transaksi Procurement kini punya print). PR (`pur/pr/print.blade.php`,
  dokumen internal departemen->approval, gate APPROVED ke atas) & PQ
  (`pur/pq/print.blade.php`, rekap penawaran 1 supplier per dokumen, gate
  CONFIRMED — PQ BUKAN RFQ letter ke supplier, arahnya catat HASIL
  penawaran yg sudah masuk, dipakai bandingkan >1 PQ utk 1 PR yang sama).
  Diverifikasi Livewire::test(): PR pakai data riil (APPROVED->200,
  DRAFT->redirect), PQ tak ada 1 baris pun di DB jadi pakai fixture
  sementara di dalam transaction yang di-rollback (DRAFT->redirect,
  CONFIRMED->200, 0 data tertinggal). `npm run build` dijalankan.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #4)

- **§12.7 — Cetak Purchase Order (PO) dibangun.** Dari 3 dokumen transaksi
  yang belum punya print sama sekali (PR/PO/PQ), PO dikerjakan duluan
  (paling kritis — dikirim ke supplier eksternal). Pola identik
  `quotation/print.blade.php` (komponen `resources/views/components/print/*`).
  Gate cetak: hanya setelah `status` APPROVED ke atas (bukan DRAFT/SUBMITTED),
  redirect balik ke entry kalau dicoba akses langsung saat masih draft — sama
  alasan quotation harus PUBLISHED dulu. Tombol "🖨 Cetak PO" baru di toolbar
  `pur/po/entry.blade.php`. Diverifikasi `Livewire::test()` (PO RECEIVED ->
  200, PO DRAFT -> redirect ke entry) + HTTP kernel 200. `npm run build`
  dijalankan. PR & PQ print MASIH terbuka (dicatat `docs/BACKLOG.md` §12.7).

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #3)

- **§7 — Outstanding PV report dibangun.** Prasyarat sudah terpenuhi
  (Stock Out live sejak 2026-08-22) tapi 1 keputusan desain masih terbuka
  (3 opsi tracking serah-terima gudang->departemen peminta) — diputuskan
  OTONOM (low-risk/reversibel, murni pilihan teknis bukan kebijakan bisnis):
  opsi PALING SEDERHANA, flag header-level `pv_receipt.is_handed_over`
  (+`handed_over_at`/`handed_over_by`), konsisten gaya Outstanding PR/PO
  existing yang juga header-level (bukan opsi per-line `c_qty_received` atau
  dokumen baru "Bukti Serah Terima Barang" — keduanya lebih rumit tanpa
  kebutuhan granularitas jelas). Layar baru `pur/report/outstanding-pv`
  (route `pur.report.outstanding-pv`): PV POSTED yg belum diserahkan, badge
  merah kalau menunggu >3 hari, tombol "✓ Sudah Diserahkan". Menu
  `PROC_MON_PV` (placeholder sejak 2026-08-21) di-flip READY_FOR_QC.
  Diverifikasi `Livewire::test()` (toggle + restore state PV dummy existing
  ke nilai asli, bukan tebakan) + HTTP kernel 200. `npm run build` dijalankan.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2 #2)

- **§3/§1 — Win-Rate Sales dibangun.** Temuan: `iqp_inquiry.sub_state` SUDAH
  punya value `CONVERTED`/`LOST` di skema sejak awal (enum-teks, dipakai
  badge/filter di banyak layar) — TAPI 0 titik kode yang pernah menuliskan
  kedua value itu (grep dipastikan dulu sebelum tambah kolom baru, sesuai
  CLAUDE.md §7). 2 titik tulis baru: `CoOrderBuilder::findOrCreateFromQuotation()`
  set `sub_state=CONVERTED` bersamaan `co_id` (saat CO pertama lahir dari
  Quotation = deal resmi menang), `inquiry/form.blade.php::markLost()` (tombol
  "❌ Tandai Kalah" baru, mirror pola `cancel()` existing, reuse kolom
  `cancel_reason` utk alasan kalah — beda dari `cancel()`: LOST boleh dari
  sub_state manapun sepanjang belum terminal, bukan cuma dari state tertentu).
  Layar baru `sales/win-rate` (route `sales.win-rate`, menu `SALES_WINRATE` di
  grup Laporan Sales): stat card won/lost/win-rate%, breakdown per PIC Sales,
  detail deal kalah + alasan. Diverifikasi `Livewire::test()` end-to-end
  (buat inquiry->confirm->markLost->cek report) + HTTP kernel 200.
  `npm run build` dijalankan.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`, lanjutan Fase 2)

- **§10 — `class="grid" grid-cols-N` literal DIPERBAIKI** (audit 2026-09-02
  pagi selesai, sesi ini eksekusi perbaikannya). 6 file/9 lokasi: grid-cols-2
  → dialek `.grid.two` existing (`production/scheduling-board.blade.php:865`,
  `rule-library.blade.php:230,245`, `mts-forecast.blade.php:238`), grid-cols-3
  → `.grid.three` (`recruitment/interview-schedule.blade.php:315,336`),
  grid-cols-7 → `[display:grid]` (tak ada dialek `.seven`, dipakai
  `components/form/date-picker.blade.php:87,92` — dampak terluas krn dipakai
  lintas modul — + `interview-schedule.blade.php:320,342,348`),
  `careers/landing.blade.php:38` (`md:grid-cols-2` responsive, dicek pakai
  `app.css` yg sama → `[display:grid]` supaya breakpoint md: tetap jalan,
  BUKAN `.two` flat yg akan hilangkan behaviour 1-kol mobile). `npm run build`
  dijalankan, `php -l` semua file lolos. Belum verifikasi visual browser
  langsung (butuh login) — pola fix identik yg sudah terbukti jalan di file
  lain (`imposition.blade.php` dst), risiko rendah.

## 2026-09-02 (sesi autonomous scheduled task `tmerp-backlog-autowork-1700`)

- **ProDev Fase 1 TUNTAS** (`docs/inquiry_product_costing/productdevelopment_tier1.md`
  jadi spesifikasi resminya, sudah dikunci sesi sebelumnya). Rangkaian 6 commit
  (`98abe9a`..`031c5e4`), semua diverifikasi HTTP kernel 200 + `Livewire::test()`
  end-to-end (bukan asumsi):
  1. **Skema DB** — ALTER `prod_part` (14 kolom teknis baru: mastercard_code,
     die_code/die_w_cm/die_h_cm, sheet_raw_w_cm/h_cm, color_set_1/2 JSON, 6 kolom
     flap) + 5 tabel baru `prod_layout_group`/`prod_layout_group_part`/
     `prod_part_attachment`/`prod_part_finishing`/`ref_finishing_type`(8 seed)/
     `mat_flap_rule`(1 placeholder). **Temuan penting**: `prod_part_process`
     TERNYATA sudah ada dari DDL awal TIER_3A (bukan migration Laravel) dgn
     skema lebih lengkap dari rencana — dipakai apa adanya (bukan duplikasi),
     cuma ditambah FK `machine_id` yg tadinya logis. `printsize_p/l`,
     `joint_type`, `pack_qty` di `prod_part` juga direuse (bukan kolom baru).
  2. **Model Eloquent**: `ProdPartProcess`/`ProdLayoutGroup`/`ProdPartAttachment`/
     `ProdPartFinishing`/`RefFinishingType`/`MatFlapRule`/`ProdMastercardSignoff`
     baru + `ProdPart::$fillable` diperluas besar (banyak kolom lama existing
     ternyata tak pernah masuk fillable, jadi tak pernah bisa diisi lewat layar
     apapun sebelumnya).
  3. **ENGINE Tingkat 1** (`ImpositionEngine::sweepFlap()`) — parametric flap
     sweep, reuse `run()` existing (3 orientasi TEGAK/PUTAR/CAMPUR), ranking
     top-5 by UP lalu efisiensi.
  4. **Layar `production/prodev`** (route `production.prodev`) — workbench
     pilih part + edit spesifikasi teknis + jalankan engine + preview visual
     SVG-style top-5 kandidat + pilih 1 (Engine suggests, Officer confirms) →
     tersimpan `prod_layout_group` + routing (`prod_part_process`) + finishing
     (`prod_part_finishing`) inline + upload referensi JPEG/PDF/AI
     (`WithFileUploads`, batas `PRODEV_UPLOAD_MAX_MB`). Bug ditemukan+diperbaiki:
     `withTimestamps(false)` di relasi many-to-many `prod_layout_group_part`
     ternyata TETAP menyalakan kolom timestamp (bukan false-kan) — pivot tak
     punya `updated_at`, query gagal; fix: hapus `withTimestamps()` total.
  5. **Cetak Master Card** (`production.prodev.print`) — komponen
     `resources/views/components/print/*`, 5 kolom tanda tangan dari
     `prod_mastercard_signoff` (model baru). Menu `PROD_CARD`/`PROD_LAYOUT`
     (`sys_app_menu`) diaktifkan SETELAH verifikasi lolos (keduanya arah ke
     layar workbench yang sama).
  6. **Costing 4 tipe part baru** (`docs/inquiry_product_costing/gemini_spark_productcosting.md`
     §IV jadi acuan formula hasil audit `calc_cogs` V1 asli):
     SHEET (identik SHEET_DIGITAL, dialiaskan bukan diduplikasi — diekstrak
     `computeSheetGeometry()` reusable), KRAFT (literal §IV.2.B, area/10000
     TANPA pembagi rim 500), DC+SHEET/DC+SF (Paper+Cetak+J36 Laminasi+J37 SF EF
     +Plong+Tooling — DITANDAI "BELUM DIVERIFIKASI Excel" di `description_calc`,
     dokumen sumber sendiri mencatat rumus RM tak konsisten di 3 titik V1).
     **Side-fix**: jalur ivory/AP di `calculateDcPart()` (tipe DC, sudah lama
     live) ternyata salah pakai `PLANO_REF_AREA`(8611, harusnya cuma duplex) —
     dikoreksi ke /10000 sesuai §IV.2.A, ditemukan saat cross-check formula
     KRAFT yg share paper-cost logic. Test lama yg assert "SHEET belum
     diimplementasi" direvisi pakai kode BOGUS (semua 3 test lolos, termasuk
     regresi byte-presisi Excel SHEET_DIGITAL — refactor tidak mengganggu).
  7. **Toggle Manual/Ambil-Data per-part** di `inquiry/costing` — pilih Master
     Card/layout CONFIRMED dari dropdown → plano/cut auto-fill dari
     `prod_layout_group` + HPP LANGSUNG terhitung (tanpa klik "Hitung" terpisah,
     tujuan eksplisit: 1 officer bukan 2 spt sebelumnya). Field terkait
     di-disable saat mode PRODEV.
  8. **ENGINE Tingkat 2** (`ImpositionEngine::sweepInterlock()`) — perluasan
     Tingkat 1 dgn variabel `shift_offset_mm` (lever utama, sesuai observasi
     user "kebanyakan kasus geser bukan kecilkan lidah") + `flap_length`
     (sekunder). Model pitch efektif = body+flap-shift, DICATAT JUJUR di
     docblock sbg interpretasi (dokumen sumber §4.3a level pseudocode/observasi
     lapangan, bukan spek presisi — user akan trial sampai cocok data riil).

  **Migration**: `2026_09_02_000021` s/d `2026_09_02_000031` (11 file — skema
  ProDev, kolom Costing baru, aktivasi menu, `is_engine_ready` 4 part_type).
  **Belum dikerjakan** (dicatat `docs/BACKLOG.md`, follow-up bukan blocker
  Fase 1): Tool #10 Intra-Product Combiner, Tool #2 Pcs→Plano, Tool #4
  Die-Rule Estimator, alokasi biaya proporsional multi-part layout group.

## 2026-09-02

- **Recruitment L8 (Rekap Sync Log) dibangun.** Menu placeholder
  `HRM_RCR_SYNCLOG` sudah ada sejak 2026-08-29 (status NOT_BUILT, sengaja
  dilewati) — data `tm2hrr.rcr_sync_log` **memang sudah terisi** (3 baris
  nyata dari `PortalSyncService`, jadwal tiap 2 menit), jadi tinggal
  dibuatkan grid, bukan bangun dari nol. Layar baru
  `resources/views/pages/recruitment/sync-log.blade.php`, murni **read-only**
  (kandidat, lowongan, backend FIREBASE/MARIADB, hasil OK/ERROR, waktu,
  catatan error) — SENGAJA tidak ada Aksi buka/edit, karena layar Offer yang
  jadi kandidat tujuan link `mount()`-nya bikin baris `AppOffer` baru kalau
  belum ada (efek samping tak diinginkan cuma dari klik baris log). Route
  `hrm.recruitment.sync-log`, menu status diflip ke READY_FOR_QC (migration
  `2026_09_02_000018`). Diverifikasi via HTTP kernel (bukan browser login) —
  200, render kandidat+backend dgn benar.
- **§4/§7 — koreksi catatan "13 kategori COA kosong belum ada layar".**
  Ternyata **layarnya SUDAH ADA** (`master/mat-category.blade.php`, section
  "Default Akuntansi" utk kategori Level 1, dibangun 2026-08-26 bersamaan
  redesign `mat_category`) — catatan lama di `BACKLOG.md` salah, sempat
  dikira belum dibangun. Dicek ulang ke DB: 18 kategori Level-1 total, 12
  masih kosong `default_coa_account_id`/`default_stock_asset_account_id`
  (akun GR/IR universal sudah terisi semua di 813). BACKLOG.md dikoreksi
  supaya tidak salah lagi — item pengisian akun tetap TERBUKA (keputusan
  bisnis user, bukan kode), cuma "perlu bangun layar"-nya yang ditutup.
- **§10 — Audit `class="grid"` tech debt SELESAI** (murni audit, TIDAK
  diperbaiki — sesuai scope item ini). Ditemukan lewat grep sistematis:
  beberapa file (`master/customer`, `employee`, `supplier`,
  `production/wo-entry`, `master/raw-material/entry`, `pur/pr/entry`) sudah
  pakai workaround `[display:grid]` (AMAN). File yang MASIH pakai
  `class="grid grid-cols-N"` mentah (N≠4, kena override diam-diam ke
  4-kolom oleh `.grid{grid-cols-4}` unlayered di `app.css`): daftar lengkap
  8 file/13 lokasi di `BACKLOG.md` §10 — termasuk `components/form/date-picker.blade.php`
  (dampak terluas, dipakai lintas modul) dan kalender Interview Recruitment.
  Belum diverifikasi visual di browser, keputusan perbaiki-atau-tidak
  menyusul terpisah.
- **§12.5 — `sys_holiday` dibangun** (tabel hari libur nasional, sebelumnya
  tidak ada sama sekali). Migration `2026_09_02_000015` bikin tabel
  `tm2mst.sys_holiday` (id_holiday, holiday_date unique, holiday_name,
  holiday_type enum NATIONAL/COMPANY, activate, created_by/updated_by) +
  seed 5 hari libur tanggal-tetap 2026 (1 Jan, 1 Mei, 1 Jun, 17 Agu, 25 Des
  — SENGAJA tidak isi hari libur kalender lunar/hasil sidang isbat, itu
  perlu update manual tiap tahun). Layar CRUD baru "Master Hari Libur"
  (`resources/views/pages/master/holiday.blade.php`, pola spreadsheet-
  editable sama seperti Master Currency/Kurs) + `App\Models\Master\SysHoliday`
  (ada helper `isHoliday(string $date): bool` utk dipakai modul Attendance
  nanti) + route `master.holiday` + menu `MD_HOLIDAY` (migration
  `2026_09_02_000016`). **Catatan**: ini baru tabel+CRUD-nya saja — aturan
  lembur H-7/H-1 yang MEMAKAI tabel ini di modul Attendance belum digarap
  (Payroll/Attendance masih belum dibangun, lihat `project_payroll_encryption_requirement.md`).
- **Label grup Sales diseragamkan**: `SALES_MON` (`sys_app_menu`) dari
  "Monitoring & Laporan" → **"Laporan"** polos, konsisten dengan modul lain
  yang sudah diseragamkan sejak 2026-08-22 (`docs/sidebarmenustandard.md`
  §1). Migration `2026_09_02_000017`.
- **QUEUE item #9 (2026-08-20, theme picker via `sys_user_menu`) — diverifikasi
  ulang, TERNYATA CUMA SETENGAH SELESAI.** Fitur pilih-tema yang terlihat di
  UI sekarang jalan lewat mekanisme LAIN (`sys_theme` + dropdown avatar,
  lihat `docs/themestandard.md`) — bukan lewat `sys_user_menu`. Tabel
  `sys_user_menu` sendiri **masih ada & terisi 10 baris** di `tm2sys` tapi
  **grep `main-menu.blade.php` = 0 referensi**, jadi genuinely orphaned:
  data tersimpan tapi tak pernah dibaca aplikasi. Item ini TETAP TERBUKA di
  `BACKLOG.md` §10 (bukan ditutup) — bukan kandidat "mudah" lagi karena
  perlu keputusan dulu: dipakai betulan (refactor dropdown menu jadi
  data-driven dari tabel ini) atau dihapus sebagai dead table.
- **§2 poin 1 — ~97 judul tab browser jadi dinamis.** Solusi:
  bukan edit 97 file `#[Title('X — TELASIH MAKMUR ERP')]` satu-satu (yang
  tidak mungkin krn PHP attribute wajib constant expression) — literal
  "TELASIH MAKMUR"/"TELASIH MAKMUR ERP" di dalam string attribute yang
  SUDAH ADA dijadikan placeholder, di-cari-ganti SEKALI di titik render
  `<title>` (`layouts::shell`/`guest`/`app`, `str_replace([...], [Brand::name(),...], $title)`).
  0 dari 97 file individual disentuh. `main-menu.blade.php` sedikit beda
  pola (`'TELASIH MAKMUR — ERP'`, bukan `'X — TELASIH MAKMUR ERP'`) —
  diseragamkan ke `'Home — TELASIH MAKMUR ERP'` supaya konsisten. Detail:
  `docs/configurationstandard.md` §5.
- **Koreksi nama company**: `org_company.company_name` dulu
  "CV Telasih Makmur Group" (salah) → **"CV. Langgeng Jaya Makmur"** (nama
  legal terdaftar sebenarnya). "Telasih Makmur" tetap dipakai sebagai
  NICKNAME company di permukaan customer-facing (portal karir, dst) —
  TIDAK dihapus, cuma diklarifikasi bukan nama legal. APP_NAME ("ERP TM",
  identitas aplikasi internal) tidak terpengaruh, konsep terpisah.
- **§2 — PDF footer dinamis**: `exports/generic-grid-pdf.blade.php`
  sekarang baca `Brand::name()`.
- **§2 — Master Kurs (`fin_exchange_rate`) dapat layar CRUD**:
  `resources/views/pages/master/exchange-rate.blade.php`, pola spreadsheet-
  editable sama spt Master Currency, versioned (1 baris `activate=1` per
  currency, baris lama otomatis nonaktif saat baris baru diaktifkan).
  **Bonus temuan**: menu "Master Currency" (`MD_CURRENCY`) ternyata
  **link mati sejak dibuat 2026-08-30** (tidak pernah dipetakan ke route di
  `main-menu.blade.php`) — diperbaiki bersamaan.
- **§2 — `geo_country.flag_emoji` terisi semua 250 baris**:
  DIHITUNG, bukan ditebak — `iso_code` (ISO 3166-1 alpha-3, sudah ada)
  dipetakan ke alpha-2 lewat tabel referensi ISO baku, lalu emoji dihitung
  via rumus Unicode Regional Indicator Symbol (deterministik, 0 karakter
  emoji diketik manual). 0 baris NULL tersisa.
- **§2 — BUG ARSITEKTURAL Filament ditemukan & diperbaiki (temuan
  terbesar hari ini)**: saat verifikasi lebih dalam lapis kosmetik
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
