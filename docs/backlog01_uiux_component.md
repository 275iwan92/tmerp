# BACKLOG 01 — UI/UX, KOMPONEN & FASILITAS GRID/FORM

> Pecahan dari `BACKLOG.md` (2026-09-22, permintaan user). Indeks semua
> backlog: **[`BACKLOG.md`](BACKLOG.md)**. Item yang sudah selesai dicatat
> di **[`LOG_JOB_DONE.md`](LOG_JOB_DONE.md)**, bukan di sini.

Perbaikan tampilan lintas-modul: komponen grid & form, fasilitas di dalamnya, standar cetak, dan migrasi Filament. Termasuk permintaan user 2026-09-22 supaya semua komponen dijadikan object oriented yang bisa diturunkan.

---

## 00. [BARU — permintaan user 2026-09-22] JADIKAN KOMPONEN OBJECT ORIENTED & BISA DITURUNKAN

> User: *"Untuk improvement UI UX, baik component, facility di grid or di
> form. dan buat semua jadi object oriented yang bisa di turunkan."*

### Keadaan sekarang (dihitung 2026-09-22)

| Hal | Jumlah | Bentuk |
|---|---|---|
| Komponen form (`x-form.*`) | 28 | Blade anonymous component — **tidak bisa diwarisi**, hanya bisa dipanggil ulang |
| Komponen grid (`x-grid.*`) | 20 | sama, Blade anonymous component |
| Trait/Concern Livewire | 6 | `HasCustomizableGrid`, `HasGridPagination`, `HasGridStatFilter`, `HasSpreadsheetGrid`, `FinanceScreen`, `ManagesProcessMachineCapability` |
| Layar Volt (single-file component) | 176 | tiap layar menulis ulang state & method-nya sendiri |
| Base class Livewire | 1 | praktis tidak ada induk bersama |

Jadi penggunaan ulang sekarang lewat **komposisi** (panggil komponen +
`use Trait`), bukan **pewarisan**. Tiap layar grid masih mengulang sendiri
properti pencarian, urutan, halaman, filter, dan aksi baris.

### Yang diminta

| # | Pekerjaan | Status |
|---|---|---|
| 00.1 | Putuskan bentuk OO-nya dulu: (a) Blade **class-based component** (`App\View\Components\*`) yang bisa di-`extends`, (b) base class Livewire (mis. `BaseGridScreen`/`BaseFormScreen`) yang diturunkan tiap layar, atau (c) keduanya. Ini menentukan seluruh pekerjaan di bawah | **menunggu user** |
| 00.2 | Kalau (b): bikin `BaseGridScreen` yang sudah memuat pencarian, urutan, halaman, kotak statistik-filter, kustomisasi kolom, ekspor, dan kolom Aksi standar — supaya layar baru cukup mendefinisikan query + daftar kolom | **BELUM** |
| 00.3 | Idem `BaseFormScreen`: mode new/edit/view, dirty-guard, Save/Confirm, validasi, breadcrumb, tombol Back | **BELUM** |
| 00.4 | Migrasikan 176 layar Volt bertahap ke induk baru — **kecuali zona beku** (Inquiry Product, ProDev, ProCost; lihat CLAUDE.md §6B) | **BELUM** |
| 00.5 | Ubah 28 `x-form.*` + 20 `x-grid.*` dari anonymous jadi class-based bila 00.1 memilih (a)/(c), supaya varian bisa mewarisi induknya (mis. `x-form.date-picker` mewarisi `x-form.input`) | **BELUM** |
| 00.6 | Tulis aturannya di `docs/uistandardindex.md` supaya layar baru wajib turun dari induk, bukan menyalin layar lama | **BELUM** |

**Catatan risiko:** 00.4 menyentuh hampir semua layar. Kerjakan bertahap per
modul dengan test regresi tiap tahap, jangan sekali jalan.

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

### 9.1 [BELUM DIPUTUSKAN — DISKUSI DULU] Klik menu sidebar saat form/modal
terbuka "tidak merespons" (dilaporkan user 2026-09-10)

