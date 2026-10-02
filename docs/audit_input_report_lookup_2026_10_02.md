# AUDIT 2026-10-02 — input ke database, grid/laporan dari database, layar input lookup, dokumen tidak relevan

> Permintaan user 2026-10-02: (1) apakah semua input data terkoneksi ke database, (2) apakah semua report dan grid
> ambil dari database, (3) apakah semua lookup sudah punya interface input, (4)+(5) file `docs/` yang tidak relevan
> dipindah ke folder unused.
>
> **Hanya pemeriksaan.** Tidak ada kode alur, data, atau layar yang diubah. Yang berubah: file dipindah ke arsip
> (bagian E), tiga catatan letak dokumen di `CLAUDE.md` / `index_master.md`, dan satu komentar basi di
> `app/Support/V2/Lookups.php`.
>
> Tanda bukti: **[C]** = saya baca / jalankan sendiri. **[A]** = hasil baca kode oleh agen pemeriksa, belum saya
> ulang sendiri (perlu dicek sebelum diperbaiki).

---

## A. Yang dijalankan

| Pemeriksaan | Hasil |
|---|---|
| Migrasi `ddt*` (mockup.test) | tidak ada yang tertunda [C] |
| Migrasi `tm2*` (tmerp.test) | **19 tertunda** — semuanya paket yang memang "ddt dulu, tm2 menunggu OK": approval flow (5), katalog model mesin + baseline PPIC + speed target (8), PO Kontan flow, Maintenance (2), Material Planning (2), menu drop ACC Digital. Tidak saya jalankan. [C] |
| Test suite | 270 test: 257 lulus, 12 skip (Firebase emulator mati), 1 error lama (`tm2mst.prod_machine_model` belum ada di tm2 — hilang begitu migrasi mesin di atas dijalankan). Sama dengan baseline, tidak ada yang baru rusak. [C] |
| Probe GET semua item menu aktif (mockup.test, ddt) | 196 item dibuka lewat kernel: **196 OK, 0 gagal** [C] |
| Semua grid v2 dijalankan ke database | 111 kelas `DbGrid` / `DbLookup`: **111 jalan, 0 error**, semuanya query ke tabel / view `ddt*` [C] |
| `php artisan data:audit --env=mockup` | 31 temuan, semuanya temuan lama (25 akun aktif milik pegawai nonaktif, 3 pegawai dengan 2 akun, stok dummy minus) [C] |

Inventaris menu (`ddtsys.sys_app_menu`): 225 item daun. Aktif: 103 grid v2, 18 lookup v2, 37 layar v2, 38 layar v1,
**6 tanpa layar**. Nonaktif: 23.

---

## B. Pertanyaan 1 — apakah semua input tersambung ke database?

**Jawaban singkat: layar dokumen bisnis YA (dengan beberapa lubang di bawah); shell v2 (menu akun) TIDAK.**

### B.1 Jalur palsu — input yang tidak tersimpan sama sekali

| # | Di mana | Masalah | Bukti |
|---|---|---|---|
| 1 | Menu akun v2 → **Change password** | Tiga kotak isian tanpa form; tombol Save hanya menutup dialog. Password tidak berubah. | `packages/devlib/resources/views/components/shell/overlays.blade.php:43-51`, `Components/LayerShell.php:83` [C] |
| 2 | Menu akun v2 → **Edit profil** | Save hanya menutup dialog; No. telepon tampil angka contoh `0812-3456-7890`; "Ubah foto" tidak berbuat apa-apa. | `overlays.blade.php:31-41` [C] |
| 3 | Menu akun v2 → **Chat** | Orang dan percakapan = data contoh (`DemoData::chat()`); pesan yang diketik hanya hidup di JS, hilang saat reload. Tabel `sys_chat_*` ada dan kosong. | `resources/views/v2/home.blade.php:4`, `app/Support/V2/DemoData.php:261-297` [C]; `chat.js:124-133` [A] |
| 4 | Menu akun v2 → tema / kerapatan / ukuran huruf / bahasa | Tersimpan per browser (localStorage / cookie), bukan di `sec_user.theme_code` / `lang_code`. Daftar tema = konstanta PHP, bukan `sys_theme`. | `theme-picker.js`, `packages/devlib/src/Theme.php` [A] |

### B.2 Input yang dikirim tetapi dibuang / tertimpa

