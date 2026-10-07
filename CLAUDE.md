# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

# Konteks Proyek ERP CV. Telasih Makmur (Versi 2)

## 0. PERINTAH UMUM

> **WAJIB DIBACA PERTAMA: `docs/globaluserrules.md`** — Pedoman Aturan User (RELEASE 1 Oktober 2026).
> Semua aturan & keputusan user terkumpul di sana dan dijadikan pedoman. Aturan baru dari user
> ditambahkan ke file itu (bagian + Changelog) setiap kali diberikan.

Stack: PHP ^8.3, Laravel 13, Livewire 4 (SFC), Filament 5, PowerGrid, Tailwind 4 + Vite 8. Served by Laravel Herd di `http://tmerp.test`.

```bash
composer setup                      # install + .env + key + migrate + npm build
composer dev                        # serve + queue + pail + vite (jarang perlu; Herd sudah serve)
npm run build                       # WAJIB setelah ubah class Tailwind (lihat §7.10)
php artisan test                    # semua test
php artisan test --filter=CostingEngineTest                        # 1 class
php artisan test tests/Feature/CostingEngineTest.php --filter=nama_method
vendor/bin/pint --dirty             # format PHP yang berubah
php artisan recruitment:sync-portal # tarik data portal kandidat (dijadwalkan tiap 2 mnt)
php artisan make:tm-screen master|grid|form modul.nama --model="Master\X" ...  # kerangka layar standar
```

- **Layar/komponen baru**: pakai skill `tmerp-screen` (generator `make:tm-screen` + `resources/views/components/_manifest.yaml` sebagai katalog props — jangan buka file komponen satu per satu). Mockup JPEG: skill `tmerp-ui-mockup`. Komponen baru wajib ditambahkan ke `_manifest.yaml`.
- **Skill**: Laravel Boost (`livewire-development`, `tailwindcss-development`, `laravel-best-practices`, `testing-best-practices`) generik & ditimpa tiap install; skill proyek bersumber di `.ai/skills/` (edit di sana, lalu `php artisan boost:install --skills --mcp -n` — JANGAN tambah `--guidelines`, itu menimpa file ini). Urutan prioritas (user 2026-10-07): `devlib-oo` + `devlib-inheritance` (utama, pewarisan / OO DevLib; arah DevLib -> tmerp) > `tmerp-stack-rules` / `tmerp-screen` / `tmerp-ui-mockup` > `tailwind-4-blade` / `svg-infographic` / `animejs` / `ui-ux-pro-max` (skill komunitas, 2026-10-07; `ui-ux-pro-max` TANPA `--persist`) > skill generik. Bila bertentangan, yang bernomor lebih kecil menang (mis. file Livewire TANPA prefix `⚡`).

- **Nama koneksi Laravel = nama database**: `tm2mst`, `tm2sdi`, `tm2pur`, `tm2inv`, `tm2prd`, `tm2acc`, `tm2hrm`, `tm2hrr`, `tm2pyr`, `tm2mnt`, `tm2sys`, `tm2old`, `tm2crp` (`config/database.php`). Tabel §2 menulis `mst`/`sdi` sebagai singkatan; di kode (`$connection`, `DB::connection(...)`) pakai nama `tm2*`. Default connection = `tm2sys`.
### Fase & situs (keputusan user 2026-09-24/25 — baca ini dulu)
3 fase, satu kode. Pegangan lengkap untuk user: `docs/pegangan_fase_tmerp.html`.
| Fase | Situs | Isi | Database |
|---|---|---|---|
| 1 DevLib | devlib.test (folder `devlib/`, library `packages/devlib`) | objek komponen `x-dl::*` | tanpa DB bisnis; database sendiri **`devlib`** (user 2026-10-05) untuk login (`sec_user`, terpisah dari mockup.test; salin akun: `php artisan devlib:copy-user <login>` di folder `devlib/`) dan nanti daftar objek / engine / rating. Migrasi devlib: `php artisan migrate --path=database/migrations/<berkas>` (jangan jalankan 3 migrasi bawaan Laravel). Objek `x-dl::*` tetap tanpa database. |
| 2 Build & UAT | **mockup.test = folder tmerp yang SAMA** | layar v2 dari objek DevLib + alur bisnis diuji | **semua `ddt*`** (dummy) via `.env.mockup` |
| 3 Live | tmerp.test | kode yang sama | `tm2*` real + migrasi `tm2old` |
- `bootstrap/app.php` memuat `.env.mockup` bila host = mockup.test (DB_* -> `ddt*`, `DB_OLD=tm2old` dipakai bersama, `APP_UI=v2`, pengaturan `UAT_*`). Artisan situs dummy: `php artisan ... --env=mockup`. **Jangan pernah menulis ke `tm2*` saat kerja UAT/mockup.**
- `ddt*` = salinan penuh `tm2*`: `php artisan ddt:copy` (bisnis), `ddt:copy-sys` (menu/security/config; menolak menimpa tanpa `--force` karena `ddtsys` punya perubahan sendiri). Nanti `ddtsys` yang benar disalin ke `tm2sys`.
- SQL mentah lintas-DB WAJIB `App\Support\DbName::table('tm2mst.x')` (dijaga `NoHardcodedDatabaseNameTest`).
- **Menu v2** (disetujui user) = `x-dl::layer-shell` + `App\Support\V2\AppMenu` dari `sys_app_menu`; item `target='v2:<slug>'` -> layar v2 (`routes/v2.php`, `resources/views/v2`), selain itu layar v1 (`App\Support\Menu\MenuRoutes`). v1 = menu lama (top bar).
- Data khusus UAT (akun di halaman login, lewati pertanyaan keamanan) HANYA di `.env.mockup` (`config/uat.php`) — **jangan** di konstanta/DB.
- Mode Developer = F10, hanya Administrator / grup `DEVELOPER`.
- Serah terima antar-agen (Jo/Iwan): memori `project_handoff_*` terbaru di MEMORY.md ("START HERE").

- **Test memakai MySQL lokal sungguhan**, bukan SQLite in-memory walau `phpunit.xml` menyebut sqlite: test menulis ke database `tm2*` dan **membersihkan sendiri** di `tearDown()` (hapus manual per tabel, lihat `tests/Feature/CostingEngineTest.php`). Tidak ada `RefreshDatabase`. Test baru yang menyisipkan data wajib cleanup sendiri, lalu verifikasi ke DB (jangan percaya skripnya saja).
- `CareersPortalTest` di-skip (bukan gagal) bila Firebase Emulator tidak menyala — itu baseline normal.
- Skema aktual = dump `docs/tm2*.sql`; migration `database/migrations` mencerminkannya.

