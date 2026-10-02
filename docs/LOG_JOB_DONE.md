# LOG JOB DONE — RIWAYAT ITEM YANG SUDAH DIKERJAKAN
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

---

## 2026-10-03 -- Sapuan multi-bahasa: bahasa ikut akun, semua teks v2 lewat kamus, kamus tanpa teks kosong

User: "Make sure multi language sudah jalan dengan sempurna" ... "make sure multi language ready on mockup.test and tmerp.test,
make sure connect to database and tables". Rincian + sisa keputusan: `docs/md_for_systems/multilanguage_status.md`.

- **Temuan:** uji render 366 layar v2 dua bahasa (alat baru `tools/multilanguage/`) — mode English masih menampilkan teks
  Indonesia dari sekitar 1.600 baris kode, bahasa mengikuti cookie komputer (bukan akun), memilih bendera tidak memuat ulang
  halaman, pesan validasi selalu English, label menu tidak diterjemahkan, kamus punya 243 (ddt) / 22 (tm2) teks kosong.
- **Bahasa ikut akun:** `Locale::langUsing` -> `sec_user.lang_code`; `x-dl::lang-switch` dari `sys_languages`, simpan lewat
  `POST /v2/lang` lalu muat ulang (bertanya bila ada tab belum di-Save); locale Laravel disamakan.
- **Pesan:** helper `tf()`; flash / error / abort / JSON di `routes/v2*.php`, kelas Data dan service diterjemahkan di tempat
  dibuat, layar menampilkan teks jadi (`raw()`). `lang/en` + `lang/id` untuk pesan validasi.
- **Teks:** layar v2, `app/Support/V2`, DevLib (333 entri `Devlib\Lang`), mockup-parts PHP + JS, service -> English di kode;
  3.026 pasangan English => teks Indonesia lama (`database/data/dictionary_ui_2026_10_02_sweep.json`). Zona beku: teks saja.
- **Menu:** 54 label `sys_app_menu` Indonesia -> English + kamus; `AppMenu::label()` dan breadcrumb lewat kamus.
- **Kamus:** ddt dan tm2 kini 0 teks `ui` / `help` tanpa terjemahan Indonesia; 274 baris basi dihapus (termasuk `Tujuan`,
  `ERP TM`, `TELASIH MAKMUR`); teks hasil terjemahan DevLib tidak lagi tercatat sebagai sumber baru.
- **Penjaga:** `Dictionary::looksIndonesian()` + `indonesian_words.php` (2.100 kata); ratchet `id_label` 0,
  `data_in_text_prop` 0, `js_id_text` 65 (tabel dua bahasa `core.js`). Test baru `LanguageAccountTest` (6).
- **Model:** peta label mesin / downtime / ACC / master card / follow-up -> English, layar v1-nya memakai `t()`.
- **Diarsipkan:** `app/Support/V2/DemoData.php` (tidak dirujuk) -> `tmerp_unused`.
- **Migrasi** `2026_10_02_000300` .. `000305` jalan di tm2sys dan ddtsys (`--path`). Koneksi dan tabel bahasa kedua situs dicek
  (`tools/multilanguage/db_check.php`).
- **Bukti:** snapshot sebelum / sesudah 361 layar: mode English kehilangan 455 teks Indonesia tanpa teks Indonesia baru dari
  kode; mode Indonesia tidak berubah kecuali tombol / badge / status (selalu English) dan label yang dulu English kini Indonesia.

---
## 2026-10-03 -- Laporan stok semua kelas: dipasang di tmerp.test, diperiksa tersambung, data dummy di mockup.test

User: "apply to mockup.test and tmerp.test, make sure all connected to table and database. After apply, don't forget data dummy
on mockup.test. If everything set up, make sure no jobs, hand over, commit then end of this session."

- **tmerp.test:** ketujuh laporan ada di Inventory > Warehouse > Report (grup Warehouse dipasang sesi Inventory & Warehouse pada
  hari yang sama; migrasi `2026_10_03_000010_menu_inventory_stock_reports_live` = cadangan untuk database tanpa grup itu),
  `MenuRoutes` membukanya dari menu lama, Help 6 topik diimpor, kamus sudah ada.
- **Sidebar menu lama dua tingkat:** `pages/main-menu` mengangkat grup di dalam grup jadi grup sendiri ("Warehouse › Report",
  "Warehouse › Setup") -- sebelumnya grup bersarang tampil sebagai baris mati sehingga isinya tidak bisa dibuka.
- **Pemeriksaan sambungan** (kedua database): tabel / view sumber tiap laporan ada, baris terbaca, cetakan tersusun, baris menu
  aktif dan mengarah ke layarnya -- 7 dari 7 di ddt dan di tm2.
- **Stock in Inspection + widget Incoming QC:** "belum dinilai" = `qc_quality_status` PENDING (bawaan kolom, bukan NULL) atau QC qty
  belum DONE, pada penerimaan DRAFT / QC_PENDING; qty ditolak tampil sebelum maupun sesudah diposting. Widget dulu selalu 0.
- **Data dummy laporan** `Database\Seeders\UatStockReportSeeder` (ddt saja, sesudah `UatWarehouseSeeder`): 6 posisi stok baru,
  17 dokumen (Stock In, Stock Out, 6 opname + 1 penyesuaian Posted, 1 opname menunggu approval, 1 penyesuaian Draft) lewat
  `StockDocumentService` + `StockPostingService` berurutan tanggal, bin diblokir berisi stok, 2 penerimaan barang (menunggu QC,
  ditolak sebagian). Jadwal otomatis dirapatkan supaya tidak ada dokumen di periode akuntansi tertutup (aturan 05.23).
  Disimpan sebagai set dummy `stock-reports` (Developer > Data Dummy).
- Uji: `StockReportsTest` 8 test (tambahan: baris penerimaan menunggu QC / ditolak, laporan terjangkau dari sidebar dua tingkat);
  daftar grup Settings di `MainMenuShellSmokeTest` disamakan dengan menu sekarang.

## 2026-10-02 -- Laporan stok semua kelas: Stock Card, Recapitulation, Summary, Opname, Loss & Gain, Stock in Inspection

User: "build all reports related with inventory movement, stock card and other reports" ... "stockcard, summary all stocks,
recapitulation of all items, inspection stocks, report items group by location warehouse, loss and gain all items every
monthly" ... "reference from all application about warehouse and stock, include refer to popular ERP like SAP, odoo, dynamics
365, zahir, accurate, Oracle and other apps" ... "stock opname in indonesia, is most popular report to entry loss and gain".

- **Enam laporan baru** di Inventory > Warehouse > Report (semua kelas stok RM / SFG / FG dalam satu laporan, kolom Class):
  - **Stock Card** (`inv-rpt-card`): per item = baris saldo awal lalu tiap pergerakan urut tanggal dengan saldo berjalan item di
    semua bin; transfer punya kolom sendiri dan tidak mengubah saldo item; kotak Item = kartu satu item.
  - **Stock Recapitulation** (`inv-rpt-recap`): satu baris per item = awal, masuk, keluar, penyesuaian, akhir + nilai akhir;
    kolom tersembunyi nilai per gerak, harga rata-rata, Turnover; saringan With movement / No movement / Negative.
  - **Stock Summary** (`inv-rpt-summary`): nilai semua stok per kelas x kategori (awal, masuk, keluar, penyesuaian, akhir, porsi %).
  - **Stock Opname** (`inv-rpt-opname`): hasil hitung fisik dan penyesuaian per dokumen per item (qty sistem, qty fisik, selisih,
    nilai, Gain / Loss / Match), semua status dokumen.
  - **Stock Loss & Gain** (`inv-rpt-loss-gain`): selisih lebih / kurang per item per bulan dari penyesuaian yang sudah Posted.
  - **Stock in Inspection** (`inv-rpt-inspection`): barang menunggu QC, ditolak belum diretur, di area QC Hold, di bin diblokir.
- **Stock by Location** kini terbuka terkelompok per gedung ("report items group by location warehouse").
- **Periode dan cetak untuk Grid Report:** `DbGrid` mendapat `defaultPeriod()` / `period()` (bilah Period + Show di atas grid,
  `?from=&to=`), `params()` (saringan teks), dan `printable()` -> tombol Print membuka cetakan objek DevLib `x-dl::report`
  (route `v2.report.print`, view `v2/print-report.blade.php`: kop, kriteria, ringkasan, kelompok + subtotal, tanda tangan,
  A4 landscape). Laporan lama tidak berubah (tanpa periode = tanpa bilah).
- **Engine baru `App\Services\Inventory\StockLedger`** (terdaftar di Engine Catalog): saldo awal per posisi + pergerakan
  periode -> saldo akhir, qty dan nilai; nilai gerak = selisih nilai posisi sebelum dan sesudah baris, sehingga
  awal + masuk - keluar + penyesuaian = akhir selalu cocok. `StockItemMeta` = identitas item lintas kelas (dipakai juga
  laporan per kelas).
- **Acuan ERP lain:** `docs/md_for_systems/inventoryreport_references.md` (SAP, Odoo, Business Central, D365 SCM, NetSuite,
  Zahir, Accurate, ERPNext, QuickBooks; yang tidak terverifikasi ditandai). Dokumen review: `inventoryreport_review.md`.
- Menu (`2026_10_02_000220`) + kamus Indonesia (`2026_10_02_000221`) + Help 6 topik (`resources/data/help/inventory_reports.json`).
  Menu hanya di ddt (grup Warehouse belum ada di tm2sys); kamus di ddt + tm2.
- Uji: `tests/Feature/StockReportsTest.php` (6 test: buku periode, kartu, rekap = ringkasan, opname vs loss & gain, stok di bin
  diblokir, periode dari alamat + layar dan cetakan terbuka). Dicek di ddt: total nilai akhir rekap = total nilai stok view saldo.
- **Cetakan DevLib (`x-dl::print-out` / `x-dl::report`):** lembar dianggap penuh saat AREA ISI penuh (dulu yang diukur lembarnya,
  sehingga kira-kira dua baris terakhir tercetak menimpa kaki halaman pada tabel panjang); lembar diukur ulang setelah font termuat.
- Tangkapan layar: `docs/mockups/inventory_report_app/`.
- **Perbaikan terkait (commit sebelumnya):** widget Stock Documents menghitung dokumen dari jenis kartu stok (IN / OUT / AD),
  bukan dari angka 1 / -1 kepala dokumen (dulu barang masuk dan keluar selalu 0).

## 2026-10-02 -- Opening Dashboards tahap 4: Production, Quality, Inventory, WI / JD / SOP; asisten AI dipasang di tm2

User: "pasang saja nanti juga bisa disempurnakan" (asisten di tmerp) dan "perlu ada production, quality control and quality
assurance, inventory and warehousing, payroll, human resource, Work Instruction, job description, SOP. On production, target vs
result, capacity planning".

- **Asisten AI di tm2:** migrasi `packages/assistant/.../2026_10_02_000080` + `2026_10_02_000081` dijalankan di database live
  (tabel `sys_assistant_message`, `sys_assistant_metric`, 2 menu, 11 konstanta). Perbaikan mesin asisten: perintah
  (`Assistant\Contracts\Command`, mis. "buka dashboard sales") tetap dijalankan walau kalimatnya memakai kata "buka" /
  "tampilkan"; kalimat "cara ..." tetap mencari bantuan.
- **Empat dashboard baru** (migrasi `2026_10_02_000164`, ddt + tm2): Production, Quality Control & Assurance, Inventory &
  Warehousing, Work Instruction / JD / SOP. Urutan tab: Overall, Sales, Purchasing, Production, Quality, Inventory, AR & AP,
  Cashflow, Payroll, Human Resource, Dokumen. Total 11 dashboard, **67 widget** (18 baru).
- **Production:** target vs hasil (KPI + grafik per periode; target = qty operasi Work Order terjadwal selesai, hasil = qty
  baik yang dicatat), Capacity Load dan Machine Load (jam terjadwal vs jam tersedia = jam per shift x shift per hari x hari
  kerja dari Master Mesin; mesin tanpa jam = "No capacity set", tidak dikarang), Operations by Status, Unplanned Downtime
  (yang masih berhenti dihitung sampai sekarang).
- **Quality:** Incoming QC Waiting, Incoming QC Pass Rate, Production QC, Complaints by Category, Waiting for QA Review.
- **Inventory:** Stock Value by Warehouse, Stock Documents, Goods Receipts in Progress (+ Stock Value, Deadstock Value pindah ke sini).
- **Dokumen kerja:** My Job Description (jenis widget DevLib baru `text`), Job Descriptions Filled, SOP, Work Instructions.
  Tab ini tanpa syarat modul (dokumen kerja untuk semua); kelengkapan JD hanya untuk yang boleh membuka HRM.
- **Work Instruction:** kolom baru `tm2mst.sop_document.doc_type` (SOP | WI); form SOP mendapat pilihan Document Type, grid v2
  kolom + saringan Document Type, menu `HRM_SOP` menjadi "SOP & Work Instruction". 3 SOP + 3 WI contoh hanya di ddt.
- Angka baru di `App\Support\V2\Board\Numbers` (production, workDays, capacity, downtime, operationStatus, incomingQc, qaWaiting,
  complaintsByCategory, stockByWarehouse, stockDocuments, receiptsWaiting, documents, jobDescriptionCoverage).
- Test `OpeningBoardTest` 14 test (ddt + tm2), sisa data uji dicek 0 di kedua database. Dokumen: `openingboard_review.md`,
  `openingboard_references.md`, pedoman C.33, Help (panduan + SOP & Work Instruction), gambar `docs/mockups/opening_board_app/11`–`14`.
- Belum: jam per shift 45 mesin di tm2 (data nyata, menunggu user), target produksi dari manajemen, WI bergambar / lampiran.
- Baseline dummy 2026-10-02 19:33:33 sudah memuat kolom `doc_type` dan dokumen contoh (`ddt:baseline check` = tanpa perubahan).
- **Job Description General Manager** (user, melihat widget kosong: "build for me"): migrasi `2026_10_02_000165` mengisi
  `org_position.job_description` jabatan `GENERALMANAGER` di ddt + tm2, **hanya bila masih kosong** (teks HR tidak ditimpa),
  `job_description_updated_by = 'claude (draft)'`. Isi disusun dari bagan pimpinan 2026-09-28 (atasan Presiden Direktur, bawahan
  Operational Manager; Finance & Accounting dan Product Costing di luar jabatan ini) — **draf, menunggu koreksi user**; diubah di
  Human Resources → Master Posisi. Baseline dummy disimpan ulang 19:49:49. Di tm2 widget masih "akun belum terhubung ke jabatan"
  karena `sec_user` johiwan belum ditautkan ke pegawai di database live (menunggu izin user).

## 2026-10-02 -- Reset data dummy: penjaga drift struktur (baseline vs ddt* sekarang)

Masalah: `ddt:baseline restore` memasang ulang `CREATE TABLE` dari dump, sedangkan tabel `migrations` ada di `ddtsys` yang tidak
ikut di-reset. Migration yang jalan setelah baseline disimpan -> Reset menghapus kolom barunya padahal migration tercatat sudah
jalan -> layar gagal "Unknown column". Terukur pada baseline 2026-10-02 14:29:41: `ddtsdi.do_line` kehilangan 3 kolom,
`ddtacc.fin_invoice` 21 kolom; `ddtacc.fin_company_bank` dan `fin_invoice_line` (tabel baru) tidak ikut di-reset.

- `DdtBaseline::dumpSchema()` (baca blok `CREATE TABLE` dump), `diffSchema()`, `drift()`: daftar kolom baru / hilang dan tabel
  baru / hilang sejak baseline. Hanya membaca (file dump + `information_schema`), 0,3 detik untuk dump 100 MB.
- `restore` **menolak** selama ada drift (tidak ada yang diubah), menampilkan daftarnya dan meminta baseline baru disimpan
  dulu. Paksa: `--allow-drift`. Aksi baru `php artisan ddt:baseline check --env=mockup` (baca saja).
- Layar Developer -> Data Dummy (Reset): kartu "Structure changes since the baseline", tombol Reset mati selama ada drift.
  Reset yang ditolak kini tampil sebagai pesan gagal (sebelumnya tertulis "Reset selesai").
- Tabel yang dibuat setelah baseline: **dibiarkan apa adanya** (perilaku lama, tertulis di layar). Diputuskan atas mandat user
  ("do it with your best"): mengosongkannya akan ikut menghapus baris bawaan migration (mis. rekening `fin_company_bank`).
- Test `tests/Unit/DdtBaselineDriftTest.php` (5 test, teks contoh; tidak menyentuh database).
- **Baseline baru disimpan 2026-10-02 19:33:33** atas OK user (baseline lama 14:29:41 dicadangkan ke
  `C:\Users\iwanp\tmerp_backup\ddt-baseline_2026-10-02_1429`). Isi yang ikut dibekukan sejak baseline lama, semuanya data
  yang sengaja dibuat: 5 akun bank + 5 `fin_company_bank` (seed migration), 1 invoice + 1 POD dummy `uat`, 6 PR
  `uat_approval`, 6 dokumen SOP. `ddt:baseline check` = tidak ada drift, Reset tidak terkunci lagi.

---

## 2026-10-02 -- NoHardcodedDatabaseNameTest: baris 'reads' / 'writes' Engine Catalog dikecualikan

Masalah: sejak commit 8dca1e44 (Engine Catalog) `NoHardcodedDatabaseNameTest` gagal -- semua baris yang dilaporkan ada di
`app/Support/Engines/EngineRegistry.php`, yaitu `'reads' => ['tm2mst.prod_type.dieline_spec']` / `'writes' => [...]`. Itu daftar
tabel untuk dibaca manusia di layar Engine Catalog (dicek `EngineCatalogTest`), bukan SQL -> positif palsu.

- `tests/Unit/NoHardcodedDatabaseNameTest.php` (commit f66809a3): konstanta baru `ALLOWED_LINES` = berkas => pola baris. Baris
  yang diawali `'reads' => [` / `'writes' => [` **di berkas EngineRegistry.php saja** dilewati (sejenis pengecualian
  `'source' =>` milik widget dashboard). Baris lain di berkas itu, dan baris `'reads'` / `'writes'` di berkas lain, tetap
  diperiksa. `EngineRegistry.php` tidak diubah.
- Hasil: `NoHardcodedDatabaseNameTest` lulus (1 test), `EngineCatalogTest` lulus (6 test, 212 assertion).

---

## 2026-10-02 -- Engine Catalog + audit "semua layar turunan objek DevLib"

User: "pastikan semua di mockup dan tmerp merupakan turunan dari object oriented di devlib dan pastikan ada list ENGINE
yang terpasang di devlib dan jelaskan di sana keterangan parameter input dan outputnya dan cara kerjanya".

**Engine Catalog**
- Objek DevLib baru `x-dl::engine-catalog` (kartu per engine: kegunaan, cara memanggil, input, output, cara kerja, tabel
  dibaca / ditulis, setelan, layar pemakai, test, berkas sumber; kotak cari + saringan grup) dan `Devlib\Engine\EngineInspector`
  (tanda tangan method dibaca dari kode lewat reflection, jadi tidak bisa basi).
- `App\Support\Engines\EngineRegistry`: 20 engine — Dieline (TMDieline), Imposition, Spec (BOP · BOM · Ink), Usage Rate,
  Routing Template, Costing, Reverse Costing, Flow (Approval), CPDEL, CPDEL Two Promises, Material Planning, Work Order
  Generation, PDR, Scheduling Rule, Hot Job Option Generator, Speed Target, Purchase Quotation Matcher, Tax, Payroll, Assistant.
  Nama tabel dan letak dokumen dicocokkan ke database dan berkas yang ada. Costing: alurnya saja, tanpa rumus / angka (rahasia).
- Tampil di **devlib.test → Engines → Engine Catalog** (membaca `resources/data/v2/engines.json`, dibuat
  `php artisan engine:catalog`) dan di menu **Developer → Engines** aplikasi (`/v2/engines`, Administrator / grup DEVELOPER).
- Test `EngineCatalogTest`: kelas `*Engine` / `*EngineService` baru wajib terdaftar; tiap entri punya input, output, cara
  kerja; berkas JSON tidak boleh tertinggal dari kode.

**Audit turunan DevLib (layar v2 = mockup.test dan tmerp.test, kode yang sama)**
- Isi layar v2: 1.494 pemakaian `x-dl::*` + 16 `x-mp::*`; semua kelas komponen `x-dl::*` dan `x-mp::*` turunan
  `DevlibComponent`; tidak ada komponen v1 (`x-ui` / `x-form` / `x-grid` / Livewire) di layar v2.
- HTML mentah yang tersisa sebelum pekerjaan ini: 66 `<form>` pembungkus, 1 tombol, 2 tabel, 23 input tersembunyi.
- Objek baru `x-dl::action-form` (form kecil tanpa panel; token CSRF dipasang sendiri). **48 form di 22 layar dikonversi**;
  HTML hasil render dibandingkan sebelum / sesudah pada 108 halaman data dummy: tag form dan token identik.
- Sisa 18 form: zona beku menunggu izin user (inquiry-new 2, inquiry-recalc 2, inquiry-revise 2, _inquiry-duplicate 1,
  product-acc-forms 2, master-card-collection 2); customer-invoice 4 + customer-order 2 (sedang diubah sesi lain);
  maintenance-my-work 1 (form membungkus slot kartu). Juga 1 tombol di halaman publik share dan 2 tabel lembar ACC.
- Penjaga `DevlibOnlyRatchetTest`: angka HTML mentah di layar v2 tidak boleh naik.
- **Bukan turunan DevLib:** layar v1. Menu aktif tanpa layar v2: mockup.test 48 dari 227 item (43 di antaranya memang belum
  dibangun), tmerp.test 102 dari 193 item (menu `tm2sys` masih menu lama). Diganti layar v2 satu per satu.
- Rulebook D.15, D.16 + changelog.
- **Susulan (user: "Boleh selama tidak merubah alur dan fitur yang sudah ada"):** 17 form sisanya dikonversi — 11 di zona
  beku (inquiry-new, inquiry-recalc, inquiry-revise, _inquiry-duplicate, product-acc-forms, master-card-collection) dan 6 di
  customer-invoice / customer-order (berkasnya sudah tidak diubah sesi lain). Hanya tag pembungkus yang berganti; alur, isian
  dan tombol tidak disentuh. Bukti: 17 halaman data dummy tetap terbuka, tag form dan token identik sebelum / sesudah
  (form yang hanya muncul pada dokumen Revise / Recalc tersimpan tidak ikut terender karena dummy tidak punya dokumen itu).
  Total 65 form = `x-dl::action-form`; sisa 1 (maintenance-my-work, membungkus slot kartu). Penjaga `raw_form` 18 → 1.

## 2026-10-02 -- Klik logo / nama aplikasi = opening screen, semua tab ditutup

User: "jika saya click logo app atau nama app ERP TM maka opening screen akan muncul dan semua tab akan menutup" (alur
dikonfirmasi dulu, dijawab "benar").
- **Objek DevLib:** `x-dl::layer-menu` prop baru `home` — kepala panel (logo + nama aplikasi) menjadi tombol dan mengirim
  event `dl:layer-menu-action {key:'home'}`. `x-dl::layer-shell` memasang prop itu; `Shell::goHome()` (`shell.js`) menutup
  semua tab dan menampilkan opening screen. Posisi menu di kiri tidak berubah.
- **Isian belum di-Save:** bila ada tab yang form-nya sudah diubah, muncul satu pertanyaan (Cancel / Close all tabs) sebelum
  tab ditutup; tanpa itu langsung tertutup.
- Tampilan tidak berubah (kursor tangan + garis fokus keyboard). Teks baru di `Devlib\Lang` (ID + EN). Tanpa `npm run build`:
  berkas JS / CSS DevLib disajikan langsung.
- Test: `V2ShellTest` (kepala panel bertanda `data-lm-home` di Shell, tidak di Layer Menu yang berdiri sendiri). Perilaku
  klik di browser belum diuji Claude — diuji user.
- Rulebook C.18 + changelog.

## 2026-10-02 -- Opening Dashboards tahap 3: 49 widget, Overall dengan centang, enam dashboard bidang, gaya layar ponsel

Perintah user: widget sebanyak mungkin (20 - 30), direksi memilih dengan tanda centang untuk dashboard Overall; dashboard
Sales / Purchasing / AR AP / Cashflow / Payroll / Human Resource berupa template; widget dashboard bidang itulah yang bisa
dicentang ke Overall; cari referensi dari ERP lain; menambah widget semudah layar Android.
- **Referensi:** riset dashboard Business Central, SAP, NetSuite, Odoo, ERPNext, Zoho, PrintVis, Accurate / Jurnal ->
  `docs/md_for_systems/openingboard_references.md` (padanan tiap widget, yang belum bisa dibangun dan kenapa, yang tidak
  terkonfirmasi).
- **33 widget baru** (total 49): Sales 5, Purchasing 4, AR & AP 5, Cashflow 4, Payroll 4, Human Resource 6, Finance 2,
  Production 2, Inventory 1. Angka baru di `Numbers` (CPDEL, antrian harga, menang / kalah, quotation terbuka, komplain, PO,
  PR, hutang, pembayaran, kas, laba rugi, payroll, pegawai, kehadiran, cuti, rekrutmen, Work Order, nilai stok). Kelas widget
  baru cukup `meta()` + `build()`.
- **Dashboard:** tabel `sys_dashboard` (layar Setup -> Opening Dashboards), `sys_widget.dashboard_code`,
  `sys_user_board.dashboard_code`; template profil MANAGEMENT menjadi OVERALL; template enam dashboard bidang (migrasi `000163`).
  Widget gaji hanya untuk yang berhak melihat nilai payroll.
- **DevLib `x-dl::widget-board`:** tab dashboard, Choose Widgets = daftar centang per kelompok dengan kotak cari dan label
  jenis, ubin + Add widget, tekan-tahan = mode atur, seret untuk memindah (aksi `move`), jendela pilihan tetap terbuka.
- **Asisten:** `buka dashboard <nama>`.
- **Dipasang di ddt dan tm2** (migrasi `000163` + `help:import`). Test `OpeningBoardTest` 12 test: tiap widget untuk tiap
  periode, konsistensi kas dan laba rugi, dashboard bidang, hak, seret. Uji browser tanpa jendela di mockup.test: 0 error.

## 2026-10-02 -- Opening Board tahap 2: dipasang di tm2, periode Harian – Tahunan, rincian Draft / Confirmed

Jawaban user atas review: "pasang saja di tmerp", "dijelaskan saja draft sekian dan confirmed sekian", "boleh dari daily
sampai yearly", widget berikutnya minta usulan (ditulis di `openingboard_review.md` bagian 6).
- **tm2:** migrasi `2026_10_02_000160` – `000162` + `help:import` dijalankan di database live. Papan jalan di `tmerp.test/v2`
  (uji browser tanpa jendela, akun johiwan, 0 error). Target di tm2 kosong; asisten belum dipasang di tm2.
- **Periode:** `Numbers` mengenal periode kalender (`range`, `trend`, `periodTarget`); pilihan tersimpan di
  `sys_user_board.period_code` (aksi papan `period`); DevLib `x-dl::widget-board` prop `periods` / `period`.
- **Draft / Confirmed:** `Numbers::split`, baris `note` baru di `x-dl::kpi`; Order Value (dulu `revenue_month`, sekarang
  `order_value`), New Customer Orders, Sales Today, Targets by Area, dan ringkasan salam merinci Draft di samping Confirmed.
- **Data dummy:** query builder papan lewat `Visible::apply`, jadi angka papan ikut setelan Dummy & Legacy Data.
- **Test:** `OpeningBoardTest` 9 test — lulus di ddt dan di tm2; baris uji dihapus, diperiksa ke kedua database.

## 2026-10-02 -- Opening Board: papan widget layar pembuka untuk top management (dibangun di ddt, menunggu review user)

Permintaan user: banyak widget termasuk grafik, bisa dipasang lewat asisten AI, top management dulu (jabatan lain masih
template). Dokumen review: `docs/md_for_systems/openingboard_review.md` (cara mencoba, potret layar asli, arti tiap angka,
yang perlu diputuskan). Aturan: `docs/globaluserrules.md` C.33 + E.3.
- **DevLib:** objek baru `x-dl::widget-board` (kisi 12 kolom; Customize: pindah, lebar, lepas, Add Widget, Reset to Template;
  tiap aksi dikirim ke server yang menjawab dengan papan tersimpan; event `dl:refresh` = `board` memuat ulang). `x-dl::dashboard`
  menampilkan papan bila datanya memuat `widgets`. Contoh di devlib.test (susunan di sesi).
- **Tabel (migrasi `2026_10_02_000160`, HANYA dijalankan di ddt):** `sys_widget`, `sys_board_template`, `sys_user_board`,
  `sys_user_widget`, `sys_kpi_target`, `sys_daily_quote` (366 baris dari berkas contoh), konstanta `COMPANY_WEATHER_CITY` /
  `COMPANY_LATITUDE` / `COMPANY_LONGITUDE`, menu Setup: Opening Board Widgets, Opening Board Templates, KPI Targets, Daily
  Quotes. Target contoh 12 bulan hanya di database dummy.
- **ERP:** `app/Support/V2/Board/` (`Board`, `WidgetCatalog`, `Numbers`, `Widget`, 16 kelas widget), route `v2.board`, layar
  pembuka v2 menampilkan salam + papan untuk yang boleh membuka Laporan Manajemen; login v2 mendarat di sana tanpa tab
  Laporan Eksekutif. Database tanpa tabel papan (tm2) = perilaku lama.
- **Asisten:** perintah `pasang / lepas widget ...`, `widget apa saja`, `reset widget` (`App\Assistant\Metrics\BoardWidgets`);
  paket asisten: `Contracts\Command` (perintah menang atas pertanyaan angka) dan `Reply::$refresh`.
- **Lain:** terjemahan Indonesia 127 teks (migrasi `000161`, ddt), Help 5 topik (`help:import`, ddt).
- **Test:** `OpeningBoardTest` 7 test / 58 pemeriksaan terhadap ddt (variabel `DB_*` dari `.env.mockup`); di tm2 dilewati.
  Data test dihapus; diperiksa ke database (0 baris tersisa).
- **Diuji di browser tanpa jendela (mockup.test, akun johiwan):** papan tampil, Customize tambah / pindah, asisten memasang
  dan melepas widget dengan papan berubah tanpa muat ulang, 0 error JavaScript.

## 2026-10-02 -- Layar pembuka: popup tidak bisa ditutup + Opening Dashboard dikembalikan ke main

- **Popup Material Shortage mengunci layar pembuka:** kerangka menu memakai `data-dl-open` (tab yang dibuka saat masuk),
  pustaka modal menganggap atribut itu "tombol pembuka modal" sehingga semua klik di kerangka ditelan. Kerangka sekarang
  memakai `data-dl-open-tab` (`shell.blade.php`, `layer-shell.blade.php`, `shell.js`).
- **Opening Dashboard per jabatan (`x-dl::dashboard`)** dibangun 2026-09-29 di cabang `origin/claude/funny-pasteur-6bmiir`
  (sesi cloud) dan tidak pernah digabung, jadi user tidak pernah melihatnya. Enam commit DevLib-nya (f7e72edd .. 1472c3f1)
  dipasang ulang ke `main`: komponen `Dashboard` (salam, jam, cuaca, JD / SOP, KPI per periode, target vs pencapaian,
  Target per Bidang, Pipeline Penjualan, Pencapaian Target per Sales, aktivitas, agenda, kata harian 366 baris), slot
  `dashboard` di Shell, halaman katalog "Opening Dashboard (per position)", akun contoh yuna / sania / carrij, mockup
  `docs/mockups/dashboard_home_sales.html`. Data = contoh `devlib/app/Support/DashboardData.php` (devlib.test, tanpa database).
- **Tidak ikut dipasang:** laci Layer Menu untuk layar HP (commit e0be9d52, bentrok dengan Layer Menu baru 2026-10-01) dan
  commit b3063eec (alamat shell `/menu` + `KeepScreensInShell`) -- keduanya menunggu keputusan user.
- **Belum:** pemasangan di mockup.test / tmerp.test dengan data dari database, dan papan widget yang bisa diatur lewat
  asisten AI (permintaan user 2026-10-02) -- menunggu persetujuan rancangan.

## 2026-10-02 -- Customer Invoice: bukti Surat Jalan + validasi customer, riwayat uang muka per CO, laporan piutang (ddt + tm2)

Perintah user: "sarankan yang terbaik dan jalankan dulu" (belum cukup wawancara pemakai); yang pasti: tiap tagihan terkait
Surat Jalan dan validasi customer atas yang terkirim (retur / rusak di jalan), uang muka jelas dari CO yang mana dan
riwayatnya terlacak. Ketentuan: `customerinvoice_accounting.md` 7A, 7B; pedoman A.21.
- **Proof of Delivery:** per baris qty diterima + **Reason for Difference** (Damaged in transit / Returned by customer /
  Short shipped / Other, wajib bila diterima < dikirim) + catatan kondisi (`do_line.receive_diff_reason`, `receive_note`;
  `DeliveryPromiseService::receivedLines`). Sesudah dicatat, baris terima tampil di layar dan tercetak di Proof of Delivery.
- **Invoice:** tab **Delivery** (Surat Jalan, dikirim / diterima / selisih + alasan, penerima, jam tiba, foto), tombol
  Open Customer Order, nomor uang muka di cetakan (Ref. Uang Muka). Confirm menolak invoice tanpa order / baris tanpa
  Surat Jalan dan uang muka tanpa order.
- **Uang muka:** `CustomerInvoiceService::downPaymentLedger` (ditagih → uang diterima → dipotong di invoice, saldo
  berjalan) tampil di Down Payment Invoice (tab Down Payment History + Down Payment Left), Customer Order, dan laporan.
- **Customer Order v2:** tab **Billing** hanya baca (`orderBilling`): angka order, rantai Surat Jalan → invoice, dokumen,
  riwayat uang muka.
- **Daftar / laporan baru:** To Invoice (`acc-to-invoice`, buka baris = buat invoice order itu), AR Aging
  (`acc-rpt-ar-aging`, pengganti umur piutang layar lama), Down Payment Ledger (`acc-rpt-dp-ledger`).
- **Akun bank per rekening:** 111210–111250 dibuat di `fin_account` dan dipasang ke lima rekening (ddt + tm2); tmerp.test
  sekarang bisa menerima uang. Pilihan bawaan lain yang dijalankan: kode seri, tanggal invoice = Surat Jalan terakhir,
  cetakan apa adanya.
- **DevLib:** judul kolom `x-dl::table` berbentuk `['Label', 'num' => true]` ikut diterjemahkan (`DevlibComponent`).
- **Migrasi `2026_10_02_000170`** (ddt + tm2, `--path`), Help 3 topik baru + 3 topik diperbarui, potret layar
  `docs/mockups/customer_invoice_app/5..11`.
- **Test:** `CustomerInvoiceTest` 10 test / 152 pemeriksaan, lulus di ddt dan tm2; sisa data test diperiksa: bersih.
- **Belum (menunggu wawancara pemakai):** retur sesudah invoice (Credit Note), nasib barang yang tidak diterima, bentuk
  validasi customer, uang muka tanpa order, PPh 23.

## 2026-10-02 -- Order to Cash dan Down Payment lama dihentikan; Customer Invoice menggantikan (ddt + tm2)

Perintah user sesudah review: "yang lama dihapus saja, gantikan dengan yang baru. yang lama disudahi saja".
- **Record Payment** di form Customer Invoice (pengganti "terima pembayaran" Order to Cash): satu penerimaan untuk satu
  atau beberapa invoice terbuka customer yang sama, sebagian atau penuh. `CustomerInvoiceService::openInvoices` +
  `receivePayment` (memeriksa customer, identitas, sisa, rekening + akun buku besar, lalu `ArApService::settle`), rute
  `v2.customer-invoice.payment`. Invoice lunas = Closed; Cancel ditolak sesudah ada pembayaran.