| # | Layar | Masalah | Bukti |
|---|---|---|---|
| 5 | **Customer Order** | Pilihan **PPN / Non PPN** tersimpan tetapi `totals()` tidak membacanya: Non PPN tetap dihitung PPN 11% (total, piutang, nilai approval). | `app/Models/Sdi/CoOrder.php:182-219` [C] |
| 6 | **Inquiry Revise** (zona beku) | Dimensi P/L/T yang berakhiran nol tampil terpotong (210 → 21, 100 → 1) karena `rtrim(...,'0')` pada bilangan bulat; bila scope DIMENSION dicentang, nilai terpotong itu yang tersimpan. ddt belum punya dokumen Revise, jadi belum ada data rusak. | `resources/views/v2/screens/inquiry-revise.blade.php:32,115-117` [C]; dims di DB bilangan bulat [C] |
| 7 | Inquiry New (zona beku) | Kategori / Detail / Standard Size / nama custom dikirim tapi tidak ada kolomnya; saat dibuka lagi layar menebak dari dimensi. `part_role` dan `flat_manual` juga dibuang. | `app/Support/V2/InquiryPayload.php:36-105` [A] |
| 8 | Inquiry Revise (zona beku) | Scope PROCESS menghapus atribut finishing bawaan induk; scope DIMENSION tidak menghitung ulang `flat_w/h`. | `InquiryDerivedWriter.php:157-187` [A] |
| 9 | ProDev (zona beku) | Susunan tersimpan (mesin, plano, gap, posisi objek) ditulis ke `layout_detail` tetapi tidak dibaca lagi saat dibuka — workbench mulai dari susunan otomatis. Arah serat, upload dieline terpisah, dan plano custom tidak tersimpan. | `pages/production/prodev.blade.php:211-244, 592-683` [A] |
| 10 | Master Card Collection | Kartu manual / foto tidak punya isian bahan, padahal bahan wajib ada supaya kartu bisa Ready / Convert. | `MasterCardService.php:211-223` [A] |
| 11 | Petty Cash | Pemilih mata uang di setiap kotak uang dikirim tapi tidak dibaca (pilih USD = diabaikan). | `field/money.blade.php`, `routes/v2.php:491-530` [A] |
| 12 | Subcon Delivery Note | Catatan (Notes) draft tertimpa alasan batal saat dokumen di-Cancel. | `SubconDelivery.php:104-106` [A] |
| 13 | Maintenance | "Meter at Done" tidak tersimpan saat WO masih Draft; isian Kind / Priority hilang bila langsung tekan Accept. | `MaintenanceService.php:141-143`, `routes/v2_maintenance.php:42-58` [A] |
| 14 | MPS Gantt (v1) | Tombol "Submit ke Master Schedule" hanya memunculkan pesan; saklar Simulasi / Live hanya tampilan. | `pages/production/scheduling-board.blade.php:599-602` [A] |

### B.3 Kolom ada, isian tidak ada

- Maintenance WO / Plan: `vendor_id` (supplier servis) tidak punya isian; `mnt_meter_reading` (pemicu PM berbasis meter)
  tidak ditulis layar mana pun. [A]
- Setting Approval: `flow_config.division_id` dipakai engine, tidak ada isiannya. [A]
- Delivery Order: tab Notes hanya tampil. PQ: catatan per baris tampil tapi tidak bisa diisi. [A]
- Inquiry New: `tax_scheme`, `ppn_percent`, `pph_percent`, diskon dokumen ditulis dari konstanta, bukan isian. [A]

### B.4 Yang bersih

Quotation, CPDEL, Delivery Order, Delivery Notes, Proof of Delivery, PR, PQ, PO, Penerimaan Barang, Approval Center
(CO / PO / PR / PRT / stok), Setting Approval, Officer Pengganti, Holiday Calendar, Material Booking, Material Planning,
Setting E-mail, tiket bantuan, semua Grid Lookup (`DbLookup::save`, satu transaksi) — semua isian sampai ke kolom tabel. [A]
Catatan: yang "bersih" di sini tetap punya butir kecil di bagian C dan D.

---

## C. Pertanyaan 2 — apakah semua report dan grid ambil dari database?

**Jawaban singkat: YA untuk 111 grid dan laporan v2 serta laporan keuangan; TIDAK untuk tiga hal di bawah.**

### C.1 Tampil tetapi bukan dari database