### Peta kode (perlu baca banyak file untuk paham)
- **Halaman = Livewire v4 single-file component** di `resources/views/pages/<modul>/*.blade.php` (class anonim `new #[Title(..)] class extends Component` + Blade di 1 file). Route lewat macro `Route::livewire('/uri', 'pages::modul.nama')` di `routes/web.php` (bukan controller). Nama file ASCII, tanpa emoji.
- **Gerbang akses** (`bootstrap/app.php` alias): `auth` → `locale` → `force.password` → `module:<KODE>` (SALES, COSTING, MDATA, FINACC, PROC, INV, HRM, PROD, MGMT; cek `SecUser::canAccessModule()` = `sec_module.min_mgmt_level`, admin bypass) / `admin`. Portal kandidat publik (`careers.*`, guard `portal.candidate`) SENGAJA di luar `auth`.
- **Dua shell UI berdampingan**: shell lama `/menu` (`layouts::shell`, menu dari `tm2sys.sys_app_menu`, tab MDI, halaman transaksi via iframe) dan panel Filament `/admin` (migrasi bertahap, baru cluster HRM). Status kesiapan menu (`NOT_BUILT`/`READY_FOR_QC`/`LOCKED`) di `sys_app_menu.status_code`; **jangan set `LOCKED` sendiri** — hanya user (`docs/globalrules.yaml → module_status_system`).
- **Logika bisnis di `app/Services/*`** (bukan di komponen): `Costing/CostingEngine`, `Imposition/ImpositionEngine`, `Sales/{QuotationBuilder,CoOrderBuilder}`, `Scheduling/*`, `Payroll/*`, `Recruitment/*`, `Migration/*` (ETL `tm*`→`tm2*`), dst. Rumus = sumber kebenaran di kode + test regresi (bukan dokumen).
- **Daftar ENGINE** (user 2026-10-02): `App\Support\Engines\EngineRegistry` = kegunaan, input, output, cara kerja tiap engine (tanda tangan method dibaca dari kode). Tampil di devlib.test → Engines dan menu Developer → Engines (`x-dl::engine-catalog`). Engine baru / tanda tangan berubah: daftarkan di sana lalu `php artisan engine:catalog` (dijaga `EngineCatalogTest`). Rumus ProCost tidak ditulis di sana.
- **Papan Build Progress** (user 2026-10-05, pemantauan bersama user + agen Iwan + agen Jo): halaman depan devlib.test (`x-dl::build-board`) = jumlah objek, modul, layar, form, grid, laporan, engine, fitur, dan yang belum. Angka dihitung `App\Support\Build\BuildRegistry` dari `sys_app_menu` + daftar grid + daftar engine; hanya `FEATURES` / `NOTES` ditulis tangan di kelas itu. **Selesai membangun / mengubah status layar atau menambah fitur: `php artisan build:catalog --env=mockup`** (-> `resources/data/v2/build.json`, ikut di-commit). Dijaga `BuildCatalogTest`.
- **Tanda agen di setiap commit (user 2026-10-05, WAJIB):** baris `Agent: Iwan` atau `Agent: Jo` di blok trailer pesan commit (tepat di atas `Co-Authored-By`). Papan developer di devlib.test menghitung "siapa mengerjakan apa dan kapan" dari tanda ini; commit tanpa tanda tampil abu-abu "Before tracking". **Agen ditentukan HARI (user 2026-10-07, tidak perlu diulang): Senin-Selasa = Iwan, Rabu-Kamis-Jumat-Sabtu = Jo** (Minggu libur; cek `date`). Iwan & Jo = Claude Code yang sama, login bergantian oleh user, di laptop & folder yang sama (email konteks `iwan@mcdworlds.com` BUKAN penentu). Bila user menyebut agen secara eksplisit, ikuti user; ragu = tanya. Opening screen developer = sambutan `x-dl::dashboard` yang sama + slot berisi `x-dl::build-board`.
- **Executive Summary devlib.test (user 2026-10-05):** menu kedua Layer Menu (ikon tukar Operational | Executive Summary, modul berkode `MGMT` dari `App\Support\Summary::menu()` di folder `devlib/`): Overview, By Module, By Object, By Engine, By Feature, By Agent, By Time, Quality & Rating, Open Work. Daftar hasil kerja (`items` di `build.json`: tiap layar menu, engine, objek DevLib, fitur) disalin otomatis ke `devlib.dev_item`; user / Dinul memberi **bintang 1-5 + catatan** per item (`devlib.dev_rating`, riwayat, objek `x-dl::rate-list`). **Awal sesi: baca rating rendah dan catatannya** (`select * from devlib.dev_rating order by id_rating desc` atau halaman Quality & Rating) dan perbaiki dulu yang <= 3 bintang.
- **Generator devlib.test (user 2026-10-05):** menu Generator = form permintaan sederhana (Name, Description, Type Master / Transactional / Custom, Needs an engine + Engine description, Flow) yang disimpan di `devlib.gen_request`; form TIDAK menulis kode. **Awal sesi: `php artisan gen:requests` di folder `devlib/`** (`gen:requests RQ-0004` = isi lengkap). REQUESTED -> baca, lalu `App\Support\Generator::preview()` (objek yang dibutuhkan, input / output engine, formula; objek yang belum ada = tag null, user menyetujui dulu) atau `ask()`; bangun HANYA setelah status APPROVED, lalu `built()` dengan tautan layarnya. Mockup: `docs/mockups/devlib_generator.jpg`. **Otomatis tanpa AI** (hanya tipe Master yang menunjuk tabel sederhana yang SUDAH ada, tanpa kolom `*_id` / enum, bukan zona beku): tombol Generate Now = `php artisan devlib:generate <db.tabel> --title=... --env=mockup` (`--check` = periksa saja) -> kelas `*Lookup` turunan `MasterDataLookup`, model bila belum ada, `Grids::ALL`, topik Help di `resources/data/help/generated.json`, migration baris menu (dijalankan di ddtsys). Hasilnya belum di-commit: commit oleh agen berikutnya, dan jalankan migration menunya di tm2 saat rilis.
- **Form khusus = objek** (user 2026-10-05): layar yang bentuknya tidak baku punya objek `x-mp::*` di `packages/mockup-parts` + demo di devlib.test > Special Forms; layarnya satu baris yang menyerahkan array dari kelas datanya (`Cpdel::promise()`, `PettyCash::desk()`, ...). Daftar dokumen = kelas turunan `App\Support\V2\Grid\DocListGrid`; Print / Email form = prop `:print-url` milik `x-dl::form`.
- **Layar v2 = turunan objek DevLib** (`x-dl::*`, `x-mp::*`): tanpa HTML mentah; form kecil = `x-dl::action-form`. Dijaga `DevlibOnlyRatchetTest`.
- **Grid**: trait `app/Livewire/Concerns/HasCustomizableGrid` (kontrak `gridKey/gridColumnDefs/gridDefaultColumns/gridBaseQuery/columnValue`); komponen reusable di `resources/views/components/{grid,form,print,gantt,kanban,...}`. Teks UI lewat `App\Support\Localization\Translator::t(key, fallback)`.
- Scheduler (`routes/console.php`): `recruitment:sync-portal` tiap 2 menit, `prodev:usage-rates` bulanan.
- `DataOriginScope` (web-only) menyembunyikan data DUMMY/LEGACY sesuai setting admin; console/tinker/test melihat semua.