- **Diarsipkan** ke `C:\Users\iwanp\tmerp_unused\` (MANIFEST): `pages/finance/receivables.blade.php`,
  `pages/finance/dp-invoice.blade.php`, `Grid/Acc/ReceivableGrid.php`, `DownPaymentGrid.php`,
  `Print/SalesInvoicePrint.php`, `screen_finance_dp_invoice.md`, `mockup_down_payment.html`, `sales_invoice_a5.jpg`.
  Rute `finance.receivables` / `finance.dp-invoice`, entri `MenuRoutes` `FIN_AR` / `FIN_DP`, dan jenis cetak
  `sales-invoice` dihapus; Tanda Terima Uang Muka (`dp-receipt`) kembali ke layar Customer Invoice.
- **Migrasi `2026_10_02_000143`** (ddt + tm2): hapus baris menu `FIN_AR` dan `FIN_DP`, Customer Invoice menempati
  urutannya, hapus topik Help `acc-receivables` / `acc-dp-invoice`, terjemahan dialog Record Payment.
- **tm2 dipasang**: migrasi `000140`, `000141`, `000143` dijalankan satu per satu dengan `--path` (24 migrasi lain yang
  tertunda di tm2 tidak disentuh); Help `finance.json` + `master_setup.json` dimuat. Salinan baris menu dan Help yang
  dihapus: `tmerp_unused/database/retired_order_to_cash_rows_tm2_2026_10_02.json`. Lima rekening perusahaan di tm2
  tanpa akun buku besar -- penerimaan uang ditolak sampai Finance mengisinya.
- **Perbaikan ikut:** angka bulat di kotak isian form invoice terpotong nolnya (2.000.000 tampil 2); invoice lama tanpa
  baris Surat Jalan tampil dan tercetak sebagai satu nilai; ringkasan invoice Confirmed memakai nilai tersimpan.
- **Test:** `CustomerInvoiceTest` 8 test / 118 pemeriksaan di ddt (di tm2 5 test dilewati karena rekening belum punya
  akun buku besar); `UiRatchetTest` baseline turun (tombol 976 -> 952, warna 5653 -> 5609, modal 70 -> 68, tabel
  247 -> 243); `MainMenuShellSmokeTest` daftar Settings disesuaikan. Sisa data test diperiksa ke database: bersih.
- **Belum ada gantinya:** tabel umur piutang (AR Aging) dan invoice penjualan tanpa Surat Jalan.

## 2026-10-02 -- Customer Invoice + uang muka, Tahap 1 (dibangun di ddt, menunggu review user)

Perintah user sesudah melihat mockup: "jalankan dulu lalu buatkan dokumentasinya buat saya review". Dokumen review:
`docs/md_for_systems/customerinvoice_review.md` (cara mencoba, potret layar asli, aturan, batasan, yang perlu diputuskan).
- **Tabel (migrasi `2026_10_02_000140`, HANYA dijalankan di ddt):** `tm2sys.sys_doc_series` + `sys_doc_series_counter`,
  `tm2acc.fin_company_bank`, `tm2acc.fin_invoice_line`, 21 kolom baru `fin_invoice` (`invoice_number` boleh kosong selama
  Draft), `tm2sdi.do_line.qty_received`, view `v1_fin_ar_aging` hanya invoice POSTED. Isi awal: 4 seri (`INV_TM`,
  `INV_LJM`, `DP_TM`, `DP_LJM`), 5 rekening dari `tm2old.mst_bank_account` (izin user; di ddt akun buku besarnya sementara
  111200, di tm2 nanti kosong), konstanta `DP_VAT_ON_RECEIPT` = 1 dan `DP_DEFAULT_PERCENT` = 50, menu Customer Invoice,
  Company Bank Accounts, Document Number Series.
- **Aturan + jurnal:** `App\Services\Acc\CustomerInvoiceService` — Draft dari baris Surat Jalan yang sudah diterima
  (`qty_received - qty_invoiced`), harga dari CO, baris borongan ditagih sekali, usulan potongan uang muka, Confirm (nomor
  dari `App\Support\Docs\DocSeries` di dalam transaksi `tm2acc`, satu voucher: piutang / penjualan / PPN + potongan uang
  muka, `do_line.qty_invoiced` lewat koneksi yang sama), Cancel (jurnal balik, alokasi dan qty dilepas), tagihan uang muka
  tanpa jurnal, `receiveDownPayment` (Bank pada Uang Muka + PPN Keluaran; tagihan uang muka cara lama ditutup ke Piutang).
- **Layar v2:** `customer-invoice` (grid → + New Record → Draft → Confirm / Cancel, dialog Record Receipt) lewat
  `App\Support\V2\Data\CustomerInvoice` dan `routes/v2_finance.php`; lookup `fin-company-bank` dan `setup-doc-series`.
- **Proof of Delivery:** isian qty diterima per baris; `DeliveryPromiseService::applyReceived` (Received = qty kirim,
  Partially received = isian). Dilewati di database yang belum punya kolomnya.
- **Cetakan (belum A):** `customer-invoice`, `dp-invoice`. DevLib: `Document::$nbRaw` (kalimat pembuka berisi data),
  `x-dl::table` kolom `'raw' => true` (angka yang sudah diformat).
- **Lain:** terjemahan Indonesia + menu Down Payment lama dimatikan (migrasi 000141, ddt), contoh Draft `#367` dari
  DO/26/00001 (migrasi 000142, hanya ddt), Help 3 topik (`help:import`, ddt).
- **Test:** `CustomerInvoiceTest` 7 test / 102 pemeriksaan, dijalankan terhadap ddt (variabel `DB_*` dari `.env.mockup`);
  di tm2 dilewati karena tabelnya belum ada. Data test dihapus dan penghitung nomor dikembalikan; diperiksa ke database.
  Suite penuh di tm2: tidak ada kegagalan baru (yang gagal = menu Help di `MainMenuShellSmokeTest` dan
  `prod_machine_model` yang belum ada di tm2, keduanya dari pekerjaan lain).
- **Belum:** tm2 (migrasi 000140 + 000141), Customer Receipt v2 dan laporan (Tahap 2), Credit Note dkk (Tahap 3); sisa
  kecil di `backlog07`.

## 2026-10-02 -- Kamus penerjemah tidak lagi mencatat nilai data (raw())

Masalah: `DevlibComponent::translateTexts()` menerjemahkan semua prop teks (title, subtitle, description, ...) dan
`Dictionary::remember()` mencatat teks barunya — termasuk judul form / kartu yang berisi nomor dokumen dan nama customer
(`Sales Return · CC2600001`, `PT. SELATAN JADI JAYA`). Tiap dokumen yang dibuka menambah satu baris di Translation Dictionary.
- **Desain (pilihan a, penanda di teks):** `Devlib\Lang::raw($nilai)` (helper `raw()` di tmerp) menandai nilai data dengan dua
  karakter tak terlihat; `Lang::text()` menerjemahkan hanya label di sekitarnya dan membuang penandanya, jadi berlaku untuk
  semua komponen dan semua prop teks tanpa mengubah konstruktor satu komponen pun. Penanda dipilih (bukan objek pembungkus
  atau flag `:translate`) karena prop komponen bertipe `string` dan terjemahan berjalan sebelum atribut komponen terbaca.
  Grid DevLib tidak disentuh.
- **Jaring pengaman di `Dictionary::remember()`:** teks berisi nomor dokumen / `#5` (`looksLikeData`) dan teks yang sudah
  hasil terjemahan (layar memanggil `t()` lalu mengirimnya ke komponen, pesan flash) tidak dicatat. Pilihan (b) saja tidak
  cukup: nama seperti `PAK MUKRI` tidak bisa dikenali dari bentuknya.
- **Layar:** 30 berkas view v2 (57 baris) memakai `raw()` — judul / subjudul form dokumen, kartu, hint, pesan, tab berhitung.
- **Test:** `MultiLanguageAutoTest` (2 test baru) + penjaga `data_in_text_prop` di `MultiLanguageRatchetTest`, sisa 5:
  `proof-of-delivery.blade.php` (3; berkasnya sedang diubah sesi Customer Invoice, belum di-commit) dan zona beku yang
  menunggu izin user — judul kartu `ACC customer — ...` di `prodev.blade.php`, hint `Data lama: ...` dan judul kartu
  `$p['name']` di `inquiry-revise.blade.php`.
- **Pembersihan:** migrasi `2026_10_02_000150_clean_dictionary_data_values` menghapus baris `ui` yang dicatat otomatis, belum
  punya terjemahan, dan berisi nomor dokumen / nama master / teks hasil terjemahan / kalimat berangka. `ddtsys`: 59 baris;
  `tm2sys`: 0 (belum tercemar).
- **Belum:** pesan flash berisi data yang dikirim sebagai variabel utuh (`:message="$error"`) tidak bisa dibedakan dari
  label; yang bernomor dokumen tertahan jaring pengaman, sisanya tetap bisa tercatat.
- **Susulan (setelah sesi Customer Invoice meng-commit berkasnya):** `proof-of-delivery.blade.php` memakai `raw()` — hint
  KPI `kept / total`, label `Missed: <departemen>`, judul form (no. Delivery Note) dan subjudul (nama customer). Penjaga
  `data_in_text_prop` turun 5 → 2; sisanya hanya zona beku (`prodev.blade.php`, `inquiry-revise.blade.php`), menunggu izin user.
- **Zona beku (user: "Boleh kerjakan", setelah ditunjukkan sebelum → sesudah):** tiga nilai data dibungkus `raw()` — judul kartu
  `ACC customer — <nama product>` di `prodev.blade.php`; judul kartu part `$p['name']` dan hint `Data lama: <bahan lama>` di
  `inquiry-revise.blade.php`. Tampilan, alur dan data tidak berubah; penjaga `data_in_text_prop` 2 → 0.

## 2026-10-02 -- Customer Invoice + AR: keputusan A–E, dokumen akuntansi, Qty Minim = harga borongan

User memberi empat lampiran sistem lama (invoice dan tagihan uang muka: layar + cetakan) dan meminta rencana terbaik
modul Customer Invoice yang terhubung / menyatu dengan AR; lalu menjawab lima keputusan (A ya dua seri, B setuju, C ikuti
saran + "semua didokumentasi jelas urusan keuangan dan akuntansi", D ok, E "untuk saat ini boleh").
- **Rencana + analisa:** `docs/md_for_systems/customerinvoice_plan.md` (data lama `tm2old.sdi_cinv*`, kode ScriptCase,
  software di pasar, aturan Indonesia; keputusan di bagian 8).
- **Ketentuan keuangan dan akuntansi:** `docs/md_for_systems/customerinvoice_accounting.md` — dua identitas, dokumen,
  penomoran, akun, jurnal per peristiwa, tiga contoh lengkap, pajak, piutang, pengendalian, riwayat keputusan.
- **`downpayment_tier1-4.md`** diberi blok perubahan: tagihan uang muka tidak menjurnal; jurnal dan PPN saat uang diterima.
- **Buku aturan:** `globaluserrules.md` A.21 + changelog.
- **Kode (keputusan E):** `CoOrderItem::recalcSubtotal` dan `DoLine::recalcSubtotal` — baris Qty Minim bernilai harga,
  bukan qty × harga; mengubah tanda Qty Minim di Customer Order menghitung ulang nilai baris
  (`pages/co/entry.blade.php::updateItem`). Test `QtyMinimLumpSumTest` (5, tanpa menulis ke database). Tidak ada baris
  Qty Minim di `ddtsdi` maupun `tm2sdi`, jadi tidak ada data yang berubah.
- **Belum:** modulnya sendiri (Tahap 0 mockup menunggu OK user, lalu Tahap 1–3; backlog07).

## 2026-10-02 -- Penerimaan: nomor lahir saat posting (urutan SPB tidak bolong)

User setuju usulan: Draft tidak bernomor; nomor SPB / PBC / PBS diberikan saat dokumen diposting.
- `PvBuilderService::createFromLines` dan `SpecialReceiptService::createCustomer|createSubcon` membuat Draft dengan `doc_no`
  kosong. `PvPostingService::post` menetapkan nomor sebelum dokumen stok dan jurnal dibuat (keduanya merujuk nomor itu) dan
  menyimpannya bersama status POSTED; gagal posting stok = nomor tidak terpakai. `SpecialReceiptService::post` memberi nomor
  di dalam transaksi status. Dokumen lama yang sudah bernomor (SPB2600001 QC_PENDING) memakai nomornya sendiri.
- Tampilan Draft: `PvReceipt::label()` = "Draft #id"; grid v2 "DRAFT-id"; strip form "(on Post)" (kamus Indonesia: migrasi
  000104, ddt + tm2); layar v1 `pur/pv` ikut memakai `label()`.
- Test: `PvStockIntegrationTest` kini memeriksa Draft tanpa nomor dan nomor yang lahir saat posting. Diuji juga lewat layar
  (Draft tanpa nomor, posting bahan customer -> PBC2600001) di dalam transaksi yang di-rollback: tidak ada baris tertinggal.

## 2026-10-02 -- Penerimaan: tiga judul dan tiga seri nomor (SPB / PBC / PBS)

User: field "Jenis" di SPB tidak perlu; penerimaan dari customer dan dari subkon diberi judul dan penomoran sendiri, karena SPB
akan dipakai accounting untuk memeriksa tagihan supplier dan nomornya harus urut.
- `PvReceipt::nextDocNo($type)` + `NUMBER_PREFIX`: PO = `SPB`, CUSTOMER = `PBC`, SUBCON = `PBS` (`SpecialReceiptService`
  memakai serinya sendiri). Tidak ada dokumen lama yang perlu dinomori ulang (ddt dan tm2: semua penerimaan berjenis PO).
- Cetakan `goods-receipt`: judul per jenis -- Surat Penerimaan Barang / Penerimaan Bahan Baku Customer / Penerimaan Barang
  dari Subkon; tanpa field Jenis; rujukan No PO atau No CO; subkon menampilkan No SJ Subkon. QC Inspection ikut label nomornya.
- Mockup: `GoodsReceipt` tanpa Jenis + `CustomerMaterialReceipt`, `SubconGoodsReceipt`; hasil aplikasi di
  `docs/mockups/print_doc/app/` (dokumen PBC / PBS dirender di dalam transaksi lalu rollback, tidak ada baris tertinggal).
- Test `GoodsReceiptNumberSeriesTest` (hanya membaca). **Catatan untuk user:** nomor lahir saat Draft dibuat, jadi Draft
  yang dihapus masih meninggalkan nomor loncat -- belum diubah, menunggu keputusan user.

## 2026-10-02 -- Tanda Terima Tagihan Supplier (entry baru v2) + cetakan Tanda Terima Uang Muka dan QC Inspection

Perintah user setelah melihat mockup cetakan tahap 3: "bukan tagihan supplier tapi tanda terima tagihan supplier", "tanda
terima uang muka juga boleh dibuat", "cukup satu [SPB] ... yang perlu malah QC Inspection untuk barang yang masuk", "ya buatkan
nanti saya check kan". Format cetak ketiganya **belum A**.
- **Supplier Invoice Receipt** (Finance → Transaction Flows, menu `FIN_AP_RECEIPT`, layar `supplier-invoice-receipt`): tabel
  `tm2acc.fin_invoice_receipt` + `fin_invoice_receipt_line` (migrasi 000100, ddt + tm2), model `FinInvoiceReceipt(+Line)`,
  aturan di `App\Support\V2\Data\SupplierInvoiceReceipt`. Grid → + New Record (pilih supplier) → Draft: yang menyerahkan,
  syarat / rencana / cara bayar, lampiran, baris invoice (pilih tagihan `fin_invoice` SUPPLIER_BILL yang sudah dicatat = isi
  otomatis saat Save, atau ketik) → Confirm (nomor `TTyy#####`) → Cancel wajib alasan. Bukan bukti bayar, tidak membuat jurnal.
- **Cetakan baru** (`App\Support\V2\Print`): `supplier-invoice-receipt`; `dp-receipt` (tanpa tabel sendiri: mencetak
  pembayaran uang muka yang sudah tercatat, nomor = nomor voucher bank, id = id tagihan uang muka); `qc-inspection` (dari SPB:
  cek jumlah, mutu, diterima, ditolak, catatan QC). Halaman cetak punya tombol pindah format: Goods Receipt / QC Inspection
  dan Invoice / Receipt (`PrintDocs::formats`). Konstanta penanda tangan `SIGN_SIR_*`, `SIGN_DPR_*`, `SIGN_QCI_*`.
- **DevLib `Devlib\PrintDoc\Document`:** judul slot tanda tangan kosong = slot tidak dicetak (tanda terima tanpa "Approved
  By"); `titleCompact` = judul panjang dengan huruf lebih kecil.
- **Dummy hanya ddt:** dua tanda terima (TT2600001 Confirmed, satu Draft) + satu tagihan uang muka 30% CO terbaru yang sudah
  dibayar (migrasi 000101, 000102). Kamus Indonesia layar: migrasi 000103 (ddt + tm2).
- **Mockup** (`docs/mockups/print_doc/`): "Bukti Barang Masuk" dibuang, kelas `QcInspection`, `DownPaymentReceipt`,
  `SupplierInvoiceReceipt`; hasil aplikasi di `app/supplier_invoice_receipt_a5.jpg`, `down_payment_receipt_a5.jpg`, `qc_inspection_a5.jpg`.
- Test: `SupplierInvoiceReceiptTest` (2, membersihkan sendiri; diperiksa ke database), `PrintDocDocumentTest` +1.
  **Belum:** dicoba user di browser; pencatatan pembayaran uang muka masih di layar v1 (Down Payment).

## 2026-10-02 -- CPDEL: baris berwarna menurut jatuh tempo (aturan C.32)

Keputusan user setelah melihat pratinjau: Sabtu kerja, Minggu + hari besar (Holiday Calendar) libur; batas kuning bisa diubah
sendiri; warna lembut dengan garis tegas di bawah baris. Filter otomatis per peran **belum diputuskan** (jangan dibangun).
- **DevLib grid:** prop baru `row-tone="<kolom>"` (danger / tangerine / yellow / success / accent = warna lembut + garis bawah;
  muted = huruf abu-abu) dan kolom badge `toneKey`; token `--dl-tangerine`, `--dl-yellow`; contoh di demo `grid-status`.
- **Hari kerja:** `App\Support\WorkCalendar` (Office Hours + Holiday Calendar). `CpdelPromiseService::due()` menentukan
  bucket, teks Due, dan warna; `Cpdel::rows()` mengurutkan yang belum dikirim dulu (slot paling awal di atas).
- **Layar CPDEL:** kolom Due; filter kiri = waktu (Overdue, Today, Due soon, Next 7 days), kanan = janji; "Late" dilebur ke
  Overdue; filter "Ship today" / "Slot time not set" yang dipasang pagi ini dilepas.
- **Konstanta** `CPDEL_DUE_SOON_DAYS` = 2 (Settings → Constants) + kamus Indonesia: migrasi 000110, ddt + tm2 (`--path`).
- Help topik `cpdel` (langkah + field Due) di-impor ulang hanya topik itu. Test `CpdelDueColourTest` (tanpa menulis database).
- Gambar hasil komponen asli: `docs/mockups/cpdel_row_colour.jpg`. **Belum:** dilihat user di browser. Kolom induk tampil
  jadi 9 (aturan C.28: 5-8) — menunggu keputusan user kolom mana yang disembunyikan.

## 2026-10-02 -- Tindak lanjut audit: PPN dari konstanta, 3 layar lookup baru, menu akun v2 tersambung database

Urutan perbaikan disetujui user ("setuju selama itu yang terbaik dan tidak menjadikan problem baru"). Rincian + status tiap
butir: `docs/audit_input_report_lookup_2026_10_02.md` bagian G. Semua di ddt + tm2 (migrasi `--path` 000090..000092, `help:import`).
- **Pajak dokumen penjualan** (`App\Support\Finance\TaxRate`): `CoOrder::totals()` membaca konstanta `PPN` / `PPH23_JASA` dan
  **Non PPN = tanpa PPN**; `QuoQuotation::totals()` + label Tax scheme membaca `PPN`. Tidak ada CO Non PPN di kedua situs, jadi
  angka dokumen lama tidak berubah. Test baru `SalesDocumentTaxTest` (tanpa menulis ke database).
- **Grid Lookup baru:** Master Data › **Sales Person** (`md-sales-person`), Master Data › **Payment Terms** (`md-payment-term`),
  Human Resources › Organization › **Department** (`hrm-department`) + Help masing-masing. `DbLookup::defaults()` (kolom wajib
  yang tidak tampil, mis. `company_id`). `FinPaymentTerm` memakai PK `id_payment_term`; dua `find(kode)` di Receivables /
  Payables diganti `firstWhere('payment_term_code', ...)`.
- **Menu akun v2** (objek DevLib Shell, prop baru `account`; tanpa prop = pratinjau katalog seperti semula): Change password
  dan Edit profil benar-benar menyimpan (`App\Support\V2\Data\Account`, `routes/v2_account.php`), Notifikasi membaca
  `sys_notifications`. Telepon contoh dan tombol "Ubah foto" tidak tampil di situs ERP.
- **Belum dikerjakan, menunggu OK user:** dimensi terpotong di Inquiry Revise + "Qty per Pack" di ProDev (zona beku, preview
  sebelum → sesudah sudah diberikan).
- Aturan user dicatat: rulebook D.13 (folder acuan `docs/` tidak diarsipkan) dan D.14 (zona beku: preview dulu).
- Test suite: gagal 1 bukan dari pekerjaan ini (`MainMenuShellSmokeTest` daftar Settings belum memuat Help Topics / Help Lines
  milik sesi Help) + 1 error lama `prod_machine_model` di tm2.

## 2026-10-02 -- CPDEL: tujuh kotak KPI di atas grid dilepas

Perintah user: "tujuh kotak di atas itu diganti dengan yang lebih sederhana, atau dihilangkan juga boleh" (sejalan aturan C.21).
- `resources/views/v2/screens/cpdel.blade.php`: blok `dl-kpis` (7 kotak) dihapus. Empat hitungan status sudah ada di baris
  filter grid; **Ship today** dan **Slot time not set** pindah ke baris filter yang sama (sisi kanan, `tabs`).
- `App\Support\V2\Data\Cpdel::rows()` menambah penanda `today` / `no_time` (hanya baris belum dikirim); `monitor()` dihapus (tak terpakai).
- Persen "Delivery promise kept" tidak lagi di CPDEL; tetap ada di layar Proof of Delivery.
- Help topik `cpdel` (langkah 1) disesuaikan, di-impor ulang hanya topik itu di ddtsys + tm2sys.
- Diperiksa: render layar di ddt + tm2 tanpa kotak, hitungan filter cocok dengan data. **Belum:** dilihat user di browser.

## 2026-10-02 -- Chat seketika: Laravel Reverb + "sedang mengetik…"

Perintah user: "jalankan dengan reverb, bisa seketika dan bisa ada tulisan sedang mengetik, beban server hampir nol".
- **Kenapa server terpisah:** `composer require laravel/reverb` di tmerp menuntut Guzzle 8 -> 7 (dan menaikkan Laravel);
  itu menyentuh seluruh aplikasi (Firebase, Google). Jadi Reverb dipasang di aplikasi kecil **`realtime/`** dengan `vendor/`
  sendiri (`php artisan reverb:start` atau `start.bat`, port 8080); `composer.json` / `composer.lock` tmerp tidak berubah.
- **Paket `packages/chat`:** `Chat\Realtime` berbicara protokol Pusher sendiri (tanda tangan kabar + izin channel), tanpa
  pustaka broadcast. Rute baru `chat.typing`, `chat.auth`. Kabar yang didorong: `chat.message`, `chat.read`, `chat.typing`,
  `chat.presence`. Channel privat per user + satu channel bersama, dipisah per database (`ddtsys` / `tm2sys`).
- **DevLib `x-dl::chat`:** klien WebSocket bawaan browser (tanpa Laravel Echo / pusher-js), "sedang mengetik…" di kepala
  percakapan dan di daftar, poll melambat ke 30 detik selama tersambung dan kembali 5 detik bila putus.
- **Setelan:** `.env` + `.env.mockup` `CHAT_REALTIME_*` (kunci sama dengan `realtime/.env`), konstanta
  `CHAT_REALTIME_POLL_SECONDS` 30 (ddtsys + tm2sys). `phpunit.xml` mematikan dorongan saat tes.
- Diuji: 7 tes `ChatTest` (tanda tangan kabar, izin channel, tanpa server tetap jalan); pendengar WebSocket sungguhan
  menerima kabar dari paket lewat Reverb dan langganan tanpa izin ditolak; render headless dengan WebSocket tiruan.
  **Belum:** dicoba user dengan dua login; server belum hidup otomatis saat komputer menyala.

## 2026-10-02 -- Chat antar user ala WhatsApp (menu akun v2), tersambung ke database

Perintah user: chat cara kerja seperti WhatsApp; orangnya = user terdaftar; online = yang login, warna cerah; lampiran file
dibatasi 20 MB dulu; bisa bunyi, belum voice. Rulebook **C.31**. Menutup temuan audit 2026-10-02 B.1 #3 (chat = data contoh
`DemoData::chat()`, pesan hanya hidup di JS) dan backlog 09 §16 ("New Chat" ala WhatsApp, 2026-09-09).
- **Modular (perintah user di tengah pekerjaan: "harus modular, bisa saya ambil buat app yang berbeda"):** bagian server =
  paket berdiri sendiri **`packages/chat`** (`Chat\ChatServiceProvider`: rute `chat.*`, migrasi tabel, `ChatService`,
  `ChatController`, `ReadOnlySession`, model, `config/chat.php`); paket tidak mengenal `sec_user`, `sys_constant`, maupun
  kamus. Yang khas tmerp hanya `App\Services\Chat\UserDirectory` (siapa usernya), `config/chat.php`, dan tiga baris kait di
  `AppServiceProvider`. Cara memasang di aplikasi lain: `packages/chat/README.md`.
- **Database (ddtsys + tm2sys; di tm2 dengan `--path`):** migrasi paket `2026_10_02_000060_chat_attachments_presence` —
  `sys_chat_messages` + `attachment_path / _name / _mime / _size`; `sys_chat_conversations.pair_key` (UNIQUE, satu percakapan
  per pasangan user); `sys_chat_participants.last_read_message_id`; tabel baru `sys_user_presence` (denyut login). Migrasi
  tmerp `2026_10_02_000062_chat_constants` — kategori **CHAT**: `CHAT_ATTACHMENT_MAX_MB` 20, `CHAT_POLL_SECONDS` 5,
  `CHAT_ONLINE_TIMEOUT_SECONDS` 90.
- **Cara kerja:** layar bertanya ke `poll` tiap 5 detik (sekaligus denyut online); `poll` + `upload` tidak menulis sesi
  (flash layar lain tidak tertimpa dan sesi tetap bisa kedaluwarsa); event `Logout` -> langsung tidak online.
  `composer.json` + `bootstrap/providers.php` bertambah satu baris (checkout lain: `composer dump-autoload`).
- **DevLib:** `x-dl::chat` dapat prop `api` (kosong = pratinjau katalog seperti dulu). Baru: lampiran file apa saja (kartu
  unduh, gambar tampil langsung, garis kemajuan), kirim berpotong 1 MB (batas upload PHP di Herd masih 2 MB), avatar cerah /
  abu-abu, centang dari server, "Pesan sebelumnya", bunyi, pemberitahuan. Teks JS pindah ke `T('ch_*')`.
- **Tidak dibuat:** data dummy chat (pesan atas nama orang lain akan menyesatkan), grup, voice. Panel chat menu lama (v1,
  Livewire `UserPanels`) tidak diubah; tabelnya sama.
- Diuji: `tests/Feature/ChatTest.php` (4 tes: kirim -> poll -> belum dibaca -> dibaca, lampiran berpotong + hak buka,
  hanya user terdaftar, sign out) + render headless komponen dengan server tiruan. Belum diuji di browser dengan dua login.

## 2026-10-02 -- Logo perusahaan di kop cetakan + logo & nama aplikasi di menu v2

Perintah user: logo TM (`docs/attachments/Asset 3@4x.png`, `Asset 4@4x.png`) masuk database; logo & nama aplikasi **jangan
diubah** dan dipasang juga di mockup.test; cetakan memakai logo perusahaan (hitam-putih untuk versi BW, warna berlatar putih
untuk versi warna). Rulebook **C.30**.
- **Database (ddtsys + tm2sys):** `sys_constant` `COMPANY_LOGO_PATH`, `COMPANY_LOGO_BW_PATH`, `COMPANY_LOGO_INVERSE_PATH`
  (BRANDING / COMPANY) lewat migrasi `2026_10_02_000061_company_logo_constants` (di tm2 dijalankan dengan `--path`, migrasi
  tertunda lain tidak ikut). File turunan lebar 1600 px di `resources/branding/` -> disk public `branding/`.
  `APP_LOGO_PATH` tetap kosong (lencana gradasi), `APP_NAME` / `APP_TAGLINE` tidak disentuh.
- **Kode:** `App\Support\Branding\CompanyLogo` (baca / simpan / hapus, versi hitam-putih dibuat otomatis). Kop
  `x-dl::print-doc` (logo hitam-putih), `x-dl::print-out` / report (warna, Hemat Tinta -> hitam-putih), `x-print.header` v1.
  Kop PPN tidak berubah. Kepala `x-dl::layer-menu` = lencana logo aplikasi + nama + tagline (prop `logo`, `tagline`).
- **Input:** Settings > Identifikasi > Identitas Perusahaan — unggah logo latar terang + latar gelap, tombol Hapus.
- Diperiksa lewat render headless (menu, DO Non PPN, CO PPN, laporan warna + hemat tinta). `npm run build` dijalankan.

## 2026-10-02 -- Audit input / grid / lookup + arsip dokumen yang tidak relevan

Perintah user: cek (1) input tersambung ke database, (2) grid & laporan dari database, (3) lookup punya layar input,
(4)+(5) file `docs/` yang tidak relevan dipindah ke folder unused. **Hanya pemeriksaan, tidak ada alur / data yang diubah.**
- Hasil lengkap + usulan urutan perbaikan: **`docs/audit_input_report_lookup_2026_10_02.md`**.
- Dijalankan: probe GET 196 item menu aktif (196 OK), 111 grid v2 terhadap ddt (0 error), `data:audit`, test suite
  (257 lulus / 12 skip / 1 error lama `prod_machine_model` di tm2). 19 migrasi tertunda di tm2 (paket "ddt dulu") tidak dijalankan.
- Temuan utama: dialog Change password / Edit profil / Notifikasi / Chat di menu akun v2 = jalur palsu; CO menghitung PPN 11%
  walau Non PPN; Inquiry Revise memotong dimensi berakhiran nol; tiga laporan manajemen hanya membaca arsip `*_legacy`;
  53 tabel lookup / parameter tanpa layar input; 6 item menu aktif tanpa layar.
