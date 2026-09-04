# CLAUDE.md — Konteks Proyek ERP CV. Telasih Makmur (Versi 2)

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
- PK: `id_<entitas>` (kecuali `prod_type`, `iqp_type` = kode varchar).
- Nama: English, snake_case.
- Master: `activate` (toggle aktif/nonaktif, boolean 1 kata — **bukan** `is_active`,
  keputusan user 2026-08-26) + `created_at/by` + `updated_at/by`.
- Transaksi: `created_at/by`, `updated_at/by`. Ber-approval: `approved_by/at`.
  Confirm/cancel/close: flag `stat_confirm`/`stat_cancel`/`stat_close` (prefix
  `stat_` = status siklus dokumen, **bukan** `is_confirmed`/`is_cancelled` —
  keputusan user 2026-08-26) + `cancel_reason` (pelaku via `updated_by`).
- State transisi dokumen → tabel `doc_state_log` (polymorphic), BUKAN kolom per-transisi.
- **DEC-FIELDNAME-STATUS (2026-08-26/27):** rename `is_active`→`activate`
  & `is_confirmed`/`is_cancelled`→`stat_confirm`/`stat_cancel` — **SELESAI**
  di semua tabel (tm2mst, tm2acc, tm2sdi, tm2pur, tm2sys **termasuk**
  `sec_user`/`sec_group`/`sec_module`, dicabut dari pengecualian awal atas
  instruksi user 2026-08-27 setelah fase lain terbukti aman). `sec_user.active`
  (kolom legacy terpisah, isinya selalu sama dgn is_active lama) **tidak**
  ikut berubah. Riwayat & catatan per-fase: `docs/fieldrename_state.yaml`.

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
    sebelum baris CO bisa diisi (beda dari baris manual di CO Mode #1 yang
    sekarang bebas free-text `description`, lihat `co/entry.blade.php::
    addItem()`). **STATUS 2026-09-03: BELUM ADA** — 0 jalur kode bikin CO
    tanpa Quotation, dan skema `co_order.quotation_id` saat ini **NOT NULL**
    sehingga CO tanpa Quotation mustahil tanpa migration (jadikan nullable +
    validasi baru: kalau `quotation_id` null maka tiap baris **wajib**
    `product_id` terisi dari Master Product). Ini gap nyata, target scope
    berikutnya — jangan asumsikan sudah bisa.
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

> **`uistandardindex.md` — peta semua standar UI/UX** (grid/form/nota/gantt/
> kanban/admin dashboard Filament) + kapan masing-masing wajib dibaca. **Cek
> file ini dulu** kalau bingung standar mana yang relevan untuk 1 layar baru,
> daripada baca satu-satu daftar di bawah.

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
- `admindashboardstandard.md` — **wajib dibaca sebelum bikin/pindahkan
  modul apa pun ke panel Filament `/admin`** (migrasi bertahap, dimulai
  2026-08-30): pemetaan Module→Cluster/Group→navigationGroup/Item→Resource,
  urutan grup harus didaftarkan eksplisit (`->navigationGroups()`), auth
  bridge kolom `login`/`pswd` (bukan email/password bawaan Filament),
  `->topNavigation()` untuk pola top-pilih-modul + sidebar-isi-modul, cara
  bikin Resource baru tanpa kena crash generator `--cluster=`. Ini HANYA
  mengatur chrome/navigasi Filament — isi layar tetap ikut standar UI
  lain sesuai jenisnya (lihat `uistandardindex.md`).
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

---

## 8. MULAI DARI MANA

Modul pertama: **Inquiry** (fondasi seluruh order). Layar pertama: **inquiry_new**.
Urutan: Model (master + iqp) → Livewire inquiry_new (CRUD + dieline preview) →
costing → quotation → proof. Costing engine & rate versioned menyusul.
