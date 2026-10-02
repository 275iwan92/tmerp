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
- **Skill**: Laravel Boost (`livewire-development`, `tailwindcss-development`, `laravel-best-practices`, `testing-best-practices`) generik & ditimpa tiap install; skill proyek bersumber di `.ai/skills/` (edit di sana, lalu `php artisan boost:install --skills --mcp -n` — JANGAN tambah `--guidelines`, itu menimpa file ini). Bila bertentangan, `tmerp-stack-rules` menang (mis. file Livewire TANPA prefix `⚡`).

- **Nama koneksi Laravel = nama database**: `tm2mst`, `tm2sdi`, `tm2pur`, `tm2inv`, `tm2prd`, `tm2acc`, `tm2hrm`, `tm2hrr`, `tm2pyr`, `tm2sys`, `tm2old`, `tm2crp` (`config/database.php`). Tabel §2 menulis `mst`/`sdi` sebagai singkatan; di kode (`$connection`, `DB::connection(...)`) pakai nama `tm2*`. Default connection = `tm2sys`.
### Fase & situs (keputusan user 2026-09-24/25 — baca ini dulu)
3 fase, satu kode. Pegangan lengkap untuk user: `docs/pegangan_fase_tmerp.html`.
| Fase | Situs | Isi | Database |
|---|---|---|---|
| 1 DevLib | devlib.test (folder `devlib/`, library `packages/devlib`) | objek komponen `x-dl::*` | tanpa DB bisnis |
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
- **Grid**: trait `app/Livewire/Concerns/HasCustomizableGrid` (kontrak `gridKey/gridColumnDefs/gridDefaultColumns/gridBaseQuery/columnValue`); komponen reusable di `resources/views/components/{grid,form,print,gantt,kanban,...}`. Teks UI lewat `App\Support\Localization\Translator::t(key, fallback)`.
- Scheduler (`routes/console.php`): `recruitment:sync-portal` tiap 2 menit, `prodev:usage-rates` bulanan.
- `DataOriginScope` (web-only) menyembunyikan data DUMMY/LEGACY sesuai setting admin; console/tinker/test melihat semua.