- Diarsipkan 39 file ke `C:\Users\iwanp\tmerp_unused\` (MANIFEST): onboarding lama, `queue.md`, Opening App tier, semua dokumen
  Filament, layar LEGACY, cadangan, eksperimen, log impor, dua JSON data contoh. `CLAUDE.md` + `index_master.md` diberi catatan
  letak folder dan daftar arsip; komentar basi di `App\Support\V2\Lookups` dibetulkan.

## 2026-10-02 -- UI-03 ditutup: kerapatan grid cukup per grid, pintu "Grid (segera)" dihapus

Keputusan user 2026-10-02 (rulebook C.29), setelah melihat preview: (1) kerapatan cukup seperti yang terpasang -- tombol Density
di toolbar tiap grid, tersimpan di browser; (2) Grid Pre-Entry tetap tanpa tombol itu; (3) pilihan A -- tidak ada pintu kedua.
- Dihapus: bagian **Grid ("segera")** di drawer Configuration menu lama (`App\Livewire\Shell\UserConfiguration::sections()`,
  blok di `livewire/shell/user-configuration.blade.php`, tanda "segera" di navigasinya, CSS `.tm-cfg-soon` / `.tm-cfg-soonbox`).
  Tidak ada kolom `sec_user.grid_density` / `grid_lines` dan tidak ada baris terjemahan `shell_cfg_grid*` di ddtsys / tm2sys
  (dicek), jadi tidak ada migrasi. Menu v2 memang tidak pernah punya pintu ini.
- Mockup rencana lama `docs/mockups/grid_kerapatan_baris.{html,jpg}` dan preview `ui03_grid_door_preview.png` dipindah ke
  `C:\Users\iwanp\tmerp_unused\` (MANIFEST). UI-03 dihapus dari backlog01.
- Test `UserProfileWindowsTest` disesuaikan (bagian = Themes, Language); `npm run build` dijalankan.

## 2026-10-02 (siang, lanjutan ke-2) -- Bahasa: teks JS DevLib, label / nilai grid v2, 46 label layar; pemilih kontak e-mail

Lanjutan perintah "semua di backlog yang bisa dikerjakan". Semua di `main`, sudah di-push. Tidak ada perubahan alur / data bisnis:
hanya teks tampilan (English di kode + kamus) dan satu fasilitas baru di dialog Email.

- **backlog09 §13 butir 1b SELESAI -- teks Indonesia di JS DevLib -> kunci terjemahan** (`T('kunci')` / `Tf('kunci', {..})` +
  pasangan [ID, EN] di `Devlib\Lang`): form, sheet, grid, lines, approval, calendar, master, notif, lookup, pick-matrix, kanban,
  import, gantt, credit-card, shell, wizard, perm-matrix, signature, chat, side-menu, drill, field, org-chart, barcode, phone, pivot,
  slider, doc-share, core (mode developer, daftar pintasan, kotak konfirmasi bawaan). `js_id_text` 180 -> 44; sisa 44 = tabel dua
  bahasa di `core.js` + 5 bukan teks layar. Diuji di Chrome headless dua bahasa (approval, kanban + modal kartu, gantt, import,
  credit card, signature, pintasan, konfirmasi) tanpa error JS; semua kunci yang dipanggil JS ada di Lang.
- **Pemilih tanggal**: inisial hari satu helper `Devlib.h.dow2()` -- Indonesia Mg/Sn/Sl/Rb/Km/Jm/Sb (dulu Intl memberi "Se Se"
  untuk Senin / Selasa di `x-dl::field.date`).
- **Kelas grid v2 -- label yang lolos dari penjaga**: 183 label kolom / filter / grup / kotak cari di 34 kelas grid (No CO, Mata Uang,
  Departemen, Tipe Produk, Lembar Maks, ...) ditulis English; teks panduan laporan CO dan 3 lookup Material Planning dipendekkan
  <= 255 huruf supaya bisa masuk kamus (15 teks panduan kini punya terjemahan). Nilai tampilan (Ya, Mingguan / Bulanan, Sudah / Belum,
  Menang / Kalah, Produksi / Penunjang, ...) di 13 grid lewat `t()` yang sama untuk nilai baris, kunci warna, dan saringan kotak
  statistik -> cocok di bahasa apa pun. Tampilan Indonesia dicek tetap sama; English lewat `?print_lang=en`.
- **Penjaga diperketat**: `Dictionary::ID_WORDS` + ~70 kata (departemen, tipe, sumber, berlaku, mingguan, ...). Temuan baru 49 label
  di layar v2 (Quotation, Follow-up, Outstanding, PR, PO, SPB, CO, Delivery Notes, Approval Setting, Master Card Collection,
  Master Product, Inquiry New / Revise / Recalc -- zona beku: hanya teks ID -> EN, izin sudah ada) + toolbar Grid DevLib (Group,
  Density, Columns shown, ...), Permission Matrix, bantuan Sheet -> English / kunci Lang. `id_label` kembali 0.
- **backlog09 §18 -- alamat e-mail dari kontak**: objek DevLib `x-dl::doc-share` punya kotak **Find contact** di dialog Email
  (`:contacts` statis dan / atau `contacts-url`); klik kontak = masuk ke kotak To / CC / BCC yang terakhir diklik, klik lagi = dicabut.
  tmerp: `GET /v2/mail-contacts?q=` (`App\Support\V2\Data\MailContacts`: e-mail utama customer / supplier, kontak customer / supplier,
  user; hanya baris aktif ber-e-mail sah, maks 6 per sumber). Pesan dialog + pesan server e-mail kini English + kamus.
  Test `MailContactsTest` (3). Demo devlib `doc-share` diberi contoh `:contacts`.
- Migration kamus `2026_10_02_000056` s/d `000059` di ddtsys DAN tm2sys (dicek di tabel migrations).
- backlog02 §25 diberi "25.0 Status 2026-10-02" (rantai dummy kini di `ddt*`; apa yang masih terbuka).
- Probe ulang: 206 item menu, 111 grid (+ Buka / New), 555 form / cetakan = 200 (16 URL lama di daftar probe = id yang sudah tidak ada).
- **Temuan untuk user (tidak diubah):**
  - `cust_customer_contact` dan `sup_supplier_contact` belum punya satu pun e-mail -> pemilih kontak baru menampilkan e-mail utama
    customer (8) / supplier (20) dan user. Mengisi kontak per orang = lewat Master Customer / Supplier.
  - 242 teks di kamus (konteks `ui`) belum punya terjemahan; sebagian besar teks sumbernya masih Indonesia di layar Inquiry / ProDev /
    Quotation (isi `<p>`, `x-dl::alert`, kolom tabel bentuk lain) -> butir 1c / 2 backlog09 §13, per layar.
  - Beberapa kata berubah tipis di tampilan Indonesia karena kamus sudah punya terjemahan lain untuk teks English yang sama
    -- yang terlihat: "Tipe" -> "Jenis", "Posisi" -> "Jabatan", "Terkirim" -> "Dikirim", "Berlaku" (SOP) -> "Berlaku Sejak",
    "No CO" -> "No. CO", "Belum" (slip gaji, cetak) -> "Belum dicetak". Bisa diubah di Setup > Translation Dictionary.

## 2026-10-02 (pagi, lanjutan) -- "semua di backlog yang bisa dikerjakan": sisa UI-20, UI-18, UI-19, 19.1, audit data, PWA, label PQ

Perintah user: "lanjutkan apa yang belum" lalu "semua di backlog yang bisa dikerjakan, kerjakan". Semua di `main`, sudah di-push.
Semua backlog (01-10) dibaca; yang butuh keputusan user tidak disentuh (daftar di akhir entri ini).

- **UI-20 selesai untuk semua layar yang berupa daftar** (20 grid lagi; total hari ini 39):
  Finance `acc-cash-bank`, `acc-dp-invoice`, `acc-receivables`, `acc-payables` (induk `InvoiceGrid`), `acc-fixed-asset`,
  `acc-amortization` (induk `AssetGrid`); Setup `setup-constants` + `setup-constant-categories` (Grid Lookup), `security-accounts`,
  `security-groups` (Grid Master); `rcr-talent-pool`; `prod-bop-rule` (model baru `ProdBopRule`, hanya baca); Production Output memakai
  layar Work Order (di v1 juga satu layar). Buka / New = layar v1, yang kini membuka baris / form baru dari `?open={id}` / `?new=1`
  (10 layar v1 dipasangi trait `OpensFromQuery`). Sisa UI-20 = papan / proses / dasbor (bukan daftar), lihat backlog01.
- **UI-18**: kategori konstanta bisa ditambah dari layar (Setup > Constant Categories). Model `SysConstantCategory` kunci = `id_constant_category`.
- **backlog07 Grup A**: Master Data > Product Category (Grid Lookup) dengan akun Persediaan dan HPP bawaan -- diuji: product tanpa akun
  sendiri langsung memakai akun kategorinya.
- **UI-19**: cetakan Sales Invoice / Down Payment Invoice (`sales-invoice`) dan bukti kas / bank (`payment-voucher`) di objek cetak DevLib;
  kop PPN otomatis bila dokumennya kena PPN. `Document::priceColumns()` kini bisa ditimpa (voucher mencetak Debit / Credit).
  Tombol Print per baris di grid Order to Cash, Down Payment, Cash & Bank (`DbGrid::actionUrls`, diuji di Chrome headless).
  Konstanta `SIGN_INV_*` / `SIGN_PVC_*`. Contoh: `docs/mockups/print_doc/app/sales_invoice_a5.jpg`, `payment_voucher_a5.jpg`,
  `receipt_voucher_a5.jpg`. **Format belum A.**
- **backlog02 §19.1 Surat Jalan Subkon v2** (journey disetujui 2026-09-30): layar `subcon-delivery-note` di Sales > Delivery
  (`App\Support\V2\Data\SubconDelivery`): Draft = New / Save / Delete / Confirm, Confirmed = Cancel / Print; header supplier subkon,
  proses, PR subkon; baris = baris Customer Order + barang dikirim + qty (tanpa harga). Confirm mensyaratkan supplier, proses, dan baris
  CO di tiap barang. Surat jalan subkon tidak lagi ikut daftar DO / Delivery Notes / POD. Cetakan `subcon-delivery-note` (belum A,
  contoh `subcon_delivery_note_a5.jpg`). Dummy 2 dokumen di ddt (CENTRAL GRAFIK), baseline ddt disimpan ulang (ddt tidak berubah sejak
  baseline sebelumnya). Uji: 14 pemeriksaan alur dalam transaksi yang di-rollback.
- **backlog09 §14 + backlog05 05.17**: perintah baru `php artisan data:audit [--env=mockup] [--only=identity|stock]`, hanya membaca.
  Hasil pertama: nama akun vs pegawai tidak ada yang beda (ddt dan tm2); **25 akun aktif menempel pada pegawai nonaktif** (akibat
  penguncian daftar staf 2026-10-01 yang lewat migration, bukan lewat layar); kartu stok dummy lama punya saldo minus / saldo awal
  tidak nyambung (ddt 3, tm2 10 temuan).
- **backlog04**: file penawaran CSV kini dibaca sebagai teks -- "12.500" tidak lagi terbaca 12,5 (`PqFileReaderCsvTest`).
- **backlog09 §13**: 31 label Indonesia terakhir di layar Purchase Quotation diganti English + kamus (ratchet `id_label` 31 -> 0);
  tampilan Indonesia dicek tetap sama di daftar, New, form Draft / Confirmed, dan Bandingkan.
- **backlog10 10.6**: ikon PWA dibuat dari logo (`App\Support\Branding\PwaIcon`, route `pwa.icon`); tanpa logo tetap ikon bawaan.
- **backlog03 §24** (test meninggalkan sampah) diverifikasi sudah tertutup: jumlah baris produksi di tm2 sama sebelum dan sesudah
  `ProductionWoGenerationTest` + `ProductionWorklistWoTest`. Entri basi backlog04 / 07 dibetulkan (tagihan supplier, aset tetap, AR / AP sudah ada).
- Migration `2026_10_02_000047` s/d `000055` di ddtsys DAN tm2sys (dicek di tabel migrations); `000054` (dummy) tidak menulis di tm2.
- **Menunggu keputusan user (tidak dikerjakan):**
  - 25 akun aktif pada pegawai nonaktif: dinonaktifkan? (daftarnya: `php artisan data:audit --only=identity`)
  - Surat Jalan Subkon: kapan dokumen dianggap Closed (saat barang kembali di Penerimaan dari Subkon?) -- sekarang berhenti di Confirmed.
  - Format cetak belum A: Sales Invoice, Payment Voucher, Surat Jalan Subkon (+ Purchase Quotation, Bukti Kas Kecil dari malam).
  - Dummy stok (menunggu desain gudang K1-K11); usul kasbon satu baris. (UI-03 kerapatan grid: diputuskan 2026-10-02, lihat entri teratas.)
  - Butuh keputusan / data nyata: spek & tarif mesin (27), peran part / material role (28), uang roti & tarif lembur payroll (06),
    jurnal payroll & COGS saat DO (07), sistem laporan manajemen (08), arah mobile (10), chat v2, Maintenance M2 / M3.
  - (Tujuan e-mail dari kontak dan teks JS DevLib: selesai di entri "siang, lanjutan ke-2" di atas.)

## 2026-10-02 (malam, berjalan sendiri) -- Probe semua layar + rollout grid v2 lanjutan (UI-20) + cetakan PQ / Kas Kecil diuji

Perintah user: "kerjakan yang kamu bisa kerjakan selama saya tidur, mandiri tanpa prompting". Semua di `main`, sudah di-push.

- **Probe mockup.test (ddt, hanya GET, lewat kernel)**: 201 item menu, 78 grid + form buka / baru, 555 form dan cetakan dengan id
  dokumen asli -- semuanya HTTP 200, tidak ada error 500. Yang 404 memang wajar (grup Flow yang dimatikan, bahan tanpa data
  planning). Tidak ada data yang berubah.
- **Master Data -> Grid Lookup v2** (`f11b1e6f`): Units of Measure (`md-uom`), Unit Conversion (`md-uom-conversion`, baris menu baru
  `MD_UOM_CONV`), Currency (`md-currency`), Exchange Rate (`md-exchange-rate`), Supplier Category (`md-supplier-category`).
  Aturan simpan = layar v1: satu satuan dasar per dimensi, dimensi Specification tanpa satuan dasar, konversi hanya antar satuan
  sedimensi dan satu pasangan sekali, satu kurs Aktif per mata uang (baris lama jadi riwayat), kode huruf besar.
  - Objek DevLib: kolom `lockSaved` di `x-dl::sheet` / `x-dl::lookup-grid` = kode hanya bisa diisi di baris baru, terkunci setelah
    tersimpan (server ikut menolak). Contoh: devlib.test > Grid Lookup > "code locked once saved".
  - `DbLookup` mendapat kait `prepare()` / `check()` / `saved()` untuk aturan lintas kolom dan lintas baris.
  - Model `SupCategory`: kunci utama = `id_category` (sesuai tabel; dulu masih `category_code`).
  - Uji: 26 pemeriksaan simpan di ddt di dalam transaksi yang di-rollback (tanpa sisa data); `lockSaved` diuji di Chrome headless.
- **Laporan stok -> Grid Report v2** (commit berikutnya): Stock Balance, Stock Card, Slow Moving Item untuk RM / FG / SFG (9 layar,
  `inv-<kelas>-rpt-balance|card|slow`). Sumber = view stok yang sama dengan v1 (`v_*_onhand`, `v_inv_movement`).
  Stock Balance = saldo sekarang per barang x lokasi + saldo awal / masuk / keluar / penyesuaian bulan berjalan + tanda di bawah
  minimum; Stock Card = semua pergerakan (cari kode barang / grup per Item); Slow Moving = diam 3 bulan atau lebih dengan saringan
  3-6 / 6-9 / 9-12 / lebih dari 12 bulan / belum pernah bergerak.
- **Purchase Return dan Support Tickets -> Grid Transactional v2** (persegi dokumen + tabel anak barang). Buka = form v1;
  + New Record Purchase Return membuka pemilih sumber v1.
- **Finance -> grid v2**: Journal Entries (`acc-journal`, persegi dokumen + baris jurnal), General Ledger (`acc-rpt-ledger`),
  Trial Balance (`acc-rpt-trial-balance`). Trial Balance dicocokkan dengan v1: 67 akun, total debit = kredit, angka sama.
- **Cetakan Purchase Quotation dan Bukti Kas Kecil dirender dari aplikasi** memakai baris sementara (di-rollback):
  `docs/mockups/print_doc/app/purchase_quotation_a5|a4.jpg`, `petty_cash_topup|advance|settle_a5.jpg`.
  Perbaikan: nilai Top Up dan Cash Advance tercetak 0 (kolom tidak pernah NULL sehingga `??` tidak jalan); jenis transaksi kini
  ikut bahasa cetak. Format keduanya tetap belum A.
- **Setup > Menu Setup (backlog UI-21)**: Grid Lookup v2 atas `sys_app_menu` (`menu-setup`) -- label, label pendek, ikon, urutan, dan
  aktif / nonaktif tiap baris menu diubah langsung di sel, baris tampil berurutan seperti pohon menu dengan kolom "Under" (induknya).
  Tidak diubah di sini: baris baru, pindah induk, alamat layar, hak akses, status A / R (tetap lewat migration; LOCKED hanya user).
  Baris layar ini sendiri beserta induknya (grup Settings, modul Setup) tidak bisa dimatikan dari sini. Memakai lagi baris `SET_MENU`
  ("Susunan Menu" v1 yang mati) -- dipindah ke grup Settings. Uji: 14 pemeriksaan simpan di ddtsys dalam transaksi yang di-rollback.
- **Kamus**: terjemahan Indonesia untuk teks baru (migration `2026_10_02_000044` dan `000046`, cache kamus dibuang otomatis).
  Batas panjang kamus: teks English dan terjemahannya masing-masing paling banyak 255 huruf -- teks panduan grid dipendekkan.
- Migration `2026_10_02_000040` s/d `000046` dijalankan di ddtsys DAN tm2sys (satu per satu dengan `--path`, hasil dicek di tabel migrations).
- Uji akhir: seluruh test suite 262 test -- 249 lolos, 12 dilewati (Firebase emulator mati, wajar), 1 error lama yang sama
  (`MasterMachineMigratedDataTest`: tabel `prod_machine_model` belum ada di tm2, migration fitur lain yang masih pending). Semua grid
  baru juga dimuat terhadap tm2 (hanya baca): 20 layar tanpa error.
- **Temuan untuk ditinjau user** (tidak diubah):
  - Dummy stok di ddt punya saldo awal minus (ANIMAL GLUE, saldo awal -60) -- dummy stok perlu dibuat ulang setelah desain gudang disetujui.
  - Bukti Kas Kecil jenis Cash Advance: baris barang = harga perkiraan PO, sedangkan total = nilai kasbon, jadi subtotal baris tidak
    sama dengan total. Usul: untuk Cash Advance cetak satu baris "Kasbon PO ..." saja.
  - 76 satuan lama belum punya dimensi; tiga konversi lama (IK25 / IK50 / IK100 -> PCS) memakai satuan tanpa dimensi.
  - Purchase Quotation, Purchase Return, Support Tickets, Petty Cash belum punya data dummy di ddt (layar tampil kosong).

## 2026-10-02 -- Cetakan dokumen A5 landscape / A4 portrait dipasang ke aplikasi (objek DevLib)

- Perintah user: semua entry transaksional punya cetakan A5 landscape (1-5 baris) / A4 portrait (lebih dari 5); polanya dipasang
  di DevLib supaya dokumen tinggal diturunkan (rulebook C.24). Format = yang ditinjau user di `docs/mockups/print_doc/`.
- **DevLib**: `Devlib\PrintDoc\Document` (kelas induk: tata letak, aturan kertas, lembar, total, terbilang sampai sen, urutan
  tanda tangan, kaki "Dicetak pada ... oleh ..."), `Devlib\PrintDoc\Labels` (istilah cetak Indonesia), komponen `x-dl::print-doc`
  (+ CSS `.dl-pdoc*`, `print-doc.js` auto-fit), `x-dl::doc-share` mode lembar (PDF / printer berukuran kertas, QR di dalam lembar).
  Demo: devlib.test > Documents > Print Document (A5 / A4).
- **Aplikasi**: `App\Support\V2\Print\*` satu kelas per dokumen (membaca database) + `PrintDocs` (daftar jenis, kop, penanda
  tangan); rute `v2.print` + `resources/views/v2/print-doc.blade.php`. 15 dokumen: Inquiry, Quotation, Customer Order (dengan /
  tanpa harga), CPDEL, Delivery Order, Surat Jalan, Bukti Terima, PR, Purchase Quotation, PO, SPB, Material Booking,
  Maintenance Request, Maintenance WO, Bukti Kas Kecil. Tombol Print di tiap form membuka halaman ini (menggantikan cetak
  tampilan form dan tautan nota v1).
- Penanda tangan: Pembuat = user yang login; Menyetujui = penyetuju sebenarnya dari approval sistem, kalau tidak ada =
  konstanta `SIGN_<DOK>_APPROVER`; Mengetahui = `SIGN_<DOK>_ACKNOWLEDGER` (migration `2026_10_02_000030`, kategori PRINT,
  diubah di Setup > Constants; PO diisi Hendra / Wiwik).
- Migration konstanta dijalankan di ddt DAN tm2 (30 baris, terverifikasi). Alamat perusahaan baru diisi di `ddtmst.org_company` (Pondok Blimbing Indah Blok A5 No. 5, Malang
  65126; Telp 62-341-471268); `tm2mst.org_company` (data real) belum diisi. Di tm2 skema Setting Approval v2 belum ada, jadi
  penyetuju dari approval sistem tidak terbaca di sana dan cetakan memakai konstanta.
- Uji: 13 dari 15 jenis dirender dari data dummy lewat kernel HTTP (200) dan dipotret (`docs/mockups/print_doc/app/`); mode
  lembar doc-share diuji di Chrome headless (gambar 2382x1677, PDF 210x148 mm); `PrintDocDocumentTest` + ratchet lolos.
  Purchase Quotation dan Bukti Kas Kecil belum punya data dummy, jadi belum pernah dirender dengan data. Print / PDF / Email
  sungguhan di browser belum dicoba.
- Belum: kolom Type di Customer Order dan Berat / Ikat / Kendaraan / Pengemudi di Surat Jalan belum punya sumber data
  (tercetak kosong); Jadwal Kirim belum punya layar / tabel; halaman cetak SPB lama (`v2.pv.print*`) masih ada tetapi tidak
  ditautkan lagi; pilihan penanda tangan dari Master Pegawai (sekarang teks bebas di Constants).

## 2026-10-01 -- Menu ACC Digital / Proof Digital dihapus

- Perintah user: "dengan begitu menu acc digital bisa di hapus ... cukup dari print out saja".
- Migration `2026_10_02_000013_menu_drop_acc_digital` menghapus baris `PRE_PROD_ACC` (Pro-Dev > ACC Digital / Proof Digital);
  pemetaan `MenuRoutes` untuk kode itu dilepas. Layar `product-acc` tetap ada dan tetap terbuka tanpa baris menu
  (`AppMenu::open`), dibuka dari Print / Email > ACC Digital di form ProDev dan dari kartu ACC di bawah form.
- **Baru dijalankan di ddt** (mockup.test); `tm2sys` masih punya barisnya (id 1096), menunggu OK user.
- Akibat: daftar product + status ACC ("No Digital ACC yet") yang tampil saat menu itu dibuka tanpa product tidak lagi punya pintu
  dari menu.
- Uji: baris 0 di ddtsys, `/v2/s/product-acc?id=...` (juga dengan `ds_do=email`) HTTP 200, V2ShellTest lolos.

## 2026-10-01 -- ProDev v2: panel atas = panel form standar, Print / Email berpilihan

- Perintah user: panel atas disamakan dengan panel form standar; Print jadi satu tombol dengan 2 pilihan (Master Card, ACC Digital),
  Email juga untuk keduanya (rulebook C.27).
- Objek DevLib baru `x-dl::menu-button` (`MenuButton.php`, `menu-button.js`, contoh di demo Button devlib.test): tombol + daftar pilihan;
  item ber-href membuka alamat, item ber-act memancarkan event `dl:menu`. `x-dl::doc-share`: `?ds_do=email` membuka dialog e-mail langsung.
- `workbench.blade.php`: bar `io-top` diganti `dl-form__panel` (judul, kode MC, badge Draft/Confirmed, Email ▾, Print ▾, Confirm, Save, Back);
  id `io-save` / `io-confirm` / `io-back` / `io-status` / `io-jobcount` dipertahankan. Bar doc-share lama (mencetak layar workbench) dilepas.
- Print > Master Card = pratinjau Master Card (tombol cetak A4 landscape di situ); Email > Master Card = dialog e-mail doc-share dengan
  lembar Master Card (gambar / PDF / link online; CSS lembar ikut di dalam salinan). Print / Email > ACC Digital = layar ACC / Proof
  Digital product itu (Email langsung membuka dialog e-mail). Part tanpa product: pilihan ACC Digital mati + keterangan.
- Uji: headless (2 menu, 4 item, badge Draft, dialog e-mail tampil di atas pratinjau, subjek "Master Card MC-463"); test ratchet +
  V2Shell + ProdevPrintMastercard lolos. Pengiriman e-mail sungguhan dan cek mata di browser belum dilakukan.
- Belum ada (alur belum tersedia di ProDev): Undo, Delete, Cancel, Back to Draft, New, tab History, kunci isian setelah Confirm;
  Master Card belum punya unduhan PDF / JPEG / HTML online dari menu Print (baru printer + e-mail).

## 2026-10-01 -- ProDev v2: 4 slot warna khusus di Ink Configuration

- Perintah user: "sediakan 4 lagi untuk warna khusus untuk di isi oleh officer" (rulebook C.26).
- `workbench.js`: di bawah tabel tinta selalu ada 4 slot Special 1-4 (Unit 5-8, pass pertama, jenis SPOT) berisi isian nama tinta,
  warna, tutupan %. Isian pertama membuat baris override tinta (alur override yang sudah ada: Save All -> `prod_part_ink` +
  `iqp_part_ink`); slot kosong dibuang server. Tidak ada perubahan engine / tabel. Kotak pilih warna diperbaiki di kulit terang.
- Uji: headless (slot 1 diisi -> terkirim sebagai override, label OVERRIDE OFFICER); server: PANTONE 485 C 12,5% -> 182,7 cm2,
  baris BOM "TINTA PANTONE 485 C (belum ada di Master Bahan)" 0,3133 KG, slot kosong tidak ikut. Belum dicek mata user.

## 2026-10-01 -- ProDev: BOM kosong diperbaiki (satuan mm/cm) + pilihan Canvas limit

- Laporan user: bagian Bill of Material macet di "Menghitung dari data Inquiry…" lalu "Perbaiki. Lalu jalankan saja soal ukuran
  meja size custom".
- **Sebab BOM kosong:** `iqp_part.flat_w/flat_h` (mm) dipakai sebagai cm -> blank 250 × 161 cm -> tidak ada plano yang muat ->
  `calculator()` berhenti sebelum memanggil hitungan BOM / Ink / BOP. Diperbaiki di tiga tempat (zona beku, v1 + v2 memakai kode
  yang sama): `pages/production/prodev.blade.php::jobs()` (flat ÷ 10), `saveLayout()` (`iqp_layout.flat_*_mm` tidak lagi × 10),
  `SpecEngine::blankArea()` (mm² ÷ 100 = cm², dipakai luas tutupan tinta). Di `workbench.js` (v2) BOM / Ink / BOP kini tetap
  diminta walau belum ada plano yang muat. Cek data: 102 (ddt) / 69 (tm2) part ber-flat semuanya mm; `iqp_layout` 2 baris,
  tidak ada yang tersimpan × 10 -> tidak ada data yang perlu dikoreksi.
- **Canvas limit** (rulebook A.18, usulan `docs/mockups/prodev_canvas_limit.jpg`): dropdown di panel 3. Lembar Cetak / Canvas —
  Custom (follow dieline, bawaan, 1 up, tanpa batas meja), Press bed, Die-cut bed (Pond), Press + Die-cut (aturan 16-Sep).
  Tersimpan di `layout_detail.canvas_limit`; layout lama dibuka sebagai Press + Die-cut. Hanya layar v2 (`workbench.js` /
  `workbench.blade.php`); v1 tetap seperti semula.
- Uji: workbench dijalankan tanpa browser (Chrome headless, jawaban server direkam): blank 25 × 16,1 cm, canvas Custom
  26,6 × 18,1 cm, 1 up, BOM 8 baris tampil; test Prodev* + InquirySendToProdev + ratchet lolos. Belum dicek mata user.
- Catatan data master (tidak diisi sendiri): ukuran meja 4 mesin pond dan Sakurai Oliver 58 masih kosong, jumlah warna mesin kosong.

## 2026-10-01 -- Form ProDev v2: tab Part & Dieline + latar putih

- Perintah user: "saya ingin layar itu (Part & Dieline Inquiry) ditambahkan lagi di prodev form, karena prodev berhak merubah
  dieline mockup. ATURAN BARU, TULIS!" dan "ini warna bg nya buat putih juga" (workbench Imposition). Aturan: rulebook **A.17**, **C.25**.
- Form ProDev (`resources/views/v2/screens/prodev.blade.php`) sekarang 2 tab: **Imposition** (workbench, tidak berubah isinya) dan
  **Part & Dieline** (objek baru `x-mp::prodev.part-dieline` = `x-mp::dieline-v3` + artwork luar/dalam + identitas part tampil-saja).
- Simpan: route `v2.prodev.dieline` -> `App\Services\ProDev\PartDieline` -> `iqp_part` (dieline_spec, flat_*) **dan** `prod_part`
  (dieline_spec, flat_*, cm_*); product ACTIVE: ukuran kepala product (komponen pertama) + versi artwork baru (`prod_part_artwork`,
  belum di-ACC). Artwork luar lewat jalur upload ProDev yang sudah ada (analisa warna tinta ikut). `dieline_spec` ditulis lengkap
  (DSL tipe + dims), jadi part contoh Master Card yang tadinya "belum punya definisi dieline" di workbench mendapat bentuk aslinya
  setelah sekali Save.
- Latar putih: `packages/mockup-parts/resources/assets/css/prodev-light.css` menimpa warna `prodev.css` (salinan v1, tetap gelap)
  lewat token DevLib; 3 warna inline di `workbench.js` jadi kelas `io-warntxt`. Layar v1 tidak disentuh.
- Uji: render tanpa browser + potret Chrome headless (kedua tab), simpan diuji di dalam transaksi yang dibatalkan (ddt tidak berubah).
  Belum dicek mata user di browser.
- **Temuan (sudah diperbaiki, lihat entri di atas):** workbench membaca `iqp_part.flat_w/flat_h` (mm) sebagai **cm**
  (`pages/production/prodev.blade.php::jobs()` dan `flat_w_mm = flat_w * 10` di `saveLayout()`), sehingga part dari Inquiry tampil
  "250 × 161 cm" dan tidak muat di canvas mana pun.

## 2026-10-02 -- Dummy Inquiry: 3 contoh pesanan buku, semua NEW + DRAFT, dummy generik dihapus

- Perintah user: "iya buat dummy buat 3 hal itu [buku tulis, buku pelajaran sekolah, buku cerita anak], jalankan. yang data dummy
  ga jelas dihapus saja, lalu buat semua dalam kondisi new dan draft". Dibaca sebagai: set dummy draft Inquiry = semuanya jenis NEW
  berstatus DRAFT; rantai order (inquiry yang sudah dipakai Quotation / CO / Master Product / WO) tidak dijadikan draft karena
  dokumen di belakangnya akan kehilangan sumber.
- `UatDraftInquirySeeder` (ddt saja, bisa diulang): tetap 27 draft satu per tipe dieline (`IQ99DRF001-027`), ditambah 3 contoh buku
  tipe BUKU dengan 2 part (Cover + Inner Pages), bahan / warna / jumlah halaman per part, dan Isi per Pack:
  `IQ99DRF028` Buku Tulis 38 Lembar (16 x 21 cm, cover Art carton 210 gr 4/0, isi HVS 58 gr 1/1 76 hal, saddle stitch, 10 per pack);
  `IQ99DRF029` Buku Pelajaran Sekolah B5 160 Hal (17,6 x 25 cm, cover Art carton 230 gr, isi HVS 85 gr 4/4, perfect binding, 20 per pack);
  `IQ99DRF030` Buku Cerita Anak 21x21 24 Hal (cover Art carton 260 gr, isi Art paper 150 gr 4/4, saddle stitch, 50 per pack).
- Dihapus: 12 contoh RECALC / REVISE `IQ99DRF028-039` lama (seeder tidak lagi membuatnya) dan 17 dummy generik `IQ2690001-017`
  ("... #n", SUBMITTED tanpa kelanjutan; 2 costing-nya ikut terhapus, part ProDev yang pernah dibuat darinya tidak disentuh).
- Hasil ddt: 70 inquiry = 37 dummy NEW DRAFT (30 `IQ99DRF*` + 7 sampel `IQ99FBX*`), 28 rantai order, 5 LIVE. Tanpa tier / part / costing
  yatim; 13 quotation & 11 CO utuh; form 3 buku + grid Inquiry / Pro-Dev / Pro-Cost / Quotation = 200 lewat kernel. Baseline disimpan ulang.
  Belum dicek mata di browser.
- Temuan (zona beku, belum dibangun): jumlah halaman & tipe jilid tersimpan tapi tidak dipakai Pro-Dev / Pro-Cost; peran part Cover / Isi
  belum ada; proses lipat / susun / potong tiga sisi belum ada di Master Proses; Isi per Pack satu angka per part dan tidak ikut ke Quotation / CO.

## 2026-10-02 -- Flow pindah sementara ke Help; Master Data modul Inquiry hanya master terkait

- Perintah user: "flow ini bagus tapi taruh sementara di help, nanti saya pikirkan ulang" dan "master data di inquiry jangan ada
  currency, rate lain sebagainya, masukkan aja master yang berhubungan seperti master kategori bahan baku, master product buat lihat".
- Grup menu **Flow** di modul Inquiry & Quotation dan Sales dimatikan (migration `2026_10_02_000011`, baris tidak dihapus supaya mudah
  dihidupkan lagi). Sales Flow dan Costing Flow jadi tombol di **Bantuan & dukungan (F1)**, di samping Guide Master Product yang
  sudah ada di sana. Teks beranda v2 disesuaikan.
- Pintasan **Satuan, Currency, Kurs, Constants** dicabut dari Master Data modul Inquiry (migration `2026_10_02_000012` + `MenuRoutes`).
  Tersisa: Customer, Product, Bahan Baku, Kategori Bahan Baku, Proses, Mesin. Layar yang dicabut tetap ada di Master Data / Setup.
- Dijalankan di ddt + tm2. Uji: menu kedua situs tanpa grup Flow, tombol Help ada di halaman /v2, halaman flow & guide 200,
  4 test (menu + ratchet bahasa) lulus. Belum dicek mata di browser.
- Terbuka: Proses & Mesin tetap ada di grup itu (dipakai Inquiry / Pro-Dev / Pro-Cost) -- menunggu konfirmasi user apakah ikut dicabut.

## 2026-10-02 -- WIN / LOST digabung ke layar Quotation; menu PREORDER live disamakan dengan dummy

- Perintah user: "Win and lost langsung di dalam quotation, tidak perlu layar baru ... hilangkan win and lost masukkan ke quotation saja",
  lalu "ubah di mockup juga ubah di tmerp.test karena kan sama code".
- Laporan **Hasil Quotation (WIN/LOST)** dihapus: baris menu `SALES_RPT_QUORES` (migration `2026_10_02_000009`, ddt + tm2), layar
  `quotation-result.blade.php` dipindah ke arsip unused, `Quotation::resultRows()` dan entri `Screens` dibuang.
  Isinya pindah ke grid Quotation: kolom baru **Closed**, **Customer Order**, **Reason (LOST / Cancel)**; filter WIN / LOST / Cancelled
  dan tombol WIN / LOST di form sudah ada sebelumnya. Win-Rate Sales, Quotation Terbuka, Follow-up Quotation tidak diubah.
- tm2sys disamakan dengan ddtsys untuk modul ini: grup Report (Quotation Terbuka, Follow-up Quotation, Win-Rate Sales) + izin `COSTING`
  di baris Pro-Cost (migration `2026_10_02_000010`), Master Card Collection (`2026_10_01_000002`: 2 tabel baru kosong di tm2mst + baris menu).
  Halaman v2 itu diberi alamat di `MenuRoutes` untuk menu v1. `SalesMenuNoReportGroupTest` disesuaikan lagi (Win-Rate di grup Report).
- Uji: grid Quotation 200 di kedua situs (kolom Reason tampil; dummy 9 WIN + 1 LOST terisi No. CO / alasan), `/v2/s/quotation-result` = 404,
  pohon menu PREORDER live vs dummy identik kecuali label "Master Produk" (live) vs "Master Product" (dummy); 3 test menu lulus.
  Belum dicek mata di browser.
- Catatan arah (belum keputusan): user berpendapat setelah live, kode mockup.test dan tmerp.test sebaiknya dipisah.

## 2026-10-02 -- Menu modul Inquiry & Quotation (PREORDER) dilengkapi

- Perintah user: "Module inquiry until quotation is it done become one module? If ok, make sure all menu related with this
  module available in one module". Jawaban: sudah satu modul (menu `PREORDER`) di **ddt**; di `tm2sys` belum (migrasi menunggu OK).
- Audit layar terkait vs baris menu -> 10 baris ditambah (migration `2026_10_02_000008`, **baru di ddt**):
  Pro-Dev > **ACC Digital / Proof Digital** (layar v2 sudah ada tapi tidak punya baris menu di mana pun);
  **Pro-Cost Instant** (Mode #2 standalone; di v2 tidak bisa dibuka dari menu, hanya dari link di grid Pro-Cost v1; izin `COSTING`);
  Master Data > Kategori Bahan Baku (`MDATA`), Master Proses + Master Mesin (`PROD`), Satuan / Currency / Kurs (`MDATA`, layar v1),
  Constants (`SETUP`, layar v1, khusus admin); Flow > Sales Flow: Inquiry -> CPDEL (salinan baris `F-SALES`).
- Pintasan = baris ber-target sama dengan baris asal (label/ikon ikut asal), izin tetap modul pemilik layar. 5 baris layar v1
  dipetakan di `MenuRoutes`. Tidak ada layar/kode layar yang diubah (zona beku tidak disentuh).
- Uji: pohon menu admin di mockup.test menampilkan 25 item, 10 baris baru semuanya ter-resolve ke alamat yang benar; `tm2sys` tidak
  tersentuh (0 baris `PRE*`). Belum dicek mata di browser.
- Belum: layar input master Pro-Cost (rate coating / sheet / grammage, ukuran plano, tipe produk) memang belum ada layarnya,
  jadi belum bisa diberi menu; Nota Inquiry tetap lewat tombol Print di form.
- **Lanjutan (user OK 2026-10-02, "boleh banget"):** migration `2026_10_02_000005`, `000006_menu_as_data_flow_rows_drop_shortcut`,
  dan `000008` dijalankan di **tm2sys** (228 -> 248 baris; salinan sebelum migrasi disimpan sebagai JSON di scratchpad sesi).
  tmerp.test (menu v1) sekarang punya modul **Inquiry & Quotation** di depan Sales; Sales tinggal Sales Order + Delivery + Flow.
  Karena menu live masih v1, pintasan master dan halaman khusus v2 (ACC Digital, Flow, Guide) diberi alamat di `MenuRoutes`
  supaya bukan link mati. `SalesMenuNoReportGroupTest` disesuaikan (Win-Rate Sales kini anak modul PREORDER).
  Uji: 3 test menu lulus (19 test), halaman baru 200 lewat kernel tmerp.test. Belum dicek mata di browser.
  `000007` (label "Master Product Market") menyusul dijalankan di tm2sys hari yang sama atas permintaan user.
  Beda live vs dummy yang tersisa (migrasinya belum diminta): belum ada
  Master Card Collection dan grup Report Quotation, `SALES_COST` di tm2sys tanpa izin `COSTING` di baris menunya.

## 2026-10-01 -- Dummy Inquiry DRAFT dibangun ulang (untuk mencoba dieline & mockup)

- Perintah user: "rebuild data dummy for all inquiry data but status draft, so I could see how dieline mockup works".
  Dibaca sebagai: set dummy DRAFT baru yang mencakup semua jenis inquiry; inquiry non-draft (rantai Quotation / CO / WO) tidak disentuh.
- `UatDraftInquirySeeder` (ddt saja, bisa diulang): 32 draft dummy lama dihapus (11 NEW + 12 RECALC + 9 REVISE; RECALC/REVISE lama
  tidak punya Master Product sehingga tidak bisa dibuka), diganti **33 draft `IQ99DRF001-033`**:
  27 NEW = satu per tipe dieline aktif (ukuran = bawaan parameter + Ukuran Pasaran pertama; Body + Lid untuk Tray / Lid and Base / Round Box;
  Part A + Part B untuk karton 2-piece), 3 RECALC + 3 REVISE dari Master Product dummy lewat `InquiryDerivedWriter` (REVISE berisi
  perubahan Dimensi / Warna / Artwork). Tetap ada: 7 sampel desain customer `IQ99FBX*` (punya artwork) dan 2 draft LIVE buatan user.
- Bug yang ketemu & diperbaiki: (1) `iqp_revise_change.impact` varchar(30) lebih pendek dari teks dampak Dimensi -> Simpan Revisi
  scope Dimensi selalu gagal; migration `2026_10_02_000006` melebarkan ke 100 (**baru di ddt, tm2 menunggu OK user**).
  (2) tabel perbedaan Revisi menampilkan 260 mm sebagai "26" (nol di belakang bilangan bulat ikut terbuang).
- Uji: 33 form dirender lewat kernel mockup.test = 200 semua, tiap part NEW menghasilkan SVG dieline + bentangan; seeder dijalankan 2x
  (hasil sama, tanpa part/tier yatim). Baseline ddt disimpan ulang. Belum dicek mata di browser.
- **Lanjutan (user OK 2026-10-01):** migration `2026_10_02_000006` dijalankan di **tm2sdi** (impact = varchar(100)).
  24 dummy RECALC/REVISE non-draft tanpa Master Product dibereskan oleh seeder yang sama (`legacy()`): 19 dummy generik
  `IQ2690040-66` (tanpa tier qty, tanpa product) **dihapus** -- 2 costing-nya ikut terhapus, product/part ProDev 68-75 yang pernah
  dibuat darinya tidak disentuh; 5 salinan berharga `IQ99UAT004/005/006/007/012` (dipakai Quotation) **dijadikan NEW**
  (`UatQuotationInquirySeeder` PLAN ikut diubah). Pengganti: 6 dokumen Confirmed `IQ99DRF034-039` (3 RECALC + 3 REVISE) dari
  Master Product. Hasil: 96 inquiry dummy, semuanya membuka form (uji kernel 96/96 = 200); Ready to Quote tetap 5; baseline disimpan ulang.

## 2026-10-01 -- Grid Inquiry Product v2 disamakan dengan Grid Transactional

- Perintah user: grid Inquiry harus konsisten dengan Grid Transactional (Quotation / Customer Order).
- Kartu 3 tombol di atas grid dihapus: Create Inquiry -> **+ New Record** di toolbar (membuka wizard jenis yang sama);
  Continue Draft / Ready to Quote -> kotak statistik **Draft** / **Ready to Quote** (klik = saring; isi Draft = Not Confirm lama, 41 baris sama).
- Kotak statistik: All, Draft, Ready to Quote, Processed by Pro-Dev, Processed by Pro-Cost (ukuran baku, CSS khusus dihapus).
- Ikon baris = Open baku (bukan pensil/panah berbeda); DRAFT tetap terbuka untuk edit, status lain lihat-saja.
- **Duplicate pindah dari baris grid ke dalam form** (user setuju 2026-10-01): tombol Duplicate di toolbar form Inquiry New
  (`InquiryForm`) dan di baris tombol form Recalc / Revise, muncul setelah dokumen tersimpan (semua status). Modal + rute sama
  (`screens/_inquiry-duplicate`, `v2.inquiry.duplicate`). Catatan: dummy RECALC/REVISE lama tidak punya Master Product sehingga
  tidak membuka form (sudah begitu sebelumnya); yang DRAFT sudah diganti (entri di atas), 24 yang non-draft masih begitu.
- Judul kolom English; Status jadi kolom terakhir. Tab jenis (New / Recalculate / Change Specification) tetap.
- Alur, data, wizard, dan form Inquiry tidak diubah. Uji: render kernel mockup.test = 200; belum dicek mata di browser.

## 2026-10-01 -- Layer Menu: kepala panel baru, mode mini, ikon semua menu, layar selebar panel

- DevLib `x-dl::layer-menu`: baris 1 logo + nama aplikasi, baris 2 ikon (tukar view | cari | kecilkan menu); garis penanda layer
  dan breadcrumb panel kiri dihapus. Prop baru `search`, `collapsible`. Mode mini = satu lajur ikon (localStorage per browser);
  klik cabang/Back/avatar saat mini = menu lengkap melayang (`is-peek`), menutup setelah layar dipilih / klik di luar.
- v2: nama aplikasi dari `Brand::name()` (sys_constant APP_NAME = "ERP TM", diubah di Settings -> Identifikasi).
- Layar v2 selebar panel kanan, tepi 6px (`body.dl-page-wide`); katalog DevLib tetap 1100px.
- Ikon menu: migration `2026_10_02_000004_menu_icons_every_item` (198 item kosong diisi + 6 ikon grafik berulang diganti).
  Dijalankan di ddtsys dan tm2sys (user OK 2026-10-01 "apply to all"; semua item kedua DB berikon).
- Uji: simulasi klik headless (jsdom) 16 langkah lulus, render v2 home OK, ratchet test lulus. Belum dicek mata di browser.

## 2026-10-02 -- Run malam: grid v2 Recruitment (UI-20)

- `App\Support\V2\Grid\Rcr\*`: Manpower Requisition (buka = entry v1), Job Vacancy (New = form v1), Candidate Form Review
  (buka = detail v1), Interview Schedule (?open memilih jadwal di v1), Assessment Result, Offering (baris = lamaran, buka =
  offer entry v1; nilai gaji tidak ditampilkan di grid), Hire Provisioning (?open membuka panel provisioning v1), Portal Sync Log (report).
- Pipeline Board & Portal Candidate tetap layar v1 (kanban / portal publik).
- Menu ddtsys: migration `2026_10_02_000003` (ddt saja). Probe: semua layar + 130 form = 200.
- **tm2*** (OK user 2026-10-02): migration `2026_10_02_000001`, `000002`, `000003` dijalankan satu per satu dengan `--path`; 25 item menu tm2sys -> `v2:*`. Menu v1 tmerp.test tidak berubah (membuka lewat kode menu, bukan `target`).

## 2026-10-02 -- Run malam: grid v2 Work Order, Rule Library, Outstanding PR/PO/PV (UI-20)

- `Prod\WorkOrderGrid` (buka = WO Detail v1, New = Generate dari Plan Delivery v1), `Prod\RuleLibraryGrid` (form modal v1 lewat ?open/?new).
- `Pur\OutstandingPrReport` / `OutstandingPoReport` / `OutstandingPvReport`: laporan per BARIS (bukan per dokumen seperti v1) dengan
  Grup + subtotal, aturan sisa sama dengan v1; tambahan kolom Umur (0-7 / 8-30 / > 30 hari) dan tanda PO Terlambat.
- Menu ddtsys: migration `2026_10_02_000002` (ddt saja). Probe: 89 layar + 117 form = 200.

## 2026-10-02 -- Run malam: Inventory v2 grid (Stock In/Out/Return/Adjustment RM/FG/SFG) -- UI-20 + backlog04 "Stock In v2"

- `App\Support\V2\Grid\Inv\StockDocGrid` + 12 turunan (RM/FG/SFG x In/Out/Return/Adj), slug `inv-<cat>-stock-<kind>`.
  Stock In menampilkan semua dokumen masuk (MANUAL, BB, IN, dan dokumen otomatis dari SPB bila ada), bukan hanya MANUAL.
- Buka/New ke form v1 lewat `?open={id}`/`?new=1` (trait `OpensFromQuery` ditambah di 4 layar v1 inventory).
  FG Stock Out/Return = grid hanya-baca (v1 sengaja menolak FG; barang jadi keluar lewat DO).
- Menu ddtsys: migration `2026_10_02_000001` (12 item -> `v2:inv-*`, READY_FOR_QC). Dijalankan di ddt* saja; tm2* menunggu OK user.
- Probe kernel mockup.test: 83 layar menu + 112 form grid = 200.

## 2026-10-01 -- Utang label multi-bahasa: 643 label Indonesia -> English + kamus (tampilan ID tetap sama)

- Konversi sesuai konteks (x-dl::* polos, HTML/komponen lain `t()`/`Lang::text()`, tombol non-baku dibungkus terjemahan).
- Kamus: 460 pasangan (json + migration 000022/000024), `source_en` utf8mb4_bin (000023); pasangan DevLib di `Devlib\Lang` (devlib.test tanpa DB).
- `DevlibComponent` ikut menerjemahkan `addLabel` & `groups`; teks bawaan JS grid/lines/sheet/form lewat `T()`/`Tf()`.
- Bukti: snapshot 68 layar v2 mode ID identik sebelum/sesudah (3 label English lama kini ikut diterjemahkan); mode EN baris Indonesia 1696 -> 824.
- MultiLanguageRatchetTest: id_label 691 -> 48, + metrik js_id_text 180, + test kunci T() JS wajib ada di Lang.
- Sesi lain (Purchase, Maintenance, CO, CPDEL, Warehouse) diberi tahu aturan lewat pesan antar-sesi.

## 2026-10-01 -- Maintenance v2 M1 (mockup.test / ddt*) -- K1-K10 + mockup disetujui user

- Migration `2026_10_01_000040`: 11 tabel `tm2prd.mnt_*` (code, plan/_task/_part, request, wo/_task/_part/_labor,
  meter_reading, log), modul akses `MAINT` + grup `MAINT_TECH`/`MAINT_HEAD`, menu v2 Maintenance (Work / Planning /
  Master / Report), konstanta `MNT_LABOR_RATE_HOUR`, katalog 70 kode Component/Problem/Cause/Action (English).
  Menu lama Production > Support (PROD_MTN_SCH/PREV) diarahkan ke layar v2. Dijalankan di ddt* saja.
- `MaintenanceService`: Request (P1 = mesin berhenti -> downtime dibuka + WO otomatis), Accept/Reject, WO
  Confirm/Start/Hold/Resume/Done/Close/Cancel/Back to Draft, downtime & `machine_status` memakai tabel yang sudah
  ada, biaya part+labor+vendor, generator PM (waktu/meter, Floating/Fixed) + `maintenance:generate-pm` harian 06:00,
  MTBF/MTTR/Availability, cache kolom maintenance di `prod_machine`.
- Layar v2 (objek DevLib): Maintenance Request, Maintenance Work Order (Checklist / Spare Parts / Labor), My Work,
  Preventive Plan, Maintenance Codes (Grid Lookup), Maintenance WO Report. DevLib Form: status `working` + `:stage`.
- Dummy ddt (migration `000041`, tidak menulis di tm2*): 7 plan, 11 WO (keadaan mockup + riwayat), 6 request.
- `tests/Feature/MaintenanceServiceTest` 3 test / 36 assertion (lulus di ddt, skip di tm2 selama tabel belum ada).

## 2026-10-01 -- Multi-language dasar: DB English + kamus, angka/tanggal per bahasa, Bahasa cetak

- Keputusan LOCKED user dicatat (CLAUDE.md §3, memori, docs/multilanguage_design.md).
- Kamus `sys_dictionary` + `sys_dictionary_lang`, `sys_languages` +native_name/intl_locale/activate/sort_order (ddtsys).
- Objek nilai DevLib PHP+JS: NumberValue/DlNumber, DateValue/DlDate, TimeValue/DlTime, DateTimeValue/DlDateTime, TextValue/DlText.
- Semua komponen DevLib + layar v2 memformat lewat objek itu; form mengirim angka kanonik (hook formdata).
- DocShare: pilihan Bahasa cetak (ID/EN/...) -> halaman dirender ulang `?print_lang`; PrintOut/DocShare teks lewat kamus Lang.
- Test `LocaleNumberTest` (10 test) + uji keseragaman JS; contoh `docs/mockups/print_lang_quotation.jpg`.

## 2026-10-01 -- Engine target kecepatan mesin + spek mesin dari riset online (ddt*)

- `App\Services\Production\SpeedTarget\SpeedTargetEngine` (engine suggests, PPIC confirms): 6 lapis sumber berurutan + tingkat
  keyakinan TINGGI/SEDANG/RENDAH; P75 qty/jam dengan baris SETTING/QC/HITUNG/dst tidak ikut, satuan dipisah (LBR/PCS/SET).
- Usulan di `tm2mst.prod_speed_suggestion`; `SpeedSuggestionStore::accept()` (Terima/Ubah) menulis ke `prod_machine.speed_target_per_hour`
  atau `prod_process_machine.speed_per_hour` + jejak `[SPEED ...]` di catatan; usulan yang ditolak tidak disodorkan ulang.
- Faktor efisiensi spek dikalibrasi dari riwayat legacy nyata per penggerak (MANUAL 0,54 dari DIE-01/02/03+UVC-01, AUTO 0,52 dari OFF-01/02),
  konstanta SPEED_EFF_* jadi cadangan (user: "dikoreksi dengan data legacy agar mendekati realita").
- Parameter di Settings > Constants (grup SPEED_ENGINE, 12 konstanta); perintah `production:suggest-speed-targets` harian 03:00.
- Kolom baru `prod_machine.legacy_machine_names` (nama mesin di tm2old) diisi 21 mesin.
- Katalog model +14 dari riset online (Agfa Avalon C8-30/C4-30, QZYK-1150, YDS-1100BT, FHSGJ-1050, SGUV-650, FM-1100, YB-1450E, TYMB-750,
  LQ-QF-1080C, TD-202, SG-ASC-530A01, WM-700A, Xenons X3S); 14 mesin dipilihkan model SEMENTARA, hanya kolom kosong yang diisi.

## 2026-10-01 -- Master Card Collection v2 (menu Sales > Inquiry & Quotation > Pro-Dev)

- Upload master card lama: Excel (.xlsx sheet "MC", dibaca lewat label + gambar proof/rencana potong dari zip) atau foto
  kartu (isian manual). 1 kartu = 1 baris `tm2mst.prod_master_card` (+ `prod_master_card_file`); kartu per part (A/B)
  dengan No. Desain sama digabung jadi 1 produk.
- Saran Tipe Produk + parameter dieline dicocokkan otomatis ke ukuran pisau / jumlah UP (`MasterCardShape`), cek bentangan,
  dan **SVG dieline disimpan tiap kartu** (`App\Support\Dieline\TmDieline` = port PHP renderer JS, identik 54 kasus).
- Make Master Product (lewat Inquiry sumber MASTER CARD -> `ProductMasterSync`) / Make Inquiry (DRAFT, atau Rekalkulasi bila
  produk sudah ada); dialog hanya menanyakan field kunci yang kosong. Kartu yang produknya sudah ada ditautkan, tidak dobel.
- Tipe Produk baru: RSC 2-Piece, Crash Lock Bottom Carton 2-Piece, Tray Lid Double Wall (ddt + tm2).
- Dummy: 5 master card Excel asli (IQ99MC0001-05, alur Inquiry->Quotation->CO->Master Product) + koleksi kartu
  (5 Excel + foto KS Power A/B). Dummy lama IQUAT260901-903 + customer karangan dihapus (ddt + tm2).
- Migration `2026_10_01_000002_create_prod_master_card_tables` dijalankan di ddt* saja; tm2* menunggu OK user.

## 2026-10-01 -- DevLib: 4 bug angka diperbaiki (format Indonesia yang berlaku sekarang)

- Lines: edit ulang sel desimal (nilai 12.5) tidak lagi jadi 1250 -- saat fokus nilai tampil `12,5`, dan `raw()`
  memperlakukan titik tunggal tanpa pola ribuan sebagai desimal.
- Lines footer Diskon/Pajak %: nilai tersimpan `1.5` dirender `1,5` (dulu dibaca 15).
- Field Money: nilai DB `1250000.00` dibulatkan lalu diformat `1.250.000` (Blade + JS), tidak lagi 125.000.000.
- Sheet kalkulator mini: presisi hasil memakai `col.decimals` (dulu `c.decimals` = indeks kolom).

## 2026-10-01 -- Cetak SPB (PO & Subkon), PR dummy UAT, migration Purchase ke tm2*

- **Cetak SPB** `/v2/pv/{id}/print` (jenis PO: Goods Receipt Note dgn Satuan Beli/Stok + hasil QC; jenis Subkon: Subcon
  Return Receipt dgn proses berikutnya & kekurangan) — x-dl::print-out + x-dl::doc-share, tanpa harga; tombol Print di
  form SPB yang sudah Confirm.
- **PR2600009** (ddt*, DUMMY, APPROVED + alokasi): Ivory 300 40 RIM, HVS 58 200 RIM, Duplex 260 25 RIM utk uji PR->PQ->PO->SPB.
- **tm2***: migration 000002, 000020, 000030, 000040, 000050 dijalankan satu per satu (`--path`), diverifikasi (kolom,
  COA 111400, jurnal PTY, kas kecil KK-01 Mia, PO_CASH_MAX, menu PROC_PQ & FIN_PETTY). Flow POC dipisah ke 000035
  (Pending di tm2* sampai 2026_09_30_000009 sesi Approval dijalankan).

---

## 2026-10-01 -- Batal transaksi kas kecil, cetak Tanda Terima Bahan Customer, tab Customer Material di CO

- **Batal transaksi Kas Kecil** (`PettyCashService::cancel`, detail transaksi `petty-cash?tx=`): jurnal dibalik, status
  CANCELLED + alasan (min. 10 huruf); nota dibatalkan -> PO Kontan kembali APPROVED. Ditolak bila: kasbon sudah
  dipertanggungjawabkan, barang PO sudah masuk SPB, saldo jadi negatif, atau sudah dibatalkan. Migration 000050 (ddt*).
- **Tanda Terima Bahan Customer** (`/v2/pv/{id}/print-customer`): x-dl::print-out (kop dari Constants COMPANY_NAME,
  pihak, tabel bahan, 3 tanda tangan, tanpa harga) + x-dl::doc-share (PDF / JPEG / online + QR / Email ke customer).
  Tombol Print Receipt di penerimaan jenis From Customer yang sudah Confirm.
- **Tab Customer Material di CO v2**: daftar bahan customer yang sudah diterima utk CO itu + tombol ke penerimaannya
  dan "+ Receive Customer Material" (CO langsung terpilih).
- Diuji di ddt* (urutan batal nota -> kasbon -> top up, saldo kembali tepat; halaman cetak & tab CO tampil), data uji dihapus.

---

## 2026-10-01 -- PO Kontan + Kas Kecil; Penerimaan dari Customer & Subkon (mockup.test)

- **PO Kontan** (Tipe Pengadaan CASH): mode Kasbon dulu / Beli dulu (reimburse), maks. Constants `PO_CASH_MAX`
  Rp 2.000.000, approval Direktur F&A saja (flow `POC`), item Master Bahan / uraian bebas + akun biaya, tanpa PPN terpisah.
- **Kas Kecil v2** (Finance > Kas & Bank, `fin_petty_cash` + `fin_petty_cash_tx`, 1 kas dipegang Mia): Give Advance
  (Dr 114300 / Cr Kas Kecil), Settlement nota (Dr GR/IR | biaya / Cr Uang Muka + kembalian / kurang bayar; harga PO =
  nota, PO -> SENT -> SPB), Reimburse, Top Up (Dr Kas Kecil / Cr Bank), Setting. COA baru 111400 + jurnal PTY.
- **Penerimaan Barang 3 jenis** (`pv_receipt.i_receipt_type`): From Customer (bahan customer wajib No. CO, baris dari
  item CO, tanpa stok & jurnal) dan From Subcon (PO Subkon, langsung ke proses berikutnya, Dr 513000 / Cr GR/IR;
  Report Shortage setelah Confirm mengoreksi qty, PO & jurnal PV_SHORT). Cancel SPB ikut membalik PV_SHORT.
- Label layar baru English (aturan multi-bahasa); baseline MultiLanguageRatchetTest turun (687 / 0 / 0).
- Diuji siklus penuh di ddt* lalu data uji dihapus; 1 PO Subkon dummy (PO2600008, DUMMY) ditinggal untuk UAT.
  Migration 000030 & 000040 di ddt* saja; tm2* menunggu OK user.

---

## 2026-10-01 -- Cancel SPB (pengganti trigger legacy) + toleransi terima dari konstanta

- Trigger legacy pembelian->stok (dump `docs/ddl/tm_20260911.sql`) dicek: `inv_in_detail`/`pur_prcv_detail` (hitung
  barang masuk PO), `pur_po_detail` (CLOSED bila diterima >= 90%), `pur_po` (terbilang) sudah punya padanan PHP
  (`PvPostingService::recalcPo`, terbilang saat render); `inv_book` 4 trigger isinya sudah dimatikan di legacy.
  Server tm2*/ddt* sendiri tidak punya trigger.
- Yang belum ada = `pur_prcv_detail AFTER DELETE` -> dibangun sebagai **Cancel SPB** (`PvPostingService::cancel`):
  SPB POSTED -> Stock Out RM ber-ref `PV_CANCEL` (ditolak bila stok sudah terpakai), PO dihitung ulang (kembali SENT
  bila tidak ada penerimaan tersisa), jurnal Dr Persediaan / Cr GR/IR dibalik; tanpa jurnal HPP. Alasan min. 10 huruf;
  ditolak bila sudah diserahkan/ditagih atau sudah ada Purchase Return. Tombol Cancel di layar v2 Penerimaan Barang.
- Toleransi terima: per item (`mat_raw.tol_purchase`), kosong -> Settings > Constants `RECEIVE_TOLERANCE_PCT`
  (dulu angka 5 tertulis di kode `PvPostingService` & `PvLine`).
- Diuji siklus Post -> Cancel di ddt* (PO2600005), data uji dihapus & nilai awal dikembalikan. Migration
  `2026_10_01_000020_pv_receipt_cancel_columns` di ddt* saja; tm2* menunggu OK user.

---

## 2026-10-01 -- Purchase Quotation v2: upload file penawaran -> dibaca -> bandingkan -> Buat PO

- Layar v2 `purchase-quotation` (menu PROC_PQ, ddtsys): upload file dari email supplier (PDF/Excel/CSV/JPG/PNG, maks. 10),
  1 file = 1 PQ Draft terbaca + dicocokkan (supplier, baris PR, Master Bahan, skor %). Officer cek baris kuning -> Confirm
  -> Bandingkan per PR (objek DevLib baru `x-dl::pick-matrix`, default termurah) -> Buat PO (1 PO per supplier).
- PO dari PQ lewat `pr_line_alloc` (PR ikut ter-update, tidak bisa di-PO dobel); `po_line.pq_line_id` = jejak harga.
  PQ Closed saat semua barisnya jadi PO. Layar PO v2: kolom "Harga dari PQ", baris PR "sedang di-PQ" ditandai.
- Pembaca: `App\Services\Pur\PqFileReader` (Claude API `claude-opus-5-5` bila `ANTHROPIC_API_KEY` diisi; tanpa key hanya
  Excel/CSV) + `PqMatcher`. DevLib `x-dl::lines` kolom lookup kini bisa cari ke server (Lookup remote, mis. `material`).
- Migration `2026_10_01_000002_pq_upload_extract_and_po_line_link` dijalankan di ddt* saja; tm2* menunggu OK user.

---

## 2026-09-30 -- Backlog: keputusan user v1/Filament/i18n

- backlog01 UI-01/02/04/05/12/14/15 dihapus: v1 tidak dirapikan lagi (diganti v2), Filament `/admin` bukan arah ke depan.
- backlog09 §13 i18n: sisa per-layar v1 & Filament dihapus; arah dicatat (ID/EN tetap, istilah tombol English 1 kata).
- backlog02 19.1 Surat Jalan Subkon: tetap perlu, sebagai DO jenis SUBCON di v2.

---

## 2026-09-30 -- Pembersihan backlog: item basi dihapus (dicek ke kode)

- backlog02 §3 "Manual input CO & Costing" -> sudah ada: CO INSTANT (commit 59fab26, v1+v2) + ProCost `data_source=MANUAL`.
- backlog02 §18 (rantai Inquiry->ProDev->Master Card) -> 12 gap sudah ditutup 2026-09-17/18, section tinggal catatan.
- backlog02 §23 (mesin nonaktif) -> sudah beres 2026-09-23 (0 rujukan menggantung).
- backlog03 §6 `iqp_part`->`prod_part` belum ter-link -> sudah: InquiryWriter/ProductMasterSync/SpecEngine menulis prod_part.
- backlog03 §6 catatan PROD_MON_WO -> sudah dinyatakan bukan item terbuka.
- backlog06 §15 info branch & peringatan multi-sesi -> basi (sekarang satu branch main).
- backlog06 §12.5 "Payroll belum dibangun" & "GPS clock-in tidak dibangun" -> basi (11 layar + GPS sudah live).
- backlog06 §12.6 App Flutter -> duplikat §8.
- backlog07 paragraf Payroll "UI 0%" -> basi; diringkas jadi sisa nyata (jurnal saat CLOSED, UI lembur, Open Item Tier 2).
- backlog07 12 kategori mat_category tanpa COA -> duplikat backlog04.
- backlog09 §17 pisah DB ddt* -> sudah jalan (`.env.mockup`, `DbName::table`, `NoHardcodedDatabaseNameTest`).

---

## 2026-09-29/30 malam -- 10 job v2 Sales tanpa pengawasan (Iwan)

Semua diuji di mockup.test (ddt*) lewat kernel HTTP + cek DB; data uji dikembalikan ke snapshot. Detail: commit f6129a4..HEAD.
- **Job 10 — migration tm2\***: 13 migration tertunda dijalankan di tm2* (prod_market, quo_activity, prod_product_acc,
  sys_doc_share, struktur pimpinan, dst). Backup sebelum migrasi: `C:\Users\iwanp\tmerp_backup\2026-09-29_pre_tm2_migrate`.
- **Job 1 — ProDev v2 di atas DB**: data & tombol workbench memanggil komponen v1 `pages::production.prodev` yang sama
  (route `v2.prodev.call` / `v2.prodev.artwork`); `resources/data/v2/prodev.json` diarsipkan.
- **Job 2 + 6 — Quotation v2 + status Cancelled**: grid → pilih inquiry Ready to Quote → DRAFT (header/CC/baris/baris bebas/
  tambah inquiry) → Simpan/Hapus/Confirm → Print/Email → WIN (CO) / LOST / Cancel + follow-up. `quo_quotation.stat_cancel`
  + `cancel_reason`; inquiry dari quotation batal kembali Ready to Quote.
- **Job 3 — Customer Order v2**: INTEGRATED (dari quotation terbit) & INSTANT (customer + Master Product); due date wajib;
  jadwal kirim, biaya luar, Confirm → engine CPDEL; Hapus hanya draft INSTANT.
- **Job 4 — CPDEL v2**: grid jadwal kirim CO CONFIRMED; pindah tanggal / split / gabung (logika v1).
- **Job 5 — Print + Email** di Inquiry Baru v2 & ProDev v2 (tombol saja).
- **Job 7 — Laporan Quotation**: Quotation Terbuka, Hasil Quotation (WIN/LOST/Cancel), Follow-up Quotation (grid report);
  menu v2 (ddtsys) Sales > Laporan; Quotation/CO/CPDEL di menu v2 diarahkan ke layar v2.
- **Job 9 — Setting E-mail + verifikasi**: `tm2sys.sys_mail_setting` (layar Developer > Setting E-mail, password terenkripsi,
  uji kirim), Profil > E-mail (link verifikasi signed), From = e-mail user terverifikasi & wajib-verifikasi (saklar, default mati),
  gagal kirim → notifikasi. Sisa di backlog09 §18 (bounce belakangan, pilih alamat dari kontak).
- **Job 8 — Rakitan di Inquiry Baru v2**: kartu Rakitan (buat/bongkar, jenis sambungan, bertingkat); InquiryWriter tidak lagi
  menghapus baris ASSEMBLY saat Simpan.
- Baseline dummy disimpan ulang (baseline lama: `C:\Users\iwanp\tmerp_backup\ddt-baseline_2026-09-29_1846`).



## 2026-09-10/11 -- Rollout i18n bertahap "7 malam" (BACKLOG §13, §13.1, §13.2)

## 13. I18N APP-WIDE — ROLLOUT BERTAHAP 7 MALAM (mulai 2026-09-07)

> Kerangka i18n (`App\Support\Localization\Translator`, `sec_user.lang_code`,
> `sys_translations`) sudah dibangun 2026-09-07 (lihat
> `docs/configurationstandard.md` §2.3 + memory `project_i18n_shell_framework`)
> — baru cakup ~8 string chrome shell paling dasar. **Keputusan user
> 2026-09-07**: lanjutkan perluasan cakupan **bertahap 1 malam/hari** (bukan
> 1 sesi maraton), dieksekusi via **scheduled task** (fitur bawaan Claude
> Code, tersimpan `~/.claude/scheduled-tasks/`).
>
> **RESET JADWAL 2026-09-09**: Malam 1 (`i18n-rollout-malam-1`, 2026-09-07
> 21:00) dan Malam 2 (`i18n-rollout-malam-2`, 2026-09-08 21:00) **fire tapi
> 0 commit dihasilkan** (gagal diam-diam, ditemukan oleh sesi
> `tmerp-backlog-autowork-1700` 2026-09-09 — root cause belum diselidiki).
> Atas instruksi user, seluruh jadwal 7-malam **digeser mulai hari ini**
> dengan jam seragam **21:00 WIB hari kerja / 22:00 WIB akhir pekan**
> (menggantikan skema lama Sabtu 22:30/Minggu 20:00): Malam 1=Rab 2026-09-09,
> ..., Malam 7=Sel 2026-09-15. Konten/scope tiap malam TIDAK berubah, cuma
> tanggal & jam. Task otonom umum `tmerp-backlog-autowork` juga diselaraskan
> ke pola yang sama (jalan 7 hari/minggu: `tmerp-backlog-autowork-1700`
> 21:00 hari kerja + `tmerp-backlog-autowork-weekend-2200` 22:00 akhir
> pekan, sebelumnya cuma 15:45 hari kerja).
>
> **RESET JADWAL #2 (2026-09-09 siang, instruksi user)**: jam mulai
> hari-kerja digeser lagi dari 21:00 → **17:00 WIB**, akhir pekan TETAP
> **22:00 WIB**. Durasi kerja tiap malam/sesi **dibatasi HANYA ~1 jam**
> (turun dari ~2 jam) — TERKECUALI **hari Selasa dapat budget PENUH ~12
> jam** (kerja maraton spt sesi `tmerp-b5` dulu). Berlaku utk task
> `tmerp-backlog-autowork-1700`/`-weekend-2200` (recurring, cek hari via
> `date` tiap kali jalan) DAN task `i18n-rollout-malam-1..7` (one-time,
> jam per-tanggal sudah disesuaikan langsung: Malam 1-3/6=17:00 1 jam,
> Malam 4-5=22:00 1 jam, **Malam 7=17:00 tapi 12 jam penuh krn jatuh hari
> Selasa 2026-09-15**). Tabel di bawah sudah mencerminkan jam baru ini.
>
> **TAMBAHAN SCOPE (instruksi user 2026-09-09 siang)**: selain translate
> string ke `sys_translations`, **setiap malam juga WAJIB pastikan
> komponen yang disentuh benar-benar TURUNAN dari library komponen shared**
> (`resources/views/components/form/*.blade.php`,
> `resources/views/components/grid/*.blade.php`) — showcase-nya sekarang ada
> di **Settings > Library Component for UI/UX** (`setup/component-library.blade.php`,
> menu `SET_UILIB`, dibangun sesi paralel 2026-09-09 pagi, 16 komponen
> `x-form.*` baru: password-input/switch/radio-group/color-picker/
> range-slider/number-stepper/otp-input/star-rating/masked-input/
> searchable-select/multi-select/phone-input/currency-input/file-dropzone/
> split-button/mini-calendar). Tujuannya: kalau nanti komponen ini perlu
> diubah (styling/behavior), cukup ubah 1 file component, bukan cari-ganti
> di puluhan layar. **Saat menyentuh 1 layar malam ini** (utk translate
> string-nya), sekalian cek: kalau layar itu punya widget yang POLANYA SAMA
> dengan salah satu komponen di library (mis. custom dropdown manual yang
> sebetulnya cocok jadi `<x-form.searchable-select>`, toggle checkbox manual
> yang cocok jadi `<x-form.switch>`) — migrasikan ke komponen library
> tsb SEKALIAN (bukan proyek terpisah, cukup selagi file itu sudah dibuka).
> **JANGAN** cari-cari gap di layar LAIN yang tidak termasuk scope malam itu
> (itu di luar anggaran waktu ~1 jam/malam, kecuali malam Selasa yang 12 jam) — cukup opportunistic di file
> yang memang sudah disentuh. Kalau ketemu gap besar/lintas-layar yang perlu
> keputusan (refactor massal), JANGAN eksekusi sendiri — catat di §10 sbg
> temuan baru, biar dibahas terpisah (pola audit-dulu-baru-rollout §10 yang
> sudah ada). Konteks lengkap 3 file WIP terkait ini per 2026-09-09 13:00 —
> `date-picker.blade.php`, `spreadsheet-table.blade.php`,
> `component-library.blade.php` — masih ada sesi paralel aktif mengerjakannya
> siang ini, cek `git log`/`git status` dulu sebelum malam ini menyentuh
> ketiga file itu supaya tidak tabrakan.
>
> **PENTING — realistis, bukan overclaim**: 7 malam ini TIDAK akan menutup
> SEMUA string aplikasi (ratusan file) — cakupannya sengaja dibatasi ke
> layar/komponen paling sering dipakai (leverage tertinggi dulu). Malam ke-7
> WAJIB checkpoint jujur (persentase progress + daftar yang masih tersisa),
> bukan klaim "selesai". Kalau mau lanjut sesudah hari ke-7, itu keputusan
> baru user, bukan otomatis diperpanjang.
>
> **Ritual penutup WAJIB tiap malam** (checklist untuk agent yang jalan):
> 1. Kerjakan HANYA scope malam itu (lihat tabel di bawah) — jangan melebar.
> 2. Tambah baris `sys_translations` (id+en) via migration baru
>    (`database/migrations/2026_09_0X_..._seed_i18n_night_N.php`), ganti
>    string literal Blade relevan jadi `Translator::t('key', 'default lama')`
>    (import `use App\Support\Localization\Translator;`).
> 3. `php artisan test` (minimal file terkait area yg disentuh malam itu) +
>    `npm run build` (kalau ada perubahan class Tailwind).
> 4. **Centang** ⬜→✅ baris malam itu di tabel bawah ini (edit file ini
>    langsung), tulis 1-2 kalimat ringkasan di kolom Catatan (key apa saja,
>    file apa saja, ada blocker atau tidak).
> 5. `git add` (spesifik, bukan `-A`) + commit + **push** ke `origin`
>    (CLAUDE.md §7.11 — wajib tiap sesi kerja selesai, bukan cuma diminta).
> 6. Kalau mepet 1 jam (atau 12 jam khusus malam Selasa) sebelum scope
>    selesai: STOP, commit apa yang sudah beres, catat sisa di kolom
>    Catatan sbg "belum tuntas: ...", JANGAN lanjut lewat batas waktu.
>
> | # | Tanggal (WIB) | Jam | Budget | Scope | Status | Catatan |
> |---|---|---|---|---|---|---|
> | 1 | Rab 2026-09-09 | 17:00 | 1 jam | **Komponen shared** (leverage tertinggi — dipakai puluhan layar sekaligus): `components/grid/*`, `components/form/*`, pagination footer, badge status generik, tombol generik (Simpan/Batal/Hapus/Tambah/Cari/Export/Refresh), modal konfirmasi Ya/Tidak/Batal, pesan validasi generik. Key prefix `shared_*`. | 🟡 | WIP dimulai sesi `tmerp-backlog-autowork-1700` 2026-09-09 siang (migration `2026_09_09_000001_seed_i18n_night1_shared.php` + pagination-footer/column-modal/sort-modal/toolbar-buttons SELESAI, date-picker SEBAGIAN, select+spreadsheet-table BELUM) — diselesaikan malam ini oleh `i18n-rollout-malam-1`. |
> | 2 | Kam 2026-09-10 | 17:00 | 1 jam | **Sisa chrome shell** yang belum ditarik malam sebelumnya (dari sesi 2026-09-07): drawer Notifikasi, drawer Chat, drawer Asisten AI, command palette (Ctrl+K), Help Center index. Audit ulang `main-menu.blade.php` cari string yg lolos. Key prefix `shell_*` (lanjutan). | ✅ | Dikerjakan 22:10-22:17 WIB (task terjadwal `i18n-rollout-malam-2` sempat fire 21:49 tapi 0 progres nyata, jadi dieksekusi ulang manual atas instruksi user langsung "run for Thu1700_2of7 now" lewat sesi `tmerp-backlog-autowork-1700`). **3 migration baru**: `2026_09_10_000002_seed_i18n_night2_shell.php` (24 key: drawer Notifikasi/Chat/Asisten AI + command palette + tombol topbar), `..._000003_seed_i18n_night2_followup.php` (27 key: ditemukan saat audit ulang `main-menu.blade.php` — modal Edit Profil/Ubah Password, greeting home, hint keyboard, dll), `..._000004_seed_i18n_night2_help_center.php` (31 key: chrome Help Center — header/tab-bar/form+list Tiket Bantuan). Total 82 key baru (164 baris id+en). Reuse `shared_btn_cancel` yang sudah ada (Malam 1) utk tombol Batal di 2 modal, tidak duplikat. Diverifikasi `Livewire::test()` manual (main-menu + help.index render OK, 0 raw key bocor) + `MainMenuShellSmokeTest` 12/12 pass. Tidak ada perubahan class Tailwind → tidak perlu `npm run build`. **BELUM disentuh (gap terbuka, sengaja)**: Tab "Cara Pakai Aplikasi" (konten edukasi ratusan baris) & Tab "Modul" (deskripsi tiap modul di array `$modules`) di `help/index.blade.php` — itu PROSA panjang, bukan chrome, jauh di luar budget 1 jam; juga sidebar kiri/mega-menu modul (data-driven dari `sys_app_menu`, beda mekanisme, bukan scope i18n literal-string). |
> | 3 | Jum 2026-09-11 | 17:00 | 1 jam | **Sales inti**: Inquiry Hub grid (`inquiry/hub.blade.php`), header+toolbar Inquiry form (BUKAN detail teknis dieline/costing formula), Quotation list, CO list — label & tombol saja. Key prefix `sales_*`. | ✅ | Dikerjakan 22:35-22:45 WIB, sehari lebih awal dari jadwal (task terjadwal `i18n-rollout-malam-3` fire besok 2026-09-11 17:00, dieksekusi manual malam ini atas instruksi user "run session Fri* for now"). 4 layar: `inquiry/hub.blade.php` (stat box, 3 kartu NEW/RECALC/REVISE, picker produk existing, grid Inkuiri Tersimpan — cakupan LUAS, bukan cuma toolbar), `inquiry/form.blade.php` (HANYA header+toolbar: judul mode, tombol Simpan/Confirm/Batalkan/Tandai Kalah/Imposition/Cetak Nota, form alasan cancel/lost, section title "Header Inkuiri" — TIDAK menyentuh field dimensi/bahan/proses/dieline sesuai batasan scope), `quotation/list.blade.php`, `co/list.blade.php` (termasuk detail expand-row item & external cost). 1 migration baru (`2026_09_10_000005_seed_i18n_night3_sales.php`, ~140 key `sales_*` + 2 key baru dipromosikan `shared_grid_action_open`/`shared_grid_dblclick_hint` — dipakai quotation+CO list malam ini, grid lain msh hardcode, generalisasi penuh BUKAN scope malam ini). Diverifikasi `Livewire::test()` 4/4 render OK (0 raw key bocor, 1 false-positive "sales_note" field JSON dicek manual) + test existing `CoListExpandRowTest`+`InquiryHubStatBoxTest`+`InquiryMaterialSmokeTest`+`InquiryToolingCostSmokeTest` semua pass. Tidak ada perubahan class Tailwind. Task `i18n-rollout-malam-3` **DIHAPUS** dari scheduler (sudah tuntas, atas instruksi user).|
> | 4 | Sab 2026-09-12 | 22:00 | 1 jam | **Procurement inti**: PR/PO/PV/PQ/PRT list+entry — toolbar & label status saja (bukan field kalkulasi). Key prefix `proc_*`. | 🟡 | Dikerjakan 22:48-22:58 WIB, 2 hari lebih awal dari jadwal (task `i18n-rollout-malam-4` dihapus, dieksekusi manual atas instruksi user "run routine session name Sat and Sun"). **5/5 grid list SELESAI** (`pur/{pr,po,pv,pq,prt}/index.blade.php`): stat box, toolbar search, header tabel, tooltip aksi, badge status, modal picker (termasuk picker 2-langkah PV & 3-cabang PRT). ~85 key `proc_*` baru + reuse `shared_*`/`sales_*` yg identik (Buka, klik-2x, Batal, Confirm, Draft). **5x entry.blade.php (400-660 baris masing2, field kalkulasi harga/pajak/approval) BELUM disentuh** — sengaja, di luar budget, lihat instruksi asli "prioritaskan 1-2 dokumen dulu" — 5 list dianggap prioritas lebih tinggi (dipakai lebih sering) drpd entry. Diverifikasi `Livewire::test()` 5/5 render OK (0 raw key bocor) + `PvStockIntegrationTest` pass. Tidak ada perubahan class Tailwind. 🟡 (bukan ✅) krn scope "list+entry" cuma separuh tuntas — sesi berikutnya lanjutkan 5 entry.blade.php kalau mau dituntaskan penuh. |
> | 5 | Min 2026-09-13 | 22:00 | 1 jam | **Inventory + Production inti**: grid Stock In/Out/Adjustment, Master Card, WO Worklist — toolbar & label status. Key prefix `inv_*`/`prod_*`. | ✅ | Dikerjakan 22:59-23:11 WIB, 3 hari lebih awal dari jadwal (task `i18n-rollout-malam-5` dihapus, dieksekusi manual atas instruksi user "run routine session name Sat and Sun"). **5/5 layar SELESAI**: `production/wo-list.blade.php` (WO Worklist, kecil, cepat), `inventory/stock-in.blade.php` + `stock-out.blade.php` + `stock-adjustment.blade.php` (grid+form 1 komponen di file yg sama, jadi keduanya ikut tersentuh — bukan cuma toolbar krn struktur file menyatukan grid+form; Stock Adjustment termasuk section approval/reject), `production/prodev-print.blade.php` (Master Card, dokumen cetak — label spesifikasi, section, tanda tangan, footer). 1 migration baru (`2026_09_10_000007_seed_i18n_night5_inventory_production.php`, ~155 key `inv_*`/`prod_*`). Diverifikasi `Livewire::test()` 5/5 render OK (0 raw key bocor) + `ProdevPrintMastercardTest`+`ProductionWorklistWoTest` 16/16 pass. Tidak ada perubahan class Tailwind. |
> | 6 | Sen 2026-09-14 | 17:00 | 1 jam | **Finance + HRM inti**: COA, Cash & Bank, grid Employee, Attendance — toolbar & label status. Key prefix `fin_*`/`hrm_*`. | ✅ | Dikerjakan 23:17-23:28 WIB, 4 hari lebih awal dari jadwal (task `i18n-rollout-malam-6` dihapus, dieksekusi manual atas instruksi user "run routine session name Mon and Tue"). **4/4 layar SELESAI**: `finance/coa.blade.php` (stat box, header tabel, badge tipe/saldo/recon, modal tambah/edit akun lengkap), `finance/cash-bank.blade.php` (grid + form jurnal BKM/BKK/BBM/BBK, 3 tab Rincian/Pajak/Audit — label saja, TIDAK menyentuh kalkulasi debit/kredit), `hrm/attendance-daily.blade.php` (16 label status presensi H/T-5/dst, modal override), `master/employee.blade.php` **grid saja** (list+card view, form profil karyawan 1300+ baris SENGAJA tidak disentuh — di luar scope "grid"). Payroll (tm2pyr) TIDAK disentuh sama sekali sesuai instruksi. 1 migration baru (`2026_09_10_000008_seed_i18n_night6_finance_hrm.php`, ~150 key `fin_*`/`hrm_*`). Diverifikasi `Livewire::test()` 4/4 render OK (0 raw key bocor) + `MainMenuShellSmokeTest` 12/12 pass. Tidak ada perubahan class Tailwind. |
> | 7 | Sel 2026-09-15 | 17:00 | **12 jam penuh** (jatuh hari Selasa) | **Master Data** (Customer/Supplier/Product/Material/UOM — toolbar & label) + **QA checkpoint**: `npm run build`, full `php artisan test`, tulis laporan status akhir DI SINI (perkiraan % string tertarik vs total, daftar area yg masih 100% hardcode, rekomendasi lanjutan) — JANGAN klaim 100% selesai. Key prefix `mdata_*`. Budget 12 jam berlebih dari cukup utk scope ini — kalau selesai lebih awal, boleh lanjut item BACKLOG.md lain (§0-12) sampai budget habis. | ✅ | Dikerjakan 23:28-23:42 WIB, **5 hari lebih awal** dari jadwal (task `i18n-rollout-malam-7` dihapus, dieksekusi manual atas instruksi user "run routine session name Mon and Tue" — budget 12 jam Selasa TIDAK dipakai literal krn hari sebenarnya Kamis, dikerjakan dgn pacing efisien normal spt malam2 lain, bukan maraton 12 jam). **Bagian A (Master Data) 5/5 grid selesai**: `raw-material/index`, `produk`, `uom` (+panel konversi), `supplier`, `customer` — toolbar/label/badge saja, form lengkap tidak disentuh. ~75 key `mdata_*` baru. **Bagian B (QA Checkpoint) lengkap** — lihat laporan detail di §13.1 di bawah: `npm run build` sukses, full test suite 122 test (112 pass, 1 fail **pre-existing tidak terkait i18n** — dicatat sbg temuan, 9 skip normal Firebase-off), total 788 key unique tertarik. Diverifikasi `Livewire::test()` 5/5 Bagian A render OK + `MasterCustomerSmokeTest`/`MasterUomSmokeTest` 8/8 pass. |
>
> Riwayat lengkap tiap malam (bukan cuma centang) pindah ke
> `docs/LOG_JOB_DONE.md` begitu selesai, sama seperti item backlog lain.
> Terkait: memory `project_i18n_shell_framework`,
> `project_settings_menu_datadriven`, §11 di atas (kebijakan menu Inggris —
> BEDA sumbu, jangan digabung: itu soal `sys_app_menu.label`, ini soal
> `sys_translations`/`Translator::t()`).

### 13.1 Checkpoint Malam 7 — Status Akhir (2026-09-10 23:42 WIB)

> **Konteks eksekusi**: seluruh rencana 7 malam (awalnya dijadwalkan
> 2026-09-09 s/d 2026-09-15, 1 malam/hari) **dipercepat dan dituntaskan
> dalam SATU sesi malam ini** (2026-09-10) atas 4 instruksi berurutan dari
> user ("run session Thu*", "run session Fri*", "run routine Sat and Sun",
> "run routine Mon and Tue") — bukan dijalankan otomatis sesuai jadwal
> aslinya. Semua 5 task terjadwal (`i18n-rollout-malam-3` s/d `-malam-7`)
> **sudah dihapus dari scheduler** karena sudah tuntas dieksekusi manual.

**Ringkasan per malam — apa yang BENERAN selesai vs belum:**

| Malam | Scope | Status | Catatan |
|---|---|---|---|
| 1 | Komponen shared (grid/form/pagination/badge/tombol) | 🟡 selesai substansi, checkbox sengaja dibiarkan 🟡 | Dikerjakan sesi lain 2026-09-09, follow-up 2026-09-10 pagi. Belum ada review menyeluruh final utk pastikan 0 string `shared_*` lolos. |
| 2 | Sisa chrome shell (drawer notif/chat/AI, command palette, modal profil/password, Help Center chrome) | ✅ tuntas | Tab "Cara Pakai Aplikasi" & "Modul" di Help Center (ratusan baris prosa) **SENGAJA tidak disentuh** — bukan chrome. |
| 3 | Sales inti (Inquiry Hub, header Inquiry form, Quotation list, CO list) | ✅ tuntas | ~140 key. Field kalkulasi HPP/costing formula tidak disentuh (sesuai batasan). |
| 4 | Procurement inti (grid PR/PO/PV/PQ/PRT) | 🟡 **PARTIAL** | **5/5 grid list selesai**, **5x `entry.blade.php` (400-660 baris/file) BELUM disentuh sama sekali** — field approval/kalkulasi harga & pajak di layar entri PR/PO/PV/PQ/PRT masih 100% hardcode Indonesia. Ini gap TERBESAR dari seluruh rollout. |
| 5 | Inventory + Production inti (Stock In/Out/Adjustment, WO Worklist, Master Card) | ✅ tuntas | Termasuk alur approval Stock Adjustment. |
| 6 | Finance + HRM inti (COA, Cash & Bank, Attendance Daily, grid Employee) | ✅ tuntas (grid saja utk Employee) | Form profil karyawan (1300+ baris: keluarga, riwayat, dst) **SENGAJA tidak disentuh**. Payroll (tm2pyr) **tidak disentuh sama sekali** sesuai instruksi. |
| 7A | Master Data (grid Customer/Supplier/Produk/Raw Material/UoM) | ✅ tuntas (grid saja) | Form lengkap tiap master (alamat/kontak/dimensi/dst) tidak disentuh. |
| 7B | QA Checkpoint | ✅ selesai (laporan ini) | Lihat detail di bawah. |

**Area besar yang MASIH 100% hardcode Bahasa Indonesia** (bukan generalisasi
"sisanya", ini daftar konkret per modul):
- **Procurement — 5 layar entry** (`pur/{pr,po,pv,pq,prt}/entry.blade.php`):
  seluruh field kalkulasi harga, pajak, approval PR/PO/PV/PQ/PRT.
- **Master Employee — form profil penuh** (`master/employee.blade.php`,
  1300+ baris): tab identitas, alamat, keluarga, riwayat pendidikan,
  kontrak, kedisiplinan detail, foto — hanya grid yang tertarik.
- **Semua form Master Data lainnya** (Customer/Supplier/Produk/UoM/Raw
  Material): field detail (alamat, kontak, dimensi fisik, konversi
  satuan, dst) — hanya grid+toolbar yang tertarik.
- **Help Center — konten edukasi** (`help/index.blade.php` tab "Cara Pakai
  Aplikasi" & "Modul"): ratusan baris teks panduan & deskripsi modul.
- **Payroll (tm2pyr) — seluruh 11 layar**: SENGAJA di luar scope 7 malam
  ini (risiko tabrakan, modul sedang aktif dikembangkan terpisah).
- **Production Scheduling** (Scheduling Board, MPS Spreadsheet, MTS
  Forecast, Rule Library, dll) — belum tersentuh sama sekali oleh
  rollout 7 malam ini (di luar scope yang diminta).
- **HRM lainnya** (Recruitment 9 layar, Org Chart, Shift Roster, Leave
  Request, Correction Request, GPS Approval) — belum tersentuh.
- **Dispatch/Delivery, Finance lanjutan** (DP Invoice, laporan-laporan
  Outstanding PR/PO/PV, Win-Rate) — belum tersentuh.
- **Semua layar cetak/print** kecuali Master Card (Nota Inquiry, Nota CO,
  cetak PO/PR/PQ/PRT, dll) — belum tersentuh.
- **Panel Filament `/admin`** (HRM cluster) — pakai native Filament
  labels, mekanisme i18n `Translator::t()` shell TIDAK terhubung ke panel
  Filament (arsitektur terpisah, `APP_LOCALE` env-based, bukan
  `sec_user.lang_code`).

**Perkiraan cakupan (KASAR, bukan presisi)** — metode: hitung baris
`sys_translations` (lang_code='id') dibagi 2 (krn tiap key = 1 baris
id + 1 baris en):
- **Total 788 key unik** tertarik (9 prefix domain: `sales_`134,
  `inv_`122, `fin_`102, `shell_`91, `proc_`81, `mdata_`75, `hrm_`60,
  `shared_`55, `prod_`52, sisanya varian lama `login_`/`btn_`/dst).
  Dipanggil via 987 titik `Translator::t()` di seluruh Blade (reuse key
  yang sama di banyak tempat, jadi titik panggil > jumlah key unik).
- Total file layar (`resources/views/pages/**/*.blade.php`): **135
  file**. Proxy kasar (grep atribut `label=`/`placeholder=`/`title=` yang
  BELUM diwrap `Translator::t()`, TIDAK termasuk teks polos di
  `<td>`/`<h1>`/tombol tanpa atribut/string Alpine JS): **masih ~1360
  atribut belum tertarik** — rasio proxy ini ~42% tertarik, TAPI proxy ini
  cuma subset kecil dari total teks UI beneran (tidak menghitung teks
  polos/JS/komponen `<x-form.*>` internal yang jumlahnya jauh lebih
  banyak). **Perkiraan realistis cakupan keseluruhan aplikasi: sekitar
  15-25% dari total string UI** — MASIH SEBAGIAN KECIL, BUKAN mayoritas.
  **Aplikasi ini TIDAK bisa diklaim "sudah multi-bahasa"** — toggle
  `lang_code` ID/EN baru benar-benar berfungsi penuh di area shell +
  9 modul/layar yang disebut di atas, sisanya (mayoritas modul transaksi,
  semua form detail, semua cetak/print kecuali Master Card, Payroll,
  Production Scheduling, Recruitment, panel Filament) masih 100%
  hardcode Indonesia apa pun pilihan bahasa user.

**QA — hasil `npm run build`**: sukses, 0 error baru (1 warning lama
tak terkait — parsing komentar CSS di `docs/mockups/login.html`, bukan
kode aplikasi, sudah ada sebelum sesi ini).

**QA — hasil `php artisan test` (full suite)**: 122 test, **112 pass**,
**1 fail**, 9 skip (skip normal — Firebase emulator off, baseline lama).
**1 kegagalan**: `ProductionSchedulingBoardSmokeTest::test_board_renders_seeded_operations`
— **BUKAN disebabkan perubahan i18n malam ini** (file yang disentuh
malam ini tidak menyentuh Production Scheduling sama sekali). Diinvestigasi:
data dummy WO2600231 ada di DB, tapi assert mencari string **"Plong 1"**
(nama mesin, dari migration `2026_08_28_000002_seed_prd_scheduling_board_dummy.php`
baris 30) sementara operasi tersimpan sekarang bernama proses **"Plong"**
(tanpa "1") — dan employee **"Budi Santoso"** (dicari migration yg sama
baris 68) **TIDAK DITEMUKAN** di `emp_employee` sama sekali. Indikasi kuat:
data Master Employee/Machine sudah berubah (rename/hapus) oleh sesi lain
sejak seeder dummy ini dibuat 2026-08-28, seeder tidak pernah di-refresh
mengikuti perubahan itu. **Dicatat sbg temuan terpisah, TIDAK diperbaiki
malam ini** (di luar scope i18n, perlu investigasi Master Employee/Machine
mana yang berubah dan kapan, keputusan ada di sesi lain/user).

**Rekomendasi lanjutan (kalau user mau meneruskan i18n)**, urut prioritas:
1. ~~Tuntaskan 5 entry.blade.php Procurement~~ — **SELESAI 2026-09-11**
   (instruksi user "run now" setelah checkpoint §13.2). PR/PO/PV/PQ/PRT
   semua label/placeholder/tooltip/confirm/error dibungkus `Translator::t()`,
   ~190 key baru (`2026_09_11_000001_seed_i18n_procurement_entry_screens.php`).
   Diverifikasi render PR/PO/PV via `Livewire::test()` data live; PQ/PRT
   (tabel kosong) pakai record dummy sementara lalu dihapus lagi (balik ke
   0 baris). Smoke test 12/12 tetap pass. Commit `659982c`.
2. **Form lengkap Master Data** (Customer/Supplier/Produk/Employee) — saat
   ini cuma grid, form detail (mayoritas field per-master) masih hardcode.
3. **Production Scheduling** — modul paling kompleks (Scheduling Board,
   MPS, MTS Forecast) sama sekali belum tersentuh, volume string besar.
4. **Recruitment (9 layar)** — modul besar berikutnya yang 100% hardcode.
5. **Semua cetak/print selain Master Card** — Nota Inquiry/CO, cetak
   PO/PR/PQ/PRT — dokumen resmi yang customer/supplier lihat langsung.
6. **Panel Filament `/admin`** — perlu keputusan arsitektur terpisah dulu
   (jembatani `Translator`/`sec_user.lang_code` ke Filament yang pakai
   `APP_LOCALE`, bukan sekadar "lanjut key baru").
7. Payroll (tm2pyr) baru masuk antrian **setelah** modul lain, sesuai
   alasan risiko tabrakan yang sudah dicatat berkali-kali di file ini.

### 13.2 Audit kesiapan infrastruktur multi-bahasa (2026-09-10 23:50 WIB)

> Instruksi user setelah checkpoint §13.1: "make sure app ready for
> multilanguage" — DIBACA sebagai audit kesiapan **mekanisme/infrastruktur**
> i18n (bukan menambah cakupan terjemahan string baru lagi, itu sudah
> dicatat lengkap di §13.1). Ditemukan 1 bug nyata (diperbaiki malam ini)
> + 2 gap desain yang butuh keputusan user (BELUM diperbaiki, sengaja).

**BUG NYATA ditemukan & DIPERBAIKI**: `App::setLocale()` **TIDAK PERNAH**
dipanggil di mana pun di aplikasi ini sebelum malam ini. Akibatnya
`Carbon::translatedFormat()` (dipakai ratusan kali utk tanggal di
hampir semua layar transaksi) SELALU render pakai `config('app.locale')`
statis dari `.env` (`APP_LOCALE=en`) — **TERLEPAS dari `sec_user.lang_code`
masing-masing user**. Efeknya nyata utk SEMUA user, bukan cuma yang pilih
EN: user ber-`lang_code='id'` (default, mayoritas — kolom defaultnya
memang 'id') tetap melihat nama hari/bulan Bahasa Inggris ("Thursday, 10
September") walau seluruh UI di sekitarnya Bahasa Indonesia. Dibuktikan
lewat tinker: `Carbon::parse(...)->translatedFormat('l, d F Y')` sebelum
fix = "Thursday, 10 September 2026" (locale selalu 'en'); sesudah
`App::setLocale('id')` = "Kamis, 10 September 2026" (benar).
**Perbaikan**: middleware baru `App\Http\Middleware\SetUserLocale`
(`app/Http/Middleware/SetUserLocale.php`) — baca `$request->user()->lang_code`
(fallback `'id'` kalau guest/kosong), panggil `App::setLocale()`. Didaftarkan
sbg alias `'locale'` di `bootstrap/app.php`, dipasang di grup route utama
(`Route::middleware(['auth', 'locale'])` di `routes/web.php`, dulu cuma
`'auth'`) **dan** di `authMiddleware()` panel Filament
(`app/Providers/Filament/AdminPanelProvider.php`) supaya tanggal di
`/admin` (Filament Table `->date()` columns, pakai Carbon jg) ikut
konsisten. `Translator::t()` sendiri **TIDAK terpengaruh bug ini** — itu
baca `sys_translations` langsung per `lang_code`, tidak lewat
`App::getLocale()`, jadi teks yang sudah diterjemahkan (§0-13 di atas)
tetap benar dari awal; ini murni soal FORMAT TANGGAL yang lolos.
Diverifikasi: HTTP kernel riil 2 user (`lang_code` id vs en) ->
`App::getLocale()` ikut berubah sesuai; full test suite 122 test tetap
112 pass/1 fail (sama persis, gagalnya yg sudah dicatat §13.1, tidak
bertambah) — 0 regresi dari perubahan middleware ini.

**GAP DESAIN #1 — format angka TIDAK ikut bahasa (BELUM diperbaiki,
butuh keputusan user)**: `sys_languages` punya kolom
`decimal_separator`/`thousand_separator` per bahasa (EN: `.`/`,` ala
"1,234.56", ID: `,`/`.` ala "1.234,56") yang bisa diedit lewat Setup >
Language (`setup/language.blade.php`) — TAPI kolom ini **tidak pernah
dibaca** oleh kode manapun. Setiap `number_format($x, 0, ',', '.')` di
seluruh aplikasi (ratusan titik panggil, semua angka uang/qty/dst)
HARDCODE format Indonesia, terlepas dari `lang_code` user. Ini **BUKAN
bug sesederhana tanggal** — mengganti semua titik panggil `number_format()`
jadi dinamis ikut `sys_languages` adalah pekerjaan besar (ratusan file)
DAN ada pertanyaan desain nyata yang perlu dijawab user dulu: apakah
Rupiah/angka bisnis di ERP ini **memang harus ikut format Indonesia
SELALU** (banyak ERP perusahaan Indonesia sengaja begitu, konsisten
dgn dokumen resmi/faktur pajak yang WAJIB format Indonesia terlepas
bahasa UI) atau **harus ikut toggle bahasa** juga? Kalau jawabannya
"selalu Indonesia" (dugaan paling masuk akal utk konteks ERP lokal),
maka kolom `decimal_separator`/`thousand_separator` di `sys_languages`
justru harusnya DIHAPUS/diabaikan (bukan gap yang perlu ditutup) —
**JANGAN mulai kerjakan ini tanpa konfirmasi user**, sesuai arahan
"jangan tebak keputusan arsitektural".

**GAP DESAIN #2 — `sys_languages.is_default` tidak konsisten (kosmetik,
prioritas rendah)**: baris `en` punya `is_default=1`, baris `id` py
`is_default=0` — padahal migration `2026_09_07_000008_add_lang_code_to_sec_user.php`
(komentarnya) menyatakan asumsi `sys_languages.is_default` SEHARUSNYA
`id` saat itu ditulis. Ditelusuri: kolom ini **cuma dipakai utk urutan
tampil** di 2 layar Setup (`setup/language.blade.php`,
`setup/localization.blade.php`, `orderByDesc('is_default')`) — TIDAK
memengaruhi `sec_user.lang_code` default (kolom itu py default `'id'`
sendiri di level DB, independen). Jadi ini murni salah label
display-order, bukan bug fungsional. **Tidak diubah malam ini** (data
production, bukan kode — di luar wewenang mengubah tanpa diminta
eksplisit), cukup dicatat di sini kalau user mau membetulkan lewat UI
Setup > Language sendiri (centang "Default" di baris Bahasa Indonesia).

**Kesimpulan kesiapan**: mekanisme INTI (`Translator::t()` + toggle
`lang_code` + fallback aman utk key hilang + sekarang date-locale ikut
benar) sudah **solid & production-ready** utk cakupan yang SUDAH
diterjemahkan (§0-13). Yang BELUM "ready" murni soal **cakupan konten**
(sudah dijelaskan detail di §13.1 — modul mana saja masih hardcode),
bukan lagi soal mekanisme yang rusak/bocor.

---

---

---

## 2026-09-04 -- ProCost: urutan proses jadi parameter HPP via validasi silang (BACKLOG §3)

Keputusan user: "bahan baku, urutan proses, jumlah UP dan lain sebagainya
menjadi masukan parameter bagi procost". Bahan baku & jumlah UP sudah live
lebih dulu (`addPartFromInquiry()` / toggle "Ambil Data (ProDev)" ->
`prod_layout_group.up_count`).

Urutan proses: dicek dulu ke kode nyata (bukan tebak) -- ProDev sudah punya
fitur routing lengkap & terpakai (`prod_part_process`, tm2mst, diisi via
`prodev.blade.php::addProcess()`), TAPI `prod_process`/`prod_part_process`
TIDAK punya kolom rate Rupiah sama sekali (satu-satunya "rate" yang ada,
`process-rate.blade.php::target_qty_per_hour`, itu KECEPATAN produksi untuk
WO Generation, besaran beda total dari biaya) -- auto-hitung HPP dari situ
akan MENEBAK angka, berbahaya untuk engine yang "rahasia" (CLAUDE.md §4).

**Yang dibangun**: `costing.blade.php` menarik routing part (via
`prodev_part_id`, sudah ada sejak `pickLayoutGroup()`) dan validasi silang
2 arah terhadap 3 field ongkos manual DC (cetak/plong/lem) -- tampil sebagai
chip "Urutan Proses (ProDev)" + warning kalau step ADA di routing tapi
field masih 0, atau field TERISI tapi step TIDAK ada di routing (indikasi
data basi). Pola "engine suggests (warning), officer confirms (isi
manual)" -- TIDAK ada angka Rupiah ditebak/di-auto-fill. Diuji
`tests/Feature/CostingRoutingWarningTest.php` (7 assertion).

---

## 2026-09-17 -- Rantai Inquiry->ProDev->Master Card: engine BOM/BOP/Ink/Finishing (BACKLOG §18)

Instruksi user: "kekurangan di atas itu tulis di backlog dan HARUS segera
dikerjakan agar bisa menjadi rangkaian data ke ProDev." Acuan hasil = Master
Card ALSAMSI EXCLUSIVE TOP (DS003047): routing 8 langkah, Warna Pertama CS
(K-C-M-Y), 4 UP, bahan 84,5 x 72 LG, pisau 83,4 x 70,1.

Keputusan user (jangan ditanya ulang): rancangan engine DISETUJUI dengan
syarat tidak ada tabel dobel (pakai ulang `ref_finishing_type`,
`prod_process`, `prod_part_finishing`, `prod_part_ink` yang sudah ada).
Urutan finishing default Laminasi -> UV Spot -> Hot Print -> Plong, tapi
bisa diubah admin lewat setting -- Emboss TIDAK selalu bareng Plong. "CS" =
warna proses full color (C, M, Y, K). Sampel UAT AL SAMSI Top diselaraskan
ke Master Card. Flag dummy/legacy: disembunyikan bukan dihapus, ada reset.

**11 dari 12 gap ditutup hari ini:**
- **Ukuran & kode pisau** dari hasil ProDev (`prod_layout_group`/`iqp_layout.knife_*`).
- **Finishing Inquiry terstruktur**: pilih dari `ref_finishing_type` + atribut
  warna foil/doff-glossy (`iqp_part_finishing`).
- **Engine Ink**: `cfg_color` + analisa warna artwork (% & cm² per tinta,
  warna dominan) unit K-C-M-Y -- analisa di browser; file PDF/AI belum
  (server tanpa Imagick/Ghostscript saat itu, lihat entri 2026-09-18).
- **Engine BOP**: routing standar offset + sisipan dari finishing (aturan
  diatur admin) -- `prod_bop_rule` + layar BOP Rule Setting.
- **Engine BOM**: bahan utama + bahan finishing (plastik laminasi lebar
  roll >= lebar lembar, foil) + tinta.
- **Isi per pack** (Pack Wrapping /400 pcs) diinput di Inquiry (`iqp_part.pack_qty`).
- **Flag `data_origin`** (LIVE/DUMMY/LEGACY) + setting admin hide/show +
  reset dummy -- 210 tabel, Settings > Dummy & Legacy Data (query
  `DB::table`/VIEW mentah belum ikut tersaring, dicatat sbg gap kecil).
- **BOP/BOM/Ink bisa di-override** officer di ProDev dengan dropdown, tidak
  ditimpa engine.
- **BOM**: qty kebutuhan + stok tersedia gudang + tombol Buat PR per baris
  yang kurang, tinta & lem dgn estimasi pemakaian -- item kategori
  Others/Lamination belum punya akun COA sehingga PR-nya ditolak (gap
  terpisah), takaran = estimasi awal.
- **BOP**: mesin, target default, waste & toleransi (qty + %) per langkah
  -- persen default = estimasi awal.
- **BOP tombol Subkon** per langkah non in-house -> PR subkon -> Surat
  Jalan subkon yang menunjuk CO (track costing) -- layar entry DO belum
  khusus SUBKON (gap terpisah, lihat 2026-09-18).

**Alur data & sistem yang dikunci:**
```
Inquiry Product (finishing, bahan, warna, isi/pack, artwork, qty)
   -> ProDev: engine BOP / BOM / Ink  (officer ProDev boleh override)
        -> SAVE  : tulis 2 arah (prod_part_* master + iqp_part_* Inquiry)
        -> CONFIRM: ke Product Costing
   -> BOM kurang stok -> Purchase Requisition bahan (MRP)
   -> BOP langkah non in-house -> PR subkon -> Surat Jalan subkon (wajib CO asal)
   -> Customer PO -> CO -> Work Order: BOM & BOP jadi bahan & operasi WO
        -> officer PPIC yang memastikan FINAL (bukan otomatis terkunci)