| # | Di mana | Masalah | Bukti |
|---|---|---|---|
| 1 | Menu akun v2 → **Notifikasi** | Tiga notifikasi ditulis mati di HTML ("PO baru menunggu approval", "Stok Bahan A di bawah minimum", "Invoice #2600393 lunas"). Tabel `sys_notifications` ada. | `overlays.blade.php:22-28` [C] |
| 2 | Menu akun v2 → **Chat** | 10 orang fiktif, 4 percakapan, lencana "3 belum dibaca" dari data contoh. | lihat B.1 #3 [C] |
| 3 | ProDev (zona beku) | Bila tidak ada mesin Offset di master: daftar mesin contoh (Komori GL440 102×72, Heidelberg XL 75 74×53); bila ukuran meja kosong: dianggap 102×72 cm. Tiga kode master card UAT digambar dari vektor JS. | `pages/production/prodev.blade.php:489-513, 64-68` [A] |

### C.2 Dari database, tetapi sumbernya hanya arsip lama

- **Laporan Manajemen: Ringkasan Eksekutif, Rincian Penjualan, Rincian Pembelian** membaca **hanya tabel `*_legacy`**
  (`co_order_legacy`, `co_delivery_legacy`, `co_invoice_legacy`, `po_*_legacy`). CO, DO, PO, dan invoice yang diinput
  di ERP baru **tidak pernah muncul** di tiga laporan itu. `app/Services/Report/ManagementReportService.php` [C]
- Laporan Akuntansi & Keuangan: angka GL hidup, tetapi kartu "Sisa Hutang Usaha" dari arsip lama. [A]

### C.3 Angka dihitung dari bilangan yang ditulis di kode (bukan konstanta / master)

| Di mana | Bilangan | Bukti |
|---|---|---|
| **Quotation** (form, grid, laporan Quotation Terbuka, cetakan) | PPN **11%** | `app/Models/Sdi/QuoQuotation.php:86` [A] |
| **Customer Order** | PPN **11%**, PPh 23 **2%** | `CoOrder.php:196,202` [C] — padahal PO sudah membaca `sys_constant` `PPN` / `PPH23_JASA` |
| Master Card → Inquiry | `ppn_percent 11`, `pph_percent 2` | `MasterCardService.php:328-329` [A] |
| Jurnal penerimaan subkon, Kas Kecil, pajak, tutup buku | kode akun mati: 513000, 211900, 114300, 111200, 322000, dst (±30 tempat) | `SpecialReceiptService.php:23-25`, `PettyCashService.php:25`, `TaxEngine.php`, `CloseService.php` [A] |
| Self-Service cuti | jatah 12 hari, H+7, maks 3 hari, 3 izin/bulan, batas lapor sakit 07:30; hari libur master tidak dipakai | `pages/self-service/index.blade.php:299, 596-690` [A] |
| MPS Gantt | tanggal awal papan = `2026-08-28` (literal) | `scheduling-board.blade.php:73` [A] |
| Ambang kecil | stok tidak bergerak 90 hari, reject 5%, rasio lancar 1,5, jatuh tempo 7 / 14 hari, toleransi cocok bank 3 hari, KPI 100% | [A] |

### C.4 Tampilan salah untuk CO direct

MPS Spreadsheet menampilkan customer "GENERAL" dan MPS Kanban menampilkan "—" untuk WO dari CO INSTANT, karena nama
customer dicari lewat quotation, padahal `co_order.customer_id` ada. [A]

### C.5 Layar dengan data dummy kosong (grid jalan, isinya 0 baris)

SOP, Approval Lokasi GPS, Routing Template, Pro-Dev Revise, Pro-Cost Revise / Recalc, Down Payment, Rule Library,
Purchase Return, Support Tickets, RM Return, FG Stock Out / Return / Adjustment, SFG Out / Return / Adjustment. [C]

---

## D. Pertanyaan 3 — apakah semua lookup sudah punya interface input?

**Jawaban singkat: BELUM.** Dari 82 tabel lookup / parameter yang bukan tabel utama grid master v2:
**53 hanya dibaca (tidak ada layar input)**, 2 punya layar tapi tidak terjangkau dari menu, 6 tidak dipakai kode,
3 hanya ditulis engine, 18 bisa diisi user. Selain itu 6 item menu aktif belum punya layar, dan banyak pilihan
dropdown ditulis mati di kode.

### D.1 Sudah punya layar input (tidak perlu tindakan)