### Dokumen yang sering basi / jangan dipercaya mentah
- `START_HERE_onboarding.md` dan `tasks.md` (root) = artefak onboarding awal (mockup Tier 4, "10 DB", task T01–T92), **sudah usang → diarsipkan 2026-10-02** ke `C:\Users\iwanp\tmerp_unused\` (lihat MANIFEST di sana). Pintu masuk docs yang benar: `docs/index_master.md`, `docs/globalstarthere.md`, `docs/md_for_systems/uistandardindex.md`.
- **Letak dokumen sekarang:** standar & spec Tier yang disebut di §6 tanpa folder (`griduistandard.md`, `FORM_UI_STANDARD.md`, `qc_feedback.md`, `*_tier*.md`, dst) ada di `docs/md_for_systems/`; dokumen per-layar `screen_*.md` ada di `docs/md_output/screens/`.
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
| pyr | tm2pyr | Payroll system with Encrypted data (**requirement, BELUM diimplementasikan** — lihat catatan di bawah tabel) |
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

**Payroll encryption (keputusan user 2026-08-30, requirement — BELUM ada
implementasi teknis sesi manapun):** data gaji & histori gaji (tm2pyr) WAJIB
terenkripsi at-rest. Belum ada keputusan algoritma/key management/model mana
saja yang kena — jangan asumsikan sudah terpasang, dan jangan mulai
implementasi sendiri tanpa konfirmasi desain (mana kolom, cara rotate key,
dampak ke query/laporan yang butuh baca nilai asli).

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
- **[VITAL] ProDev & ProCost — 2 mode independen, JANGAN dicampur** (dikunci user
  2026-09-03, jangan tanya ulang ke user soal ini):
  - **Inquiry Product punya 3 doc-type** (`inquiryproduct_flow.md` §6, locked Jo):
    **BARU** (dari nol, Master Product belum ada), **REVISI** (reorder, spec
    BERUBAH), **REKALKULASI** (reorder, spec SAMA, cuma harga bahan naik/turun).
    `iqp_costing.calc_type` di kode SAAT INI hardcode `'NEW'` (kolom Costing
    ini spesifik belum ikut bercabang). **Koreksi 2026-09-04** (klaim lama di
    sini sempat bilang "3 mode cuma konsep, belum ada percabangan behavior
    nyata" — SALAH, sudah diverifikasi ke kode): REVISI & REKALKULASI adalah
    **layar Livewire terpisah** (`inquiry/revise.blade.php` route
    `inquiry.revise`, `inquiry/recalc.blade.php` route `inquiry.recalc`),
    BUKAN mode di `form.blade.php`. REVISI punya scope selector nyata
    (MATERIAL/DIMENSION/ARTWORK/COLOR/PROCESS, `inquiryproduct_delta_revise.md`)
    yang mengunci/membuka blok edit; REKALKULASI 100% read-only reuse kecuali
    Qty Order. Detail UI/UX & status implementasi lengkap:
    `docs/inquiry_product_costing/inquiryproduct_rules.md`.
  - **ProDev Mode #1 (integrasi)** — utk Inquiry BARU: ProDev **HARUS** ambil/
    berawal dari `iqp_part` (seed `prod_part` baru dari spec kasar Inquiry).
    **STATUS 2026-09-03: LINK INI BELUM ADA** — 0 jalur kode bikin `prod_part`
    baru, 0 link dari layar Inquiry mana pun ke ProDev. Ini gap nyata yang
    perlu diperbaiki, bukan sudah selesai.
  - **ProDev Mode #2 (manual/standalone)** — ProDev bekerja SENDIRI, **TIDAK
    melihat Inquiry Product sama sekali** — mencerminkan proses manual riil
    yang SEKARANG masih pakai Excel + Adobe Illustrator (didigitalkan apa
    adanya). Ini BUKAN gap, BUKAN sesuatu yang perlu "diperbaiki" jadi kayak
    Mode #1 — 2 pintu masuk yang SENGAJA terpisah & sah keduanya.
  - **ProCost Mode #1 (Ambil Data)** — tarik dari `prod_layout_group` yang
    CONFIRMED di ProDev. **SUDAH ADA & jalan** (`costing.blade.php::
    pickLayoutGroup()`), bukan sesuatu yang baru dibangun.
  - **ProCost Mode #2 (manual/standalone)** — officer input SEMUA parameter
    sendiri (panjang, lebar, running meter, dst), dibantu **tabel NILAI
    DEFAULT** (referensi nilai awal — perlu diklarifikasi apakah maksudnya
    `sys_constant` category=PRODUCTION/group=COSTING yang sudah ada, atau
    tabel lain yang belum dibangun — JANGAN asumsi, cek/tanya dulu kalau
    relevan). Ini toggle `data_source=MANUAL` yang sudah ada di Costing Hub.
  - **Syarat mutlak**: HPP hasil Mode #1 dan Mode #2 **HARUS SAMA** utk
    geometri yang setara — keduanya cuma 2 JALUR INPUT DATA ke `CostingEngine`
    yang SAMA, bukan 2 logika harga berbeda.
  - Detail lengkap & riwayat investigasi: memory
    `project_prodev_procost_2mode_architecture.md` +
    `feedback_prodev_must_pull_from_inquiry_new.md`.
- **[VITAL] 3 ENGINE inti — InqProd, ProDev, ProCost — masing-masing GOAL-nya**
  (dikunci user 2026-09-03, pelengkap poin di atas):
  - **InqProd Engine** (`inquiry/form.blade.php`) — GOAL: (1) picker visual
    tipe produk + live dieline preview + dimensi gampang di-adjust + preset
    "Ukuran Pasaran" — **SUDAH ADA & DIVERIFIKASI BAGUS, JANGAN DIUBAH**
    (verdict eksplisit user), improvisasi di atasnya SANGAT boleh. **Koreksi
    2026-09-04**: yang dikunci itu KEMAMPUAN RENDER dieline (TMDieline DSL,
    `resources/js/tmdieline.js` — sungguhan tidak disentuh), BUKAN lokasi UI
    pemilihan Tipe Produk. Tipe Produk sudah dipindah dari per-Part ke level
    Inquiry (1 tipe berlaku semua Part, dipilih di Header dekat "Kategori
    Produk" — sales-facing shortcut baru), dan tiap Part sekarang gerbang
    dulu di "Peran Part" (Body/Lid/Sleeve/dst — istilah dari riset Esko
    ArtiosCAD/glosarium packaging) sebelum Dimensi & Preview Dieline live
    aktif; tipe non-multi-part auto-role "MonoForm" (istilah dari "Mono
    Carton"), dieline langsung aktif. Galeri visual popup (gambar dieline ala
    Pacdora) sementara dilepas dari scope saat pemindahan ini, belum
    dibangun ulang di lokasi baru. Detail lengkap: memory
    `project_prodtype_multipart_category_system.md`. (2)
    **saran-saja** (bukan blokir/auto-koreksi, pola sama "engine suggests,
    officer confirms") kalau dimensi terlalu sempit/tidak simetris — BELUM
    ADA (0 logic ditemukan, target scope baru); (3) **mockup generator 3D**
    ala **Pacdora** (dikonfirmasi user via screenshot pacdora.com/mockups,
    "ENGINE nya adalah mockup generator") — BUKAN simulasi fold-fisika dari
    nol: pilih template box per Tipe Produk (sudah ada dropdown-nya) → upload
    desain 2D/JPEG → di-composite ke template 3D siap-pakai → render foto
    produk realistis. BELUM ADA (0 upload capability di layar ini), butuh
    keputusan desain terpisah (bikin sendiri library template shell vs pakai
    API mockup pihak ke-3) sebelum mulai bangun.
  - **ProDev Engine** (`ImpositionEngine.php`) — GOAL: dari geometri 1 part,
    cari layout produksi FISIK paling hemat bahan+plate+tooling (plano,
    UP/orientasi, interlock, gabungan antar-part, meter-lari-pisau). Output
    `prod_layout_group`. SUDAH LENGKAP (Tingkat 1/2 + Tool #2/#4/#10,
    2026-09-03).
  - **ProCost Engine** (`CostingEngine.php`) — GOAL: dari parameter produksi
    (sumber Mode #1 ATAU #2, HARUS hasil sama) hitung HPP akurat + ladder
    harga per qty-tier (toleransi/margin/pembulatan/PPN). SUDAH LENGKAP,
    4 tipe part (DC/SHEET_DIGITAL/KRAFT/DC+SHEET).
  - Detail lengkap: memory `project_inqprod_engine_goal.md`.
- **[VITAL] Alur Quotation & Customer Order — dari InqProd berharga sampai CO**
  (dikunci user 2026-09-03, jangan tanya ulang ke user soal ini):
  - **Prasyarat:** InqProd sudah melewati ProDev+ProCost (sudah ada harga/HPP)
    dan **disetujui Sales** (bukan Direksi lagi — publish costing = beda
    tahap, lihat §4 "Costing rahasia" di bawah).
  - **Langkah 1 — SALES QUOTATION**: Sales pilih-pilih InqProd yang mana saja
    masuk quotation (via `availableInquiries` — inquiry customer sama, costing
    `published_flag=true`, belum ditarik ke quotation ini); sisanya (header,
    harga, qty, diskon per baris) **AUTO** dari snapshot InqProd tapi **BOLEH
    diedit** sebelum publish. **SUDAH ADA & jalan**: `quotation/entry.blade.php`
    + `App\Services\Sales\QuotationBuilder`.
  - **Langkah 2 — Customer OK → CUSTOMER ORDER Mode #1 (auto dari Quotation)**:
    1 CO = 1 Quotation (locked 2026-08-20, FK `co_order.quotation_id` NOT
    NULL + UNIQUE ke `quo_quotation`). Item CO = **snapshot** item Quotation
    saat CO lahir (bukan live-linked — prinsip snapshot §4). **SUDAH ADA &
    jalan**: `App\Services\Sales\CoOrderBuilder::findOrCreateFromQuotation()`
    dipanggil begitu Quotation berstatus `PUBLISHED`.
  - **Customer Order Mode #2 (manual/standalone)** — pembuatan CO **tanpa**
    lewat Quotation, meniru alur manual versi sebelumnya (ScriptCase V1), TAPI
    dengan syarat **Master Product (`prod_product`) harus dibuat dulu**
    sebelum baris CO bisa diisi. **SUDAH ADA** (2026-09-21, commit 59fab26):
    `co_order.entry_mode` INTEGRATED / INSTANT, `quotation_id` nullable,
    `CoOrderBuilder::createInstant()` + picker Master Product per baris.
  - **ATURAN LOCKED user 2026-10-01 — Quotation WAJIB integrated, CO boleh direct:**
    Inquiry → Quotation **harus selalu integrated** (baris quotation dari
    inquiry yang costing-nya PUBLISHED; tidak ada quotation yang melompati
    ProDev/ProCost, termasuk data dummy/seeder). Customer Order **boleh direct
    input tanpa Quotation** (INSTANT) karena (1) kompatibel dengan sistem lama
    (ScriptCase V1) dan (2) realita pabrik: Direksi bisa minta percepatan yang
    langsung dikerjakan Produksi — jalur tercepatnya input langsung di CO.
    Jangan hapus/persulit jalur INSTANT, dan jangan buat jalur Quotation direct.
  - **Pola sama seperti ProDev/ProCost**: 2 mode = 2 JALUR INPUT, bukan 2
    alur bisnis berbeda — tujuan akhir (dokumen CO valid, siap CPDEL) harus
    sama, cuma titik masuknya beda (dari Quotation vs manual+Master Product).
  - **Langkah 3 — CO CONFIRMED → CUSTOMER PLAN DELIVERY (CPDEL)**: nama baru
    utk "Plan Delivery" (rename 2026-09-03, & dipindah dari grup menu
    "Delivery" ke grup **"Sales Order"**, satu grup dgn Customer Order —
    lihat `sidebarmenustandard.md`/migration `rename_cpdel_and_regroup_
    delivery_menu`). **ENGINE** (baru 2026-09-03): begitu CO `confirmCo()`,
    tiap `co_order_item` PRODUCT yang belum punya jadwal manual di-auto-buat
    1 baris `co_order_delivery` (qty penuh @ `due_date` item) — item yang
    sudah diisi manual via tab Delivery CO Entry TIDAK ditimpa. **`due_date`
    WAJIB terisi di semua baris PRODUCT sebelum CO bisa di-Confirm** (guard
    tambahan 2026-09-03, `itemsMissingDueDate` — tombol Confirm CO disabled +
    warning kalau ada yang kosong) — jadi **TIDAK ADA lagi CO CONFIRMED
    dengan due_date kosong**. **SUDAH ADA & jalan**:
    `co/entry.blade.php::confirmCo()`. Grid CPDEL
    (`co/delivery-plan.blade.php`) sendiri **sudah live sejak 2026-08-20**
    dan punya 3 aksi per baris: **Split** (pecah qty+tanggal), **Gabung/Join**
    (baris item+CO sama → baris tanggal-paling-awal), atau **biarkan** hasil
    default engine — cocok mockup `co_delivery_plan_mockup.html`.
  - **Menu grup "Delivery"** (setelah CPDEL pindah ke "Sales Order") WAJIB
    berisi **Delivery Order** + **Delivery Notes** (2 item terpisah, keputusan
    user 2026-09-03) — meng-override keputusan lama 2026-08-28 yang sengaja
    TIDAK memberi Surat Jalan menu sendiri. Delivery Notes utk sekarang reuse
    grid `dispatch.do.index` yang sama (belum ada layar terpisah).
  - Detail & riwayat: memory `project_quotation_co_flow.md`,
    `project_cpdel_engine.md`; dokumen gabungan lengkap
    `docs/SALES_QUO_ORDER, CPDEL.md`.
- **Costing rahasia (Sisi A):** HPP/margin hanya Costing+Direksi. Publish (approve
  Direksi) = deklasifikasi harga ke Sisi B (Sales).
- **Due date** = 14 hari kerja dari **approval prove FISIK** (bukan PO/mockup).
- **Diskon** jarang (occasional): item di `iqp_tier`, dokumen kolom-kembar header↔co_order, NULL default.

---

## 5. STATUS TIER 3A (per sesi ini)

**SELESAI & terpasang di MySQL lokal:**
- tm2mst: geo_* + fin_currency + cust_customer + prod_* (product/part/material/process/type/category) + mat_raw + uom_* (16 tabel).
- tm2sdi: `iqp_*` 12 tabel (inquiry/type/tier/part/part_material/part_process/tooling/external_cost/prove/prove_log/costing/costing_detail).
- FK internal (dalam-DB) tegak. FK lintas-DB = logis (Laravel).

**GAP tertunda (tambal via ALTER, non-blocking):**
1. Estimasi kecepatan F15 (est_days/speed/override/status) — ada di mockup, belum di DDL.
2. ~~Rate costing versioned (coating/film/sheet)~~ — **CLOSED**: `mat_coating_rate`/`mat_sheet_rate`/`mat_grammage` + `sys_constant` sudah ada & dipakai `CostingEngine.php` (Costing Hub live di `inquiry.costing`). `product_costing.html` yang tadinya dikira gap ini ternyata duplikat Costing Hub — dibatalkan.
3. Selaras diskon qty (mockup teks vs DDL angka).

---

## 6. DOKUMEN ACUAN (di /docs)

> **Konvensi nama file (DEC-NAMING 2026-08-26):** nama file `docs/*.md`/`*.yaml` mengikuti
> `sys_app_menu.label` (menu live di `tm2sys`) — lowercase, tanpa spasi, digabung jadi satu
> kata, + suffix jenis dokumen (`_tier1..4`, `_rules`, `_state`, dst). `sys_app_menu.doc_slug`
> = sumber kebenaran basis nama per submodule; dokumen yang bukan milik 1 menu item (aturan
> global/standar/engine/referensi) terdaftar di tabel `sys_doc_reference` (bukan file .md,
> jangan ditulis manual — cek `SELECT * FROM tm2sys.sys_doc_reference`). Migration:
> `2026_08_26_000001_add_doc_slug_and_doc_reference.php`. **Saat bikin dokumen baru:** cek dulu
> `doc_slug` submodule terkait (atau tambahkan row baru di `sys_app_menu`/`sys_doc_reference`
> kalau belum ada), jangan bikin prefix sendiri.

> **Konvensi nama file `docs/screens/*.md` per-engine (DEC-NAMING-SCREEN 2026-09-05,
> fokus saat ini: inqprod/prodev/procost, berlaku juga utk engine lain nanti):**
> pola `screen_<engine>_<variant?>_<screentype?>.md`.
> - `<engine>` = kode pendek engine sesuai CLAUDE.md §4 (`inqprod`, `prodev`,
>   `procost`, dst) — **bukan** nama route/folder Livewire lama (`inquiry`,
>   `production_prodev`).
> - `<variant>` = doc-type/mode kalau 1 engine punya >1 layar entry yang
>   mengerjakan data beda (inqprod: `new`/`revise`/`recalc`). Tidak ada
>   variant → field ini dilewati.
> - `<screentype>` = **HANYA** ditambahkan kalau utk 1 unit `<engine>_<variant>`
>   yang sama ada LEBIH DARI 1 jenis layar (form/detail vs grid/worklist):
>   `_form` (entry/detail/workbench, dulu tanpa suffix), `_grid` (worklist,
>   dulu `_list`), `_print` (cetak/nota, penamaan lama tetap dipakai). Kalau
>   cuma ada 1 jenis layar utk unit itu (mis. 1 form tanpa grid terpisah),
>   **tidak perlu suffix**.
> - Contoh: `screen_inqprod_new.md`/`_revise.md`/`_recalc.md` (form-only,
>   masing-masing 3 doc-type), `screen_inqprod_grid.md` (worklist GABUNGAN
>   ketiganya — tidak ada `<variant>` karena bukan pasangan 1-ke-1 dgn salah
>   satu doc-type, jadi ditulis `<engine>_grid` langsung), `screen_prodev_form.md`
>   + `screen_prodev_grid.md` + `screen_prodev_print.md`, `screen_procost_form.md`
>   + `screen_procost_grid.md`.
> - **Print/nota lintas-variant** (bukan pasangan 1-ke-1 dgn 1 form/grid,
>   mis. nota cetak yang sama dipakai semua doc-type inqprod) pakai prefix
>   **`printout_`** (bukan `screen_`) + `<engine>` + nama dokumennya:
>   `printout_inqprod_nota.md` (2026-09-05, gantinya `screen_inquiry_nota.md`).
>   `_print` (di bawah prefix `screen_`) tetap dipakai kalau cetakannya
>   memang pasangan 1-ke-1 dgn 1 form/grid tertentu (`screen_prodev_print.md`).
> - **Resolved 2026-09-05:** `screen_inquiry_hub.md` → `screen_inqprod_grid.md`,
>   `screen_inquiry_nota.md` → `printout_inqprod_nota.md`. `screen_inquiry_imposition.md`
>   + `_list.md` (route `inquiry.imposition`) dikonfirmasi user **LEGACY/TIDAK
>   AKTIF** — digantikan `screen_prodev_form.md`/`_grid.md` (route
>   `production.prodev`). Kedua file lama **diarsipkan 2026-10-02** ke
>   `C:\Users\iwanp\tmerp_unused\docs\md_output\screens\` — jangan bangun
>   fitur baru di layar itu.

> **`uistandardindex.md` — peta semua standar UI/UX** (grid/form/nota/gantt/
> kanban/admin dashboard Filament) + kapan masing-masing wajib dibaca. **Cek
> file ini dulu** kalau bingung standar mana yang relevan untuk 1 layar baru,
> daripada baca satu-satu daftar di bawah.

- `flow_product_master.md` — **PEDOMAN ALUR wajib dibaca sebelum menyentuh
  Inquiry/ProDev/ProCost/Quotation/CO/Master Product/Revisi/Rekalkulasi/ACC Digital**
  (user 2026-09-29): Quotation WIN → Master Product (multi-part, rakitan bertingkat
  Single/Double Join), Revisi = product turunan, Rekalkulasi = versi harga bertanggal,
  ACC Digital wajib, due date CO +14 hari kerja adjustable. Tampil di Menu v2 →
  Sales → Alur → Pedoman (`/v2/pedoman/product-master`) — update dokumen ini tiap
  ada keputusan alur baru.
- `componentstandard.md` — **wajib dibaca sebelum menulis markup layar apa
  pun** (keputusan user 2026-09-23): komponen UI berlapis & object oriented —
  token `@theme` → primitive `app/View/Components/Ui/*` (`x-ui.button`,
  `badge`, `card`, `tabs`, `table`, `modal`, ...; turunan `UiComponent`,
  palet `Tone`) → komposit `x-form/x-grid` → layar. Ubah induk = semua ikut.
  Dilarang `<button class>`/hex/`fixed inset-0`/`<table class>` mentah di
  halaman baru; `UiRatchetTest` menjaga angkanya tidak naik. Migrasi layar lama
  (termasuk zona beku §6B) = kulit saja, esensi wajib dibuktikan sama (§6 dok).
- `globalarchitecture_tier0.md` — arsitektur global, keputusan LOCKED.
- `inquiryproduct_tier1.md` — alur & proses inquiry (aktor, state, aturan bisnis).
- `inquiryproduct_tier2.md` — business logic, state machine, pseudocode HPP.
- `TIER_3A_*.sql` — **DIHAPUS 2026-08-26** (sudah dijalankan, isinya tertanam live di MySQL). Skema aktual = dump per-database (`docs/tm2*.sql`), digantikan user secara berkala — bukan lagi file DDL statis ini.
- `prefixlist.md` — konvensi prefix lengkap.
- Mockup Tier 4 (HTML/CSS) — acuan UI: `inquiry_new/recalc/revise.html`,
  `inquiry_costing_*.html`, `inquiry_proof.html`, `inquiry_quotation.html`,
  `product_costing.html`, `*_nota_*.html`.
- `griduistandard.md` — **wajib dibaca sebelum bikin/ubah grid apa pun**
  (Master maupun worklist/transaksi): tanpa header halaman, kotak statistik
  pastel, kolom identitas avatar+nama+kode, klik 2x buka detail, toolbar
  minimal. Kolom Aksi: grid Master = Edit+Toggle-aktif; grid non-Master =
  Buka + Confirm kondisional (tidak pernah toggle aktif/nonaktif).
- `sidebarmenustandard.md` — **wajib dibaca sebelum menata menu
  `sys_app_menu`/sidebar modul apa pun**: selalu ada grup input & grup
  "Laporan" (label ringkas, tanpa kata "Laporan" berulang di item-nya),
  nama item laporan ikut `#[Title]` layar aslinya, grup sidebar otomatis
  collapsible (tak perlu kerjaan tambahan).
- `ganttboardstandard.md` — **wajib dibaca sebelum bikin/ubah layar Gantt
  apa pun** (jadwal resource×waktu dengan drag/resize, mis. Scheduling Board):
  komponen reusable `resources/views/components/gantt/{board,row,task-block}
  .blade.php`, kontrak Row/Block VM, Alpine store `gantt` untuk highlight
  lintas-boundary. Gotcha wajib: jangan tulis nama tag komponen berkurung
  sudut (`<x-...>`) di dalam komentar mana pun — Blade tag compiler ikut
  mengompilasinya dan merusak file.
- `kanbanboardstandard.md` — **wajib dibaca sebelum bikin/ubah layar Kanban
  apa pun** (kolom kategori/status diskrit, kartu drag-drop antar kolom,
  mis. Pipeline Board, Monitoring Dashboard): pola `x-data="{draggedId}"` +
  `dragover.prevent`/`drop`, validasi bisnis WAJIB di server (DB = satu-
  satunya sumber kebenaran posisi kartu, drag di layar cuma usulan sampai
  re-render), tombol aksi non-drag selalu tersedia sbg fallback.
- `docs/qc_feedback.md` — **tempat user tulis hasil QC/revisi/bug setelah cek aplikasi sendiri di browser** (Claude Code tidak bisa login browser otomatis, lihat §7). Cek file ini kalau user minta "cek qc_feedback" / "kerjakan yang belum" — proses entry di bagian "Belum Dikerjakan", pindahkan ke "Selesai" (isi Perbaikan+tanggal) atau "Ditutup" (kalau ternyata bukan bug) setelah selesai, jangan hapus entry.
- `docs/attachments/` — folder tempat menaruh **form manual (kertas/scan) & file Excel yang SAAT INI benar-benar dipakai** di lapangan, dijadikan acuan sebelum membangun/mengubah layar terkait (jangan menebak field, cek dulu ke sini kalau ada dokumen relevan). **Daftar isi lengkap: `docs/attachments_index.md`** — cek/update file itu setiap ada attachment baru ditambah/dipakai, jangan baca ulang seluruh folder dari nol tiap sesi.
- `printnotastandard.md` — **wajib dibaca sebelum bikin/ubah layar cetak
  dokumen transaksi (Nota)** — kop+parties+signature+footer, beda dari
  laporan tabel (`FORM_UI_STANDARD.md`): komponen reusable
  `resources/views/components/print/{document,sheet,header,parties,party,
  section,signatures,signature,footer}.blade.php`, tema warna per dokumen
  via prop `theme`. Gotcha sama seperti `ganttboardstandard.md` §8.
- `production_scheduling_wo_generation_delta.md` — **wajib dibaca sebelum
  menyentuh alur generate WO / layar Worklist WO-Detail-Monitoring** —
  delta atas `production_scheduling_tier1-4.md` (LOCKED, tidak direvisi):
  WO lahir dari `co_order_delivery` (Plan Delivery) bukan CO langsung, 1
  `iqp_part` = 1 `prd_wo` (konvergensi antar-part sengaja belum dimodelkan),
  target rate dikunci ke `prod_product`+`part_code` teks (bukan `prod_part`
  yang ternyata tak pernah diisi).
- ~~`admindashboardstandard.md`~~ — standar panel Filament `/admin`. **Tidak
  berlaku lagi**: user 2026-09-30 memutuskan Filament bukan arah ke depan
  (jangan bangun/pindahkan modul ke Filament; v2 DevLib yang menggantikan).
  Dokumennya diarsipkan 2026-10-02 ke `C:\Users\iwanp\tmerp_unused\`.
- `spreadsheetgridstandard.md` — **wajib dibaca sebelum bikin/ubah grid
  spreadsheet-editable apa pun** (klik sel langsung edit, drag-fill AutoFill
  copy nilai ke bawah, "+ Baris" langsung dari tabel, simpan batch lewat
  "Simpan Semua") — pola TERPISAH dari `griduistandard.md`, opt-in HANYA
  utk master kecil/kolom scalar (pilot: Master Currency, list Satuan Master
  UOM). Adopsi perilaku AutoFill DataTables.net versi ringan buatan sendiri
  (Alpine+Livewire, keputusan user 2026-08-30) — DataTables Editor asli
  (dibutuhkan utk inline-edit sungguhan) berbayar & belum ada license key,
  DataTables juga bentrok arsitektur dgn cara Livewire re-render DOM tabel.
- `themestandard.md` — **wajib dibaca sebelum menambah/ubah pilihan tema**
  atau token warna baru yang harus ikut ganti per mode: matrix 4 mode
  (Pastel/Cerah/Dark/Hitam) x 3 corak (Biru/Hijau/Merah) = 12 kombinasi,
  data 100% di `tm2sys.sys_theme` (kolom `mode`/`family`/`hue`) supaya
  gampang diedit langsung lewat DB — BUKAN hardcode di CSS/Blade. Picker
  grid ada di dropdown avatar (`main-menu.blade.php`), toggle cepat
  matahari/bulan di topbar. Cakupan baru shell lama (`layouts::shell`) —
  halaman transaksi via `<iframe>` & panel Filament `/admin` (sengaja
  dipaksa terang, `darkMode(false)`) belum ikut sistem ini.

---

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

## 8. MULAI DARI MANA

Modul pertama: **Inquiry** (fondasi seluruh order). Layar pertama: **inquiry_new**.
Urutan: Model (master + iqp) → Livewire inquiry_new (CRUD + dieline preview) →
costing → quotation → proof. Costing engine & rate versioned menyusul.