**Root cause (sudah diinvestigasi, BUKAN bug kode project):** kedua Resource
yang sudah ada (`EmpEmployeeResource`, `OrgPositionResource`) pakai pola
Filament **`ManageRecords` (`--simple`)** — form Edit/Create dibuka sebagai
**modal** di atas halaman index, bukan halaman terpisah
(`EmpEmployeeResource.php` `getPages()` cuma daftar `index`). Modal Filament
pasang backdrop full-viewport dgn z-index di atas sidebar
(`vendor/filament/support/resources/views/components/modal/index.blade.php`
§160-181) — klik pertama ke sidebar kena backdrop dulu (cuma `close()` modal,
tidak tembus ke link), klik kedua baru jalan. Ada kontributor tambahan: bug
kecil vendor Filament sendiri (`modal.js`, referensi upstream
filamentphp/filament#18022) yang kadang menelan 1 klik berikutnya di mana
saja di halaman. Dikonfirmasi TIDAK ada custom overlay/CSS/JS/unsaved-changes-
guard buatan project yang jadi penyebab — 100% perilaku bawaan pola modal
Filament v5.

**Riset banding aplikasi lain (2026-09-10):** Odoo (auto-save begitu navigasi,
tanpa dialog), SAP Fiori classic (dialog konfirmasi "unsaved data, leave?"),
SAP Fiori Elements draft-enabled (auto-save sbg draft → bebas navigasi kapan
saja tanpa dialog), Dynamics 365 (dialog konfirmasi kalau auto-save off, auto-
save kalau on). **Tidak ada satu pun yang "klik = tidak terjadi apa-apa"** —
selalu langsung navigasi(+autosave/draft) atau muncul dialog. Jadi ekspektasi
user (klik menu harus selalu direspons) sesuai standar industri; perilaku
Filament panel saat ini menyimpang.

**Arah yang user SETUJU secara prinsip (2026-09-10) tapi MINTA DIDISKUSIKAN
LEBIH LANJUT dulu — JANGAN eksekusi sebelum ada konfirmasi ulang:** adopsi
pola **auto-save sebagai draft ala SAP Fiori Elements** — perubahan tersimpan
otomatis begitu diketik/pindah field, user bebas klik menu lain kapan saja
tanpa dialog/blokir, karena data sudah aman. Ini kemungkinan juga berarti
pindah dari pola `ManageRecords --simple` (modal) ke Resource dgn halaman
Create/Edit terpisah (route sungguhan) supaya tidak ada backdrop yg menutupi
sidebar sama sekali — tapi ini mengubah standar yg sudah didokumentasikan di
`docs/admindashboardstandard.md` §6, berlaku ke semua Resource Filament ke
depan (belum tentu cocok utk transaksi bernilai besar yg butuh Save eksplisit
— lihat prinsip approval/costing rahasia §4 CLAUDE.md, perlu ditimbang saat
diskusi). **Hal yang perlu diputuskan bareng user sebelum mulai:**
1. Draft/auto-save berlaku utk SEMUA Resource Filament, atau cuma tertentu
   (master kecil vs transaksi ber-approval)?
2. Kalau pindah dari modal ke halaman Create/Edit terpisah — apa konsekuensi
   ke UX yang sudah ada (mis. list index jadi 2 klik jauh dari form)?
3. Auto-save di Filament (Livewire) butuh mekanisme sendiri (bukan bawaan
   native seperti draft SAP) — perlu didesain: kapan trigger save (per
   keystroke/blur/interval), bagaimana tandai record "draft" vs "final" di DB
   (kolom `stat_*` yg sudah ada, atau kolom baru?), race condition kalau ada
   2 tab/user edit record sama.

**Mitigasi cepat [SELESAI 2026-09-11]** — supaya klik di luar modal tidak
diam-diam menutup form tanpa disadari user, selagi solusi jangka panjang
(draft/halaman terpisah) masih menunggu diskusi. **Koreksi API saat eksekusi**:
draf di atas menyebut `->closedByClickingAway(false)` sbg method fluent per-
`EditAction`/`CreateAction` — dicek ke vendor, method itu **TIDAK ADA** di
`filament/actions` versi ini (bukan tebakan yang benar). Yang benar:
`Filament\Support\View\Components\ModalComponent::closedByClickingAway()`
adalah **static config global**, dipanggil sekali di
`AdminPanelProvider::boot()` (baru ditambah, sebelumnya cuma ada `panel()`).
Efeknya jadi berlaku ke SEMUA modal panel `/admin` sekaligus (bukan cuma 2
Resource ini) — sejalan dgn niat asli "dan Resource --simple lain yg
menyusul", jadi tidak perlu diulang tiap Resource baru. Root cause (backdrop
z-index di atas sidebar) TIDAK hilang — mitigasi ini murni cegah "form
ke-close tanpa sadar". Diverifikasi: `php artisan route:list --path=admin`
(panel boot tanpa error) + tinker cek `ModalComponent::$isClosedByClickingAway`
= `false`. Reversibel (hapus method `boot()` di provider).

---

---

## 10. TEKNIS / UI STANDAR — TECHNICAL DEBT

- **[SELESAI 2026-09-10 malam] Standarisasi tombol aksi umum (Save/Cancel/Close)**
  — 5 titik yang menyimpang dari standar LOCKED (`CLAUDE.md` §3,
  `FORM_UI_STANDARD.md` §1a) sudah diperbaiki (`button-label-standardization-rollout`,
  dieksekusi manual krn task terjadwal 21:07 WIB fire tapi 0 progres nyata).
  Detail lengkap: `docs/LOG_JOB_DONE.md` 2026-09-10. **Belum termasuk**
  mekanisme toggle label ikut `lang_code` scr otomatis (masih perlu
  integrasi ke `Translator::t()`, lihat `project_i18n_shell_framework`).

- **Halaman transaksi iframe** (Inquiry, Stock In, Quotation, dst) —
  belum ikut sistem tema `sys_theme` (themestandard.md §6), masih warna
  Tailwind literal.
- **Rename `is_active`→`activate` global** — sudah selesai di semua DB
  KECUALI `sec_user.active` (kolom legacy terpisah, sengaja tidak diubah,
  BUKAN item terbuka — dicatat di sini supaya tidak disangka lupa).
- **WYSIWYG template-editor untuk Nota/print** — ide sempat dilontarkan
  saat print/nota component diekstrak (2026-08-28), sengaja ditunda
  (keputusan setingkat modul baru, belum diminta).