Grid Lookup v2 (18): Satuan, Konversi Satuan, Currency, Kurs, Kategori Supplier, Product Category, Office Hours,
Customer Receiving Hours, Delivery Delay Reasons, Katalog Model Mesin, Maintenance Codes, Translation Dictionary,
Menu Setup, Constants, Constant Categories, Demand Forecast, Material Usage Standard, Purchase Lead Time.
Grid Master v2 (20): Customer (+ cabang, kontak), Supplier (+ cabang, kontak), Bahan Baku, Kategori Bahan, Gudang
(+ lokasi), Shift (+ detail), Holiday, Mesin, Proses (+ proses×mesin), Routing Template, Crew, Tipe Scrap, BOP Rule,
Product Market (+ ukuran standar), COA, Posisi, SOP, Pegawai, Account, Group.
Lewat layar v1 dari menu: identitas perusahaan, target rate proses, aturan approval finance, hak akses grup, aturan
data origin, bahasa.

### D.2 Tabel yang dibaca aplikasi tetapi TIDAK punya layar input

[A]; untuk 9 tabel (sales person, payment term, department, tax scheme, sheet rate, coating rate, finishing type, BPJS
rate, part type) saya cari ulang sendiri perintah create / insert di kode: hanya ETL migrasi lama yang menulis.

| Kelompok | Tabel | Dipakai oleh |
|---|---|---|
| **Sales** | `sales_person`, `fin_payment_term`, `cust_category` | PIC Sales di Inquiry / Quotation / CO; syarat bayar di Customer / Supplier / Quotation / CO / AR / AP |
| **Pajak** | `fin_tax_scheme` | tarif PPN / PPh di invoice dan layar Pajak |
| **Organisasi** | `org_department`, `org_division`, `org_level` | PR, PO, Posisi, Shift, Roster, Stock Out, Requisition, SOP |
| **Pro-Cost (zona beku)** | `mat_coating_rate`, `mat_film_rate`, `mat_flute_allowance`, `mat_grammage`, `mat_sheet_rate`, `mat_sheet_discount_tier`, `mat_sheet_supplier`, `mat_join_type`, `mat_packing_type`, `prod_part_type` | semua tarif HPP |
| **Inquiry / ProDev (zona beku)** | `prod_type`, `prod_type_param`, `ref_finishing_type`, `mat_plano_size` | tipe produk + dieline, finishing, pilihan plano |
| **Payroll** | `pyr_bpjs_rate`, `pyr_denda_rule`, `pyr_overtime_rate`, `pyr_pph21_ter_bracket`, `pyr_status_rule` | seluruh hitungan gaji |
| **Finance** | `fin_asset_category`, `fin_cost_center`, `fin_profit_center`, `fin_partner_account` | aset, jurnal, COA, bahan baku |
| **Periode** | `fin_period` (tahun baru tidak bisa dibuat dari layar), `inv_period` (tidak ada yang bisa membuat / menutup, padahal tutup buku GL mensyaratkannya) | Period Closing |
| **HR / produksi** | `emp_work_center`, `emp_employee_work_center_skill`, `prod_process_precedence`, `sys_geofence_location`, `emp_leave_balance` | Shift Roster, routing, absen GPS, jatah cuti |
| **Recruitment** | `app_pipeline_stage_config`, `rcr_approval_matrix`, `rcr_approver_assignment`, `rcr_offer_approval_matrix`, `rcr_track` | tahap pipeline, approval requisition / offer |
| **Sistem** | `sec_module`, `sec_recovery_question`, `sys_setting`, `sys_theme`, `sys_login_hero`, `sys_quotes`, `sys_user_menu`, `sys_app_menu_color` | gerbang modul, login, tema |
| **Wilayah** | `geo_country/region/city/district/village` | pemilih alamat (data referensi 245 ribu baris) |

### D.3 Punya layar, tidak terjangkau menu

- **Master Product**: form lengkap v1 `/master/produk` tidak dibuka menu mana pun; layar v2 hanya bisa mengubah nama.
  Product baru lahir dari Quotation WIN, Master Card Collection, atau Pro-Cost Instant. Part (`prod_part`) tidak punya
  editor. Ini sesuai pedoman alur, tetapi artinya CO direct untuk product yang belum pernah ada tidak bisa dibuat. [A]
- `prd_mts_forecast` (layar lama `/production/mts-forecast`) — sudah digantikan Demand Forecast. [A]

### D.4 Item menu aktif tanpa layar sama sekali [C]

Inventory › Print Pallet Label (QR), Print Item Label (QR), Label Print History, Scan Transaction (Mobile Camera),
Track via QR/Barcode; HR › Lembur.

### D.5 Pilihan dropdown bisnis yang ditulis mati di kode (tidak ada tabel, tidak ada layar)