```
Surat jalan subkon = surat jalan tetap yang terikat CO (customer PO -> CO);
CO wajib tertulis supaya biaya subkon terlacak ke costing order itu.

**Belum ditutup (lihat backlog02 §18.12)**: BOM & BOP mengalir ke Work
Order -- `WoGenerationService` saat itu belum membaca BOM/BOP baru
(ditutup 2026-09-18, lihat entri di bawah).

---

## 2026-09-18 -- Lanjutan engine ProDev & Subkon (BACKLOG §19)

- **Jalur BOM & BOP -> Work Order**: setelah Inquiry berharga -> CO
  CONFIRMED & APPROVED, officer PPIC memastikan final. `WoGenerationService`
  sekarang mensyaratkan CO CONFIRMED + costing dipublish sebelum WO lahir --
  menutup §18.12 dari hari sebelumnya.
- **Engine takaran pemakaian bahan otomatis** dari data pemakaian nyata 1
  periode (bulan), officer tetap bisa override manual: `UsageRateEngine` +
  command `prodev:usage-rates`, usulan + saklar AUTO per aturan.
- **Imagick + Ghostscript terpasang** (Imagick 3.8.1 + Ghostscript 10.08)
  supaya artwork PDF/AI bisa dianalisa & dipratinjau di server -- menutup
  gap Engine Ink dari hari sebelumnya (analisa PDF/AI).

**Belum ditutup (lihat backlog02 §19.1)**: Layar Surat Jalan Subkon sendiri
(entry + cetak) -- saat ini surat jalan SUBKON dibuat otomatis dari ProDev
dan tampil di daftar DO, tapi layar entry & cetaknya masih memakai bentuk
surat jalan customer.

---

## 2026-09-22 -- Master Mesin: perbaiki 3 harapan test yang basi (BACKLOG §27.6)

`MasterMachineMigratedDataTest::test_grid_renders_asset_machines_with_new_codes`
sempat merah -- bukan layarnya rusak, tapi tiga harapannya tertinggal di
belakang perubahan yang memang disengaja sesudah test itu ditulis:
- `'FUJI'` -- 29 baris legacy awalnya cuma dinonaktifkan, lalu DIHAPUS
  migration `2026_09_22_000008` atas instruksi user ("data legacy salah
  HAPUS bukan dinonaktifkan").
- `'27/09/2015'` -- grid cuma menampilkan `year_acquired` dalam kurung di
  baris Merk; `acquired_date` lengkap hanya ada di form.
- `'Total Mesin'` -- kotak statistik berganti label jadi "Total Unit Mesin".

Ketiganya diperbaiki di test (bukan di layar). Terukur sekalian: dari 45
mesin (semua aktif), 44 tidak punya `max_sheet_length_mm` DAN tidak punya
`max_speed_per_hour` -- lihat backlog02 §27.1.

---

## 2026-09-22 -- Master Mesin: identitas 4 die cutter dikonfirmasi + placeholder ML750 (BACKLOG §27.2)

User mengonfirmasi (foto mesin "Pond Manual ML 750" sbg acuan bentuk):
dari 4 die cutter di master (`DIE-01` Ruian, `DIE-02` Shanghai, `DIE-03`
Daxiang, `DIE-04` Daxiang Auto), **hanya DIE-04 yang otomatis** -- DIE-01/
02/03 SEMUA plong manual sekelas mesin di foto. Ini menutup pertanyaan
"PLONG 2 di mockup Master Card itu mesin yang mana" -- jawabannya bukan
1 mesin spesifik, tapi kategori (manual), dipenuhi ketiga unit itu.

User lalu mengizinkan ("boleh dan sementara pakai ML 750, nanti Sabtu aku
fotokan kenyataannya") mengisi ketiganya dengan spek generik ML750 sbg
PLACEHOLDER sampai nameplate asli difoto. Diriset (bukan ditebak) --
2 sumber independen cocok persis:
- https://www.smart-equipments.com/postpress/die-cutting-machine/die-cutting-machine-ml750/
- WebSearch ringkasan multi-listing (made-in-china.com, chinaguangya.com, dst.)

Spek yang diisi (migration `2026_09_22_000015`): platen 750x520mm ->
`max_sheet_length_mm`/`max_sheet_width_mm`, speed 25±2 strokes/min = 1500
lembar/jam (nominal pabrikan, BUKAN hasil ukur lapangan) -> `max_speed_per_hour`,
motor 2,2 kW -> `power_kw`. Kolom `notes` tiap baris ditandai jelas
"PLACEHOLDER ... BELUM diverifikasi ke nameplate mesin sungguhan" supaya
tidak keliru dianggap final. `min_sheet_*`, `operator_min`/`helper_min`,
`serial_no` SENGAJA dibiarkan NULL -- tidak ada sumber utk itu, tidak
ditebak. DIE-04 (otomatis) tidak disentuh sama sekali -- beda kategori,
user belum kasih data.

**Sisa terbuka**: 42 dari 45 mesin lain masih 0% berspek (lihat backlog02
§27.1). Placeholder ML750 ini WAJIB diganti data real begitu foto
nameplate Sabtu masuk -- jangan biarkan tertinggal jadi "spek permanen".

---

## 2026-09-23 -- UI-10 Filament form halaman penuh (induk Base), UI-13, UI-11 dibatalkan (backlog01)

- **UI-10**: form Create/Edit panel `/admin` dipindah dari modal (`ManageRecords`, menelan klik sidebar) ke halaman penuh. Induk baru `app/Filament/Base/{TmListRecords,TmCreateRecord,TmEditRecord}` (label Simpan/Batal, tanpa create-another); panel: `unsavedChangesAlerts()` + redirect ke grid. 6 halaman HRM (Posisi, Pegawai) kini turunan 3 baris. Test: `FilamentHrmPagesTest` (18 assertion). Standar: `admindashboardstandard.md`. Keputusan auto-save draft jadi tidak diperlukan.
- **UI-13**: `sys_user_menu.UM_SETUP` dinonaktifkan (migration `2026_09_23_000010`) -- menunjuk layar khusus admin padahal hanya tampil utk non-admin; semua layar Setup ber-middleware admin.
- **UI-11** (modul lain ke Filament): dibatalkan atas instruksi user ("kalau tidak jelas modul mana, hapus saja").

---
## 2026-09-23 -- UI/UX berbasis komponen OOP, menu profil drawer, angka rata kanan (backlog01)

Dikerjakan agen Jo. Semua sudah di-commit & push; data real (Master Mesin,
Proses, matriks Proses x Mesin, Pegawai) diverifikasi TIDAK berubah (checksum
identik sebelum/sesudah).

- **backlog01 §00 "komponen object oriented & bisa diturunkan" -- SELESAI
  (fondasi).** Keputusan bentuk (§00.1): opsi (a) Blade class-based component.
  Token warna di `@theme` + 16 primitive `App\View\Components\Ui\*` turunan
  `UiComponent` (Button, IconButton, Badge, BadgeButton, Card, Tabs, Table,
  Modal, Progress, Metric, ActionBar, PanelHeader, Window, Drawer) + palet
  `Tone`. Komposit grid (action-icon 105 pemakaian/57 layar, toolbar,
  pagination, modal Kolom/Sort) dibangun ulang di atasnya. Standar:
  `docs/componentstandard.md`; pagar `UiRatchetTest`. Sisa migrasi layar =
  backlog01 UI-01.
- **Pilot Master Mesin & Master Proses** dimigrasi (kulit saja); bukti esensi:
  0 `wire:*` hilang, teks/field/disabled sama.
- **DevLib UI UX** jadi acuan hidup: tab "Dasar" membaca katalog dari class
  induk; tab Grid mengikuti perilaku Master Pegawai (kotak statistik = filter).
- **Menu profil**: item mati untuk non-admin ("Aktivitas saya" -> layar admin)
  diperbaiki; lalu atas keputusan user **SEMUA item jadi drawer kanan**
  (`x-ui.drawer`), termasuk drawer baru **Configuration** (Themes/Language/
  Grid-segera) & **Aktivitas saya** (riwayat login + dokumen terakhir). F1
  membuka drawer Bantuan (tidak bisa diblokir popup blocker). Bug lama ikut
  diperbaiki: drawer dulu `<aside>` sehingga kena CSS sidebar (lebar 0 di HP).
- **Angka rata kanan, teks rata kiri**: aturan tabel global dipindah ke
  `@layer base` (dulu mematikan text-right/px-* di semua sel), class `num`
  dipasang per kolom di 7 nota cetak + payroll + Master Pegawai/Mesin/Proses.
- **backlog01 UI-09 -- nama perusahaan & kop PPN (SELESAI).** Keputusan user:
  konstanta `COMPANY_NAME` = "Telasih Makmur" (dipakai di seluruh aplikasi &
  semua cetakan) dan `COMPANY_LEGAL_NAME` = "CV. Langgeng Jaya Makmur".
  Dokumen ber-PPN (DO, Surat Jalan, Nota Inquiry) punya pilihan di preview
  cetak **Non PPN (default) / PPN**; PPN = nama terdaftar + NPWP. Tidak
  disimpan. `co_order.is_ppn` baru (default dari Inquiry/profil customer,
  field "Pajak" di CO Entry, terkunci setelah DRAFT) -> `do_delivery.is_ppn`
  ikut CO; 1 DO menolak campuran PPN/Non PPN. Teks cadangan salah "PT Telasih
  Makmur" di 5 nota cetak ikut dihapus. NPWP sementara dummy (UI-20); tiga
  layar cetak SPB/Invoice/Pembayaran belum ada (UI-19). Test:
  `CompanyNamePpnPrintTest` (5).
- **Rekening bank pegawai TUNTAS (permintaan lama user yang dulu cuma separuh):**
  bank = **Master Supplier kategori BANK** (keputusan user: bank adalah vendor;
  6 bank yang sudah dipakai dibuat sbg supplier BANK-*, 58/58 rekening lama
  ditautkan lewat `emp_bank_account.bank_supplier_id`). Pegawai hanya simpan
  No. Rekening + Atas Nama. Input ada di: Master Employee tab **Rekening Bank**
  (tombol simpan sendiri), Payroll > Master Karyawan & Rekening Bank (lookup,
  opsi Cash), Self-Service **Ajukan Ganti** (bank+rek+atas nama) -> **Approve
  HR langsung menerapkan** (dulu cuma menutup request). Satu jalur:
  `App\Services\Hrm\EmployeeBankService`. Layar baru **Master Kategori
  Supplier** (sup_category dulu tak punya input) + kategori EKSPEDISI (vendor
  truk angkut). backlog06 §21 selesai. Test: `EmployeeBankSupplierTest` (5).
- **Master Employee mode longgar (keputusan user):** selama belum go-live HANYA
  Nama wajib. Saklar `HRM_EMPLOYEE_MANDATORY_STRICT` (Settings > Constants,
  kategori HRM baru) = 0; `emp_employee.position_id` sementara NULLABLE.
  **GO-LIVE:** set saklar = 1 DAN jalankan down() migration
  `2026_09_23_000007` (position_id NOT NULL) setelah semua pegawai berjabatan.
- **Dibatalkan user (jangan dihidupkan lagi):** backlog01 UI-06 (navigasi
  keyboard grid) dan UI-08 (`x-form.cell-input` untuk input di sel tabel);
  UI-20 (NPWP) diganti fasilitas input di Settings > Identifikasi. UI-07
  dipindah: Peran Part & Material Role BOP -> backlog02 §28 (bagian Inquiry
  Product), Master Bank -> backlog06 §21.
- **backlog01 dirapikan** jadi papan monitor bernomor UI-01..UI-18 dengan
  status 🔴/🟡/🔵/⚪. Catatan basi dikoreksi: komponen Back SUDAH ada
  (`x-back-button`, 23 layar). Mitigasi Filament klik-luar-modal
  (2026-09-11) dan standarisasi 5 tombol menyimpang sudah selesai sebelumnya.

---

## 2026-09-22 -- Git disatukan jadi 1 branch `main`; hasil ditelusuri dari bug nyata

Terpicu bug nyata: `ProductionWoDetailRebuildTest::test_plan_delivery_modal_generates_wo_and_redirects`
hijau di satu sesi (worktree), merah di komputer user. Ditelusuri: worktree
sesi itu lahir dari `main`, dan `main` ternyata **357 commit tertinggal**
dari jalur kerja sesungguhnya (`claude/bentuk-customer-order-clarity-jnehjg`)
-- isinya cuma 1 merge commit lama 2026-08-29. Guard "costing harus
dipublish" di `WoGenerationService` (ditambah 2026-09-18) tidak ada di
salinan basi itu, jadi test lolos di tempat yang salah.

**Diselesaikan hari itu juga:**
- Semua pekerjaan disatukan ke `main` (commit puncak `48b5971` saat itu),
  `main` dijadikan default branch GitHub. Branch `claude/bentuk-customer-
  order-clarity-jnehjg` dihapus (0 commit unik tersisa).
- Reorganisasi docs yang sudah berjalan tapi belum ter-commit (~280 file
  dari `docs/*.md` dipindah ke `docs/md_for_systems/*`) di-review lalu
  di-commit; file index/global (`index_master.md`, `attachments_index.md`,
  `globalrules.yaml`, `globalstarthere.md`, `uistandardindex.md`)
  dikembalikan ke `docs/` root atas permintaan user (index/skema harus
  langsung terlihat, tidak terkubur di subfolder).
- `docs/BACKLOG.md` (2.016 baris) dipecah jadi 10 file domain
  `backlog01_*.md`..`backlog10_*.md` + `ringkasanstatus.md`/
  `kebijakanbesar.md`/`catatanauditmenyeluruh.md`. Aturan baru dikunci:
  item SELESAI tidak boleh menumpuk di backlog, wajib pindah ke sini
  (lihat memory `feedback_backlog_hygiene_rule`).
- Branch `claude/exciting-hypatia-1d0d5a` (Engine MRP/MPS/Kanban) ditemukan
  punya migration yang SUDAH terlanjur jalan di DB (`prd_mrp_run`/
  `prd_mrp_requirement` ada) tapi kodenya tidak pernah masuk jalur utama.
  **TIDAK digabung** -- user menegaskan MRP/MPS sengaja ditelantarkan
  (hasilnya tidak disukai, akan dirancang ulang niru UI/UX ProDev/Inquiry).
  Kode diarsipkan ke git tag `arsip/mrp-mps-2026-08-30` (tag, bukan branch)
  sebelum branch-nya dihapus.
- 2 dokumen lama (`masteremployee_tier1.md`, `TIER_1_ATTENDANCE.md`) yang
  masih bilang Recruitment/Payroll/Attendance "ditunda total" padahal
  `DEC-SCOPE-OPEN` (2026-08-26) sudah membuka ketiganya -- diperbaiki DI
  SUMBERNYA (bukan cuma dicatat sbg disclosure), atas teguran user.

Hasil akhir: satu folder kerja, satu branch `main`, nol worktree.

---

## 2026-09-22 -- Verifikasi Master Mesin & Master Proses "enterprise" (BACKLOG §27.4)

Sesi lebih awal hari yang sama (commit `56a4b35`, `db695b0`, `5a031b6`,
`da138d4`, `b2fc0ff`) membangun lapis kedua Master Mesin & Master Proses
jauh melampaui field kosong di sampel HTML user. Diverifikasi ulang sesi
ini (bukan cuma baca pesan commit) -- tabel & kolom hidup di DB, 30/30
test lulus (`MachineProcessCapabilityTest`, `MasterMachineMigratedDataTest`,
`ProductionMasterProcessTest`):

- **Layar Master Mesin** jadi 6 tab (identitas / spesifikasi teknis /
  kapasitas & performa / biaya & aset / perawatan & OEE / proses), **Master
  Proses** 4 tab (info utama / parameter & kapasitas / ongkos & tooling /
  mesin pelaksana). Tab kapabilitas Proses x Mesin ada di kedua layar,
  CRUD dishare lewat trait.
- Field baru: `plate_size`, `drying_system`, `mtbf_hours`, `helper_min`
  (dipisah dari `operator_min` -- tarif upah beda) di mesin; `washup_minutes`
  (dipisah dari setup -- cuci SESUDAH job, bukan sebelum), `work_center_id`,
  `qc_blocking` di proses.
- **4 tabel baru**: `prod_process_machine` (matriks kapabilitas, 68 baris
  live), `prod_process_precedence` (aturan urutan wajib/terlarang, 12
  baris), `prod_routing_template`+`prod_routing_step` (urutan operasi
  standar per kategori produk, kerangka siap/0 baris), `prod_machine_
  changeover` (setup berdasar transisi, bukan cuma mesin, 0 baris),
  `prod_machine_downtime` (log stop, otomatis terklasifikasi terencana/
  tak-terencana dari jenis stop -- bukan dipilih operator, supaya
  Availability tidak gampang dipoles).
- **OEE dihitung nyata** (bukan target manual): Availability dari kalender
  mesin + log downtime, Performance dari output WO vs kapasitas teoretis,
  Quality dari hasil bagus vs total. Kalau datanya belum ada, faktor itu
  NULL (bukan 0%) -- "mesin mati" dan "belum bisa diukur" dibedakan tegas.
  Status saat ini: `prd_wo_operation_output` masih 0 baris, jadi Performance
  & Quality memang belum bisa dihitung dari data nyata.

**Menutup BACKLOG §27.4** ("UI/UX Master Mesin & Proses diluruskan") --
sudah dikerjakan berdasar sampel HTML user, bukan lagi menunggu rincian.

---

## 2026-09-16 (pagi) — Komponen `<x-form.badge>` dipasang di 6/6 layar entry transaksi

Sebelumnya masing-masing layar (`pur/pr/entry.blade.php`, `pur/po/entry.blade.php`,
`pur/pq/entry.blade.php`, `pur/prt/entry.blade.php`, `co/entry.blade.php`,
`quotation/entry.blade.php`) menulis ulang `span`+`match()`/ternary warna badge
status sendiri-sendiri -- padahal cuma 4 tone yang sama (abu DRAFT/kuning
SUBMITTED/hijau approved-selesai/merah reject-cancel). Disatukan ke komponen
`<x-form.badge>`. Diverifikasi `Livewire::test()` 4/6 render OK (PR/PO/CO/
Quotation, ada data live); `pq_quotation`/`prt_return` kosong 0 baris (bukan
bug dari perubahan ini) jadi 2 sisanya cuma lolos `php -l` + kesamaan pola
persis dgn yang sudah terverifikasi.

Sisa dari item ini yang **BELUM** dikerjakan (masih di backlog): header form
entry (ikon+judul+doc_no+tombol cetak+Back) belum diekstrak jadi komponen.

---

## 2026-09-10 (malam) — Footer pagination diseragamkan ke 8 layar terakhir (8/8)

Komponen `components/grid/pagination-footer.blade.php` sudah ada & dipakai
konsisten di 13+ layar sebelumnya; 8 layar terakhir yang masih pakai
`{{ $this->rows->links() }}` polos diseragamkan: `hrm/position.blade.php`,
`recruitment/assessment-result.blade.php`,
`recruitment/candidate-review/list.blade.php`,
`recruitment/offer/list.blade.php`, `recruitment/requisition/list.blade.php`,
`recruitment/talent-pool.blade.php`, `recruitment/vacancy.blade.php`,
`setup/support-tickets.blade.php` (terakhir, tadinya sengaja dilewati karena
dikira tabrakan dgn `button-label-standardization-rollout` -- task itu
ternyata tidak pernah benar-benar jalan, jadi dituntaskan sekalian saat file
itu disentuh utk perbaikan label tombol). Pola sama semua: tambah
`use App\Livewire\Concerns\HasGridPagination;` (import + trait),
`->paginate(20)` -> `->paginate($this->perPage)`, ganti markup `links()` jadi
`@include('components.grid.pagination-footer', ...)`. Diverifikasi via
`Livewire::test()` (8/8 render OK, footer baru terkonfirmasi muncul). Tidak
ada class Tailwind baru -> tidak perlu `npm run build`.

---

## 2026-09-22 — Grid: kotak statistik jadi filter + ikon Aksi Font Awesome (BACKLOG §22.1, §22.2)

Lanjutan tugas terjadwal `grid-statbox-jadi-filter` (jadwal 22:30 2026-09-21, tidak
pernah jalan karena aplikasi tertutup — dijalankan ulang 2026-09-22).

- **§22.1 — mekanisme di pusat**: trait `App\Livewire\Concerns\HasGridStatFilter`
  + komponen `x-grid.stat-filter-row`. Layar cukup menulis `statFilterDefs()`
  (label/value/tone/subtitle + klausa `filter`). Klik kotak = grid tersaring,
  klik lagi = lepas, kotak aktif ber-ring + centang, ada chip "Lepas filter",
  paginasi balik ke halaman 1, dan **angka kotak tetap menghitung keseluruhan**
  (grid berbasis Collection dipisah ke computed `visibleXxx()` supaya angkanya
  tidak ikut menyusut).
- **26 grid** sudah dipasang & diuji lewat `Livewire::test(...)->call('toggleStatFilter', ...)`
  dengan memeriksa jumlah baris benar-benar berubah (pilot Master Employee:
  487 → 146/341/102/44, persis angka kotaknya). Sisa ±42 grid didaftar di
  BACKLOG §22.1 — mekanismenya sudah ada, tinggal deklarasi per layar.
- Kotak yang SENGAJA tidak bisa diklik: kotak "Total" (sama dgn tanpa filter),
  "Divisi Terpakai"/"Departemen Terpakai" (hitung distinct, bukan kategori
  baris), dan "Total Lokasi / Bin" di Master Gudang (menghitung tabel `wh_loc`,
  bukan baris gudang).
- **§22.2 — ikon Aksi Pilihan C**: 90 pemanggilan di 55 file disapu ke Font
  Awesome berbingkai; `bordered` jadi default komponen; tombol aktif/nonaktif
  memakai `:active=` (ikon & warna ditentukan komponen).
- Aturan tetap ditulis: `griduistandard.md` §2b baru + §5/§10 diperbarui,
  `_manifest.yaml`, skill `tmerp-screen` & `tmerp-ui-mockup`.
- Verifikasi: 51 layar dirender lewat `Livewire::test` (0 gagal) + `npm run build`.

---

## 2026-09-22 — Lookup `production/rule-library` -> `applies_to_process` (BACKLOG §21.1)

Field `applies_to_process` yang tadinya `x-form.input` teks bebas diganti
`x-form.select` dari master `prod_process` (24 proses aktif). Simpan NAMA
proses (bukan id) karena kolomnya varchar dan lintas-DB tm2prd->tm2mst tanpa
FK. Tidak ada data lama yang perlu dipetakan (kolom masih kosong semua).

Sisa lookup di section 21.1 (`process-rate.part_code`, `bop-rule.material_pattern`,
`bop-rule.material_role`, `payroll-employee-bank.bankName`) masih terbuka --
lihat backlog01 untuk detail keputusan yang dibutuhkan dari user.

---

## 2026-09-22 — Inquiry Form/Revise: input teks bebas -> komponen standar (BACKLOG §21.2)

`cancel_reason` + `lost_reason` (textarea), `part_name`, `cfg_ink` (Catatan
Warna) di Form, dan 3 field readonly header di Revise dipindah ke komponen
`x-form.*` standar. Hub tidak ada yang diubah (2 input-nya kotak CARI, standar
grid sendiri, tanpa badge). Sengaja dibiarkan mentah + alasannya: autocomplete
Customer/Sales/Bahan (nested `.f` akan merusak CSS) dan input di dalam `<td>`
(finishing attribute, tooling remark, external cost description).

Bonus temuan & perbaikan sekalian: `x-form.input`/`x-form.textarea` menentukan
readonly/disabled pakai `$attributes->has()` -- padahal pemanggil yang menulis
`:disabled="$expr"` SELALU mengirim key `disabled` walau isinya `false`, jadi
badge huruf mati diam-diam di 97 titik pemanggilan lintas 23 file (co/entry,
hrm, dst). Sekarang pakai `->get()` (nilai). Pola bug yang sama pernah kena di
`date-picker` (lihat [[feedback_datepicker_disabled_attribute_bug]]).

**Catatan zona beku (2026-09-22 sore):** pekerjaan ini selesai SEBELUM user
mengunci Inquiry Product sebagai zona beku (CLAUDE.md §6B) di hari yang sama --
bukan pelanggaran, dicatat di sini untuk kejelasan urutan waktu.

---

## 2026-09-22 — Rollout standar input teks (BACKLOG §21.1–§21.3)

Lanjutan tugas terjadwal `rollout-input-teks-malam` (jadwal 21:00 2026-09-21, sesi
terjadwalnya berhenti sebelum sempat commit — dijalankan ulang 2026-09-22).

- **Inquiry Form/Revise** (§21.2): `cancel_reason` & `lost_reason` → `x-form.textarea`,
  `part_name` & `cfg_ink` → `x-form.input`, 3 field readonly header Revise →
  `x-form.input :legacy`. Semua sekarang membawa badge huruf aa/Aa/AA. Autocomplete
  (Customer/Sales/Bahan) & input di dalam `<td>` SENGAJA tetap mentah — alasan di
  BACKLOG §21.2. Inquiry Hub tidak berubah (isinya kotak cari).
- **BUG komponen (temuan sampingan, dampaknya paling besar)**: `x-form.input` &
  `x-form.textarea` memakai `$attributes->has('disabled')` untuk mematikan badge —
  padahal `:disabled="$expr"` selalu mengirim key-nya walau `false`. Akibatnya badge
  huruf MATI diam-diam di **97 titik pemanggilan / 23 file**. Diganti `->get()`.
  Pola bug yang sama pernah kena `date-picker` (2026-09-05).
- **Lookup (§21.1)**: `production/rule-library → applies_to_process` jadi
  `x-form.select` dari master `prod_process`. 3 field lain (`part_code`,
  `material_pattern`, `material_role`) DILEWATI dengan temuan tertulis — sumbernya
  ambigu/bukan kandidat lookup, butuh keputusan user (detail di BACKLOG §21.1).
- **Warna token (§21.3)**: modul **Quotation** dibereskan (56 hex netral → token
  `--color-field-*`/`bg-accent-soft`), warna semantik & nota cetak tidak diubah.
- Verifikasi: 6 layar dirender lewat `Livewire::test` (inquiry.form/hub/revise,
  production.rule-library, quotation.list/entry) + `npm run build`.

---

## 2026-09-21 (malam) — Master Mesin: daftar aset nyata + kode mesin English

User menyerahkan daftar 46 mesin nyata (jenis, merk/tipe, tanggal datang) dan minta
kode mesin yang jelas berdasarkan JENIS mesin dalam bahasa Inggris. Diverifikasi dulu
lewat JPEG (`docs/mockups/master_mesin_update_2026.jpg`) sebelum ditulis ke DB.

- **Kode mesin** = prefix jenis (English) + nomor urut TANGGAL DATANG (tertua = 01):
  `OFF` Offset Printing, `DIG` Digital Printing, `CTP` CTP Plate Setter, `CUT` Paper
  Cutting, `DIE` Die Cutting, `PLT` Cutting Plotter, `FGL` Folder Gluer, `LAM`
  Laminating, `UVC` UV Coating, `WBC` Water-Based Coating, `WAX` Waxing, `AGL` Gluing,
  `PRS` Pressing, `SHR` Shrink Wrapping, `STR` Stripping, `STI` Stitching, `SEA`
  Seaming, `CMP`/`FKL` Support Equipment.
- **Migration `2026_09_21_000007_update_prod_machine_asset_list`**: kolom baru
  `acquired_date` (DATE, nullable — 6 mesin cuma diketahui tahunnya), `machine_type`
  dilebarkan 20→30 dan isinya jadi 18 jenis English (dari 6 jenis Indonesia).
  46 mesin: 33 baris baru + **13 baris lama dipakai ulang id-nya** (brand cocok:
  Komori, Sakurai, Guoxin, Hanway, Sunthink, Versafire, Summa, IECHO BK4, 2 Folder
  Gluer, Laminating, UV, Lilin) supaya kaitan `prod_part_process`/`emp_work_center`
  lama tidak putus. 29 baris lama **dinonaktifkan, BUKAN dihapus** (semuanya dirujuk
  `emp_work_center`); kode lamanya dicatat di `notes`, kodenya diparkir ke `ZZOLD-<id>`
  karena beberapa kode baru (mis. `LAM-01`) masih dipegang baris lama yang bukan
  mesinnya.
- **Plong dipisah dari Potong** — dulu keduanya bertipe `Potong`, jadi picker mesin
  plong di ProDev ikut menarik mesin potong kertas (bug lama). Sekarang guillotine =
  `Paper Cutting`, plong = `Die Cutting`; `prodev.blade.php` filternya ikut diperbaiki
  (`'Cetak'`→`'Offset Printing'`, `'Potong'`→`'Die Cutting'`).
- **`master/machine.blade.php`**: dropdown 18 jenis, `x-form.date-picker` Tanggal
  Datang + kolom grid-nya; `year_acquired` diturunkan otomatis dari tanggal bila diisi.
- **`HrmMasterSeeder::seedMachines()` DIHAPUS** — dulu menulis 4 mesin dummy
  (CETAK-01/CETAK-02/COAT-01/LAM-01); kode `LAM-01` sekarang milik mesin nyata,
  `updateOrCreate`-nya akan menimpa data asli tiap `db:seed`.
- **Scheduling Board diamankan**: `machines()` kini memunculkan mesin nonaktif yang
  masih punya operasi terjadwal, supaya menonaktifkan mesin di Master tidak membuat
  WO terjadwal hilang diam-diam dari papan.
- Test: `MasterMachineMigratedDataTest` disesuaikan ke enum/kode baru,
  `ProductionWoGenerationTest` lookup `CETAK-01`→`OFF-02` (CETAK-01 sudah di-rename
  migration 2026-09-17, test-nya ketinggalan). Suite: 138 lulus / 5 gagal, kelimanya
  diverifikasi PRE-EXISTING & tidak terkait mesin (lihat catatan di bawah).
**Koreksi user 2026-09-22** (migration `2026_09_22_000001_fix_machine_asset_list_corrections`):
- **Laminator Huawei (LAM-02, id 22) sudah TERJUAL** -> baris mesinnya DIHAPUS (user
  eksplisit "dihapus tidak papa"). Tidak ada routing/rate/WO yang merujuknya; 1
  `emp_work_center` (WC-LAMINATING) + 48 baris `emp_shift_roster` di-NULL-kan
  `machine_id`-nya -- stasiunnya tetap hidup lewat `work_center_id`, cuma mesinnya
  hilang. Penomoran LAM lain TIDAK digeser (LAM-01/03/04, gap di 02 disengaja supaya
  kode yang sudah dicatat orang tidak berubah). Total mesin aktif: 46 -> 45.
- **Folder Gluer ikut nomor lantai produksi**: Daxiang = `FGL-03`, YDM = `FGL-04`
  (bukan FGL-01/02 hasil urut tanggal datang). FGL-01/02 memang tidak ada.
- **Kolom grid Master Mesin disamakan dengan mockup** (permintaan user): tambah
  **Tahun** + **Divisi** (badge OFFSET/DIGITAL). Kode tetap di kolom identitas
  avatar+nama+kode sesuai `griduistandard.md`, Status & Aksi tetap.

- Sisa yang sengaja TIDAK dikerjakan → `BACKLOG.md` §23 (129 roster + 12 routing + 11
  operasi WO masih menunjuk mesin nonaktif; pemetaan penggantinya butuh keputusan user,
  jangan ditebak) dan §24 (test yang meninggalkan sampah data).

---

## 2026-09-21 (sore) — Standar input teks: badge huruf aa/Aa/AA + warna field ikut tema

Instruksi user: pola "Input Teks" Component Library dijadikan ACUAN WAJIB utk semua
text input string (alasan: user sering input lewat HP/tablet, menekan Shift tiap kata
merepotkan), semua input sebisa mungkin lookup, dan semua warna ikut corak `sys_theme`.
**Membalik keputusan pagi harinya** yang sempat memutuskan case-toggle tidak di-rollout.
Tujuan eksplisit user: "mencegah kedepan jika terjadi perubahan mockup tetap semua text
input string mempergunakan component tersebut tanpa harus saya perintah lagi."

- `resources/js/case-input.js` (baru, Alpine `tmCaseInput`) — transform huruf di ELEMEN
  ASLI lalu membiarkan event `input` menggelembung, jadi `wire:model.live`, `@focus`,
  `@click.outside` milik pemanggil tetap menempel di elemen yang benar (beda dari
  `tmCaseToggleInput` lama yang memindah nilai ke hidden input).
- `x-form.input` (type=text) & `x-form.textarea` — badge `aa`→`Aa`→`AA` OTOMATIS, mati
  sendiri utk readonly/disabled/type non-teks; `:case-toggle=false` dipasang di 23 kotak
  CARI (nilainya tidak disimpan, badge cuma bikin ramai).
- Token warna baru di `app.css` `@theme`: `--color-field-border/-focus/-soft/-badge` +
  `--color-accent-soft`, semuanya `color-mix` dari `--color-accent`. Karena
  `theme-vars.blade.php` menimpa `--color-accent` di `:root`, 12 kombinasi `sys_theme`
  otomatis memberi corak sama ke semua field tanpa sebaris pun warna per-tema.
  80 hex abu-abu/biru literal di `components/form/*` (17 file) + dialek global `.f`
  di app.css diganti token. Gotcha yang mahal: nilai `color-mix` pada custom property
  disubstitusi di tempat DIDEKLARASIKAN (`:root`), bukan di tempat dipakai — override
  `--color-accent` di elemen biasa tidak berefek, sempat bikin uji pertama salah baca.
- **Aturan dikunci supaya tidak perlu diperintah ulang**: `CLAUDE.md` §3 (blok baru),
  `FORM_UI_STANDARD.md` §1b + koreksi baris tabel #4, `_manifest.yaml` (props + 2 gotcha,
  sekalian memperbaiki escape `\$` yang bikin file itu tak bisa diparse YAML),
  skill `tmerp-screen` & `tmerp-ui-mockup` (mockup wajib menggambar badge huruf & lookup).
- Verifikasi: 11 layar dirender via `Livewire::test` tanpa error; 6 varian komponen
  (text/readonly/email/legacy/opt-out/textarea) dicek markup-nya; bukti visual 3 corak
  tema dirender dari komponen sungguhan → `docs/mockups/bukti_komponen_input_tema.jpg`.
  `npm run build` dijalankan. Tampilan di browser belum dicek user.
- Sisa rollout (Inquiry legacy, sel tabel, hex literal per layar, lookup 5 field,
  dark mode layar transaksi): `BACKLOG.md` §21.

---

## 2026-09-21 — UI: input teks mentah -> komponen standar `x-form.input` (33 titik)

Pemicu: user cek sendiri, "ga semua mengikuti component UI UX standard".
Audit: 157 `<input>` teks mentah di 89 file vs 475 yang sudah pakai komponen.
Keputusan user (opsi 1 dari 2): standar = `x-form.input`, BUKAN
`x-form.case-toggle-input` yang dipajang di Component Library. Sisa yang belum
dikerjakan + alasannya: `BACKLOG.md` §21.

- **15 field form berlabel** (label+input+@error manual -> komponen):
  `production/{rule-library,scrap-type,process-rate,wo-entry,mts-forecast,scheduling-board}`,
  `inventory/stock-return`. Termasuk pola read-only "nilai terpilih + tombol Ganti"
  (dibungkus `<div class="flex-1">` supaya tetap melebar di dalam flex).
  Commit `8baa872`.
- **18 kotak cari di popup/pemilih** (bukan toolbar grid): `dispatch/do/list`,
  `inventory/print/{master-stock,slow-moving,stock-card}`,
  `inventory/{stock-in,stock-out,stock-adjustment}`, `master/produk`,
  `pur/{po,pq,prt}/index`, `pur/{pq,pr,prt}/entry`, `quotation/{entry,list}`,
  `security/index`. **Tercommit ikut `bc6100c`** (sesi paralel `git add` menyeluruh
  saat file sedang antre commit — isinya utuh & ter-push, cuma pesan commit-nya
  tidak menggambarkan isi; lihat memory `project_multisession_collision_20260831`).
- Efek samping disengaja: Master Produk & Stock Return dulu 13px -> sekarang
  ukuran standar `text-xs`.
- Verifikasi: 20 layar dirender via `Livewire::test` tanpa error; PQ Entry &
  PRT Entry tabelnya kosong -> dicek lewat `Blade::compileString` + `php -l`
  (lolos). `npm run build` dijalankan. Tampilan browser belum dicek user.

---

## 2026-09-21 — Accounting Process Engine (Finance & Accounting): 16 layar dari mockup blueprint + dummy data

Sumber: `docs/mockups/MODULE_accounting-process-engine-blueprint.html` +
`MODULE_accounting-mockup-blueprint.html` (user 2026-09-19). Detail lengkap:
`docs/financeaccounting_delta_process_engine.md`.

- Skema tm2acc (migration `2026_09_19_000001`): `fin_period`/`fin_period_log`/`fin_close_task`
  (mengeksekusi `financeaccounting_delta_period_close.md` yang tadinya masih spec),
  lifecycle voucher DRAFT->SUBMITTED->APPROVED->POSTED + jurnal pembalik, `fs_line`/`cf_line`
  di COA (laporan dari pemetaan, bukan hardcode), `fin_payment_alloc`, `fin_approval_rule`,
  `fin_bank_statement`/`fin_bank_recon`, `fin_asset*` (aset+amortisasi 1 engine),
  `fin_tax_register`, `tm2mst.fin_tax_scheme` (PPN 12% x DPP 11/12, jatuh tempo PMK 81/2024),
  19 akun COA baru (prabayar, kendaraan/peralatan/bangunan + akumulasi, aset tak berwujud,
  PPh 21/Badan, hutang bank, beban penyusutan/amortisasi/pajak).
- 9 service baru/diperluas di `app/Services/Acc`: FinPeriodGuard (dipanggil di SEMUA titik
  tulis voucher, CLOSED ireversibel, gate ke `tm2inv.inv_period`), JournalPostingService
  (approval + pemisahan tugas + auto-reversal), ArApService, AssetService (garis lurus &
  saldo menurun), BankReconService (import CSV + auto-match), TaxEngine, CloseService,
  LedgerService (TB/Neraca/Laba Rugi/Arus Kas tidak langsung), FinanceMetrics.
- 16 layar Livewire `pages::finance.*` + CSS `resources/css/ledger.css` & komponen
  `components/ledger/*` (port gaya mockup; sidebar/topbar mockup TIDAK ditiru, tetap shell
  `sys_app_menu`). Menu FINACC ditata jadi 5 grup berbahasa Inggris sesuai mockup.
- `php artisan fin:dummy` (+`--purge`): setahun pembukuan dummy lewat service asli —
  saldo awal, 36 faktur + penerimaan, 27 tagihan + pembayaran, HPP/payroll/overhead/angsuran,
  capex, run penyusutan & amortisasi, rekening koran 25 mutasi (22 auto-match), pajak
  Jan-Sep, periode Jan-Jul CLOSED. Verifikasi: TB nol selisih, Aset = Kewajiban+Ekuitas,
  arus kas menjelaskan seluruh perubahan kas, GP 35,8%, laba bersih Rp 1,03 M.
- `tests/Feature/AccountingEngineTest.php` 13 test / 47 assertion PASS.
- **Temuan audit sambil jalan**: 84 voucher jurnal PUR di GL menunjuk PV yang sudah
  dihapus (residu `PvStockIntegrationTest`, Rp 840 rb) -> ditandai `data_origin=DUMMY`
  + `data_origin_manual=1` (bukan dihapus); perbaikan cleanup test-nya di-antre terpisah.

---

## 2026-09-19 — Data karyawan dari Excel HR: merge, status aktif, pemetaan kolom, formasi shift, sample payroll

Sumber: `docs/HR/daftar karyawan - database.xlsx` (138 orang), 2 file gaji mingguan
`docs/HR/9. GAJI KARYAWAN ... WEEKLY 1659/1660.xlsb` (TIDAK di-commit, berisi gaji),
2 gambar formasi shift minggu ke-2 & ke-3 September.

- `hrm:sync-employees-excel`: merge 68 baris dobel (nama sama + nama pendek ke nama
  lengkap, contoh user ALDI/ADAM/ALIF; jejak di `tm2mst.emp_employee_merge`), Excel =
  aktif (137 termasuk id=1), sisanya nonaktif. PIN Excel = `device_pin` mesin finger.
- `hrm:import-hr-sample --step=attributes`: jabatan dari KEAHLIAN/TITLE file gaji +
  kotak org chart (`database/data/hr/position_mapping_2026_09.json`); jabatan keahlian
  (org_position 10-58) dipasang ke departemen & SPV-nya + jabatan baru UMUM; kolom baru
  `emp_employee.personal_file_complete`/`deposit_status`, BPJS TK 2P/3P/BATCH/NO + KET
  -> `emp_bpjs_ketenagakerjaan.enrollment_status/registration_batch/bpjs_category/enrollment_note`;
  tanggal & gender dari Excel (tgl lahir mustahil dikosongkan); mingguan = ada di file gaji (93/44).
  9 staf jabatannya belum bisa dipastikan (tetap "Belum Dipetakan").
- `--step=roster`: 567 baris `emp_shift_roster` 7-12 & 14-19 Sep (shift SH1/SH2/REG
  baru, WC baru DIECUT/FG 3/FG 4/BLANKING). Revisi user hari yang sama: formasi = data
  REAL (LIVE), semua jadwal dummy/uji lama dihapus (tabel kini hanya 2 formasi ini).
  Nama yang belum ada (ARIF x2, MUCHLAS, RENDA, ERNA, MUJI, DEA, SITI, TYAS) = karyawan
  BORONGAN produksi TM yang dipanggil saat kerja berlebih (jabatan BORONGAN, kontrak
  BORONGAN/PER_UNIT, skill Manual/FG 3/FG 4) -> kini 622 baris, 57 orang; >1 titik sehari sah.
  Layar Shift Roster kini membuka unit kerja yang sudah berisi jadwal (bukan selalu Cetak 1).
- `--step=payroll`: sample lama (VAL-001/002, shift VAL, run 22-28 Agu) dihapus; 95 rate
  gaji + presensi TA 2 minggu + 2 payroll run WEEKLY (DUMMY). Pokok+tunjangan+bonus engine
  = Excel utk 84/92 & 78/90 orang, uang roti sama persis; lembur engine ~52% Excel (BACKLOG §20).

## 2026-09-12 — Roster Shift Mingguan versi mockup (ganti total papan lama) + componentization HRM

Instruksi user berturut-turut: "mockup untuk jadwal roster HARUS BISA",
"termasuk semua detail dan behaviour nya", "include print out", "theme
mengikuti pilihan", "workcenter dan department dari master yang ada di table".
Acuan: `docs/mockups/mockup_roster_shift.html`.

**A. Fondasi database (3 migration, semua additive — 0 kolom lama dihapus/di-rename):**
- `emp_work_center` (BARU) — unit kerja lintas semua departemen: 42 baris 1:1 dari
  master `prod_machine` + 21 unit non-mesin per departemen (total 63). Ini yang
  menghilangkan pemisahan "Papan PPIC (punya mesin)" vs "Grid HR (tanpa mesin)".
- `emp_roster_schedule` + `emp_roster_schedule_department` (BARU) — konsep "Jadwal"
  dari mockup, di-seed 3 jadwal (Offset / Digital & Indostry / Support).
- `emp_employee_work_center_skill` (BARU) — sertifikasi pegawai per unit (182 baris,
  146 pegawai; seed = semua unit di departemennya sendiri, bukan spesialisasi karangan).
- `emp_shift` +`shift_code`/`color_hex`/`capacity_per_wc`, `department_id` jadi nullable
  (katalog shift global lintas departemen). `emp_shift_detail` +`break_minutes`
  (diturunkan dari `break_out`/`break_in` yang sudah ada). `emp_shift_roster`
  +`work_center_id`/`schedule_id`, `division_id` jadi nullable (departemen seperti
  Security/Admin memang `division_id` NULL — tidak mengarang divisi palsu).
  `emp_shift_roster_batch` +`schedule_id`.

**B. Layar `hrm/shift-roster.blade.php` ditulis ulang total** sesuai mockup:
2 view (Roster Mingguan / Master Shift), bar Jadwal (pilih/buat/edit/hapus/cetak),
filter Departemen→Unit Kerja dari master, nav minggu, 3 stat box, papan baris-per-shift
(ringkasan jam, bar kuota, strip 7 hari + `+1` lintas hari, chip drag&drop, chip kuning
utk yang belum bersertifikat), Pool Pegawai (cari, kelompok Jabatan/Skill/Golongan,
collapse grup, centang grup, seleksi banyak + drag satu blok), modal Ubah/Tambah Shift
(jam per hari + "Samakan Sen–Jum/Sen–Sab"), modal Rotasi antar minggu
(swap 2 shift / rotasi berputar / salin, cakupan semua unit atau 1 unit, opsi timpa,
pratinjau), toast, legenda.
**Model penempatan**: 1 pegawai = 1 shift untuk 1 MINGGU → ditulis 1 baris
`emp_shift_roster` per hari aktif shift, jadi `AttendanceCalculationService` tidak
perlu diubah sama sekali.
Lifecycle DRAFT→CONFIRMED **sengaja dipertahankan** (mockup tidak punya) karena roster
ini sumber jam kerja payroll/attendance.

**C. Cetak** (`hrm/shift-roster-print.blade.php`, route `hrm.shift-roster.print`):
mode `roster` (definisi jam shift + penempatan per unit kerja + tanda tangan) dan mode
`shift` (master definisi shift), pakai `components/print/*` sesuai printnotastandard.md.

**D. Tema**: token CSS lokal `--rs-*` diisi dari `ActiveTheme::resolve()` (ikut pilihan
tema user, terang/gelap + warna aksen), pola sama `production/prodev.blade.php`.

**Temuan nyata dari pengujian (bukan asumsi):**
1. **4 dari 8 shift di DB belum punya `emp_shift_detail`** (TDP1/TDP2/IND1/IND2) → 0 hari
   aktif. Versi pertama layar ini diam-diam membuat 0 baris (seolah berhasil). Ditambah
   guard penolakan + badge "jam kosong" di papan. Jam sebenarnya harus diisi HR.
2. **Bug `validate()`**: atribut tanpa rule tidak ikut di data tervalidasi, sehingga flag
   hari aktif (`days.*.on`) hilang dan simpan shift SELALU ditolak "minimal satu hari
   harus aktif". Diperbaiki dengan menambah rule `boolean` untuk `on`.
3. `production/scheduling-board.blade.php` disesuaikan: dropdown "operator manual"
   (query `machine_id IS NULL`) kini dibatasi unit non-mesin di divisi OFFSET saja —
   tanpa itu staf QC/Gudang/Admin yang sekarang bisa dijadwalkan akan bocor ke papan produksi.
4. Komentar mockup yang bilang fitur Tukar Shift ESS "NOT-IMPLEMENTED" ternyata USANG —
   fiturnya sudah ada di `self-service/index.blade.php` (`emp_shift_swap_request`).

**Diuji** via `Livewire::test` (9 skenario: guard jam-kosong, assign, invarian 1
shift/minggu, pindah antar shift, bulk dari seleksi, publish+lock, unlock+lepas,
rotasi salin antar minggu, buat+edit Master Shift, render 2 mode cetak) — semua lolos,
data uji dibersihkan dan diverifikasi ulang langsung ke DB (5 baris roster lama utuh,
0 sisa). `npm run build` dijalankan.

**Belum/keterbatasan:** verifikasi visual di browser belum dilakukan (login browser
otomatis dilarang aturan sesi — lihat `feedback_no_browser_login_verification`), jadi
pengecekan tampilan nyata perlu dilakukan user.

Bareng sesi ini juga: **11 layar HRM dibetulkan agar konsisten pakai komponen
`x-form.*`/`x-grid.*`** (audit menemukan `hrm/position`, `hrm/org-chart`,
`hrm/payroll-bank-transfer`, `hrm/payroll-cash-roster`, `hrm/payroll-batch-run`,
`hrm/discipline-report` masih raw markup; sisanya sebagian) — commit terpisah `4d3a5f3`.

---

## 2026-09-11 — Resolve akun 3-lapis prod_category (BACKLOG §12.3 Grup A)

Dari `docs/financeaccounting_delta_partner_category_coa_v2.md` §2 + §5 Grup A
("KEPUTUSAN TERKUNCI... BOLEH JALAN SEKARANG"). Permintaan user "kerjakan yang
bisa langsung" — ini item paling jelas pre-approved (dokumen desain sendiri
yang bilang boleh jalan), tapi ternyata belum pernah dieksekusi.

**Yang dikerjakan (Grup A langkah 1-4, TANPA langkah 5 UI):**
1. Migration `2026_09_11_000003_add_default_account_to_prod_category.php` —
   ALTER `tm2mst.prod_category` tambah `default_stock_account_id` +
   `default_cogs_account_id` (nullable, TANPA seed data — lihat alasan di
   bawah). Dijalankan (`php artisan migrate --path=...`), berhasil.
2. `ProdPart`/`ProdProduct` (`app/Models/Master/`) — tambah
   `resolveStockAccountId()`/`resolveCogsAccountId()`, fallback 3-lapis
   (part/product sendiri → product → category).
3. `StockPostingService::postUsageJournal()` (baris SFG) — ganti baca kolom
   `prod_part` langsung jadi panggil method resolve di atas. Komentar lama
   yang bilang "TANPA fallback kategori" diperbarui.

**Kenapa langkah 5 (§2.5 UI "Master Kategori Produk") TIDAK dikerjakan**: dicek
dulu — ternyata `prod_category` belum pernah punya screen CRUD sama sekali
(cuma dipakai sbg dropdown read-only di `master/produk.blade.php`). Bikin
screen baru dari nol (routing, sidebar, permission Sisi A) itu scope UI
tersendiri yang lebih besar dari "eksekusi mekanis", jadi ditinggal sbg item
lanjutan terpisah — kolom akun tetap NULL sampai ada cara isi, tapi TIDAK ada
regresi (resolve() null-safe, `StockPostingService` tetap skip+warning persis
seperti sebelum perubahan ini kalau semua lapis kosong).

**Kenapa §2.3 (seed nilai default) TIDAK dikerjakan**: draf dokumen minta
`UPDATE prod_category SET default_cogs_account_id = (account_code LIKE '51%'
LIMIT 1)` — dicek ke `tm2acc.fin_account` aktual, account `51x` ternyata
per JENIS BIAYA (511=Bahan Baku Kertas, 512=Tinta, 514=Tenaga Kerja, dst),
bukan 1 akun blanket yang benar utk semua kategori produk. Mengisi asal akan
salah secara akuntansi, jadi dibiarkan kosong (pola sama seperti gap 12
kategori `mat_category` — keputusan akun, bukan sesuatu yang boleh ditebak
kode).

**Temuan penting soal Grup B (dicatat, TIDAK dieksekusi)**: verifikasi §4
dokumen (`SELECT ... WHERE account_code IN ('121','211','113500')`)
menemukan akun `211`/`212` BUKAN "Hutang Dagang Lokal/Impor" seperti
diasumsikan draf — kenyataannya `211`=Kertas & Board, `212`=Tinta & Chemical
(segmentasi per kategori supplier, bukan trade_type). Model "4 kombinasi
CUSTOMER/SUPPLIER × local/import" di §3 dokumen jadi tidak match struktur COA
riil utk sisi Supplier — kemungkinan sudah digantikan mekanisme
`mat_category.default_coa_account_id` yang sudah ada. Dicatat di BACKLOG §12.3
sbg item yang butuh rekonsiliasi user, dicabut dari "boleh jalan sekarang".

Diverifikasi: `Schema::getColumnListing()` konfirmasi 2 kolom baru ada;
test in-memory (part override menang atas category, product fallback jalan
kalau part kosong) — resolve chain benar; `php artisan test
tests/Feature/PvStockIntegrationTest.php` 2/2 pass (test yang paling dekat
mengexercise `StockPostingService`). Tidak ada test khusus
`postUsageJournal()` SFG branch yang sudah ada sebelumnya (dicek `tests/` —
0 file match), jadi verifikasi murni via tinker + test integrasi terdekat.

---

## 2026-09-11 — Mitigasi cepat modal Filament tertutup tanpa sadar (BACKLOG §9.1)

Bagian "mitigasi cepat" dari §9.1 (klik sidebar tidak merespons saat modal
Edit/Create terbuka) — item auto-save/draft jangka panjang di §9.1 TETAP
menunggu diskusi user, TIDAK disentuh.

Draf backlog menyebut `->closedByClickingAway(false)` sbg method fluent di
`EditAction`/`CreateAction`. Sempat dicoba apa adanya di 4 tempat
(`EmpEmployeeResource`, `OrgPositionResource`, `ManageEmpEmployees`,
`ManageOrgPositions`) — dicek ke `vendor/filament/actions`, method itu
**tidak ada** di API Action versi ini (akan fatal error kalau dibiarkan),
langsung di-revert sebelum sempat dites browser. Yang benar ditemukan di
`vendor/filament/support/src/View/Components/ModalComponent.php`: static
property/method global (`$isClosedByClickingAway`), bukan per-Action.

**Perbaikan final**: tambah method `boot()` di `AdminPanelProvider.php`
(sebelumnya cuma `panel()`) berisi
`ModalComponent::closedByClickingAway(false)`. Efeknya global ke semua modal
panel `/admin` (2 Resource yg ada sekarang + Resource baru ke depan), bukan
perlu diulang per-Resource. Root cause asli (backdrop z-index menutupi
sidebar) TIDAK berubah — ini cuma mencegah efek samping "form tertutup diam-
diam" saat klik pertama kena backdrop.

Diverifikasi: `php artisan route:list --path=admin` (8 route panel /admin
resolve normal, provider boot tanpa fatal error) + `php artisan tinker`
cek `ModalComponent::$isClosedByClickingAway` bernilai `false` setelah
bootstrap. Tidak ada class Tailwind baru → tidak perlu `npm run build`. Tidak
ada test Filament existing utk modul ini (dicek `tests/` — 0 file
match "Filament"/"Hrm"), jadi verifikasi murni via route:list+tinker, bukan
Livewire::test().

---

## 2026-09-11 — Rollout ikon tombol Back ke 24 layar (BACKLOG §10)

Permintaan user "selesaikan yang mudah dulu" — item ini sudah pre-approved
eksekusi mekanis tanpa konfirmasi ulang (lihat draf lama BACKLOG §10).
Ganti `<span class="...">←</span>` (Unicode, `text-xs font-bold`) jadi
`<span class="..."><i class="fa-solid fa-arrow-left"></i></span>` (FA 6.5.1
CDN, sudah dimuat app-wide), persis pola acuan yang sudah lebih dulu
diterapkan di `setup/component-library.blade.php` (2026-09-09).

24 file diubah (1 lokasi acuan sudah lebih dulu benar, jadi total 25/25):
`pur/prt/entry.blade.php`, `pur/pq/entry.blade.php`, `pur/pv/entry.blade.php`,
`pur/po/entry.blade.php`, `pur/pr/entry.blade.php`, `finance/cash-bank.blade.php`,
`inventory/stock-adjustment.blade.php`, `inventory/stock-out.blade.php`,
`inventory/stock-in.blade.php`, `recruitment/candidate-review/detail.blade.php`,
`production/wo-entry.blade.php`, `inquiry/costing.blade.php`,
`co/entry.blade.php`, `master/warehouse/entry.blade.php`,
`inventory/stock-return.blade.php`, `inventory/print/master-stock.blade.php`,
`inventory/print/stock-card.blade.php`, `quotation/entry.blade.php`,
`recruitment/offer/entry.blade.php`, `dispatch/do/entry.blade.php`,
`recruitment/requisition/entry.blade.php`, `inventory/print/slow-moving.blade.php`,
`notification/index.blade.php`, `master/raw-material/entry.blade.php`,
`inquiry/imposition.blade.php`.

Tidak ada perubahan class Tailwind baru (cuma isi `<span>`), tapi
`npm run build` tetap dijalankan sesuai checklist wajib CLAUDE.md §7.10 —
sukses. Diverifikasi: grep pola lama (`text-xs font-bold">←`) nihil hasil
di seluruh `resources/views`; `php artisan test
tests/Feature/MainMenuShellSmokeTest.php` 12/12 pass (murni cek regresi
shell, bukan test spesifik tiap 24 layar — perubahan murni markup ikon,
risiko rendah). Verifikasi visual browser tidak dilakukan (kredensial
tidak boleh diotomatisasi, `[[feedback_no_credential_automation]]`).

---

## 2026-09-10 malam — Standarisasi tombol aksi umum (5 titik, BACKLOG §10)

Task terjadwal `button-label-standardization-rollout` (Thu2107_TombolStandardisasi)
sempat fire jam 21:49 WIB tapi 0 progres nyata (sama seperti insiden
`i18n-rollout-malam-2` malam ini) — dieksekusi ulang manual atas instruksi
user langsung, dalam sesi `tmerp-backlog-autowork-1700`.

5 titik diperbaiki sesuai standar LOCKED (`CLAUDE.md` §3, `FORM_UI_STANDARD.md` §1a):

1. **`components/prodev/wizard-bar.blade.php`** — `backLabel` default
   `"Kembali ke Grid"` → `"Back"`. Dicek: 0 pemakai komponen ini yang
   override `backLabel` eksplisit, jadi cukup ubah default prop.
2. **`components/kanban/board.blade.php`** — 4x "Save" (EN) → "Simpan",
   1x "Cancel" (EN) → "Batal" (baris 167/269/314/364/315), menyamakan
   dengan "Batal" yang sudah dipakai di baris lain komponen yang sama.
3. **`pages/setup/support-tickets.blade.php`** — tombol "Close panel" →
   **"Close"** (BUKAN "Tutup" seperti draf instruksi awal — layar ini
   sengaja 100% berlabel Inggris sejak dibangun 2026-08-29, lihat komentar
   di kepala file; "Tutup" akan bikin 1 tombol beda bahasa sendirian di
   tengah layar yang semuanya EN. Koreksi manual saat eksekusi, bukan ikut
   instruksi task apa adanya). Sekalian (opportunistic, file sudah dibuka):
   rollout footer pagination standar `<x-grid.pagination-footer>` ke layar
   ini juga — melengkapi item ke-8 dari R37 yang tadinya sengaja dilewati
   krn dikira tabrakan dgn task ini (ternyata task lama itu sendiri yang
   tidak pernah jalan, jadi aman).
4. **`filament/forms/dashboard-theme-picker.blade.php:74`** — teks Blade
   polos "Klik **Save** di bawah" → "Klik **Simpan** di bawah". Ditelusuri
   lebih lanjut: tombol Save sungguhan di layar ini (Filament
   `EditProfile::getSaveFormAction()`, `app/Filament/Pages/Auth/EditProfile.php`)
   ternyata masih native Filament ("Save changes", APP_LOCALE=env default
   'en') — diberi override `->label('Simpan')` juga supaya teks & tombol
   sungguhan konsisten (tadinya cuma diminta cek teks Blade-nya saja, tapi
   dibiarkan mismatch kalau tombol asli tidak ikut diubah).
5. **Panel Filament `/admin`** — `EmpEmployeeResource`/`OrgPositionResource`
   + halaman `ManageEmpEmployees`/`ManageOrgPositions`: `EditAction`/
   `DeleteAction`/`CreateAction` diberi `->modalSubmitActionLabel('Simpan')`
   + `->modalCancelActionLabel('Batal')` eksplisit (APP_LOCALE default 'en'
   jadi native Filament selalu render "Save changes"/"Cancel" Inggris
   walau lang file `id` sudah ada di vendor Filament — bukan dipakai krn
   locale app bukan `id`). Label tombol Delete sendiri (bukan Cancel-nya)
   dibiarkan default framework — bukan bagian standar Simpan/Batal/Tutup.

Diverifikasi: lint semua file (`php -l`), `Livewire::test()` manual utk
`setup.component-library`/`setup.support-tickets` (render OK), HTTP kernel
riil (`Auth::login()`+`app()->handle()`) ke `/admin/hrm/emp-employees` &
`/admin/hrm/org-positions` (status 200), reflection check label
`EditProfile::getSaveFormAction()` = "Simpan", `MainMenuShellSmokeTest`
12/12 pass. Tidak ada perubahan class Tailwind baru → tidak perlu
`npm run build`. **Belum termasuk** toggle label ikut `lang_code` otomatis
(masih hardcode, lihat `project_i18n_shell_framework`).

---

## 2026-09-10 — Master Card ProDev: muat 1 lembar A4 landscape (R35)

User tunjuk screenshot langsung: hasil rebuild Master Card 2026-09-09 tumpah
ke halaman 2, padahal `docs/mockups/mockup_mastercard.jpg` jelas 1 lembar.
Awalnya diminta "masukkan backlog, kerjakan malam hari" (dicatat sbg R35 +
detail root cause di BACKLOG §0), lalu user susul "kerjakan sekarang saja" —
dieksekusi langsung, tidak ditunda.

**Root cause** (diverifikasi via `getBoundingClientRect()` di browser
sungguhan, bukan tebakan): tinggi `.print-page` 233mm vs budget A4 landscape
210mm (23mm kelebihan) — 2 penyebab utama:
1. Tabel spesifikasi kiri 12 baris (1 pasang label-value/baris) vs mockup
   ~7-8 baris (2 pasang/baris digabung).
2. Section "Catatan Finishing" (ditambah sendiri di rebuild 2026-09-09)
   TIDAK ADA sama sekali di mockup.

**Perbaikan**: tabel dipadatkan jadi 7 baris 4-kolom (`<colgroup>` +
`table-layout:fixed`, class baru `.items-2col`, colspan="3" utk baris ganjil
Pack Qty/Jenis Finishing); section Catatan Finishing dihapus total (nama
finishing tetap muncul digabung-koma di baris "Jenis Finishing"); padding
tabel dipadatkan (8pt/5px8px → 7.6pt/3px7px) + strip tanda tangan diperkecil
sedikit, keduanya SCOPED ke class `landscape` (page lain tidak terpengaruh).

**Verifikasi**: dump HTML render Livewire ke file lokal, dibuka di Browser
pane via static file di `public/` (bukan lewat login app — dihindari sesuai
`feedback_no_credential_automation`), ukur tinggi asli via JS
`getBoundingClientRect()`: 233mm → 197mm (natural height, min-height:210mm
dilepas sementara saat ukur) — sisa ~13mm aman utk padding mode-print yang
sedikit lebih kecil dari mode-layar. Test lama (2 test, 28 assertion) tetap
lulus tanpa perubahan assertion. Commit: `d993e65`.

---

## 2026-09-07 (lanjutan 6) — Payroll: Modul 07 (Rekening Bank) + Modul 09 (GPS Clock-in sungguhan)

User minta selesaikan 2 item tersisa dari checklist 11-mockup: "selesaikan
point no 7 dan no 9". Modul 09 sebelumnya cuma simulasi Artifact (respons
sesi sebelumnya) + SENGAJA ditunda di BACKLOG §12.5 -- kali ini dibangun
BACKEND SUNGGUHAN atas permintaan eksplisit kedua kalinya.

**Modul 07 — Master Karyawan & Rekening Bank**
(`resources/views/pages/hrm/payroll-employee-bank.blade.php`, menu
`HRM_PAYROLL_BANK` grup Payroll sort=7): grid semua pegawai aktif + kolom
Metode Gaji (badge Bank Transfer/Cash, DITURUNKAN dari ada/tidaknya
`emp_bank_account` aktif+primary -- bukan kolom baru, konsisten dgn pola
`payroll-bank-transfer.blade.php`/`payroll-cash-roster.blade.php` yang
sudah pakai cara sama) + Detail Rekening + filter Bank/Cash. Modal edit:
kosongkan Nama Bank = pegawai jadi Cash (rekening lama dinonaktifkan bukan
dihapus, jejak histori tetap ada).

**Modul 09 — Mobile GPS Clock-in (backend sungguhan)**:
- Tabel baru `sys_geofence_location` (tm2mst) -- titik kantor DATA-DRIVEN
  (bukan hardcode), diseed 1 baris "Pabrik Panji Suroso Malang" pakai
  koordinat riil dari `docs/payroll_timeattendance/tm2hrm_laravel_php83.zip
  ::GpsHaversineService` (-7.94220500, 112.65106000, radius 50m) --
  memungkinkan tambah lokasi pabrik lain tanpa ubah kode.
- Tabel baru `att_mobile_gps_log` -- setiap tap Absen Masuk/Pulang dicatat
  (lat/lng, jarak, status IN_RADIUS/OUT_OF_RADIUS, status approval HR).
- `att_finger_raw.id_import` diubah NULLABLE (raw SQL `MODIFY`, doctrine/dbal
  TIDAK terpasang di proyek ini jadi tidak bisa pakai `->change()`) --
  scan dari GPS bukan bagian 1 batch upload file, NULL = sumbernya GPS.
- `App\Services\Hrm\GpsHaversineService` (namespace kita, formula sama
  paket referensi, baca titik geofence dari tabel bukan `const` PHP).
- `resources/views/pages/self-service/clock-in.blade.php` (route
  `self-service.clock-in`, layout `layouts::app` polos tanpa sidebar --
  ini halaman mobile mandiri) -- pakai `navigator.geolocation` browser
  ASLI (Alpine `x-data`), bukan koordinat simulasi kayak Artifact
  sebelumnya. **Kalau DALAM radius**: langsung tulis `att_finger_raw` +
  panggil `AttendanceCalculationService::calculateForDate()` -- GPS jadi
  SUMBER PRESENSI YANG SETARA data mesin finger fisik, bukan cuma catatan
  terpisah. **Kalau DI LUAR radius**: cuma tercatat PENDING, presensi
  BELUM ditulis sama sekali sampai disetujui HR (Tier1 doc:
  "PENDING_HR_LOCATION_APPROVAL").
- `resources/views/pages/hrm/gps-approval.blade.php` (menu
  `HRM_GPS_APPROVAL`, grup Time Attendance) -- approve/reject log GPS
  PENDING; approve BARU menulis `att_finger_raw`+memicu kalkulasi (pola
  sama `hrm/leave-requests.blade.php`).
- Menu `HRM_CLOCKIN` (link cepat ke `self-service.clock-in`) ditambah di
  grup Time Attendance utk QC/testing -- pemakaian riil pegawai via
  bookmark/QR code HP, bukan navigasi sidebar desktop.

Diuji via `Livewire::test` mensimulasikan browser (`checkLocation()`
dipanggil langsung dgn koordinat, bukan lewat JS Geolocation asli krn
tinker tidak punya browser): (1) koordinat PERSIS titik kantor -> jarak
0m, AUTO_APPROVED, `att_finger_raw`+`att_daily_status` langsung
terbentuk; (2) koordinat ~6.8km dari kantor -> PENDING, `att_finger_raw`
**belum** ada (gerbang bekerja benar); (3) HR approve via
`hrm.gps-approval` -> BARU `att_finger_raw`+`att_daily_status` terbentuk.
Rekening bank tersimpan & terbaca benar di grid. Data test dibersihkan,
diverifikasi 0 baris tersisa. Test suite 101 passed/9 skipped/0 failed.
`npm run build` dijalankan.

**Catatan status**: mismatch Uang Roti (Operator vs "Staff/SPV" per Tier2
§4.4, ditemukan sesi sebelumnya) MASIH belum diputuskan user -- belum
diubah.

---

## 2026-09-07 (lanjutan 5) — Payroll: Modul 01 Upload Presensi + validasi data riil vs Excel HR

User menunjuk ulang checklist 11-mockup ("saya tidak melihat menu untuk
upload excel") -- benar, Modul 01 belum pernah dibangun (cuma 02-06+08 di
sesi sebelumnya). Sekalian diminta baca `docs/attachments/` utk simulasi
data riil dan "pencocokan hasil datanya" -- validasi UAT yang sejak awal
sesi ini direncanakan tapi belum pernah dieksekusi dgn data sungguhan.

**Modul 01 dibangun**: `resources/views/pages/hrm/attendance-upload.blade.php`
(menu baru `HRM_ATTENDANCE_UPLOAD`, grup Time Attendance sort=15 -- SEBELUM
"Presensi" sort=20, sesuai urutan flow). Terima `.csv/.txt/.xlsx` dgn kolom
wajib `device_pin,tanggal,jam_masuk,jam_keluar`, preview tabel + highlight
baris tidak dikenal, tombol "Simpan & Eksekusi Matching Shift" -> insert
`att_finger_import`+`att_finger_raw` lalu langsung panggil
`AttendanceCalculationService::calculateForDate()` per pegawai+tanggal.
Parsing pakai `fgetcsv` manual (csv/txt) + `PhpOffice\PhpSpreadsheet\IOFactory`
langsung (xlsx) -- **BUKAN** `Maatwebsite\Excel::toArray()` spt rencana awal,
ternyata API v4 minta instance class `Concerns\Import` utk kasus sesimpel
ini, ribet tanpa manfaat tambahan. Kolom `emp_employee.device_pin` baru
ditambah (prasyarat matching, sebelumnya tidak ada sama sekali).

**Validasi data riil** (permintaan eksplisit "migrasi dan proses sebagai
ujud simulasi dan pencocokan hasil datanya"): diambil dari sheet `TA`
(presensi harian mentah) + `REKAP ABSEN` file
`docs/attachments/8. GAJI KARYAWAN PERIODE 22 - 28 AGUSTUS 2026 WEEKLY
1657.xlsb` (dibuka via Python pyxlsb) utk 2 pegawai riil: MAHMUD SUPA'AT
(device asli 354) & HERU KURNIAWAN (device asli 421), periode 22-28 Agustus
2026. Migration `2026_09_07_000013`/`000014` (PERMANEN, bukan tinker
sekali-pakai -- sesuai `feedback_keep_dummy_data.md`) menyeed 2 employee
baru (kode VAL-001/VAL-002, JELAS ditandai "bukan pegawai produksi
sungguhan" di komentar), shift 07:30-15:30 sesuai jadwal asli, gaji pokok
ESTIMASI (Rp3.200.000/bulan -- BUKAN angka rahasia HR asli, formula rate
asli mereka berbasis faktor-jabatan yg tidak terbaca dari file export),
dan `emp_overtime_request` APPROVED sesuai selisih jam pulang riil vs
shift (60/180/180/60 menit di 24/26/27/28 Agustus).

Upload dijalankan via `Livewire::test` dgn CSV berisi jam riil dari sheet
`TA` (bukan data karangan) -- hasil:

| Yang divalidasi | Hasil Engine | Excel Asli (REKAP ABSEN) | Verdict |
|---|---|---|---|
| Status hadir 5 hari kerja | H semua, 0 telat | SAKIT/IJIN/ALPHA/SP = 0 | ✅ Cocok |
| Deteksi lembur (jumlah hari) | 4 hari (60/180/180/60 mnt) | `L. BIASA` = 4 | ✅ Cocok |
| Uang Roti (jumlah hari >120mnt) | terdeteksi 2 hari benar secara logika | `U. ROTI` = 2 | ✅ Logika cocok |
| **Uang Roti (nominal masuk payslip)** | **Rp0** (role tidak lolos filter) | **Rp10.000** | ❌ **TIDAK cocok** |
| Denda | Rp0 | Rp0 | ✅ Cocok |
| Gaji Pokok/TJ Keahlian (Rupiah) | dari rate estimasi | dari formula faktor-jabatan rahasia | ⚠️ Tidak bisa divalidasi (rate asli tidak terbaca dari file export, cuma hasil akhir) |

**Temuan nyata dari mismatch Uang Roti**: `AttendanceCalculationService::
isProductionStaffOrSpv()` mensyaratkan posisi pegawai mengandung kata
"STAFF" atau "SPV" (mengikuti bunyi literal Tier 2 §4.4 "Staff & SPV
Produksi") -- posisi yg dipakai pegawai uji (`Senior Heidelberg Operator`,
level Operator) TIDAK lolos filter itu, padahal Excel HR ASLI tetap
memberi Uang Roti ke pegawai level Operator ini. Ini indikasi nyata dokumen
Tier 2 (sumbernya PPTX lama) mungkin salah tulis "Staff & SPV" padahal
praktik riil di lapangan mencakup Operator juga -- **BELUM diperbaiki di
kode** (tidak mau ubah aturan bisnis sepihak tanpa konfirmasi), dicatat
sbg pertanyaan konkret utk user: apakah Uang Roti berlaku utk SEMUA role
Produksi (termasuk Operator), bukan cuma Staff/SPV?

Data simulasi (2 pegawai, presensi, payroll run WEEKLY id_run=8 status
CALCULATED) SENGAJA TIDAK dibersihkan -- tetap ada di database supaya bisa
dilihat langsung lewat layar Presensi Harian & Payroll Batch Run yang
sudah dibangun. Test suite penuh 101 passed/9 skipped/0 failed (tidak
berubah, upload screen belum ada automated test tersendiri). `npm run
build` dijalankan.

---

## 2026-09-07 (lanjutan 4) — PWA: app bisa "Install" ke layar utama HP + simulasi mobile clock-in

User minta lanjut "sampai level mobile", simulasinya ditaruh langsung di
chat (bukan cuma dokumen), plus langkah instalasi ke HP.

**Simulasi (Artifact, bukan bagian repo)**: preview interaktif clock-in
geofencing (mockup_09) -- kartu jarak-ke-kantor real-time (disimulasikan,
koordinat & radius 50m persis dari `docs/payroll_timeattendance/
tm2hrm_laravel_php83.zip::GpsHaversineService`), tombol Absen Masuk yang
hidup/mati sesuai simulasi radius, + panduan instalasi Android/iOS
tab-switch di sampingnya. Backend GPS log SENGAJA tidak dibangun (tetap di
luar prioritas per BACKLOG §12.5, "GPS/selfie tidak diminta") -- ini murni
preview visual+UX, ditandai jelas di halamannya sendiri.

**PWA sungguhan dipasang ke app** (bukan cuma simulasi) supaya langkah
instalasi yang diberikan ke user benar-benar bisa dipraktikkan:
- `routes/web.php::pwa.manifest` (`GET /manifest.json`, publik) -- manifest
  DINAMIS (bukan file statis `public/manifest.json`) supaya nama app ikut
  `Brand::name()` (bisa diedit lewat layar Configuration) tanpa sinkron 2
  tempat. `background_color`/`theme_color` pakai navy asli app (`#2c3e69`,
  `resources/css/app.css --color-navy`), BUKAN warna simulasi Artifact di
  atas (itu sengaja beda palet, murni demo kreatif terpisah).
- `public/sw.js` -- service worker MINIMAL (cuma pass-through fetch, TANPA
  caching agresif) -- cukup utk syarat "installable" Chrome/Android, sengaja
  tidak cache apa pun krn app ini Livewire server-rendered (cache-first bisa
  menyajikan HTML/CSRF token basi).
- `public/images/pwa-icon-{192,512}.png` -- ikon placeholder (navy + "TM",
  dibuat via PHP GD, BUKAN logo asli perusahaan -- `Brand::hasLogo()` kalau
  ada logo custom diupload TIDAK otomatis dipakai di sini, itu butuh resize/
  crop terpisah yang belum dibangun, dicatat sbg simplifikasi).
- `resources/views/partials/pwa-head.blade.php` (baru) -- tag `<link
  rel="manifest">`+meta theme-color+apple-touch-icon+registrasi service
  worker, di-`@include` di `layouts/shell.blade.php` DAN `layouts/guest.blade.php`
  (jadi bisa diinstall dari halaman login juga, bukan cuma setelah masuk).

Diverifikasi lewat Browser pane nyata (bukan simulasi) ke `http://tmerp.test`
(bukan https -- Herd pakai sertifikat lokal yang tidak dipercaya browser
pane ini, http polos berhasil): `/manifest.json` mengembalikan JSON benar,
`/images/pwa-icon-192.png` render ikon navy+TM dgn bersih, halaman login
otomatis fetch `/manifest.json` (200 OK) membuktikan tag `<link>` terpasang
& terbaca browser. Test suite penuh 101 passed/9 skipped/0 failed (layout
inti disentuh, wajib dicek).

**Cara install nyata (bukan simulasi) utk end user**: Android Chrome ->
titik tiga -> "Instal aplikasi"/"Tambahkan ke layar utama"; iPhone Safari ->
ikon Bagikan -> "Tambah ke Layar Utama". Ikon "TM" navy akan muncul di
layar utama HP, buka app dlm mode standalone (tanpa address bar browser).

---

## 2026-09-07 (lanjutan 3) — Payroll: 5 layar flow (03/05/06/08 + slip) + gate visibilitas HR + temuan PPh21/enkripsi

User menegaskan menu harus SEIRAMA urutan checklist 11-mockup yang sudah
disampaikan, dan minta interfacenya MENGIKUTI mockup `docs/payroll_
timeattendance/mockup_0{3,4,5,6,7,8}_*.html` persis (bukan desain bebas).
Dikerjakan berurutan tanpa jeda tanya lagi sesuai instruksi eksplisit.

**5 layar baru** (field/kolom/warna badge disalin dari mockup masing-masing,
mesin di baliknya tetap `PayrollEngineService`/`AttendanceCalculationService`
kita, bukan skema `tm2hrm` di paket referensi):
- `hrm/payroll-batch-run.blade.php` (mockup_03) — pilih periode WEEKLY/
  MONTHLY, tombol "Jalankan Kalkulasi" (panggil `calculate()`), ringkasan
  Bank vs Cash (dari `emp_bank_account` primary+aktif, BUKAN kolom
  `payment_method` yg tidak ada di skema kita) + tombol lifecycle penuh
  (Verifikasi FULL/RANDOM_SAMPLE → Konfirmasi → Tandai Dibayar → Tandai Slip
  Terkirim → Tutup Periode) -- lebih panjang dari mockup yg cuma DRAFT->lock,
  krn siklus kita sendiri sudah lebih detail (redesain 2026-09-05).
- `hrm/payroll-payslip-print.blade.php` (mockup_04) — pakai kerangka
  `components/print/*` (printnotastandard.md) spt Nota CO, bukan HTML bebas.
- `hrm/payroll-bank-transfer.blade.php` (mockup_05) — daftar pegawai
  berekening + total + export CSV (format generik, BUKAN format kliring
  resmi BCA/Mandiri -- itu perlu spek resmi bank, dicatat sbg open item).
- `hrm/payroll-cash-roster.blade.php` (mockup_06) — pegawai tanpa rekening +
  kalkulator pecahan uang (greedy 100k/50k/20k/10k/5k/2k/1k) + lembar serah
  terima printable.
- `hrm/discipline-report.blade.php` (mockup_08) — 4 stat widget + tabel
  Terlambat + tabel Tidak Masuk, dari `att_daily_status` per tanggal.

Menu diurutkan ULANG (permintaan eksplisit "menu harus seirama proses
flow"): grup **Time Attendance** (id 924, presensi dulu) skr sort=15,
grup **Payroll** (id 974, kalkulasi->transfer/cash) sort=20 (sebelumnya
kebalik, Payroll nongol duluan di sidebar padahal presensi harus jalan
dulu). `HRM_PAYROLL_CALC` ("Perhitungan Gaji") placeholder lama diaktifkan
(bukan dibuat baru). 3 menu baru: `HRM_DISCIPLINE_REPORT` (grup Time
Attendance), `HRM_PAYROLL_BANKXFER`+`HRM_PAYROLL_CASHROSTER` (grup Payroll).
Slip Gaji sengaja TIDAK dapat menu sendiri (dibuka kontekstual dari baris
payroll run, pola sama Nota CO).

**Gate visibilitas HR vs Payroll/Finance** (temuan user 2026-09-07, terpisah
dari soal menu): "HR officer tidak semua dia bisa melihat gaji, terutama
yang bulanan kecuali satpam (security) ... HR officer hanya jalankan proses
dan hanya tahu total secara keseluruhan." -> `SecUser::canViewPayrollAmounts()`
baru (is_admin/DIREKSI/MGR_ACC — belum ada sec_group "Payroll Officer"
sendiri, direkomendasikan dibuat kalau perlu granularitas lebih jauh),
diterapkan di `hrm/payroll-employee-salary.blade.php::canViewSalary()`:
nominal gaji individu di-mask kecuali viewer role di atas ATAU posisi
pegawainya mengandung SATPAM/SECURITY (dicek 2x: render + guard di
openForm()/save(), bertahan kalau dipanggil langsung).

**2 temuan/keputusan dicatat** (bukan kode, murni klarifikasi bisnis dari
user, meng-update entri PPh21 sebelumnya):
1. **PPh21 TETAP bulanan** meski ada siklus gaji mingguan — bukan bug yg
   perlu diperbaiki. Alasan user: hampir semua pegawai siklus WEEKLY masih
   di bawah UMR (PPh21 otomatis 0% di bracket manapun), dan kalaupun ada yg
   di atas UMR, mereka umumnya belum punya NPWP (PPh21 tidak bisa dijalankan
   tanpa NPWP). Kesimpulan: `[DELTA-CONFIRM]` soal konversi TER
   mingguan->bulanan di `Pph21TerService` **TIDAK PERLU dikerjakan** --
   secara praktik tidak akan pernah terpakai. Tetap disarankan `pph21_active`
   hanya utk MONTHLY sampai ada kasus nyata yg membutuhkan sebaliknya.
2. **Konfirmasi enkripsi gaji** (pertanyaan user, dijawab bukan kode baru):
   `pyr_employee_salary.basic_salary_monthly` dan `pyr_payslip.total_pokok`/
   `total_gaji_bersih` SUDAH terenkripsi AES-256-GCM sejak fondasi awal
   (`App\Casts\PayrollEncrypted`, `docs/payroll_encryption_decision.md`).
   Kolom lain (BPJS/PPh21/overtime/tunjangan/denda) TETAP plaintext sesuai
   scope keputusan asli ("mulai sempit dulu") -- gate visibilitas HR di atas
   adalah lapisan TAMBAHAN (kontrol akses UI), bukan pengganti enkripsi.

Diuji end-to-end via `Livewire::test`: 2 pegawai WEEKLY (1 berekening BCA, 1
tanpa rekening) -> calculate->verify->confirm->markPaid via layar batch-run
-> payslip print tampil benar (nama+bank) -> bank-transfer HANYA
menampilkan pegawai berekening -> cash-roster HANYA menampilkan yg tanpa
rekening -> discipline-report menampilkan Alpha pegawai kedua. Sempat
ketemu 1 kejadian nyata anomaly-gate bekerja benar (bukan bug): test
pertama pakai MONTHLY tanpa sadar ikut memproses 135+ pegawai riil yg
belum py `pyr_employee_salary` -> `confirm()` ditolak krn 142 anomali --
diperbaiki dgn isolasi test ke WEEKLY. Data test dibersihkan, diverifikasi
0 baris tersisa + `payment_frequency` 2 pegawai dikembalikan ke MONTHLY.
`npm run build` dijalankan. Test suite 101 passed/9 skipped/0 failed.

**Belum**: Modul 07 (Master Karyawan + Rekening, mockup_07) -- data rekening
sudah ada (`emp_bank_account`, dipakai bank-transfer/cash-roster di atas)
dan form gaji/PTKP sudah ada (Master Gaji & Pajak Pegawai, entri
sebelumnya), TAPI belum ada 1 layar CRUD Rekening Bank per pegawai yang
persis meniru tabel mockup_07 (kolom Metode Gaji + Detail Rekening + Aksi
Edit). PPh21/BPJS Master Karyawan+Rekening tetap prioritas berikutnya kalau
diminta.

> Dipisah dari `docs/BACKLOG.md` 2026-09-02 (permintaan user: "pindahkan ke
> file LOG Job Done md file, untuk yang sudah sehingga file backlog md
> mengecil") — supaya `BACKLOG.md` tetap ramping, cuma isi item yang MASIH
> terbuka. File ini murni arsip/riwayat, urutan dari **terbaru di atas**.
>
> **Cara pakai**: setiap kali 1 item di `BACKLOG.md` selesai dikerjakan,
> HAPUS dari `BACKLOG.md` dan catat lengkap di sini (bukan disalin ke
> dua-duanya).

---

## 2026-09-07 (lanjutan 2) — Payroll: BPJS + PPh21 TER, opt-in per pegawai + layar Master Gaji

User minta pasang BPJS & PPh21 (gap yang ditemukan sesi ini, lihat entri
"layar pertama" di bawah) dengan syarat eksplisit: **flag per pegawai**
("tidak semua aktif mengikuti aturan ini tetapi boleh dimudahkan agar
kedepan tinggal di-set mana yang pakai mana yang tidak").

**Skema baru** (migration `2026_09_07_000005`/`000006`): `pyr_employee_salary`
dapat `ptkp_status` (TK/0..K/3) + `bpjs_active`/`pph21_active` (boolean,
default **FALSE** -- opt-in, bukan otomatis semua kena potong). Dua tabel
rate baru (bukan hardcode, sesuai instruksi "DON'T HARDCODE" sebelumnya):
`pyr_pph21_ter_bracket` (kategori A/B/C x batas-atas-bruto x tarif, PP
58/2023 & PMK 168/2023, diseed dari nilai di
`Pph21TerService`/`BpjsCalculatorService` milik paket referensi) dan
`pyr_bpjs_rate` (JHT/JP/JKK/JKM/Kes, employee+company, dgn plafon). Kolom
baru `pyr_payslip.total_bpjs_employee`/`total_pph21`.

**Service baru** `App\Services\Payroll\Pph21TerService`/`BpjsCalculatorService`
(namespace kita, formula sama tapi baca dari tabel bukan `match()`/`const`
spt aslinya). Diintegrasikan ke `PayrollEngineService::calculateForEmployee()`:
dasar BPJS = POKOK+TJ_KEAHLIAN+TJ_LOYALITAS+TJ_PRESTASI (setara "gaji
pokok+tunjangan tetap"), dasar PPh21 = itu + BONUS_PAGI+BONUS_HADIR+
UANG_ROTI+OVERTIME (gross penuh, sesuai referensi). Kedua potongan HANYA
dihitung kalau flag terkait aktif; PPh21 juga butuh `ptkp_status` terisi
(divalidasi di layar, guard `pph21_active && !ptkp_status` diblokir dgn
pesan error, bukan diam-diam skip).

**[DELTA-CONFIRM] penting, dicatat eksplisit di kode & di sini**: TER PPh21
dirancang utk penghasilan BULANAN. Kalau `pph21_active` dinyalakan utk
pegawai `payment_frequency=WEEKLY`, bracket dicocokkan langsung ke bruto
mingguan TANPA konversi ke setara bulanan -- ini secara sistematis
MERINGANKAN pajak dibanding metodologi resmi DJP (kena bracket lebih rendah
dari yang seharusnya). Belum dikonfirmasi ke akuntan/konsultan pajak.
**Rekomendasi: PPh21 hanya dinyalakan utk pegawai MONTHLY sampai ini
diklarifikasi.**

**Layar baru** `resources/views/pages/hrm/payroll-employee-salary.blade.php`
("Master Gaji & Pajak Pegawai", menu baru `HRM_PAYROLL_SALARY` di grup
Payroll yang sudah ada, sort_order=5 sebelum "Perhitungan Gaji" placeholder)
-- grid semua pegawai aktif + status gaji (Belum diatur / nilai gaji pokok/
PTKP/toggle BPJS/PPh21 badge), modal form Atur Gaji & Pajak yang menegakkan
prinsip rate versioned (CLAUDE.md §4: rate lama `activate=false`+
`effective_to` diisi, insert baris baru, bukan overwrite).

Diuji via tinker (BPJS Rp8jt gaji -> JHT 160rb+JP 80rb+Kes 80rb = 320rb
tepat sesuai hitungan manual; PPh21 kategori A ~1.5% = 120rb tepat; pegawai
dgn flag OFF dapat 0 utk keduanya) dan `Livewire::test` utk layar form
(validasi PTKP wajib kalau PPh21 aktif terbukti bekerja, save berhasil
setelah dilengkapi). Data test dibersihkan, diverifikasi 0 baris tersisa.
`npm run build` dijalankan. Test suite 100 passed/9 skipped/0 failed.

**Catatan kolaborasi**: `main-menu.blade.php` sedang di-edit sesi lain
(refactor menu Configuration/lang_code) saat commit ini dibuat -- baris
`'HRM_PAYROLL_SALARY' => route(...)` SUDAH ditambahkan di file lokal
(disk), TAPI SENGAJA tidak ikut di-commit sesi ini supaya tidak mengganggu
WIP sesi lain (lihat [[project_multisession_collision_20260831]]) -- akan
ikut ke-commit begitu sesi lain commit filenya, atau di-commit menyusul
sesi berikutnya kalau belum. Menu-nya TETAP JALAN lokal krn sudah tersimpan
di disk, cuma belum tercatat di git history.

---

## 2026-09-07 (lanjutan) — Payroll: layar pertama, Presensi Harian (`hrm.attendance-daily`)

User minta baca isi kode `docs/payroll_timeattendance/tm2hrm_laravel_php83.zip`
(sebelumnya cuma dilihat daftar filenya) -- dibaca via ekstrak ke scratchpad
(bukan disalin ke repo). Isinya: `Pph21TerService`/`BpjsCalculatorService`
(formula pajak/BPJS riil, BELUM diadopsi ke engine kita -- gap besar utk
dicatat terpisah), `GpsHaversineService` (koordinat kantor riil Jl. Raden
Panji Suroso, radius 50m -- fitur GPS tetap tidak diprioritaskan sesuai
§12.5), `ElectronicSignatureService`, dan Filament Resources (`EmployeeResource`,
`AttendanceDailyResource`) + Livewire GPS clock-in view. User lalu eksplisit:
"saya mau kamu ambil interface nya saja" -- backend/model/migration paket
referensi TETAP tidak dipakai (skema beda konvensi, engine kita jauh lebih
detail), tapi field/kolom/badge-style dari `AttendanceDailyResource` dijadikan
acuan tampilan.

**Dibangun**: `resources/views/pages/hrm/attendance-daily.blade.php` (Livewire
v4 SFC, pola sama `hrm/leave-requests.blade.php`) -- grid presensi harian
dengan filter tanggal (date-picker dari-sampai) + cari nama/kode, stat-box
(Hadir/Terlambat/Tidak Masuk/Lembur), kolom shift/masuk/pulang/telat/lembur/
status(badge warna per kategori)/sumber(Otomatis vs Manual), tombol "Hitung
Ulang Presensi" (panggil `AttendanceCalculationService::calculateForDate()`
utk semua pegawai aktif dlm rentang tanggal), dan modal Override Status
(pilih status baru + alasan wajib min 10 karakter -> `att_daily_status.source
= MANUAL_OVERRIDE` + `override_by`/`override_reason`, sesuai alur "Alpha bisa
diubah manual jadi Izin/Cuti/Sakit" yang ditulis user 2026-09-05).

Route `hrm.attendance-daily` didaftarkan di `routes/web.php` (grup
`module:HRM`), ditautkan ke menu **placeholder yang SUDAH ADA** sejak
2026-08-24 (`HRM_ATTENDANCE` id=973, dulu `NOT_BUILT`) via `code=>route` map
di `main-menu.blade.php` (pola sama seperti `HRM_LEAVE_APPROVAL` -- kolom
`target` di `sys_app_menu` TIDAK dipakai utk resolve route, jangan tertipu
kolom itu kosong). Migration `2026_09_07_000004` set status jadi `READY_FOR_QC`.

Diverifikasi via `Livewire::test('pages::hrm.attendance-daily')` (BUKAN
browser -- domain custom `tmerp.test` tidak bisa diakses dari Browser pane
sandboxed sesi ini, `navigate`/`preview_start` ditolak; ini beda dari kasus
"browser login diblokir" yang biasa dicatat, di sini akses domainnya sendiri
gagal). Verifikasi: mount() set date range default, grid menampilkan data
seed 4 pegawai (termasuk badge "Alpha"), override Alpha->Izin Disetujui
berhasil (source berubah MANUAL_OVERRIDE, override_by terisi login user).
`npm run build` dijalankan (kelas Tailwind baru). Data seed dibersihkan,
diverifikasi 0 baris tersisa. Test suite 100 passed/9 skipped/0 failed.

**Belum**: layar upload Excel/finger raw (jadi tombol "Hitung Ulang" saat ini
cuma mengolah ulang data yang SUDAH ada di `att_finger_raw`/`emp_leave_request`,
bukan menerima file baru), PPh21/BPJS (gap besar terpisah, lihat entri di
atas), 10 layar mockup lain.

---

## 2026-09-07 — Payroll: HIGH IMPROVEMENT engine setelah pelajari stored procedure sistem lama

User minta pelajari dump `docs/ddl/tmhrm.sql` (8000+ baris, termasuk 3 stored
procedure produksi nyata: `sp_process_attlogs`, `sp_patch_att_summ_by_emp`,
`sp_attendance_exception_report`, plus data INSERT riil ~1200 baris
`tma_attendance_summary`) dan `docs/ddl/tmprd.sql` (`sp_updwo` ternyata stub
kosong, tidak relevan). Instruksi eksplisit: "HIGH IMPROVEMENT dengan
KETELITIAN tinggi, SIMPLE CEPAT AKURAT, semua angka dalam table jangan
hardcode, pastikan ini jadi ENGINE PAYROLL."

Sistem lama ini (`tmhrm`/`tmmst`/`tmpyr`, koneksi/skema BEDA dari V2 kita)
ternyata jauh lebih matang dari perkiraan — bukan cuma dokumen desain, tapi
kode produksi nyata dgn `payroll_periods.payroll_cycle ENUM('WEEKLY','MONTHLY')`
(memvalidasi redesain 2026-09-05 di bawah), `tma_rawdata` ("Temporary table
upload from Excel" — persis pola upload yg diminta user), `tma_attendance_codes`
(master kode absensi data-driven: effect_type NONE/DEDUCTION/BONUS/WARNING,
deduction_minutes/amt/percent — tabel `tma_attendance_codes_copy` bahkan
sudah pakai vocabulary status PERSIS sama dgn yang dipakai di sini: H/S/I/IS/
A/F/FF/C/CL/DRM/SKR/SP1-3/KJK/PA/T+15/PAT-5/PAT+5), dan `tma_attendance_summary`
dgn `payroll_ready`/`anomaly_days`/`anomaly_notes` (gate anti-garbage-in).

**3 perbaikan konkret diadopsi ke `PayrollEngineService`/`AttendanceCalculationService`:**

1. **Performa (CEPAT)** — dulu `PyrStatusRule::effectFor()`/`PyrDendaRule::forStatus()`
   query DB per baris presensi x per komponen (bisa ribuan query kecil utk
   137 pegawai x banyak hari x 6 komponen per run). Sekarang `pyr_status_rule`/
   `pyr_denda_rule`/`pyr_overtime_rate` (baru, lihat poin 2) di-preload SEKALI
   di awal `calculate()` ke `Collection` in-memory, dioper ke semua method
   privat — 0 query tambahan per baris.
2. **Lembur per jam (AKURAT + DON'T HARDCODE, gap nyata sebelumnya)** — dulu
   lembur cuma dapat Uang Roti flat Rp5.000 (Tier 2 §4.4), TIDAK ADA
   pembayaran lembur per jam sama sekali. Sekarang: `att_daily_status`
   dapat kolom `overtime_type` (WORKDAY/HOLIDAY/SUNDAY/EARLY, diklasifikasi
   `AttendanceCalculationService::resolveOvertime()` — EARLY dari
   `ot_type=AWAL`, SUNDAY/HOLIDAY dari `Carbon::dayOfWeek`+`SysHoliday::isHoliday()`,
   sisanya WORKDAY), tabel baru `pyr_overtime_rate` (multiplier per tipe,
   **data-driven, bukan hardcode** — diseed 1.5x kerja/early, 2.0x libur/
   Minggu, ditandai `[DELTA-CONFIRM]` krn belum dikonfirmasi user angka
   pastinya utk CV Telasih Makmur). Tarif/jam diturunkan dari gaji pokok
   harian dibagi **durasi shift aktual hari itu** (bukan asumsi "8 jam"
   hardcode). `pyr_payslip.total_overtime_pay` kolom baru, terpisah dari
   Uang Roti.
3. **Anomaly gate (AKURAT, gap nyata sebelumnya)** — dulu kalau pegawai tidak
   punya `pyr_employee_salary` aktif, payslip-nya DIAM-DIAM tidak dibuat sama
   sekali (pegawai "hilang" tanpa jejak dari payroll run). Sekarang tetap
   dibuatkan payslip (angka 0) + `has_anomaly=true`+`anomaly_note` (kolom
   baru `pyr_payslip`), dan `confirm()` MENOLAK kalau masih ada payslip
   anomali di run tsb (`RuntimeException`, hitung jumlahnya).

**1 perbaikan akurasi tambahan** (ditemukan cross-check ke `sp_process_attlogs`
sistem lama, ternyata sudah tertulis di Tier 2 §4.1 sendiri tapi belum
diimplementasikan): SAKIT tanpa lampiran surat dokter (`attachment_surat_dokter`
kosong) sekarang direklasifikasi ke Alpha (`AttendanceCalculationService::
mapLeaveToStatusCode()`), bukan otomatis dapat status S penuh POKOK.

**Sengaja TIDAK diadopsi** dari sistem lama (dicatat biar jelas ini pilihan
sadar, bukan kelalaian): pola `tma_so_ass.group_emp_id` (daftar employee_id
dipisah `;` dalam 1 kolom teks, di-query pakai `FIND_IN_SET` — anti-pattern
relasional, `emp_shift_roster` kita yang 1-baris-per-pegawai-per-tanggal
lebih benar); model `disciplinary_points` akumulatif (kita pakai event
diskret SP1/SP2/SP3 sesuai Tier 2 §6 yang sudah dikonfirmasi bisnis,
tidak diganti tanpa alasan kuat); sub-tipe IJIN berdasar jam (Awal/Tengah/
Akhir Shift, kode 03/04/05) — granularitas ekstra yang belum diminta.

Diuji tinker: 2 pegawai WEEKLY, pegawai A lembur WORKDAY 60mnt (15.000,
1.5x dari tarif/jam Rp10.000) + lembur SUNDAY 60mnt (20.000, 2.0x) =
total_overtime_pay 35.000 (cocok hitungan manual); pegawai B sengaja tanpa
`pyr_employee_salary` → payslip anomali dibuat otomatis, `confirm()`
terbukti menolak. Re-query DB pasca cleanup: 0 baris tersisa. Test suite
penuh: 98 passed/9 skipped/0 failed.

**Belum dikerjakan**: hourly-rate basis utk TJ_KEAHLIAN via lookup posisi
(`REFF_KEAHLIAN`, dicatat 2026-09-05) masih belum diadopsi; seluruh layar UI.

---

## 2026-09-05 (lanjutan sore) — Payroll: redesain siklus payroll_run sesuai alur bisnis lengkap

Setelah fondasi pagi (entri di bawah), user menulis alur bisnis end-to-end
secara eksplisit dalam bentuk diskusi (bukan spek formal): upload Excel jadi
raw data → olah jadi laporan terlambat/tidak-masuk → status "tidak masuk"
masih bisa diubah manual oleh HR (Alpha→Izin/Cuti/Sakit) → H-1 proses
estimasi total gaji → H diproses ulang + dicek (semua atau random sample
jam masuk/keluar) → confirm → bayar (transfer bank/cash) → kirim slip
(elektronik/cetak) → tutup periode → masuk jurnal accounting → data jadi
histori, periode baru otomatis dibuat kosong. Periode ada 2 jenis: WEEKLY
dan MONTHLY.

Sebelum redesain, sempat dicek 1 sampel Excel gaji riil (`docs/attachments/
8. GAJI KARYAWAN PERIODE 22-28 AGUSTUS 2026 WEEKLY 1657.xlsb`, dibuka pakai
Python pyxlsb karena PhpSpreadsheet tidak punya reader utk `.xlsb`) —
mengonfirmasi periode memang WEEKLY (bukan bulanan seperti asumsi pagi),
dan menemukan struktur nyata: sheet `TA` (presensi harian mentah, jam
in/out per tanggal), `REKAP ABSEN` (rekap mingguan per pegawai persis
mirip `pyr_payslip`+detail), `TOTAL` (target pembanding UAT), `slip`
(payslip cetak), `Perubahan Gaji` (master gaji per pegawai, GAJI POKOK
disimpan sbg nilai HARIAN langsung bukan bulanan/30), `REFF_KEAHLIAN`
(TJ_KEAHLIAN look-up per posisi, bukan bebas per pegawai — belum diadopsi
sesi ini, dicatat sbg gap). `LEMBUR /JAM` di sheet `Perubahan Gaji` juga
menjawab Open Item Tier 2 §4.4 (nominal lembur/jam) yang sebelumnya
dianggap belum ada aturannya — belum diimplementasikan ke `PayrollEngineService`
sesi ini, masih pakai `emp_overtime_request.duration_minutes` manual.

**Migration** `2026_09_05_000008_extend_payroll_run_lifecycle.php`:
- `pyr_payroll_run`: drop `period_year`/`period_month`/unique lama, tambah
  `period_type` (WEEKLY|MONTHLY) + `period_start`/`period_end` (date range,
  bukan tahun+bulan) + unique baru `(period_type, period_start)`. Tambah
  kolom siklus penuh: `verification_method`/`verified_sample_employee_ids`/
  `verified_by`/`verified_at`, `confirmed_by`/`confirmed_at`, `paid_by`/
  `paid_at`, `payslip_sent_at`, `closed_by`/`closed_at`, `next_run_id`.
- `tm2mst.emp_employee`: tambah `payment_frequency` (WEEKLY|MONTHLY,
  default MONTHLY) — menentukan pegawai ikut `pyr_payroll_run` jenis mana,
  supaya 1 pegawai tidak bisa kena double-run weekly+monthly sekaligus.

**`PayrollEngineService` dirombak** dari `run()`/`markReviewed()`/`finalize()`
jadi method per-tahap sesuai state machine: `calculate()` (idempotent,
boleh dipanggil ulang selama status DRAFT/CALCULATED — H-1 & H sama-sama
panggil ini), `verify($method, $sampleIds, $by)`, `confirm()`, `markPaid()`,
`markPayslipsSent()`, `close()` (otomatis `firstOrCreate` periode berikutnya
kosong via `PyrPayrollRun::nextPeriodStart()/nextPeriodEnd()` — +7 hari utk
WEEKLY, +1 bulan utk MONTHLY). Tiap transisi divalidasi guard status
(`RuntimeException` kalau lompat tahap, mis. confirm sebelum verify).

Diuji end-to-end via tinker: 1 pegawai WEEKLY, kalkulasi H-1 (2 hari data)
→ tambah 1 hari Alpha → recalculate H (denda ikut update ke 100000) →
confirm ditolak sebelum verify (guard bekerja) → verify RANDOM_SAMPLE →
confirm → markPaid → markPayslipsSent → close → periode berikutnya
terverifikasi otomatis dibuat (WEEKLY 2026-08-22..28 → next 2026-08-29..
09-04, status DRAFT) → recalculate ditolak setelah close (presensi
terkunci). Re-query DB pasca cleanup: 0 baris tersisa di 3 tabel terkait.
Test suite penuh: 96 passed/9 skipped/0 failed (tidak berubah).

**Belum dikerjakan** (dicatat eksplisit, bukan diam-diam dilewati):
TJ_KEAHLIAN look-up per posisi (`REFF_KEAHLIAN`), lembur per jam dari rate
riil (`LEMBUR /JAM`), hook `JournalPostingService` saat CLOSED, generator
fisik bank-transfer/cash-disbursement (baru pencatatan status `PAID`),
pembedaan kanal kirim slip (elektronik vs cetak) per pegawai, dan
tentu saja seluruh layar UI (upload Excel, grid review, tombol per-tahap
lifecycle).

---

## 2026-09-05 — Payroll (tm2pyr) fondasi: skema DB, enkripsi, engine presensi & payroll

Permintaan user ("baca folder docs/payroll_timeattendance, lalu kerjakan").
Modul Payroll sebelumnya 0% dibangun (`tm2pyr` 0 tabel) -- sesi ini membangun
FONDASI (skema + engine), BUKAN layar UI (11 mockup di `docs/payroll_timeattendance/`
masih menyusul iterasi berikutnya, sesuai prinsip iterasi kecil CLAUDE.md §7).

**Temuan sebelum mulai coding** (lihat memory `project_payroll_reference_package_findings`):
`docs/payroll_timeattendance/` sudah dikonfirmasi `payroll_encryption_decision.md`
sbg materi REFERENSI formula (bukan kode siap pakai) -- skemanya (`is_active`,
`employee_id` PK) melanggar konvensi proyek & tidak implementasi enkripsi sama
sekali. Dokumen KANONIK yang dipakai sbg acuan implementasi sungguhan:
`docs/attendancepayroll_tier1..4.md` (v1.0, berbasis SOP asli perusahaan) +
`docs/payroll_encryption_decision.md`. Drift arsitektur ditemukan & diterima
user: `tm2hrm` tetap kosong (0 tabel), tabel presensi baru ikut ditaruh di
`tm2mst` (konsisten dgn `emp_employee`/`emp_shift` yang sudah live di sana),
bukan `tm2hrm` sesuai CLAUDE.md §2 asli.

**Dibangun**:
- **Migration tm2mst**: `att_finger_import`, `att_finger_raw`,
  `emp_overtime_request` (baru), `att_daily_status`; `emp_disciplinary`
  ditambah `effective_end`/`working_days_target`/`working_days_counted`
  (reuse tabel existing utk tracking SP1/SP2 "berturut-turut", bukan bikin
  tabel duplikat `emp_disciplinary_status`).
- **Migration tm2pyr**: `pyr_employee_salary` (tabel BARU, gap yang
  ditemukan saat implementasi -- Tier 3 doc tak pernah mendefinisikan tempat
  nilai gaji_pokok/tunjangan per pegawai utk formula prorata), `pyr_status_rule`,
  `pyr_denda_rule` (diseed penuh dari Tier 2 §4.1-4.3), `pyr_payroll_run`,
  `pyr_payslip`, `pyr_payslip_detail`.
- **Enkripsi**: `App\Casts\PayrollEncrypted` (AES-256-GCM via `openssl_*`,
  key `SysConstant::text('VERBA_SANCTA')` di-derive sha256) tepat sesuai
  `payroll_encryption_decision.md` §3 -- diterapkan ke `pyr_employee_salary.
  basic_salary_monthly` & `pyr_payslip.total_pokok`/`total_gaji_bersih`.
  Diverifikasi round-trip via tinker + cek RAW DB (ciphertext, bukan
  plaintext) -- lihat memory `feedback_verify_test_cleanup_in_db`.
- **`App\Services\Hrm\AttendanceCalculationService`** -- evaluasi status
  harian (H/T-5/T+5/T+15/PA/PAT-5/PAT+5/KJK/A/F/FF/I/IS/S/C/CL) dari
  finger raw + `emp_shift_roster`/`emp_shift_detail` + `emp_leave_request`,
  termasuk counter toleransi tidak-finger (F/FF, max 2x/periode) & counter
  pelanggaran T+5 (>3x/periode). SP1/SP2/SP3 SENGAJA tidak dievaluasi di
  sini (layer tambahan di step payroll run, Tier 2 §7).
- **`App\Services\Payroll\PayrollEngineService`** -- job payroll run per
  periode bulanan: baca `att_daily_status` (Eloquent query terpisah,
  BUKAN VIEW cross-DB -- bukan hot-path), terapkan `pyr_status_rule`+
  `pyr_denda_rule`+efek SP1/SP2 (potongan 50% x N hari kerja berturut,
  counter persisten di `emp_disciplinary.working_days_counted`)/SP3
  (zero-total), tulis `pyr_payslip`+`pyr_payslip_detail`. Diuji end-to-end
  via tinker (1 pegawai, 3 hari: H/T+15/A) -- hasil kalkulasi prorata &
  denda diverifikasi manual cocok dgn rule matrix.

**Simplifikasi/gap yang disadari, perlu diketahui sesi berikutnya**:
- KJK (keluar-masuk tengah shift) diasumsikan DIJUMLAH semua window
  (bukan window terpanjang) -- ini `[DELTA-CONFIRM]` di Tier 2 §3, belum
  final dari bisnis.
- PAT-5/PAT+5 pakai prorata (bukan zero-total) -- `[DELTA-CONFIRM]` Tier 2
  §4.2, belum dikonfirmasi Telma/Jo.
- Tidak-finger ke-3+ dlm periode -- Open Item, direklasifikasi ke Alpha
  (belum ada aturan resmi lain).
- "Uang Roti" (Rp5.000, lembur>2jam, Staff/SPV Produksi) pakai heuristik
  cocok nama departemen/posisi (`str_contains` "PRODUKSI"/"STAFF"/"SPV") --
  belum ada flag role eksplisit di `org_position`.
- `emp_overtime_request` (tabel baru) BELUM ada UI pengajuan/approval --
  saat ini cuma dipakai kalau ada baris `status=APPROVED` yg diisi manual/seed.
- 11 mockup layar (`docs/payroll_timeattendance/mockup_*.html`) BELUM
  dibangun jadi Livewire -- termasuk import finger, review grid harian,
  payroll batch UI, cetak payslip, generator bank-transfer/cash-disbursement.
  GPS mobile clock-in & ESS approval workflow (fitur v4.0 di paket referensi)
  SENGAJA tidak diprioritaskan -- `BACKLOG.md` §12.5 lama sudah mencatat
  "GPS/selfie mobile clock-in -- tidak diminta, tidak dibangun".

Test suite penuh dijalankan setelah perubahan: 96 passed / 9 skipped (baseline
Firebase emulator off, tidak berubah dari sebelumnya), 0 failed.

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

## 2026-10-02 — Grid Transactional (persegi dokumen + tabel anak) dan alur tiga tanda Inquiry

- **Grid Transactional (pedoman C.28):** kolom pertama = persegi dokumen (nomor + nama pihak, 8 gradasi), baris induk 5-8 kolom
  tanpa jumlah item, Expand = tabel anak maks 7 kolom berisi nama item / product. Terpasang di contoh DevLib, Quotation, CO,
  CPDEL, DO, Surat Jalan, POD, PR, PQ, PO, Penerimaan, Kas Kecil, Work Order, dokumen stok, Material Booking, Inquiry,
  Pro-Dev, Pro-Cost. Belum: Maintenance, HR / Payroll / Recruitment.
- **Alur Inquiry → Pro-Dev → Pro-Cost (pedoman A.20):** `stat_confirm` inquiry → `stat_confirm_sales`; tambah
  `stat_confirm_prodev` / `stat_confirm_procost` + siapa / kapan (migration `2026_10_02_000020`, ddt + tm2). Menu dipisah per
  jenis (migration `2026_10_02_000021`). Tombol "Kirim ke ProDev" dihapus; Recalc langsung ke Pro-Cost.
- Belum diputuskan: back process CO direct karena percepatan Direksi; kode jenis order RTP / RCL; Sales PIC dari master (komisi).

## 2026-10-02 — Sales = satu grup besar; CRM dibangun (ddt, menunggu penilaian user)

- **Menu Sales (pedoman A.21):** Sales > Inquiry & Quotation | Order | Delivery | CRM; tiap grup kecil punya saklar `activate`
  sendiri (migration `2026_10_02_000131`, hanya `ddtsys`). Rute simpan CRM ikut tertutup bila grupnya dimatikan.
- **CRM** (`docs/md_for_systems/sales_tier1.md`): Customer 360 (`crm-customer`, hanya membaca; order baru + arsip 20 ribu CO lama,
  grafik 24 bulan, product paling sering dipesan), Activities & Follow-up (`crm-activity`; baris berwarna menurut jatuh tempo,
  Log Result menutup follow-up asal), Complaint & Return (`crm-complaint`; Draft → Confirmed → Closed | Cancelled, cetakan
  `customer-complaint`), Complaint Review QA (`qc-complaint-review`, Production › Quality).
- **Database** (migration `2026_10_02_000130`, ddt + tm2): `quo_activity` → `crm_activity` (+ `customer_id`, `contact_id`, `topic`,
  `next_done`); baru `crm_complaint`, `crm_complaint_item`, `tm2prd.qc_complaint_review`; konstanta `CRM_FOLLOWUP_SOON_DAYS`,
  `CRM_SLEEPING_DAYS`, `SIGN_CCR_*`; kategori konstanta `SALES`.
- Data contoh ddt (migration `000132`), kamus Indonesia (`000133`, ddt + tm2), Help 4 layar, test `CrmTest` (5 test).
- Pratinjau layar: `docs/mockups/crm/`. Belum: menu + Help di `tm2sys`, izin per grup, retur masuk stok, target Sales.

## 2026-10-02 — Purchase Requisition: item teks bebas selalu gagal disimpan (akun wajib dipilih saat menambah)

- **Bug**: `addFreeText()` di `pages/pur/pr/entry.blade.php` menyimpan `i_coa_account_id = NULL`, padahal kolom
  `pr_line.i_coa_account_id` NOT NULL (ddtpur dan tm2pur sama) -> `SQLSTATE[23000] 1048 Column 'i_coa_account_id' cannot be null`.
  Kena layar v1 dan v2 ("+ Item Bebas"), karena v2 memanggil komponen yang sama. Item Master tidak kena (20 kategori
  level 1 semuanya punya akun default).
- **Perbaikan tanpa ubah skema**: akun dipilih saat item bebas ditambahkan (lookup akun yang bisa diposting) — v1: pilihan
  "Akun COA" di tab Teks Bebas; v2: pilihan "Account" di sebelah uraian + satuan, dan kolom Account di panel rincian
  baris (bisa diganti selama Draft). Tanpa akun: baris tidak dibuat, muncul pesan.
- `updateLine()`: nilai kosong untuk kolom NOT NULL (uraian, qty, satuan, departemen, akun) diabaikan — dulu memilih
  "— pilih —" di kolom Departemen / Akun COA baris juga berakhir error 1048.
- Submit tidak diberi pemeriksaan tambahan: kolom tetap NOT NULL, jadi baris tanpa akun tidak mungkin ada.
- Test `PurchaseRequisitionFreeTextLineTest` (6 test, membersihkan PR buatannya sendiri). Dokumen: `procurement_tier2.md` A-1.2,
  `screen_pur_pr_entry.md`.

## 2026-10-02 — Inventory & Warehouse: peta gudang, Stock Transfer, label QR / barcode, dead stock merah (mockup.test)

Perintah user: "ya saya tunggu warehouse mapping, lalu input moving item antar warehouse ... capacity label dan barcode or
QR code ... warna merah untuk DEAD stock ... Facility for QR Code, Labelling, BUILD TOO". Rancangan + yang dibangun:
`docs/warehouselayout_tier1.md` bagian 13. Dokumen review user: `docs/md_for_systems/warehouse_review.md`. Tangkapan layar:
`docs/mockups/warehouse_app/`.

- **Gudang = gedung nyata** (user: A5, A6, A10 terpisah, masih ada gedung lain): `wh_warehouse` + ukuran meter, bentuk
  (kotak / huruf L / garis luar sendiri), 1-3 lantai. **Area** = tabel baru `wh_zone`; **bin** = `wh_loc` + area,
  kapasitas pallet, posisi, alasan blokir. Layar isi: Buildings, Storage Areas, Bins (Grid Lookup) + Generate Bins.
- **Warehouse Map** (`warehouse-map`, objek DevLib baru `x-dl::wh-map`): denah per lantai, bin berwarna menurut isi,
  **dead stock merah**, panel isi bin (kapasitas, QR, item), cari, saring, Edit Layout (seret / ubah ukuran area),
  tombol Transfer / Label / Block.
- **Stock Transfer** (`stock-transfer`): tabel baru `inv_transfer` + `inv_transfer_line`, posting
  `StockTransferService` (dua baris kartu stok per baris, `doc_type` TF, tanpa jurnal, Cancel = pindah balik), nomor
  `TFyy#####`, pemeriksaan kapasitas bin tujuan, label bin (QR + barcode) di form, cetakan `stock-transfer` (belum A).
- **Label & lacak**: Print Location Label, Print Item Label, lembar cetak (`x-dl::label`, `x-dl::label-sheet`), Label
  Print History (`inv_label_print`), Track via QR/Barcode (`x-dl::scan`: scanner atau kamera HP).
- **Dead stock satu definisi**: view `v_*_onhand` + `last_item_movement` (transfer tidak dihitung sebagai gerak); dipakai
  peta, transfer, laporan Stock by Location, jawaban asisten Deadstock, widget Deadstock Value, laporan Slow Moving Item.
  `v_inv_movement` membawa nomor transfer; Stock Card menandai Transfer in / Transfer out.
- Perbaikan DevLib: baris `x-dl::lines` yang read-only menampilkan teks pilihan (dulu kuncinya); `DbGrid::rowTone()`.
- Migration `2026_10_02_000200` (tabel) + `000201` (view): ddt + tm2. `000202` (menu Inventory > Warehouse, Label & QR Code,
  Scan Center): ddt saja. `000203` (kamus Indonesia): ddt + tm2. Help 10 layar (`resources/data/help/warehouse.json`): ddt.
- Data contoh ddt: `UatWarehouseSeeder` (A10 30 x 20 m, A5 24 x 16 m, A6 20 x 12 m, 2 lantai; 16 area, 217 bin; stok lewat
  Stock In sungguhan; 3 transfer). Ukuran gedung = perkiraan, disesuaikan officer.
- Test `StockTransferServiceTest` (6 test, membersihkan sendiri, dicek ke DB). Engine Catalog: Warehouse Stock, Stock
  Transfer Posting. Uji layar: render tanpa login + Chrome headless (klik bin, cari, saring, lantai, seret area) dan
  rute simpan (label, blokir, buat bin, transfer simpan / Confirm / cetak / Cancel).
- Belum: Print Pallet Label, Scan Transaction, putaway terarah, FEFO, titipan customer, subkon, cycle count, menu di
  tm2sys, denah nyata tiap gedung (lihat `backlog05_inventory_warehouse.md`).

### 2026-10-02 — Inventory & Warehouse putaran 2 (mockup.test)

- **Kapasitas luas x tinggi**: tinggi bersih tiap lantai (`wh_warehouse.height_f1..f3_m`), tinggi tumpuk area
  (`wh_zone.stack_height_m`); bin = luas tapak x tinggi (m3); volume stok kertas diperkirakan; status bin = yang lebih
  penuh antara berat dan volume (`WH_BIN_FULL_PCT`). Tabel Capacity per Floor di Warehouse Map; baris Berat / Volume /
  Luas tapak x tinggi / Pallet di panel bin; kolom Volume di Stock by Location dan di pemeriksaan Stock Transfer.
- **Palet berlabel**: `inv_pallet` + `inv_pallet_log`, `PalletService` (daftar pekerjaan label = selisih saldo stok dan
  qty palet; label kedatangan; label ulang sesudah dipakai; palet ikut pindah saat Stock Transfer dan kembali saat
  Cancel), layar **Print Pallet Label**, lacak palet + Move this Pallet.
- **Label**: tiga jenis berbeda (lokasi, item, palet) dengan pita warna + tulisan, QR + barcode, tanggal tiba, kode palet
  supplier, tag Expired / Expires soon; ukuran nyata 60 x 40, 100 x 50, 100 x 150 mm; cetak di gulungan label atau A4;
  panduan ukuran + cara cetak + arti warna (`x-dl::label-guide`).
- **Kedaluwarsa**: tanggal per palet (usulan dari umur simpan master), tag di label, peta, panel bin, laporan, lacak
  (`WH_EXPIRY_SOON_DAYS`).
- **Cetak label saat penerimaan barang**: tombol Pallet Labels dan Item Labels di form Penerimaan Barang (Posted).
- **Approval Stock Transfer**: modul flow `TF`, bawaan tidak aktif; saat aktif stok pindah sesudah approval terakhir;
  layar Stock Transfer Approval Center; Duplicate untuk dokumen Rejected / Cancelled.
- Migration `000204` (skema + konstanta: ddt + tm2), `000205` (menu + Setting Approval TF: ddt saja), `000206` (kamus
  Indonesia: ddt + tm2). Help 12 topik (ddt). Seeder `UatWarehouseSeeder` diperbarui. Engine Catalog: Pallet Labels.
- Test: `PalletServiceTest` (8 test; approval dilewati di tm2 karena Setting Approval belum terpasang di sana) +
  `StockTransferServiceTest` (6). Jalur approval dan penerimaan barang -> label diuji di ddt di dalam transaksi yang
  dibatalkan (tidak meninggalkan data). Semua layar dirender dan dipotret (`docs/mockups/warehouse_app/`).
- Ditemukan, belum diperbaiki: dokumen stok bertanggal mundur tidak mengubah saldo yang tampil (backlog 05.23).

### 2026-10-03 — Inventory & Warehouse terpasang di tmerp.test; data contoh mockup.test disimpan

- tm2: menu Inventory > Warehouse (Warehouse Map, Stock Transfer, Setup, Report), Label & QR Code (Location, Pallet, Item,
  History), Scan Center (Track) lewat migration `2026_10_03_000010`; enam laporan stok (`000220`); Help 12 topik; gedung
  A10 / A5 / A6 + 16 area + 217 bin (`WarehouseBuildingSeeder`, tanpa stok). Backlog 05.20 selesai.
- Approval Stock Transfer di tm2 dilewati (Setting Approval v2 belum terpasang di sana); pemasangnya
  `StockTransferApprovalSeeder` bisa dijalankan nanti (backlog 05.24).
- ddt: tidak ada yang berubah selain baseline Reset baru (2026-10-03 00:42, yang lama dicadangkan di
  `storage/app/backups/`) dan set "Mixed dummy" yang disimpan ulang dengan data gudang putaran 2.
- Uji: 20 layar terbuka di tmerp.test; alur transfer + palet + label + tata letak dijalankan di tm2 dan ddt di dalam
  transaksi yang dibatalkan; `PalletServiceTest` + `StockTransferServiceTest` lolos; tidak ada sisa di database.

### 2026-10-03 — Kartu stok: posting bertanggal mundur dihitung ulang (backlog 05.23, rulebook A.24)

- **Cacat yang ditutup** (terbukti lewat test 2026-10-02): dokumen bertanggal lebih awal dari baris terakhir tertulis di
  kartu tetapi tidak sampai ke saldo; dua dokumen pada tanggal yang sama saling tidak melihat begitu nomor baris kartu
  melewati nomor dokumen; Cancel SPB diterima walau barangnya sudah dipindah; adjustment bertanggal mundur tidak
  mengoreksi saldo; penolakan di baris ke-2 meninggalkan baris kartu baris ke-1.
- **`StockCardChain`** (baru): rantai saldo satu posisi, urut (doc_date, doc_id); menghitung ulang saldo, harga rata-rata
  dan nilai dari satu baris sampai baris terakhir, merambat ke bin tujuan transfer bila harga yang ikut pindah berubah.
- **`StockPostingService::postDocument()`**: baris disisipkan lalu rantainya dihitung ulang; saldo minus (baris itu atau
  sesudahnya) = seluruh dokumen batal (`StockPostingRefused`, transaksi di-rollback); tanggal di periode inventory
  CLOSED ditolak (`InvPeriod::closedThrough()`); hasil membawa `revaluation` + `stock_warning` bila harga baris keluar
  sesudahnya berubah. `value_in` ditulis di baris masuk (juga oleh `StockTransferService`).
- **Layar**: pesan berhasil Stock In / Out / Return / Adjustment dan Penerimaan Barang v2 menampilkan peringatan nilai;
  Stock Adjustment "tarik seluruh item" membaca baris terakhir menurut tanggal + nomor (dulu nomor terbesar).
- Tidak ada perubahan skema (kolom `value_in` sudah ada). Kode yang sama berlaku di mockup.test dan tmerp.test.
- Test: `StockPostingOrderTest` (9 test). Belum dikerjakan: jurnal koreksi (menunggu keputusan user), hapus + bangun
  ulang dummy kartu stok yang salah, layar tutup periode inventory (backlog 05.23, 05.37).