- **[BARU 2026-09-05] Standarkan SEMUA komponen UI, bukan cuma per-kasus
  saat ditemukan** (instruksi eksplisit user setelah komponen scrollbar
  `.tm-scroll` diangkat jadi standar bersama Grid+Form —
  `griduistandard.md` §13, `FORM_UI_STANDARD.md` §4). Pola yang sama harus
  ditempuh utk elemen UI lain yang saat ini masih ditempel manual/beda-beda
  per file, alih-alih 1 komponen reusable:
  - Audit lintas `docs/uistandardindex.md` — cek tiap `*standard.md` sudah
    benar-benar ditegakkan di SEMUA layar turunannya (grid/form/print/
    gantt/kanban/admin dashboard), bukan cuma di layar contoh yang ditulis
    saat standar itu dibuat.
  - Cari pola visual/interaksi yang berulang di ≥2 layar tapi masih
    di-copy-paste (bukan `<x-...>` component/class bersama) — kandidat
    kuat: border-radius/shadow modal, warna badge status, tombol
    icon-only, spacing card statistik, dst. — index sendiri sudah
    menegaskan "jangan ekstrak standar dari 1 contoh", jadi baru jadi
    komponen begitu polanya kepakai berkali-kali (persis kasus tm-scroll).
  - Kalau ditemukan gap (dokumen bilang standar X, tapi kode Y tidak
    ikut), JANGAN langsung refactor massal sendiri — laporkan dulu daftar
    gap-nya ke user (dampaknya lintas banyak layar, berpotensi
    architectural), baru eksekusi per-batch setelah dikonfirmasi, sama
    seperti pola kerja rekonsiliasi PowerGrid+Filament (§12 griduistandard.md).
  - Belum ada scope/urutan modul yang ditentukan — ini masih ITEM
    BACKLOG terbuka, bukan tugas yang sudah mulai dikerjakan.

  **Temuan konkret dari audit awal 2026-09-05** (screenshot user: header
  form entry + footer pagination grid) — dicatat di sini, **BELUM
  dikerjakan**:
  - **Header form entry (ikon+judul+doc_no+badge status+tombol
    cetak+tombol Back) — 0 komponen reusable.** Struktur identik di-copy-
    paste manual di ≥6 layar (`pur/pr/entry.blade.php:404-424`,
    `pur/po/entry.blade.php:258-278`, `co/entry.blade.php:328-361`,
    `pur/pq/entry.blade.php:295-311`, `pur/prt/entry.blade.php:343-357`,
    `quotation/entry.blade.php:177-184+`). Variasi yang harus diakomodasi
    komponen barunya: badge status via `match()` inline berbeda-beda tone
    per file (padahal cuma 4 tone yang sama: abu DRAFT/kuning
    SUBMITTED/hijau approved-selesai/merah reject-cancel), label+emoji
    tombol cetak beda (`🖨 Cetak PR` vs `🖨️ Preview Cetak`), kondisi
    kemunculan tombol cetak beda (`@unless` vs `@if`), sebagian layar (CO,
    Quotation) tambah elemen ekstra kanan (ringkasan angka, tombol
    wire:click) di wrapper `ml-auto flex items-center gap-4` sendiri.
  - **[SELESAI 2026-09-16 pagi] Komponen `<x-form.badge>` sekarang
    dipakai di 6/6 layar** (`pur/pr/entry.blade.php`, `pur/po/entry.blade.php`,
    `pur/pq/entry.blade.php`, `pur/prt/entry.blade.php`, `co/entry.blade.php`,
    `quotation/entry.blade.php`) — sebelumnya masing-masing menulis ulang
    span+`match()`/ternary warna badge sendiri (menganggur sebelum ini).
    Diverifikasi `Livewire::test()` 4/6 render OK (PR/PO/CO/Quotation, ada
    data live); `pq_quotation`/`prt_return` kosong 0 baris (bukan bug
    dari perubahan ini) jadi 2 sisanya cuma lolos `php -l` + kesamaan
    pola persis dgn yang sudah terverifikasi — sesi berikutnya boleh
    re-verify begitu ada transaksi PQ/PRT nyata. Header form entry
    (ikon+judul+doc_no+tombol cetak+Back) masih BELUM diekstrak jadi
    komponen — itu tugas berikutnya di item ini kalau mau dilanjutkan.
  - **Tombol Back — instruksi eksplisit user: standarkan juga** (bukan
    cuma didokumentasikan). Polanya sudah tertulis detail di
    `FORM_UI_STANDARD.md` §1 (pill navy `bg-[#43508a]` + lingkaran panah
    putih `w-6 h-6`, kapan pakai `ml-auto` vs tidak) tapi **belum ada
    komponen `<x-form.back-button>` yang mengeksekusinya** — semua 6+
    layar di atas menulis ulang markup pill yang sama persis manual.
    Jadikan bagian dari komponen header form (§ di atas), atau komponen
    berdiri sendiri kalau ternyata dipakai di luar konteks header juga
    (cek dulu — ada pengecualian tercatat: `inquiry/form.blade.php` dkk
    pakai sistem visual beda, `master/supplier.blade.php`/
    `master/employee.blade.php` tombol "Kembali" untuk toggle state
    bukan navigasi, JANGAN ikut disamakan).
    **[SELESAI 2026-09-11]** Rollout ikon `←` Unicode → Font Awesome
    `fa-solid fa-arrow-left` ke SEMUA 24 layar nyata yang masih pakai
    pola lama (1 lokasi acuan `component-library.blade.php` sudah lebih
    dulu jadi 25). Diverifikasi: grep pola lama nihil hasil, `npm run
    build` sukses, `MainMenuShellSmokeTest` 12/12 tetap pass. Detail
    daftar file: `docs/LOG_JOB_DONE.md`.
  - **Footer pagination — komponennya SUDAH ada & baik**
    (`components/grid/pagination-footer.blade.php`, dipakai konsisten 13+
    layar). **[SELESAI 2026-09-10 malam, 8/8]**: `hrm/position.blade.php`,
    `recruitment/assessment-result.blade.php`,
    `recruitment/candidate-review/list.blade.php`,
    `recruitment/offer/list.blade.php`,
    `recruitment/requisition/list.blade.php`,
    `recruitment/talent-pool.blade.php`, `recruitment/vacancy.blade.php`,
    `setup/support-tickets.blade.php` (terakhir, tadinya sengaja dilewati
    krn dikira tabrakan dgn `button-label-standardization-rollout` — task
    itu ternyata tidak pernah benar-benar jalan, jadi dituntaskan sekalian
    saat file itu disentuh utk perbaikan label tombol) — pola sama semua:
    tambah `use App\Livewire\Concerns\HasGridPagination;` (import + trait),
    `->paginate(20)` → `->paginate($this->perPage)`, ganti
    `<div class="mt-3">{{ $this->rows->links() }}</div>` jadi
    `@include('components.grid.pagination-footer', ['paginator' => $this->rows])`.
    Diverifikasi via `Livewire::test()` (8/8 render OK, footer baru
    terkonfirmasi muncul). Tidak ada class Tailwind baru → tidak perlu
    `npm run build`.