| Layar | Daftar |
|---|---|
| Inquiry New (zona beku) | Part Type (padahal tabel `prod_part_type` ada), Source (Email / WA / ...), jenis + status tooling, jenis biaya luar, perlakuan pajak, peran part |
| ProDev (zona beku) | 8 satuan BOM, 13 peran bahan, 9 nama tinta |
| Quotation | jenis + hasil follow-up, pilihan skema pajak "11%" |
| Customer Order | kategori biaya lain (Trucking / Expedition / Lainnya), cara kirim |
| CPDEL | saluran kesepakatan (WhatsApp / Phone / Email / Meeting), keputusan eskalasi |
| Proof of Delivery | sebab janji meleset + departemen penanggung jawab — padahal `co_delay_reason` sudah ada dan punya layar |
| Delivery Delay Reasons | daftar departemen (8) di `DeliveryDelayReasonsLookup::DEPARTMENTS`, bukan `org_department` [C] |
| Material Usage Standard | jenis bahan INK / VARNISH / COATING / FILM / GLUE [C] |
| Maintenance | prioritas + SLA, jenis request, jenis tugas PM, jenis meter |
| Setting Approval | kode dokumen, label cetak (Diperiksa / Menyetujui / Mengetahui) |
| Purchase Order | Procurement Type, PO Type |
| Self-Service | jenis cuti, kategori izin, hubungan keluarga, jenis dokumen |
| Portal kandidat | gender, kendaraan, status nikah, jenis SIM |
| Roster kas payroll | pecahan uang |
| ACC Digital | jenis ACC, saluran |

### D.6 Diketik bebas padahal masternya ada

| Isian | Layar | Master yang ada |
|---|---|---|
| Satuan | baris PR, PQ, Quotation, Surat Jalan Subkon, item bebas PO, Preventive Plan | `uom_unit` |
| Syarat bayar | PQ, PO | `fin_payment_term` |
| Proses | Surat Jalan Subkon, "proses berikutnya" penerimaan subkon | `prod_process` |
| Lokasi rak | Penerimaan Barang | `wh_loc` |
| Kurs | PQ (bawaan 1) | `fin_exchange_rate` |
| Ship To | PO | `wh_warehouse` |
| Penerima uang | Kas Kecil | `emp_employee` |
| Kontak customer | CPDEL, POD, ACC Digital | `cust_customer_contact` |
| Drawing by | Proof Digital | `emp_employee` |
| Departemen peminta | Material Planning → Create PR (dicari dengan nama "PPIC") | `org_department` |

---

## E. Pertanyaan 4 + 5 — dokumen yang dipindah ke `C:\Users\iwanp\tmerp_unused\`

Cara menilai: setiap file di `docs/` (627 file, di luar `legacy_scriptcase`) dihitung siapa yang masih merujuknya
(kode, `CLAUDE.md`, dokumen lain, memori), lalu dipindah **hanya** bila ada bukti jelas: dinyatakan usang oleh dokumen
induk, arah yang dibatalkan user, salinan persis, cadangan / log / eksperimen, atau tidak dibaca kode.
Semua tercatat di `MANIFEST.md` arsip.

**Dipindah (39 file):**

| Kelompok | File | Alasan |
|---|---|---|
| Onboarding lama | `START_HERE_onboarding.md`, `tasks.md` (root) | dinyatakan usang di `CLAUDE.md` |
| Antrian lama | `docs/queue.md` | antrian 2026-08; isinya sudah jadi aturan tetap / digantikan backlog |
| Opening App | `md_for_systems/openingapp_tier1-4.md`, `openingapp_instruksi_layout.md` | "usang berat" menurut `index_master.md` dan catatan audit |
| Filament | `admindashboardstandard.md`, `flows/flow_admin_dashboard_filament.md`, 8 × `screens/screen_admin_*.md` | user 2026-09-30: Filament bukan arah ke depan |
| Layar LEGACY | `screen_inquiry_imposition.md`, `_list.md`, `screen_recruitment_portal.md`, `mockups/mockup_inquiry_imposition_grid_form.html` | layar lama yang sudah digantikan |
| Salinan | `md_for_systems/payroll_summary_20260916.yaml` | sama persis dengan `payroll_summary.yaml` |
| Cadangan | `docs/backups/` (2 file) | cadangan Agustus |
| Eksperimen | `docs/reference/livewire-screens-experiment/` (6 file) | eksperimen sebelum DevLib |
| Log | `docs/ddl/import_*.log` (5 file) | log impor 2026-09-11 |
| Data contoh | `resources/data/v2/costing.json`, `sales_masters.json` | tidak dibaca kode mana pun |
| Sampah | `toArray())` (root) | file salah ketik, muncul lagi |