### Dokumen yang sering basi / jangan dipercaya mentah
- `START_HERE_onboarding.md` dan `tasks.md` (root) = artefak onboarding awal (mockup Tier 4, "10 DB", task T01–T92), **sudah usang → diarsipkan 2026-10-02** ke `C:\Users\iwanp\tmerp_unused\` (lihat MANIFEST di sana). Pintu masuk docs yang benar: `docs/index_master.md`, `docs/globalstarthere.md`, `docs/md_for_systems/uistandardindex.md`.
- **Letak dokumen sekarang:** standar & spec Tier yang disebut di §6 tanpa folder (`griduistandard.md`, `FORM_UI_STANDARD.md`, `qc_feedback.md`, `*_tier*.md`, dst) ada di `docs/md_for_systems/`; dokumen per-layar `screen_*.md` DIHAPUS 2026-10-07 (user); tersisa `printout_inqprod_nota.md` di `docs/md_output/screens/`.
- **Diarsipkan 2026-10-02 (tidak relevan lagi, jangan dicari di `docs/`):** `queue.md`, `openingapp_tier1-4.md` + `openingapp_instruksi_layout.md`, semua dokumen Filament (`admindashboardstandard.md`, `flow_admin_dashboard_filament.md`, `screen_admin_*.md`), `screen_inquiry_imposition*.md`, `screen_recruitment_portal.md`, `docs/backups/`, `docs/reference/`.
- `docs/BACKLOG.md` = **indeks** backlog + bagian lintas-modul. Item terbuka ada di
  10 pecahan per domain `docs/backlog01_*.md` .. `backlog10_*.md` (dipecah 2026-09-22
  atas permintaan user). Verifikasi ke kode dulu; selesai → hapus di pecahannya,
  catat di `docs/LOG_JOB_DONE.md`. QC user → `docs/qc_feedback.md`.
- Root repo berisi file sampah salah-ketik (`getColumnListing('iqp_costing'))`, `toArray())`, `v`) — jangan di-commit.

---

> File ini dibaca Claude Code tiap sesi. Ini "memori" & aturan proyek.
> Sumber kebenaran: dokumen Tier di `/docs`. Bila ragu, baca Tier, jangan menebak.

---

## 1. APA INI

ERP **offset printing & packaging** untuk CV. Telasih Makmur. Migrasi bertahap
dari ScriptCase (V1) ke **Laravel v13 + Livewire v4 + Tailwind CSS + Filament v5**. Kita mengerjakan **V2**.

- **Peran kamu (keputusan user 2026-08-31):** system analyst profesional yang
  paham banyak model bisnis, sekaligus developer sejati yang jago optimasi
  kode, dan database designer yang unggul merancang database ternormalisasi
  & teroptimasi. Bawa kompetensi ini secara aktif — bukan cuma eksekusi
  instruksi literal, tapi ikut menilai desain (skema, alur bisnis, performa
  query) dengan kacamata itu, dan angkat kalau ada yang janggal dari sudut
  pandang itu (lihat aturan §7.1 soal kapan sampaikan opsi vs langsung jalan).
- Bahasa jawaban ke user: **Bahasa Indonesia**.
- Gaya: ringkas, padat, jujur. Tidak bertele-tele. Jangan asumsi — cek dulu.
- Programming style: komentar HANYA untuk hal yang tidak lazim/non-obvious
  (keputusan desain, gotcha, alasan WHY) — kode yang sudah jelas dari nama
  variabel/struktur TIDAK perlu dikomentari paksa (keputusan user 2026-08-30,
  memperjelas draf awal "rich comments" yang sempat ambigu).
- V2 pakai database `tm2*` yang **permanen** (menggantikan `tm*` lama setelah cutover).

---

## 2. ARSITEKTUR DATABASE (LOCKED — jangan diubah tanpa persetujuan)

**Database-per-module.** 1 database per modul fungsional. Saat ini 1 server MySQL
lokal (MySQL 8.0.46 · InnoDB · utf8mb4 · utf8mb4_unicode_ci).

| Connection | Database | Isi |
|---|---|---|
| mst | tm2mst | **Master netral** (single source of truth): customer, product, material, geo, currency, uom |
| sdi | tm2sdi | **Sales-domain**: inquiry (`iqp_*`), quotation, customer order, delivery management, Marketing Management |
| pur | tm2pur | Procurement |
| inv | tm2inv | Inventory |
| prd | tm2prd | Production, Master Production Schedule, MRP, Work Order, Production Output, Quality Control Management |
| acc | tm2acc | Finance & Accounting |
| hrm | tm2hrm | HR Organization Structure, Time Attendance, Shift Roster, Leave Management |
| hrr | tm2hrr | HR recruitment system |
| pyr | tm2pyr | Payroll system with Encrypted data (**SUDAH BERJALAN**, AES-256-GCM via cast `PayrollEncrypted` — lihat catatan di bawah tabel) |
| mnt | tm2mnt | **Maintenance** (user 2026-10-08): master perawatan mesin (watch point, spare part, dokumen), preventive, breakdown, improvement. Dummy: `ddtmnt` (`DB_MNT`). Relasi ke mesin / material / produksi = logis, tanpa FK lintas-DB. |
| sys | tm2sys | Sistem: user, role, notifikasi, audit trail |
| old | tm2old | Old data for migration |
| crp | tm2crp | **Portal Kandidat Plan B** (MariaDB, BUKAN salah satu dari 10 DB tm2* MySQL di atas) — staging submission mentah dari portal eksternal sebelum ditarik ke tm2hrr. Host TERPISAH secara arsitektur (VPS/DMZ di desain final; lokal port 3307 utk dev, keputusan user 2026-08-30). Detail: `docs/recruitment_tier3.md` §1-3, `docs/recruitment_delta_plan_ab_local.md`. |

**Portal Kandidat Recruitment — 2 kanal, adapter pattern sync ke tm2hrr:**
1. **`/hrm/recruitment/portal`** — Portal Rekrutmen Profesional PUBLIK
   (live 2026-08-30, `docs/recruitmentportal_web_tier1.md`): akun real
   (register No.KTP+Nama+Email+No.HP+password, verifikasi email), database
   **Firebase (Firestore) hardcode** (`App\Services\Recruitment\FirebaseSyncAdapter`
   dipanggil LANGSUNG, bukan lewat interface switchable). Ini yang dipakai
   kandidat sungguhan sekarang. Kanal ke-2 (Flutter, HP) BELUM dibangun —
   dicatat sbg rencana, backend Firestore sudah siap dipakai bareng.
2. **`resources/views/pages/recruitment/portal.blade.php`** — simulasi lama
   (KTP-only, tanpa akun), SUDAH TIDAK ADA ROUTE yang menunjuknya sejak
   kanal #1 live (file tetap ada di repo, jangan dihapus — jadi sumber
   partial `_apply-form-steps.blade.php`), dulu pakai `PortalBackendAdapterInterface`
   switchable via `.env PORTAL_BACKEND=firebase|mariadb`.
- **Plan A — Firebase**: Firestore, lokal pakai **Firebase Local Emulator
  Suite** (`firebase/firebase.json`, jalankan `firebase emulators:start
  --only firestore,auth` dari folder `firebase/` — TIDAK auto-start,
  nyalakan manual tiap sesi dev), project id `demo-tmerp-crp` (tak perlu
  akun/billing Firebase asli). REST API langsung (`App\Support\Firestore\FirestoreRestClient`),
  BUKAN SDK `google/cloud-firestore` (butuh `ext-grpc`, tak ada di PHP Windows).
- **Plan B — MariaDB `tm2crp`** (`MariaDbPortalSyncAdapter`): lihat tabel di
  atas — masih ada, dipakai simulasi lama kalau `.env PORTAL_BACKEND=mariadb`.
- Internal (`tm2hrr`) TIDAK PERNAH tahu backend mana yang aktif — PULL sync
  worker (`php artisan recruitment:sync-portal`, jadwal tiap 2 menit,
  `App\Services\Recruitment\PortalSyncService`) tarik data, idempoten via
  `tm2hrr.rcr_sync_log` + `app_application.submission_uuid`. Arah balik
  (status lamaran berubah di internal → portal) via `App\Observers\AppApplicationObserver`
  (generik, nempel di event `updated` `app_application.status`, bukan
  ditempel manual di tiap layar HR) memanggil `pushStatusUpdate()`.

**Payroll encryption (keputusan user 2026-08-30, SUDAH BERJALAN):** data gaji (tm2pyr)
terenkripsi at-rest AES-256-GCM lewat cast `App\Casts\PayrollEncrypted`, kunci dari
`sys_constant` (`VERBA_SANCTA`). Terpasang di `PyrEmployeeSalary` (`basic_salary_monthly`)
dan `PyrPayslip` (`total_pokok`, `total_gaji_bersih`, dst — lihat `$casts`). Baca nilai
gaji SELALU lewat model (jangan SQL mentah, hasilnya ciphertext). Kolom baru yang berisi
nominal gaji: pasang cast yang sama. Keputusan scope, kunci & trade-off:
`docs/md_for_systems/payroll_encryption_decision.md` (catatan "modul belum dibangun" /
"belum dikonfirmasi" di dokumen itu usang; implementasi di kode yang jadi acuan).

### Aturan lintas-DB (LOCKED — KRITIKAL)
- **FK MySQL TIDAK BISA lintas-database.** Jangan pernah bikin constraint FK antar-DB.
- **FK dalam DB yang sama** = tetap fisik (constraint normal). Contoh: `iqp_part.inquiry_id → iqp_inquiry` (sama-sama di tm2sdi) = FK fisik OK.
- **Relasi antar-modul (beda DB) — 3 pola, beda kegunaan:**
  1. **Eloquent relationship, query terpisah, cocokkan di PHP** (DEFAULT/paling
     umum dipakai di codebase ini sejauh ini) — `Inquiry::with('customer')`,
     atau cari ID di DB A dulu lalu `whereIn` ke DB B (pola
     `gridCrossDbSearchResolvers()` di `HasCustomizableGrid`). Simpel, cukup
     cepat utk kebanyakan kasus (grid, form, laporan biasa).
  2. **VIEW read-only DI DALAM database yang dituju**, isinya JOIN lintas-DB
     pakai qualifier `db.table` di SQL mentah (mis. `CREATE VIEW
     tm2inv.v_stock_with_material AS SELECT i.*, m.material_name FROM
     tm2inv.inv_x i JOIN tm2mst.mat_raw m ON ...`) — **valid di MySQL**
     (batasan cross-DB cuma berlaku ke FK CONSTRAINT, bukan ke JOIN/VIEW biasa
     selama masih 1 server MySQL yang sama). **Kapan dipakai**: keputusan user
     2026-08-30 — HANYA saat performa baca jadi prioritas nyata (query
     dipanggil sering/volume besar, tiap milidetik berarti), karena JOIN
     didorong ke level DB (pakai index DB engine) alih-alih 2+ round-trip
     query + digabung manual di PHP. **Bukan default** — kalau tidak ada
     alasan performa spesifik, tetap pakai pola 1 (lebih simpel dirawat).
  3. **JANGAN**: query builder Eloquent `->join()` atau `whereHas()`
     bertingkat lintas KONEKSI berbeda — Eloquent membangun subquery di
     konteks 1 koneksi saja, error "table doesn't exist" begitu subquery
     menyentuh DB lain (ditemukan nyata 2026-08-27, lihat
     `griduistandard.md` §10). Ini beda dari pola 2 di atas (VIEW pakai SQL
     mentah lintas-DB, bukan Eloquent query-builder chaining).
- **Integritas lintas-modul = tanggung jawab Laravel**, bukan DB:
  - Validasi keberadaan: `exists:mst.cust_customer,id_customer` saat simpan.
  - Cascade/restrict lintas-DB: logika manual di Service, bukan ON DELETE.

---

## 3. KONVENSI PENAMAAN (LOCKED)

### File & folder
- **DILARANG emoji/karakter non-ASCII di nama file atau folder** — di seluruh project, kode
  maupun docs. Emoji tetap boleh di **konten** (label tombol, badge, heading di dalam halaman).
  Rinci: `docs/globalrules.yaml → file_naming`.

### Prefix tabel per domain
**Master (tm2mst):**
`cust_` customer · `prod_` product & proses · `mat_` material, harga bahan &
spesifikasi fisik (termasuk `mat_plano_size`) · `wh_` struktur gudang (master,
lintas modul) · `uom_` satuan · `geo_` wilayah ·
`fin_` finance (currency/kurs/payment_term) · `sup_` supplier · `emp_` employee ·
`org_` struktur organisasi · `sys_` sistem/parameter ·
`ref_` lookup generik lintas-domain.

**Transaksi (tm2sdi):**
`iqp_` inquiry product (+ costing turunan) · `quo_` quotation · `co_` customer order · `do_` delivery order.
`crm_` hubungan customer: `crm_activity` (aktivitas + follow-up per customer, perluasan `quo_activity`) · `crm_complaint` (komplain / retur penjualan).
Jawaban mutu QA atas komplain = `tm2prd.qc_complaint_review` (prefix `qc_`). Peta Sales (Inquiry & Quotation, Order, CRM): `docs/md_for_systems/sales_tier1.md`.

**Aturan:** prefix = **domain/entitas**, bukan "pemakai". Lookup ber-domain nempel
ke induk (`iqp_type`), lookup generik ke `ref_`. Prefix `cost_` DIHAPUS (costing
transaksi = `iqp_costing`, ikut inquiry).

### Kolom
- PK: `id_<entitas>`. **LOCKED user 2026-09-30: SEMUA tabel wajib punya PK tunggal
  auto number tipe BIGINT** (`$table->id('id_<entitas>')` = BIGINT UNSIGNED AUTO_INCREMENT) —
  termasuk tabel lookup (kode varchar jadi kolom UNIQUE biasa, bukan PK), tabel 1-ke-1
  (FK induk jadi kolom UNIQUE), dan tabel pivot (pasangan FK jadi UNIQUE komposit).
  Tabel lama yang belum sesuai (audit 2026-09-30: 35 dari 300) dimigrasi bertahap atas
  persetujuan user; tabel bawaan framework Laravel (`cache`, `sessions`, `jobs`, dst) dikecualikan.
- Nama: English, snake_case.
- Master: `activate` (toggle aktif/nonaktif, boolean 1 kata — **bukan** `is_active`,
  keputusan user 2026-08-26) + `created_at/by` + `updated_at/by`.
- Transaksi: `created_at/by`, `updated_at/by`. Ber-approval: `approved_by/at`.
  Confirm/cancel/close: flag `stat_confirm`/`stat_cancel`/`stat_close` (prefix
  `stat_` = status siklus dokumen, **bukan** `is_confirmed`/`is_cancelled` —
  keputusan user 2026-08-26) + `cancel_reason` (pelaku via `updated_by`).
- State transisi dokumen → tabel `doc_state_log` (polymorphic), BUKAN kolom per-transisi.
- **Inquiry (`iqp_inquiry`) punya tiga tanda tahap** (user 2026-10-02, `docs/globaluserrules.md` A.20): `stat_confirm_sales`
  (dulu `stat_confirm`), `stat_confirm_prodev`, `stat_confirm_procost` + `confirm_*_by/at`. Grid Sales / Pro-Dev / Pro-Cost =
  saringan jenis (NEW / REVISE / RECALC) AND tanda atas tabel yang sama (`App\Services\Sales\InquiryStage`).
- **DEC-FIELDNAME-STATUS (2026-08-26/27):** rename `is_active`→`activate`
  & `is_confirmed`/`is_cancelled`→`stat_confirm`/`stat_cancel` — **SELESAI**
  di semua tabel (tm2mst, tm2acc, tm2sdi, tm2pur, tm2sys **termasuk**
  `sec_user`/`sec_group`/`sec_module`, dicabut dari pengecualian awal atas
  instruksi user 2026-08-27 setelah fase lain terbukti aman). `sec_user.active`
  (kolom legacy terpisah, isinya selalu sama dgn is_active lama) **tidak**
  ikut berubah. Riwayat & catatan per-fase: `docs/fieldrename_state.yaml`.

### Bahasa: DATABASE & FORMULA = ENGLISH — LOCKED (keputusan user 2026-10-01)
> User: *"DATABASE DAN SEMUA FORMULA HARUS ENGLISH. CREATE DICTIONARY TABLE AS TRANSLATOR TO UI UX AND
> PRINT OUT. ANOTHER LANGUAGE ONLY FOR INTERFACE AND PRINT OUT FOR USERS."*
- **English = bahasa dasar (base language) di database**: nama tabel/kolom, kode, status, nilai lookup/master
  (satuan, syarat bayar, kategori, label menu, dst), konstanta, dan **semua formula/rumus** (nama variabel,
  kolom rumus, parameter engine) ditulis English. Angka disimpan mentah (DECIMAL), tanggal ISO — tanpa format.
- **Bahasa lain (Indonesia, Jepang, Mandarin, ...) HANYA untuk tampilan layar dan cetakan**, diterjemahkan saat
  tampil lewat **tabel kamus** `tm2sys.sys_dictionary` (teks English + konteks) + `sys_dictionary_lang`
  (terjemahan per bahasa). Tidak ada terjemahan = tampil English. Tidak ada tabel `_tl` / kolom JSON per bahasa.
- Format angka & nama bulan 3 huruf mengikuti bahasa tampilan (`sys_languages` + kamus konteks `date.month_short`):
  id 1.234,56 · 10-Okt-2026, en 1,234.56 · 10-Oct-2026. Form mengirim angka kanonik `1234.5` ke server.
- Bahasa layar & cetak = pilihan user (pilihan "Bahasa cetak" di menu Print/Email bebas dari bahasa layar);
  tidak ada kolom bahasa di customer/supplier.
- Pengecualian (bukan pelanggaran): teks bebas ketikan user (catatan, alamat) dan nama diri (nama customer,
  nama bahan dari supplier) disimpan apa adanya, tidak diterjemahkan.
- **Cara membangun modul baru (otomatis ID + EN):** tulis semua label ENGLISH. Komponen `x-dl::*` (dan `DbGrid`/`DbLookup`)
  menerjemahkan sendiri; teks lain `t('...')` / `@t('...')` (JS `Devlib.h.t`). Teks baru tercatat otomatis di `sys_dictionary`
  -> terjemahan diisi di **Setup > Translation Dictionary**. Angka/tanggal/jam lewat objek DevLib (`Devlib\Locale`, `NumberValue`,
  `DateValue`, `TimeValue`, `DateTimeValue`, `TextValue`). Dijaga `MultiLanguageRatchetTest` (label Indonesia mentah / format sendiri tidak boleh bertambah).
- **Nilai DATA di prop teks komponen dibungkus `raw()`** (no. dokumen, nama customer / product, angka, tanggal — di judul form,
  kartu, hint, pesan): `:title="'Customer Order · '.raw($o->co_no)" :subtitle="raw($customer.' · '.$status)"`. Labelnya tetap
  English dan tetap diterjemahkan komponen; nilainya tidak diterjemahkan dan **tidak tercatat di kamus** (tanpa `raw()` tiap
  dokumen yang dibuka menambah satu baris `sys_dictionary`). Kalimat yang sudah jadi lewat `t()` + `str_replace` = `raw()` seluruhnya.
- **Bahasa ikut AKUN, pesan diterjemahkan di tempat dibuat (sapuan 2026-10-03):** bahasa tampilan = `sec_user.lang_code`
  (`Devlib\Locale::langUsing`; cookie hanya untuk tamu / login), memilih bendera menyimpan ke akun (`POST /v2/lang`) lalu memuat
  ulang. Pesan flash / error / abort / JSON / pesan service: `t('English.')` atau `tf('Quotation :no created.', ['no' => $x])`,
  dan layar menampilkannya sebagai teks jadi (`raw(session('v2_flash'))`, `raw($errors->first())`). Label menu `sys_app_menu`
  English + kamus (`AppMenu::label`). Tombol / badge / status selalu English, tanpa pasangan kamus. Pesan validasi: `lang/id`.
  Paket (`packages/*`): PHP `\Devlib\Lang::text('English')`, JS `Devlib.h.t('English')`; DevLib sendiri: pasangan di `Devlib\Lang::DICT`.
  Status, sisa keputusan dan cara menambah bahasa ke-3: `docs/md_for_systems/multilanguage_status.md`; alat uji: `tools/multilanguage/`.
- **Translation Dictionary dua panel ala ScriptCase (user 2026-10-03):** pohon kelompok pesan kiri (`sys_dictionary_group`, Setup >
  Message Groups) + grid satu kolom per bahasa aktif. Kelompok diisi otomatis oleh objek DevLib yang menampilkan teks
  (`DevlibComponent::dictionaryGroup()` -> `Lang::hint`), `tf()` = messages, menu = menu; `first_seen` = layar pertama ("Used on").
  **Kamus proyek (`sys_dictionary`) menimpa kata bawaan DevLib** — `Lang::DICT` hanya cadangan, isinya disalin ke kamus (`origin=devlib`).
  Bahasa dinyalakan di **Setup > Languages** (saklar Active; developer boleh ubah detail). Rinci: `multilanguage_status.md` §7.
- Data lama yang masih Indonesia di DB = utang yang dikonversi bertahap (audit & rencana:
  `docs/multilanguage_design.md`); data REAL (mesin/proses/pegawai) & zona beku §6B dikonversi hanya atas izin user.

### Input teks & komponen form — LOCKED (keputusan user 2026-09-21)
**Berlaku terus-menerus, termasuk saat mockup berubah — jangan menunggu
diperintah ulang.** Detail + alasan: `docs/FORM_UI_STANDARD.md` §1b.
- Setiap input teks string pakai `x-form.input` (type=text) / `x-form.textarea`
  yang SUDAH membawa badge huruf **aa → Aa → AA** (bantu user yang mengetik di
  HP/tablet). Badge mati sendiri utk readonly/disabled/type non-teks.
  `:case-toggle="false"` hanya utk kotak CARI atau alasan khusus yang ditulis.
- **Lookup dulu**: nilai yang sudah ada di master WAJIB dipilih lewat
  `x-form.searchable-select`/`select`/picker, bukan diketik bebas.
- Warna field lewat token `--color-field-*`/`bg-accent-soft` (app.css `@theme`,
  dicampur dari `--color-accent`), JANGAN hex literal — supaya semua field ikut
  corak tema `sys_theme` yang dipilih user.
- Sama berlakunya utk SEMUA komponen `x-form.*` lain di `_manifest.yaml`: ada
  komponennya = pakai komponennya, HTML mentah dianggap pelanggaran.
### Tombol aksi (UI) — LOCKED (keputusan user 2026-09-10)
Label tombol aksi umum (Back/Save/Cancel/Close, dst) **WAJIB satu kata yang
sama persis di semua layar** — standardisasi memudahkan pengguna, jangan bikin
UI tidak seragam. **Berlaku default di SELURUH aplikasi termasuk panel
Filament `/admin`** (label native Filament seperti Save/Create/Edit/Delete
di-override ikut standar ini, bukan dibiarkan default bawaan framework).
**Satu-satunya pengecualian yang SAH**: user secara eksplisit minta layout/
label khusus untuk 1 layar tertentu — bukan variasi bebas per developer.
- **UPDATE user 2026-09-30 (form dokumen v2 / DevLib `x-dl::form panel="top"`): istilah SELALU English**,
  tidak ikut bahasa — tombol **New, Save, Undo, Delete, Confirm, Cancel, Duplicate, Back to Draft, Back**;
  status **Draft, Confirmed, Cancelled, Closed**. Undo = buang perubahan belum di-Save; Cancel = batalkan
  dokumen Confirmed. Aturan ID/EN di bawah ini hanya untuk layar lama yang belum pindah.
- Kata baku per aksi, ikut `lang_code` user ([[project_i18n_shell_framework]]):
  ID → Simpan/Batal/Tutup, EN → Save/Cancel/Close.
- **Back** terkecuali dari toggle bahasa — **selalu "Back"** (Inggris) di
  kedua mode, ini LOCKED lebih dulu di `docs/FORM_UI_STANDARD.md` §1
  (2026-08-24, sebelum keputusan i18n di atas) dan tidak berubah.
- **Pengecualian yang SAH (bukan pelanggaran)**: tombol "+ Tambah [objek]"
  untuk nambah baris/item DI DALAM form (mis. "+ Tambah Cabang", "+ Tambah
  Proses") — beda aksi dari "+ New Record" level grid (LOCKED di
  `griduistandard.md` §6), boleh sebut objeknya karena memang aksi yg
  berbeda, bukan variasi kata untuk aksi yang sama.
- Detail spesifikasi lengkap & daftar file yang masih menyimpang (perlu
  perbaikan bertahap): `docs/FORM_UI_STANDARD.md` §1a, `docs/backlog01_uiux_component.md` §10.

---

## 4. PRINSIP DESAIN (LOCKED)

- **Snapshot/beku:** transaksi membekukan nilai dari master saat momen penting.
  Inquiry deep-copy struktur produk (`prod_*` → `iqp_part/_material/_process`).
  Costing membekukan rate (`rate_snapshot`). Master berubah → transaksi lama TIDAK berubah.
- **Rate fluktuatif** (coating/film/sheet): pola versioned + `is_active`; **1 baris
  `is_active=1` per jenis** = harga berlaku. `effective_date` untuk riwayat.
- **Engine pattern:** "engine suggests, officer confirms" (scheduling, imposition, routing).
- **[VITAL] Alur ProDev/ProCost, 3 engine, Quotation → CO** — teks lengkap di `docs/claude_vital_flows.md`. **WAJIB dibaca sebelum menyentuh Inquiry, ProDev, ProCost, Quotation, Customer Order, CPDEL.** Inti yang tidak boleh terlewat:
  - ProDev & ProCost punya 2 mode input (integrasi / manual) yang HARUS menghasilkan HPP sama lewat `CostingEngine` yang sama — jangan dicampur, jangan tanya ulang.
  - Quotation WAJIB integrated (dari inquiry costing PUBLISHED); Customer Order boleh direct/INSTANT (user 2026-10-01). 1 CO = 1 Quotation untuk mode INTEGRATED; `due_date` wajib terisi sebelum Confirm CO.
  - Engine inqprod (dieline TMDieline) beku; ProDev = `ImpositionEngine`; ProCost = `CostingEngine`.
- **Costing rahasia (Sisi A):** HPP/margin hanya Costing+Direksi. Publish (approve
  Direksi) = deklasifikasi harga ke Sisi B (Sales).
- **Due date** = 14 hari kerja dari **approval prove FISIK** (bukan PO/mockup).
- **Diskon** jarang (occasional): item di `iqp_tier`, dokumen kolom-kembar header↔co_order, NULL default.

---

## 5. STATUS TIER 3A — dipindah ke `docs/claude_md_archive_2026_10_07.md` (sudah usang)

## 6. DOKUMEN ACUAN — daftar lengkap di `docs/claude_docs_reference.md`

Baca file itu (atau `docs/md_for_systems/uistandardindex.md`) sebelum menyentuh area berikut; jangan menebak:
- Alur produk / Inquiry→Master Product: `flow_product_master.md`. Material Category / kode item / PR-PO: `matcategory_delta.md` §5 (LOCKED, 7x disampaikan).
- Gudang, bin, transfer, label: `warehouselayout_tier1.md`. Grid: `griduistandard.md`. Form/komponen: `componentstandard.md`, `FORM_UI_STANDARD.md`.
- Menu: `sidebarmenustandard.md`. Gantt / Kanban / Nota cetak / spreadsheet grid: `ganttboardstandard.md`, `kanbanboardstandard.md`, `printnotastandard.md`, `spreadsheetgridstandard.md`.
- Generate WO / Scheduling: `production_scheduling_wo_generation_delta.md`. QC user: `docs/qc_feedback.md`. Lampiran form/Excel nyata: `docs/attachments_index.md`.
- Konvensi nama file docs: ikut `sys_app_menu.doc_slug` / `sys_doc_reference` (jangan bikin prefix sendiri).

## 6B. ZONA BEKU & URUTAN KERJA (LOCKED — instruksi user 2026-09-22)

### JANGAN DISENTUH tanpa permintaan eksplisit user
**Inquiry Product, ProDev, dan ProCost — beku total.** Bukan hanya kodenya:
**data dummy DAN tampilan UI/UX-nya juga**. Tiga modul ini sudah dianggap
jadi oleh user.

> User 2026-09-22: *"pastikan prodev dan inquiry prod, prodcost tidak diubah
> baik dari segi dummy data dan interface UI UX nya sampai saya sendiri yang
> memintanya dengan kesadaran penuh."*

**Diperjelas user 2026-09-23 (menggantikan tafsir kaku di bawah):** zona beku
= **tidak boleh mengubah ESENSI dan FLOW — flow data maupun flow dokumen —
tanpa izin user**. Bukan berarti modul ini selalu dilewati. Sebelum mengubah
apa pun di sini, **tanyakan dengan jelas apa yang akan diubah** (file, elemen,
sebelum→sesudah) dan tunggu persetujuan. Sudah disetujui tanpa perlu tanya:
mengubah teks UI bahasa Indonesia → English. Tidak pernah boleh tanpa izin:
apa pun yang mengubah flow/esensi.

Tafsir awal (2026-09-22): jangan rapikan, jangan seragamkan ke standar grid/form terbaru,
jangan tambah/hapus/seed dummy, jangan "sekalian perbaiki" apa pun di sana —
walaupun kelihatan menyimpang dari standar, dan walaupun sedang mengerjakan
tugas lain yang kebetulan menyenggol file-nya. Kalau sebuah pekerjaan
memaksa menyentuh ketiganya, **berhenti dan tanya dulu**.

### Urutan kerja yang dikunci user
```
Inquiry Product -> ProDev -> ProCost   (SELESAI, beku)
   -> Master Mesin & Master Proses     (dikerjakan 2026-09-22/23)
      -> Customer Order                <- SEKARANG (mulai 2026-09-26), dibangun sebagai layar v2 di Fase 2
```
Customer Order sudah punya 2 mode di v1 (INTEGRATED dari Quotation / INSTANT manual + Master Product,
commit 59fab26). Layar v2 = alur & field yang sama dengan v1, ditulis ulang dengan objek DevLib, membaca
database dummy lewat model/service yang ada (bukan data contoh JSON). Konfirmasi user journey dulu.

### Master Mesin & Master Proses = DATA REAL
Isi `prod_machine` / `prod_process` / `prod_process_machine` adalah **data
nyata pabrik**, bukan dummy. Banyak mesin sudah terdaftar tapi **speknya
belum ada** (kolom ukuran lembar, kecepatan, operator, model masih kosong).

- Jangan mengarang spek. Riset ke situs pabrikan atau tanya user.
  Lihat `feedback_research_master_data_online` & `feedback_ask_before_inserting_data`.
- Jangan tandai `data_origin='DUMMY'`.
- Setiap mesin wajib punya proses (`feedback_every_machine_must_have_process`).

### MRP & MPS — sengaja ditelantarkan
Engine MRP/MPS/Kanban buatan 2026-08-30 **tidak dipakai** karena user tidak
menyukai hasilnya. Kodenya diarsipkan di tag `arsip/mrp-mps-2026-08-30`
(bukan branch). Tabel `tm2prd.prd_mrp_run` & `prd_mrp_requirement` terlanjur
ada di DB tanpa kode — biarkan, jangan dipakai dan jangan di-drop.
Rencana user: dirancang ulang nanti, **meniru model UI/UX ProDev & Inquiry**.

### Git: satu jalur, tanpa cabang
Lihat `docs/globalgitbranch.md`. Tidak boleh ada branch baru maupun worktree.

---

## 7. WORKFLOW CODING (aturan kerja Claude Code)

1. **Think before coding.** Jangan berasumsi — kalau ragu atau ada >1 interpretasi
   yang masuk akal, sampaikan opsi/tradeoff-nya dulu di jawaban, jangan pilih diam-
   diam. Kalau ada pendekatan yang jauh lebih sederhana dari yang diminta, sampaikan
   juga sebelum mulai kerja. **Kapan boleh langsung jalan vs harus berhenti tanya**
   (keputusan user 2026-08-31, menimbang terhadap Auto Mode yang defaultnya condong
   jalan terus): kalau ambiguitasnya wajar & tidak berbahaya (reversibel, dampak
   kecil/lokal) — jalan saja dengan pilihan terbaik, sebut opsi lain secara singkat
   di jawaban. Kalau ambiguitasnya menyangkut hal berbahaya/berisiko (hard-to-revert,
   arsitektural, lintas-modul) atau instruksinya sangat membingungkan sampai tebakan
   terbaik pun kemungkinan salah arah — **berhenti dan tanya dulu** (AskUserQuestion)
   sebelum lanjut, jangan menebak.
2. **Simplicity first.** Tulis kode minimal yang menyelesaikan masalah — jangan
   bikin fitur spekulatif, abstraksi sekali pakai, atau konfigurasi berlebihan.
   Hindari *error handling* untuk skenario yang tidak mungkin terjadi. Kalau 200
   baris bisa ditulis bersih jadi 50 baris, tulis ulang.
3. **Baca dokumen dulu.** Sebelum bikin model/migration, cek DDL di `/docs` — jangan tebak kolom.
4. **Model Eloquent** wajib set `$connection` (mst/sdi/dst), `$table`, `$primaryKey`
   (karena PK bukan `id` default).
5. **Migration** cerminkan DDL 3A yang sudah ada (jangan bikin skema baru yang beda).
   FK lintas-DB: JANGAN dibuat. FK dalam-DB: boleh.
6. **Livewire component** ikut mockup HTML sebagai acuan tampilan & field.
7. **Iterasi kecil** — 1 layar/fitur per waktu (mulai `inquiry_new`), bukan semua sekaligus.
8. **Validasi** ganti FK lintas-DB yang hilang (rule `exists:conn.table,col`).
9. Bila ada keputusan desain baru, konfirmasi ke user dulu — jangan putuskan sendiri hal arsitektural.
10. **WAJIB `npm run build` setelah ubah class Tailwind di Blade/Livewire.** Project
   ini pakai Vite production build (`public/build/`), **bukan** `npm run dev` yang
   hot-reload — jadi class Tailwind baru (`w-64`, dsb.) di file yang baru diedit
   **TIDAK otomatis muncul di CSS terkompilasi**. Insiden 2026-08-21: 8 grid Master
   diedit toolbar-nya tapi CSS terakhir di-build sehari sebelumnya → elemen
   tampil tanpa styling sama sekali (search box selebar layar, tombol
   "+ New Record" tak kelihatan). Jangan cuma `php -l` (itu cuma cek syntax PHP,
   tidak cek CSS). **Checklist wajib tiap sesi yang mengubah tampilan (Blade/Volt
   dengan class Tailwind baru):** (a) selesai edit → (b) `npm run build` → (c) baru
   laporkan selesai / minta user cek browser. Kalau ragu apakah class-nya baru,
   build saja — build ini murah (~2 detik) dan tidak merusak apa pun.
11. **WAJIB commit + push tiap selesai kerja** (keputusan user 2026-08-30,
   dipertegas 2026-08-31) — bukan cuma saat diminta. Setiap sesi/potongan
   pekerjaan yang selesai (1 fitur/perbaikan/checklist item) wajib: (a)
   `git add` file relevan (bukan `-A` membabi buta — cek `git status` dulu,
   jangan ikutkan file mencurigakan/kredensial), (b) `git commit` dengan
   pesan jelas per potongan kerja (bukan 1 commit raksasa gado-gado — ini
   yang jadi titik rollback kalau ada yang salah), (c) `git push` ke GitHub
   (`origin`) sebagai backup off-machine. Tujuan: histori commit granular =
   riwayat versi yang bisa di-rollback (`git revert`/`git reset` ke commit
   sebelum yang bermasalah) kalau ada pekerjaan yang keliru. Tetap ikuti
   Git Safety Protocol (jangan force-push/reset --hard tanpa izin eksplisit,
   review diff sebelum commit).

12. **SEMUA DATA WAJIB PUNYA INPUTAN** (keputusan user 2026-09-23, tegas:
   *"setiap ada hal yang tidak bisa di input oleh user, kamu HARUS membuatnya,
   SEMUA HAL DATA HARUS ADA INPUTAN nya, INCLUDE CONSTANTS"*). Setiap nilai yang
   dibaca aplikasi (konstanta, baris master, identitas perusahaan, parameter)
   harus bisa dibuat/diubah user berwenang lewat layar — bukan cuma lewat
   migration/tinker. Kalau belum ada layarnya, BUATKAN sebagai bagian pekerjaan
   yang sama (bukan item backlog "menunggu data user"); pastikan yang diinput
   benar-benar terpakai/tercetak. Rumah yang ada: Settings → Constants (semua
   `sys_constant`), Settings → Identifikasi (identitas aplikasi + perusahaan).

---

## 8. MULAI DARI MANA — lihat arsip di atas (usang; kini: layar v2 DevLib, Customer Order, lihat §6B).