---

---

## 21. INPUT TEKS & KOMPONEN FORM — SISA ROLLOUT (instruksi user 2026-09-21)

**Aturan tetapnya sudah dikunci** di `CLAUDE.md` §3 + `FORM_UI_STANDARD.md` §1b
(+ skill `tmerp-screen` & `tmerp-ui-mockup`): input teks string = `x-form.input`/
`x-form.textarea` dgn badge huruf aa/Aa/AA, lookup dulu kalau nilainya ada di
master, warna lewat token tema. **KOREKSI:** catatan di section ini sebelumnya
(pagi 2026-09-21) menulis case-toggle "sengaja tidak di-rollout" — itu keputusan
sementara yang DIBALIK user di hari yang sama: alasannya user banyak input lewat
HP/tablet, menekan Shift tiap kata merepotkan. Yang berlaku = versi ini.

### 21.1 Lookup — field teks bebas yang nilainya sudah ada di master
Audit 2026-09-21 atas seluruh `x-form.input`: mayoritas field ber-nama-master
ternyata SAH teks bebas (itu nama/kode master yang sedang dibuat di layar itu).
Sisa yang benar-benar perlu diubah jadi lookup:
- ~~`production/rule-library` → `applies_to_process`~~ **SELESAI 2026-09-22** —
  sekarang `x-form.select` dari master `prod_process` (24 proses aktif, simpan
  NAMA proses karena kolomnya varchar & lintas-DB tm2prd→tm2mst, tanpa FK).
  Tidak ada data lama yang perlu dipetakan (kolomnya masih kosong semua).
- `production/process-rate` → `part_code` — **DILEWATI 2026-09-22, butuh
  keputusan user.** Sumbernya AMBIGU, dua kandidat yang artinya bertabrakan:
  (a) `tm2mst.prod_part.part_code` isinya kode hasil generate (`PDDUM1-1`,
  `FB-DUPLEX-001-BODY`), bukan peran part; (b) yang dimaksud layar ini
  ("mis. BODY", dan 1 baris data nyata di `prod_part_process_machine_rate`
  memang `BODY`) = **Peran Part**, yang saat ini cuma array PHP hardcode di
  `inquiry/form.blade.php::partRoleOptions()` (body/lid/base/sleeve/tray/
  insert/divider/left/right/monoform), BUKAN tabel master. Keputusan yang
  dibutuhkan: naikkan daftar peran part jadi master (`ref_part_role` di
  tm2mst) lalu pakai di kedua layar, atau biarkan teks bebas.
- `production/bop-rule` → `material_pattern` — **DILEWATI, bukan kandidat
  lookup.** Isinya sengaja POLA `LIKE` multi-nilai (`%LEM LAMINASI%|%LEM
  LAMINATING%`, `TINTA SW 490 N PR. {ink}%`), bukan 1 nilai master; dijadikan
  dropdown justru merusak fungsinya. Kalau mau dibantu, bentuknya picker yang
  MENYISIPKAN nama bahan ke dalam pola — desain tersendiri, bukan lookup.
- `production/bop-rule` → `material_role` — **DILEWATI, butuh keputusan user.**
  Tidak ada masternya di tm2mst (`ref_*` yang ada cuma finishing_type,
  legacy_lookup, param_option); nilai yang dipakai sekarang 11 label yang
  hidup di tabel itu sendiri (TINTA, FOIL, LEM BOX, VARNISH UV, dst).
  Pilihan: (a) bikin master kecil (`ref_bom_row`), (b) dropdown dari nilai
  distinct tabel itu sendiri + boleh ketik baru, (c) biarkan teks bebas.
- `hrm/payroll-employee-bank` → `bankName` (teks bebas; belum ada master bank
  di tm2mst — butuh keputusan: bikin master bank kecil atau `ref_` lookup).
- Sisanya (`customer_name`, `product_code`, `uom_name`, dst) **BUKAN** item
  terbuka: itu form master-nya sendiri.

### 21.2 Layar yang inputnya masih HTML mentah (belum lewat komponen)
- ~~**Inquiry Form / Revise / Hub**~~ — **SELESAI 2026-09-22** untuk semua
  field yang memang berlaku: `cancel_reason` + `lost_reason` (textarea),
  `part_name`, `cfg_ink` (Catatan Warna) di Form, dan 3 field readonly header
  di Revise. Hub **tidak ada yang diubah**: 2 input-nya kotak CARI (standar
  grid sendiri, tanpa badge). Yang SENGAJA dibiarkan mentah + alasannya:
  autocomplete Customer/Sales/Bahan (kotak cari di dalam `<div class="f
  ac-wrap">` — `x-form.input :legacy` akan bikin `.f` bersarang dan CSS-nya
  rusak), serta input di dalam `<td>` (finishing attribute, tooling remark,
  external cost description) yang masuk item "input di dalam sel tabel" di
  bawah. `part_name` memakai `:legacy="'flex-1 min-w-0'"` (wrapper netral,
  bukan `.f`) karena pemanggilnya sudah punya `.f` sendiri.