**TIDAK dipindah — menunggu keputusan user (saya tidak yakin):**

| File / folder | Kenapa ragu |
|---|---|
| `docs/md_prompt_rel20260924/` (28 file: prompt user + contoh aplikasi demo `.zip`) | folder buatan user; isinya bahan awal Inquiry New v2 yang sekarang sudah Approved |
| `docs/legacy_scriptcase/` (58.679 file, 3 GB, tidak di-git) | sumber ScriptCase V1 — masih jadi acuan alur lama |
| `docs/ddl/tm2*.sql` (dump 2026-08-28 / 09-09) | sudah basi dibanding skema sekarang (`tm2hrm/hrr/pyr.sql` kosong); pengganti hidup = Data Dictionary. Dump ini file user |
| `docs/database/db_*.md` (12 file, 2026-09-05) | sebagian salah sekarang (`db_pyr.md`: "database kosong"); pengganti hidup = Data Dictionary |
| `docs/md_output/screens/` (118 file tersisa) | dokumen layar v1 per 2026-09-05; masih acuan alur untuk layar yang belum pindah ke v2. `screen_prodev_print.md` mendokumentasikan file yang sudah tidak ada |
| `*_state.yaml` vs `*_state_v2.yaml` (finance, inventory, procurement) | dua versi berkepala sama; tidak jelas mana yang berlaku |
| `md_for_systems/attendancepayroll_tier1-4.md` vs `payroll_timeattendance/attendancepayroll_tier1-4.md` | nama sama, isi beda (v1.0 vs v4.0) |
| `kebijakanbesar.md`, `ringkasanstatus.md`, `catatanauditmenyeluruh.md`, `checklistmockuperp.md` | status per 2026-09-02 / 09-16; masih ditautkan dari `BACKLOG.md` |
| `mockups/inquiry_nota_{new,recalc,revise}.html`, `nota_inquiry_mockup_bw.html` | mockup nota 2026-08; format cetak Inquiry sekarang sudah A lewat generator baru |
| `mockups/data tables/` (4 file pustaka DataTables) | tidak dirujuk; mungkin sisa percobaan MPS Spreadsheet |
| `docs/mockups/print_doc/` | **tidak disentuh** — sesi lain sedang bekerja di sana (commit `6f389205`) |
| `00_setup_databases.sql`, dua dokumen Blueprint | dokumen awal; tidak dirujuk kode |

**Temuan sampingan:** `App\Support\V2\DemoData` — hanya `chat()` yang masih dipakai, sisanya kode mati di tmerp.
Ada 3 worktree lama di `.claude/worktrees/` padahal aturannya tanpa worktree.
Jalur dokumen di `CLAUDE.md` §6 masih menulis `docs/<nama>.md` padahal filenya di `docs/md_for_systems/` — sudah
saya beri catatan letak, isi §6 tidak saya ubah.

---

## F. Usulan urutan perbaikan (menunggu keputusan user)

1. **Shell v2**: Change password + Edit profil + Notifikasi + Chat — sambungkan ke database atau sembunyikan sampai siap.
2. **Customer Order**: PPN mengikuti pilihan PPN / Non PPN; tarif PPN + PPh 23 dari `sys_constant` (sama dengan PO).
   Quotation: tarif PPN dari konstanta yang sama.
3. **Inquiry Revise** (zona beku, perlu izin): perbaiki pemotongan nol pada dimensi — satu baris.
4. **Laporan Manajemen**: gabungkan data ERP baru dengan arsip lama.
5. **Grid Lookup untuk tabel tanpa layar input**, mulai dari yang paling sering dipakai: Sales Person, Payment Term,
   Department / Division / Level, Tax Scheme, Customer Category; lalu Payroll (5 tabel), Finance (cost center, profit
   center, kategori aset, periode), Recruitment (5 tabel). Tabel tarif Pro-Cost = zona beku, perlu izin.
6. **Isian bebas → lookup**: satuan, syarat bayar, proses, lokasi rak, kontak customer.
7. Daftar pilihan yang ditulis mati (D.5) → tabel + Grid Lookup, mulai dari yang mempengaruhi laporan (sebab janji
   meleset di POD memakai `co_delay_reason`).