- **BUG KOMPONEN yang ikut ketahuan & sudah diperbaiki 2026-09-22**:
  `x-form.input`/`x-form.textarea` menentukan readonly/disabled pakai
  `$attributes->has()` — padahal pemanggil yang menulis `:disabled="$expr"`
  SELALU mengirim key `disabled` walau isinya `false`, jadi badge huruf mati
  diam-diam di **97 titik pemanggilan lintas 23 file** (co/entry, hrm, dst).
  Sekarang pakai `->get()` (nilai). Pola bug yang sama pernah kena di
  `date-picker` (memory `feedback_datepicker_disabled_attribute_bug`).
- **Input di dalam sel tabel** (~25, mis. `mps-spreadsheet`, grid editable) —
  komponen berlabel tidak cocok. Perlu komponen sel tersendiri
  (`x-form.cell-input`) yang membawa badge huruf versi ringkas. Belum
  diputuskan bentuknya — jangan bangun tanpa persetujuan.
- **Kotak cari toolbar grid** (~55 layar) — sengaja tetap mentah (standar grid
  sendiri, `griduistandard.md` §6) dan memang TIDAK perlu badge huruf.
- **Sengaja beda / tidak aktif**: Login & Register, Portal Karier, kotak chat
  & AI (`shell/user-panels`), field COA "MENYUSUL" di Master Customer, layar
  legacy `production/old_prodev*` dan `inquiry/imposition*`.

### 21.3 Warna: sisa hex literal di luar komponen form
Token `--color-field-*` + `--color-accent-soft` sudah dipakai SEMUA komponen
`resources/views/components/form/*`. Yang belum: **hex literal di file layar**
(ratusan titik, mis. `border-[#c7cfda]` di modal/kartu/tabel per layar) dan
komponen non-form (`grid/`, `print/`, `report/`). Efeknya: corak tema baru
kelihatan di field, belum di seluruh kerangka layar. Ini lanjutan langsung dari
`themestandard.md` §6 ("halaman iframe belum ikut sistem tema") — kerjakan
per-modul, bukan sekali sapu, dan verifikasi tiap layar setelahnya.

**Progres per modul:**
- ✅ **Quotation** (2026-09-22) — `quotation/entry.blade.php` + `list.blade.php`,
  56 hex netral → token (`border-field-border`, `border-field-badge`,
  `bg-field-soft`, `bg-field-badge`, `bg-accent-soft`, `text-muted`).
  Pemetaan yang dipakai: border abu (#d7dbe2/#d9dfe8/#c7cfda) →
  `border-field-border`; garis pemisah tipis (#eef1f5) → `border-field-badge`
  (nilainya setara); latar abu muda (#f8fafc/#fafbfd/#f4f6fa/#f4f5f8) →
  `bg-field-soft`; latar kebiruan (#eef3fb/#f7faff) → `bg-accent-soft`.
  Warna semantik (hijau terbit, amber tooling, ungu baris bebas) + navy brand
  tombol SENGAJA tidak diubah. `quotation/print.blade.php` juga tidak diubah:
  nota cetak punya tema sendiri (kertas), lihat `printnotastandard.md`.
- ⏳ Sisanya belum: production (1213 hex), master (781), inquiry (638),
  pur (676), hrm (632), recruitment (488), inventory (455), self-service (276),
  setup (222), co (203), finance (180), careers (126), dispatch (104).

### 21.4 Mode gelap untuk layar transaksi
Token field saat ini dihitung untuk latar TERANG (dicampur ke abu-abu terang).
Di tema Dark/Hitam, sidebar & shell ikut gelap tapi isi layar transaksi masih
putih — field akan tetap terbaca, tapi belum benar-benar "dark mode". Ini
scope terpisah yang sudah lama tercatat (`themestandard.md` §6), jangan
dianggap regresi dari pekerjaan 2026-09-21.

---

## 22. AGENDA GRID — 4 KETENTUAN BARU (instruksi user 2026-09-21 malam)

Semua berlaku untuk SELURUH grid (pola sama), bukan per layar. Referensi visual
dari user: aplikasi grid pihak ketiga (tangkapan layar di sesi 2026-09-21).

### 22.1 Kotak statistik jadi FILTER DATA — MEKANISME SELESAI 2026-09-22
Aturan tetapnya sudah dikunci di `griduistandard.md` §2b (+ skill
`tmerp-screen`/`tmerp-ui-mockup`), mekanismenya dibangun di PUSAT:
trait `App\Livewire\Concerns\HasGridStatFilter` (`statFilterDefs()`,
`toggleStatFilter()`, `clearStatFilter()`, `applyStatFilter()`, `statBoxes()`)
+ komponen `x-grid.stat-filter-row`. Layar cukup mendeklarasikan definisinya.

**Sudah dipasang & diuji (26 grid)**: Master Employee (pilot, 146/341/102/44
cocok dgn angka kotak), Machine, Shift, Customer, Supplier, Produk, UOM,
Currency, Gudang · Quotation List, CO List, Delivery Order List · COA ·
HRM Position · ProDev List, WO List, Master Proses, Scrap Type, Rule Library ·
PR Index, PO Index · Stock In/Out/Return · Costing List, Costing Instant List.

**Belum dipasang (± 42 grid)** — mekanismenya sudah ada, sisanya tinggal
mendeklarasikan `statFilterDefs()` per layar (± 15 baris + 1 baris markup):
`co/delivery-plan`, `finance/cash-bank`, `finance/dp-invoice`,
`hrm/attendance-daily`, `hrm/correction-requests`, `hrm/discipline-report`,
`hrm/employee-contact-gap`, `hrm/gps-approval`, `hrm/leave-requests`,
`hrm/payroll-batch-run`, `hrm/payroll-employee-bank`,
`hrm/payroll-employee-salary`, `hrm/payroll-payslip-list`,
`hrm/sop-documents`, `inquiry/hub`, `inquiry/imposition-list` (LEGACY),
`inventory/stock-adjustment(-approval)`, `master/exchange-rate`,
`master/holiday`, `master/mat-category`, `master/raw-material/index`,
`production/bop-rule`, `production/master-crew`, `production/mts-forecast`,
`production/old_prodev_list` (LEGACY), `production/process-rate`,
`production/scheduling-board`, `pur/po/approval`, `pur/pq/index`,
`pur/pr/approval`, `pur/prt/approval`, `pur/prt/index`, `pur/pv/index`,
`pur/report/outstanding-{po,pr,pv}`, `recruitment/sync-log`,
`sales/win-rate`, `security/index`, `setup/component-library`,
`setup/data-origin`.
- Catatan `master/mat-category`: grid hirarki (pohon 5 level) — filternya perlu
  keputusan dulu (menyaring simpul = memutus pohon), jangan disamakan begitu
  saja dgn grid datar.
- Catatan grid laporan (`pur/report/*`, `sales/win-rate`,
  `finance/cash-*`): sebagian kotaknya angka RUPIAH/rata-rata → sesuai §2b
  dibiarkan tidak bisa diklik, bukan dipaksa jadi kategori.

### 22.2 Ikon kolom Aksi — PILIHAN C — SELESAI 2026-09-22
Sapu bersih 90 titik pemanggilan `x-grid.action-icon` di 55 file: karakter
Unicode diganti Font Awesome, bingkai kotak jadi DEFAULT komponen
(`bordered=true`), dan tombol aktif/nonaktif jadi `:active="$row->activate"`
(ikon+warna ditentukan komponen: `fa-ban` merah / `fa-circle-check` hijau)
sehingga tidak ada layar yang bisa menyimpang. Ikon non-Master yang dipilih:
Buka `fa-up-right-from-square`, Confirm/Post `fa-check`, Batal/Tolak
`fa-xmark`, Lihat `fa-eye`, Reset password `fa-key`, Konversi `fa-rotate`,
Split `fa-scissors`, Naik/Turun `fa-chevron-up`/`fa-chevron-down`.
`griduistandard.md` §5 & §10 sudah diperbarui (§5 tidak lagi menyatakan
default tanpa border; §10 tinggal menyisakan "Aksi di KIRI" sbg pilot).

Rincian keputusan aslinya:
Ganti karakter Unicode `✎`/`⏻` (tipis, beda-beda antar perangkat, tidak sebaris
dgn Font Awesome tombol Back) menjadi **ikon berbingkai kotak**:
- Edit = `fa-pen` dalam kotak bergaris.
- Nonaktifkan = `fa-ban` (merah) · Aktifkan = `fa-circle-check` (hijau).
- Bingkai = varian `bordered` yang SUDAH ada di `x-grid.action-icon` tapi selama
  ini cuma dipakai 2 layar pilot (`co/list`, `raw-material/index`) — sekarang
  jadi standar, jadi `griduistandard.md` §5 & §10 WAJIB diperbarui (§5 masih
  menyatakan default tanpa border).
- Mockup pembanding: `docs/mockups/pilihan_ikon_aksi_grid_konteks.jpg`.

### 22.3 Kerapatan baris + Baris per sel — KEPUTUSAN LENGKAP, siap dibangun
Panel dari toolbar grid, **dua setelan yang berdiri sendiri**:
- **Kerapatan**: Rapat / Normal / Longgar → hanya mengubah padding sel.
- **Baris per sel**: Otomatis (teks membungkus penuh, tinggi baris ikut isi) atau
  Tetap N (dipotong `line-clamp` + titik tiga, semua baris sama tinggi). Saat
  Otomatis, kotak angka N dikelabukan & tidak bisa diisi.
- Plus tautan "Kembalikan ke bawaan". Bawaan app = Normal + Tetap 1.
- Mockup: `docs/mockups/grid_kerapatan_baris.jpg` (6 kombinasi).

**Keputusan user 2026-09-22:**
1. **SATU setelan berlaku di semua grid** — disimpan "di user profile", BUKAN
   per grid. Tidak ada override per grid (jangan dibangun tanpa diminta).
2. **Ikut PENGGUNA, bukan perangkat** — jadi kolom baru di `sec_user`, satu
   rumah dgn `theme_code`/`lang_code` yang sudah ada (pola preferensi tampilan
   per user memang sudah di situ). BUKAN localStorage.
3. **Manual, TANPA deteksi resolusi otomatis.** Lebar layar sebetulnya bisa
   dibaca browser (`window.innerWidth`, tidak bergantung versi Windows), TAPI
   user memutuskan tidak dipakai: penskalaan DPI 125/150% & ukuran jendela
   bikin angka itu menyesatkan, dan ganti tampilan sendiri tanpa diminta
   membingungkan. Jangan bangun auto-switch, jangan pula popup saran.

**Rencana teknis** (belum dikerjakan):
- Migration: `sec_user.grid_density` (enum RAPAT/NORMAL/LONGGAR, default NORMAL)
  + `sec_user.grid_lines` (tinyint, 0 = Otomatis, default 1).
- Dibaca sekali di layout/trait grid → class CSS di pembungkus tabel; jangan
  query per baris.
- 2 pintu masuk, 1 nilai yang sama: layar Pengaturan Akun/Profil (rumah resmi)
  dan panel cepat di toolbar grid (menulis ke record user yang sama, supaya
  user tidak perlu meninggalkan grid saat menyetel).
- **Gotcha**: `-webkit-line-clamp` TIDAK bisa dipasang langsung di `<td>`
  (merusak layout sel) — bungkus isi sel dalam `<div>`.
- Tulis aturannya di `griduistandard.md` + `_manifest.yaml` + 2 skill, pola sama
  seperti §22.1/§22.2, supaya grid baru ikut tanpa diperintah ulang.
### 22.4 Navigasi keyboard di grid (instruksi user 2026-09-21)
Sel grid bisa difokus & dijelajahi dengan keyboard, plus panel bantuan pintasan
(ikon keyboard di toolbar). Daftar dari contoh user:
`↑`/`↓`/`←`/`→` pindah 1 sel · `Home`/`End` sel pertama/terakhir baris ·
`Ctrl+Home`/`Ctrl+End` sel pertama/terakhir halaman · `PageUp` baris terakhir
halaman sebelumnya · `PageDown` baris pertama halaman berikutnya ·
`Enter` buka detail/aksi sel · `Esc` tutup · `Ctrl+C` salin nilai sel terfokus.
- Perlu diputuskan: apakah `Enter` membuka baris (pola `dblclick` yang sudah ada
  di `griduistandard.md`) atau membuka menu aksi sel seperti contoh.
- Catatan: ini yang paling besar dari empat item — sentuh semua grid, perlu
  fokus DOM + status sel aktif, dan harus tidak bentrok dgn pintasan F1 (Help)
  serta hotkey shell yang sudah ada.

---

## 17. KOP DOKUMEN PRINT — NAMA COMPANY HARUS IKUT ADA/TIDAKNYA PPN
(instruksi user 2026-09-11, diperjelas 2026-09-11, **BELUM DIEKSEKUSI** —
user eksplisit minta audit+catat dulu, perbaikan menyusul)

> **Permintaan user (versi awal)**: "semua print out HARUS pakai HEADER nama
> company 'TELASIH MAKMUR' bukan CV Langgeng Jaya Makmur. Nama 'CV Langgeng
> Jaya Makmur' digunakan hanya pada dokumen yang ada PPN nya dan itupun pada
> bagian tertentu seperti 'Surat Jalan', 'Invoice', 'Purchase Order with
> PPN'."
>
> **Koreksi/klarifikasi user (penting, ganti pemahaman di atas)**: "INGAT
> semua yang mengandung PPN pakai CV Langgeng Jaya Makmur, itupun bisa di
> override oleh user. Yang tidak mengandung PPN pakai Telasih Makmur, baik
> itu surat jalan, Invoice, ataupun Purchase Order." — **jadi Surat
> Jalan/Invoice/PO BUKAN otomatis exception tetap** (koreksi audit awal
> sesi ini yg sempat salah generalisasi "Surat Jalan = selalu Langgeng").
> Aturannya murni **per-dokumen, berdasar ada/tidaknya PPN di dokumen ITU**,
> berlaku SAMA ke semua jenis dokumen, PLUS ada kemampuan **override manual
> oleh user** (belum jelas di level mana — per dokumen? per transaksi?
> perlu diklarifikasi saat eksekusi).

**Konteks — ini MEMBALIK sebagian keputusan lama:** `docs/configurationstandard.md`
baris 151 & 233-236 (keputusan 2026-09-02) bilang kop dokumen **sengaja**
pakai `org_company.company_name` (nama LEGAL, "CV. Langgeng Jaya Makmur"),
sementara "Telasih Makmur" cuma nickname buat konteks eksternal (careers
portal, dst). Keputusan itu **kini disempurnakan**: nama legal cuma dipakai
kalau dokumen itu benar-benar mengandung PPN, sisanya (termasuk dokumen yg
SECARA JENIS biasanya berpotensi PPN tapi instance-nya kebetulan tidak, mis.
PO non-PPN) pakai nickname "Telasih Makmur". `docs/configurationstandard.md`
§233-236 PERLU diperbarui saat item ini dieksekusi (saat ini masih
menyatakan aturan lama tanpa pengecualian — jangan dianggap kontradiksi,
itu memang belum di-update).

**Root cause teknis (header):** HANYA ADA 1 baris di `tm2mst.org_company`
(`company_id=1`, `company_code='TM'`, `company_name='CV. Langgeng Jaya
Makmur'`, dikonfirmasi via tinker 2026-09-11). **Setiap** print blade query
`OrgCompany::where('company_code','TM')` atau `where('activate', true)` —
keduanya selalu balikin baris yang sama itu — lalu oper ke
`<x-print.header :company="...">` yang render `$company->company_name` apa
adanya (`resources/views/components/print/header.blade.php:9`). Makanya
**SEMUA print out saat ini seragam "CV. Langgeng Jaya Makmur"**, termasuk
Master Card (`prodev-print.blade.php`, ini yg discreenshot user) yang
harusnya netral/internal (Master Card = dokumen produksi, tidak pernah
mengandung PPN sama sekali).

**Temuan penting — sinyal "dokumen ini ada PPN atau tidak" TIDAK seragam,
beda kolom/tabel per modul** (audit DDL 2026-09-11):

| Modul/dokumen | Tabel & kolom sinyal PPN | Catatan |
|---|---|---|
| Inquiry | `iqp_inquiry.tax_scheme` enum `PPN`\|`NON_PPN` (`tm2sdi.sql:487`) | Flag eksplisit di header inquiry |
| Costing | `iqp_costing.ppn_mode` (`tm2sdi.sql:200`, snapshot dari inquiry) | Ikut inquiry asalnya |
| Quotation | `quo_quotation.tax_scheme` (`tm2sdi.sql:875`) | **HATI-HATI beda arti**: ini `EXCL`\|`INCL` (harga blm/sdh termasuk PPN dlm angka yg ditampilkan), **BUKAN** flag "ada PPN atau tidak" — jangan tertukar dgn `tax_scheme` di `iqp_inquiry` yg maknanya beda walau nama kolom sama |
| Customer Order (`co_order`) | **TIDAK PUNYA kolom PPN sendiri** | Kemungkinan warisan dari Quotation/Inquiry asal (`quotation_id` FK) — perlu ditelusuri rantainya saat eksekusi, jangan asumsi |
| Delivery Order/Surat Jalan (`co_order_delivery`) | **TIDAK PUNYA kolom PPN sendiri** | Sama, kemungkinan warisan dari CO/Quotation — **ini yg bikin generalisasi awal "Surat Jalan = selalu PPN" SALAH**, PPN Surat Jalan ikut status dokumen sumbernya, bukan otomatis selalu ada |
| Purchase Order | `pur_po.c_ppn` decimal (`tm2pur.sql:136`) | Nilai nominal PPN hasil hitung — `>0` kemungkinan sinyal "ada PPN", tapi ini ANGKA HASIL bukan flag input eksplisit, perlu cek apakah bisa 0 karena memang non-PPN vs 0 karena belum dihitung |
| Purchase Request/PQ/PRT | **TIDAK ADA kolom PPN di `tm2pur.sql`** | Dokumen ini secara struktur TIDAK PERNAH mengandung PPN — selalu Telasih Makmur, tidak perlu logika kondisional |
| Invoice | `fin_invoice.tax_amount` decimal (`tm2acc.sql:148`) | Tabel ADA tapi masih KOSONG (belum ada print/data nyata) — `>0` kemungkinan sinyal, sama catatan spt PO |
| Payment Voucher | `fin_voucher.ppn_type` enum `NONE`\|`MASUKAN_11`\|`KELUARAN_11` (`tm2acc.sql:308`) | Flag eksplisit paling jelas dari semua tabel yg ditemukan |
| Customer master | `cust_customer.tax_profile` enum `PPN_11`\|`EXEMPT` (`tm2mst.sql:107`) | Kemungkinan ROOT signal default per customer — banyak dokumen transaksi (Inquiry/Quotation/CO) kemungkinan mewarisi dari sini saat dibuat, perlu ditelusuri |
| WO, Master Card (ProDev), Slip Gaji | Tidak ada & tidak relevan | Dokumen produksi/internal/HR — PPN secara konsep tidak berlaku, selalu Telasih Makmur, tidak perlu logika kondisional |

**Kesimpulan dari temuan di atas — ini BUKAN pekerjaan kecil "ganti 1
kondisi di beberapa file", tapi butuh keputusan desain dulu:**
1. **Sumber kebenaran PPN per dokumen** — apakah tiap print controller cek
   kolom lokalnya sendiri (`iqp_inquiry.tax_scheme`, `pur_po.c_ppn`, dst),
   atau ditelusuri naik ke induk kalau dokumen itu sendiri tidak punya
   kolom PPN (CO/DO/Surat Jalan → ikut Quotation/Inquiry asal)? Perlu
   dipetakan 1-1 per jenis dokumen, tidak bisa 1 rule generik krn nama
   kolom & makna beda-beda (lihat catatan `tax_scheme` Quotation vs
   Inquiry di tabel atas — JANGAN disamakan).
2. **Mekanisme "override oleh user"** — belum ada kolom/UI utk ini di
   mana pun. Perlu diputuskan: override per dokumen (kolom baru tiap
   tabel transaksi, mis. `company_name_override`) atau 1 toggle
   global per print (tombol di layar cetak, tidak persisten)? Field baru
   di tiap tabel transaksi = kerja migration lumayan banyak (Inquiry, CO,
   DO, PO, Invoice minimal).
3. **Rekomendasi arah (belum diputuskan, utk didiskusikan saat eksekusi)**:
   bikin 1 helper terpusat (mis. `App\Support\CompanyResolver::forDocument($doc)`
   atau method di tiap model transaksi `hasPpn(): bool`) yang jadi SATU
   tempat logika "dokumen ini PPN atau tidak", dipanggil oleh 11
   `company()` method print controller — supaya kalau sumber sinyal PPN
   berubah/ditemukan lebih akurat, cukup ubah 1 tempat. `org_company`
   sendiri kemungkinan perlu kolom kedua (nickname "Telasih Makmur" di
   samping `company_name` legal) supaya 1 baris tetap cukup, komponen
   `<x-print.header>` tinggal terima hasil resolve (bukan `$company`
   mentah) atau prop bool `:has-ppn`.

**Invoice — BELUM ADA print template, tabel `fin_invoice` juga masih
kosong.** Dicatat di sini supaya begitu Invoice print dibangun, langsung
pakai logika `tax_amount > 0 → CV Langgeng Jaya Makmur` dari awal, bukan
hardcode salah satu nama.

**Setelah dieksekusi:** update juga `docs/configurationstandard.md` §233-236
(saat ini menyatakan kop SELALU pakai nama legal tanpa pengecualian — akan
jadi salah/usang begitu perbaikan ini jalan).

---

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

---

## DARI AUDIT MENYELURUH `docs/*.md` (agent riset 2026-09-02)

Konteks lengkap audit ini ada di `BACKLOG.md`.

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

---
