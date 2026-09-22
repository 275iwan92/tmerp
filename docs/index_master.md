---
name: index_master
description: Index master seluruh dokumen /docs — layar, flow modul, database/tabel, standar UI/UX, dan dokumen Tier lama
---

# Index Master Dokumentasi — TELASIH MAKMUR ERP V2

> Dibuat 2026-09-05 (permintaan user: dokumentasi per-layar untuk dikoreksi, flow per-modul, database/tabel, dan
> standarisasi UI/UX+komponen). **Konvensi penamaan**: `screen_*` (docs/screens/), `flow_*` (docs/flows/), `db_*`
> (docs/database/), `uistandard_*` (docs/uistandard/) — semua underscore, tanpa hyphen, supaya mudah di-grep.
>
> **Cara pakai**: dokumen per-layar (`screen_*`) ditulis dari HASIL BACA KODE SUNGGUHAN, bukan spek yang didikte
> duluan — kalau ada yang menyimpang dari aplikasi sungguhan, koreksi langsung isi filenya. Tandai "TBD - perlu
> verifikasi" yang perlu dicek ulang tetap dibiarkan apa adanya sampai dikonfirmasi.

---

## 1. LAYAR (docs/screens/, 127 file)

### 1.1 Auth & Security (6)
| File | Deskripsi |
|---|---|
| `screen_auth_forgot_password.md` | Lupa password berbasis 4 pertanyaan keamanan (tanpa email/link), reset password ke default_password. |
| `screen_auth_login.md` | Halaman login utama (username/email + password), termasuk panel akun contoh UAT. |
| `screen_auth_password_change.md` | Halaman paksa ganti password saat user masih memakai password default. |
| `screen_auth_register.md` | Form pendaftaran akun baru (customer/supplier/internal) dengan verifikasi email & WhatsApp OTP, akun menunggu approval admin. |
| `screen_auth_security_setup.md` | Setup 4 pertanyaan keamanan akun (tgl lahir, nama ibu, HP, pertanyaan+jawaban) dipaksa sekali sebelum akun bisa dipakai normal. |
| `screen_security_index.md` | Panel admin gabungan Pengguna, Grup, Hak Akses Grup, dan Migrasi Data Legacy. |

### 1.2 System & Setup (5)
| File | Deskripsi |
|---|---|
| `screen_main_menu.md` | Shell utama aplikasi (app shell) — mega menu modul, sidebar, tab kerja, notifikasi, chat, tema, dan menu profil. |
| `screen_setup_configuration.md` | Layar induk Configuration — Identitas Aplikasi (nama/tagline/font/logo) plus 2 sub-komponen anak Bahasa & Semua Konstanta. |
| `screen_setup_configuration_constants.md` | Sub-komponen spreadsheet-grid untuk mengelola semua baris sys_constant. |
| `screen_setup_configuration_languages.md` | Sub-komponen daftar bahasa aktif (sys_languages) + grid terjemahan teks UI (sys_translations). |
| `screen_setup_support_tickets.md` | Grid admin untuk mengelola tiket Help & Support. |

### 1.3 Notifikasi, Help, Self-Service (3)
| File | Deskripsi |
|---|---|
| `screen_notification_index.md` | Notification Center pribadi — filter tipe/status baca, tandai terbaca dengan redirect. |
| `screen_help_index.md` | Help Center gabungan 3 tab — tiket bantuan, panduan UI/UX, penjelasan alur tiap modul. |
| `screen_self_service_index.md` | Portal Karyawan self-service — 9 tab (profil, keluarga, dokumen, cuti, izin keluar, tukar shift, dst). |

### 1.4 Careers Portal — Publik (6)
| File | Deskripsi |
|---|---|
| `screen_careers_landing.md` | Halaman utama Portal Karir publik, menampilkan daftar lowongan terbuka. |
| `screen_careers_login.md` | Halaman login kandidat Portal Karir menggunakan akun Firestore. |
| `screen_careers_register.md` | Registrasi akun kandidat baru (No.KTP+Nama+Email+No.HP+password) dengan verifikasi email. |
| `screen_careers_vacancy_detail.md` | Halaman detail 1 lowongan publik, titik masuk ke alur apply atau register. |
| `screen_careers_apply.md` | Form "Data Calon Karyawan" 1-halaman (termasuk sub-section partial `_apply-form-steps.blade.php`). |
| `screen_careers_dashboard.md` | Dashboard "Lamaran Saya" — riwayat & status lamaran kandidat yang sudah login. |

### 1.5 Recruitment — Internal (12)
| File | Deskripsi |
|---|---|
| `screen_recruitment_requisition_list.md` | Grid daftar Permohonan Tenaga Kerja (Requisition). |
| `screen_recruitment_requisition_entry.md` | Detail Requisition — header + approval berjenjang. |
| `screen_recruitment_vacancy.md` | Grid Posting Lowongan — publish dari requisition APPROVED. |
| `screen_recruitment_pipeline_board.md` | Kanban board kandidat per tahap seleksi, drag-drop divalidasi server. |
| `screen_recruitment_interview_schedule.md` | Kalender jadwal interview (Day/Month/3-Month/Year), drag-drop kandidat. |
| `screen_recruitment_assessment_result.md` | Input hasil Psikotes/Interview per kandidat, guard sebelum naik tahap. |
| `screen_recruitment_offer_list.md` | Grid worklist Offering. |
| `screen_recruitment_offer_entry.md` | Detail penawaran gaji/kontrak, approval berjenjang, tracking respons. |
| `screen_recruitment_hire_provision.md` | Provisioning kandidat diterima jadi karyawan resmi (emp_employee+login). |
| `screen_recruitment_talent_pool.md` | Pencarian kandidat existing, undang ke lowongan baru tanpa isi ulang data. |
| `screen_recruitment_sync_log.md` | Grid read-only rekap sync worker Portal -> tm2hrr. |
| `screen_recruitment_portal.md` | Simulasi lama (KTP-only) — **TIDAK AKTIF**, digantikan Portal Profesional. |

### 1.6 Inquiry Product (9)
| File | Deskripsi |
|---|---|
| `screen_inqprod_grid.md` | Landing page Inquiry — pilih tipe dokumen (NEW/RECALC/REVISE), worklist. |
| `screen_inqprod_new.md` | Entry Inquiry Product BARU — header, qty tier, part & dieline, bahan, tooling. |
| `screen_inqprod_revise.md` | REVISI — copy-record + versi baru, scope selector Material/Dimensi/Artwork/Warna/Proses. |
| `screen_inqprod_recalc.md` | REKALKULASI — reuse spec/dieline/material/proses, refresh harga bahan & qty. |
| `screen_inquiry_imposition_list.md` | Worklist Imposition Engine. |
| `screen_inquiry_imposition.md` | Imposition Engine per-inquiry — layout produksi (plano/UP/orientasi). |
| `screen_procost_grid.md` | Worklist Product Costing. |
| `screen_procost_form.md` | Costing (HPP) per-inquiry — CostingEngine, coating, ladder harga per qty-tier. |
| `printout_inqprod_nota.md` | Cetak Nota Inquiry — harga per jenjang qty + spek teknis + dieline. |

### 1.7 Sales Order & Delivery (12)
| File | Deskripsi |
|---|---|
| `screen_quotation_entry.md` | Entry/edit Sales Quotation — konsolidasi Inquiry berharga. |
| `screen_quotation_list.md` | Grid worklist Quotation. |
| `screen_quotation_print.md` | Cetak Surat Penawaran Harga (setelah PUBLISHED). |
| `screen_co_list.md` | Grid worklist Customer Order (lahir dari Quotation PUBLISHED). |
| `screen_co_entry.md` | Entry/edit CO — snapshot dari Quotation, Confirm memicu CPDEL. |
| `screen_co_delivery_plan.md` | Customer Plan Delivery (CPDEL) — split/gabung/geser jadwal kirim. |
| `screen_co_print.md` | Cetak Nota Customer Order. |
| `screen_dispatch_do_list.md` | Grid worklist Delivery Order (VIEW `v_do_list`). |
| `screen_dispatch_do_entry.md` | Entry/detail DO — tarik outstanding dari CO, Confirm terbitkan No. DO. |
| `screen_dispatch_do_print.md` | Cetak DO internal (Sisi A, harga tampil penuh). |
| `screen_dispatch_do_print_surat_jalan.md` | Cetak Surat Jalan eksternal (Sisi B, tanpa kolom harga). |
| `screen_sales_win_rate.md` | Laporan Win-Rate Sales — CONVERTED/LOST per PIC Sales. |

### 1.8 Finance & Accounting (3)
| File | Deskripsi |
|---|---|
| `screen_finance_coa.md` | Master Chart of Accounts — grid tree berjenjang. |
| `screen_finance_cash_bank.md` | Jurnal Kas & Bank (BKM/BKK/BBM/BBK), Draft->Post. |
| `screen_finance_dp_invoice.md` | Tagihan Uang Muka customer — terbitkan, catat bayar, alokasi ke invoice final. |

### 1.9 HRM (5)
| File | Deskripsi |
|---|---|
| `screen_hrm_position.md` | Master Posisi/Jabatan — field "Melapor Ke" sumber hierarki. |
| `screen_hrm_org_chart.md` | Visualisasi pohon Struktur Organisasi (read-only). |
| `screen_hrm_shift_roster.md` | Papan alokasi shift — mode PPIC (drag-drop) & Grid HR (klik-siklus). |
| `screen_hrm_leave_requests.md` | Approve/reject Cuti/Sakit/Izin/Cuti Khusus dari self-service. |
| `screen_hrm_correction_requests.md` | Approve/reject koreksi data karyawan dari self-service. |

### 1.10 Inventory (8)
| File | Deskripsi |
|---|---|
| `screen_inventory_stock_in.md` | Stock In manual (STOCK/BB) RM/SFG/FG, DRAFT->POSTED tanpa approval. |
| `screen_inventory_stock_out.md` | Stock Out manual RM & SFG saja — FG hanya lewat DO. |
| `screen_inventory_stock_return.md` | Retur dari produksi (RM & SFG), wajib referensi Stock Out POSTED. |
| `screen_inventory_stock_adjustment.md` | Stock Adjustment/Opname, approval berjenjang berbasis nilai absolut selisih. |
| `screen_inventory_stock_adjustment_approval.md` | Antrian approval AD/OP. |
| `screen_inventory_print_master_stock.md` | Laporan Master Stok (mutasi per item per rentang tanggal). |
| `screen_inventory_print_slow_moving.md` | Laporan Slow Moving Item (ambang 3/6/9/12 bulan). |
| `screen_inventory_print_stock_card.md` | Kartu Stok — ledger kronologis per item+lokasi. |

### 1.11 Master Data (16)
| File | Deskripsi |
|---|---|
| `screen_master_customer.md` | Master Customer — grid+form bersatu. |
| `screen_master_supplier.md` | Master Supplier — grid/form/detail 3-panel. |
| `screen_master_employee.md` | Master Employee — 6-tab profil, auto-provision login. |
| `screen_master_produk.md` | Master Produk (prod_product) — BUKAN Master Card. |
| `screen_master_mat_category.md` | Kategori Bahan Baku — hirarki 5 level self-referencing. |
| `screen_master_raw_material_index.md` | Grid Master Item Bahan Baku (mat_raw). |
| `screen_master_raw_material_entry.md` | Form Master Item Bahan Baku — 5 tab, confirm workflow. |
| `screen_master_machine.md` | Master Mesin/lini produksi — assignment presisi Offset Printing. |
| `screen_master_shift.md` | Master Shift (emp_shift + emp_shift_detail). |
| `screen_master_warehouse_index.md` | Grid Master Gudang (struktur, bukan stok). |
| `screen_master_warehouse_entry.md` | Form Master Gudang + kelola Lokasi/Bin. |
| `screen_master_warehouse_print_labels.md` | Cetak Label Rak per lokasi/bin. |
| `screen_master_currency.md` | Master Mata Uang — spreadsheet-editable. |
| `screen_master_exchange_rate.md` | Master Kurs — versioned per mata uang. |
| `screen_master_uom.md` | Master Satuan + panel Konversi antar-satuan. |
| `screen_master_holiday.md` | Master Hari Libur — spreadsheet-editable. |

### 1.12 Production Scheduling & Product Development (15)
| File | Deskripsi |
|---|---|
| `screen_prodev_grid.md` | Grid worklist Pro-Dev (prod_part), asal Inquiry vs Manual. |
| `screen_prodev_form.md` | Workbench ProDev — Imposition Engine (Tingkat 1/2, Best Sheet, Combiner). |
| `screen_prodev_print.md` | Cetak Master Card. |
| `screen_production_master_process.md` | Master Proses produksi (Cetak/Laminasi/Potong/dst). |
| `screen_production_master_crew.md` | Master Crew (regu/operator). |
| `screen_production_scrap_type.md` | Master Tipe Scrap (REJECT/WASTE). |
| `screen_production_process_rate.md` | Target rate default per Produk+Part+Proses+Mesin/Pegawai. |
| `screen_production_mts_forecast.md` | Entry forecast Make-To-Stock, banding vs actual. |
| `screen_production_mps_spreadsheet.md` | MPS ala Excel — DataTables+KeyTable, edit sel langsung. |
| `screen_production_wo_list.md` | Worklist Work Order — entry point generate dari Plan Delivery. |
| `screen_production_wo_entry.md` | WO Detail — Master Card, Bill of Process, QC Gate, Breakdown/Subcontract, Output F15. |
| `screen_production_wo_print.md` | Cetak Work Order (Bill of Process + Bill of Material). |
| `screen_production_scheduling_board.md` | MPS Gantt Chart — drag/resize/reassign, "Sisip Hot Job". |
| `screen_production_monitoring_dashboard.md` | MPS Kanban Board — kolom mesin/proses, drag-drop PdrEngine. |
| `screen_production_rule_library.md` | Constraint Rule Library (BATCHING/SEQUENCING). |

### 1.13 Procurement (19)
| File | Deskripsi |
|---|---|
| `screen_pur_pr_index.md` | Grid worklist PR — identitas Departemen peminta. |
| `screen_pur_pr_entry.md` | Form entry PR — approve/reject/hold digabung. |
| `screen_pur_pr_approval.md` | Antrian approval PR. |
| `screen_pur_pr_print.md` | Cetak PR (internal, gate APPROVED ke atas). |
| `screen_pur_pq_index.md` | Grid PQ, picker supplier + Confirm dari grid. |
| `screen_pur_pq_entry.md` | Form PQ — banding antar-supplier, sumber pembuatan PO. |
| `screen_pur_pq_print.md` | Cetak rekap internal PQ. |
| `screen_pur_po_index.md` | Grid PO, picker supplier untuk buat baru. |
| `screen_pur_po_entry.md` | Form PO — tarik alokasi PR, hitung pajak, approve/kirim-ke-supplier. |
| `screen_pur_po_approval.md` | Antrian approval PO. |
| `screen_pur_po_print.md` | Cetak PO resmi ke supplier + tanda tangan 3 pihak. |
| `screen_pur_prt_index.md` | Grid PRT — picker 2 sumber (reject QC / stok gudang). |
| `screen_pur_prt_entry.md` | Form PRT — retur ke supplier, approval Manajer Operasional. |
| `screen_pur_prt_approval.md` | Antrian approval PRT. |
| `screen_pur_pv_index.md` | Grid PV (Penerimaan Barang) — picker 2 langkah. |
| `screen_pur_pv_entry.md` | Form PV — dual UOM, QC kualitas & quantity, POST ke stok. |
| `screen_pur_report_outstanding_pr.md` | Laporan PR APPROVED belum sepenuhnya jadi PO. |
| `screen_pur_report_outstanding_po.md` | Laporan PO terkirim belum sepenuhnya diterima. |
| `screen_pur_report_outstanding_pv.md` | Laporan PV POSTED belum diserahkan ke departemen peminta. |

### 1.14 Admin Dashboard — Filament (8)
| File | Deskripsi |
|---|---|
| `screen_admin_hrm_cluster.md` | Cluster navigasi Filament untuk modul HRM. |
| `screen_admin_hrm_empemployee_resource.md` | Resource Filament Master Employee. |
| `screen_admin_hrm_empemployee_manage_page.md` | Halaman index (list+create+edit) Master Employee. |
| `screen_admin_hrm_orgposition_resource.md` | Resource Filament Master Posisi. |
| `screen_admin_hrm_orgposition_manage_page.md` | Halaman index Master Posisi. |
| `screen_admin_hrm_pipelineboard_page.md` | Page pembungkus Pipeline Board (reuse Livewire lama). |
| `screen_admin_auth_login.md` | Login panel Filament /admin (auth bridge login/pswd). |
| `screen_admin_auth_editprofile.md` | Edit Profile Filament + preferensi Tampilan Dashboard. |

---

## 2. FLOW MODUL (docs/flows/, 12 file)

| File | Deskripsi |
|---|---|
| `flow_system_setup.md` | Registrasi & login, keamanan akun, administrasi Security/Configuration, Help Center, notifikasi, self-service. |
| `flow_recruitment_portal_public.md` | Kandidat publik: landing sampai sync data lamaran ke tm2hrr internal. |
| `flow_recruitment_internal.md` | Rekrutmen internal: Requisition sampai Provisioning ke ERP. |
| `flow_inquiry_product.md` | Hub sampai HPP published & Nota, 3 doc-type, titik masuk ProDev/Costing/Quotation. |
| `flow_sales_order_delivery.md` | Inquiry berharga sampai Surat Jalan diterima customer. |
| `flow_finance_accounting.md` | Setup COA, jurnal Kas & Bank, tagihan uang muka customer. |
| `flow_hrm.md` | Struktur Organisasi, Shift Roster, approval Cuti/Koreksi Data. |
| `flow_inventory.md` | Stock In dari Purchase Receive sampai Laporan (Master Stock/Slow Moving). |
| `flow_master_data.md` | Kelompok master & konsumsi lintas modul (bukan alur linear). |
| `flow_production_scheduling.md` | ProDev (imposition) -> Master Card -> WO auto-generate -> Scheduling -> Monitoring. |
| `flow_procurement.md` | PR -> PQ -> PO -> PRT -> PV, approval merged-into-entry, Laporan Outstanding. |
| `flow_admin_dashboard_filament.md` | Migrasi bertahap shell lama -> Filament /admin, auth bridge, pola Cluster->Resource->Page. |

---

## 3. DATABASE & TABEL (docs/database/, 12 file — 1 per koneksi tm2*)

| File | Deskripsi |
|---|---|
| `db_mst.md` | Master netral lintas-modul — customer, supplier, employee/HR, geo, produk & part baku, material, organisasi, satuan, gudang. |
| `db_sdi.md` | Sales-domain — Inquiry (iqp_*), Costing HPP, Quotation, Customer Order, delivery plan. |
| `db_sys.md` | Sistem — auth/permission (sec_*), menu dinamis, notifikasi, chat, tema, constant. Koneksi DEFAULT Eloquent. |
| `db_pur.md` | Procurement — PR/PQ/PO/PRT/PV. |
| `db_inv.md` | Inventory — stok RM/SFG/FG, kartu stok, layer FIFO/LIFO, periode. |
| `db_prd.md` | Production — Work Order, operasi/routing, penjadwalan, breakdown, forecast MTS. |
| `db_acc.md` | Finance & Accounting — COA, jurnal, invoice AR/AP, DP allocation, cost/profit center. |
| `db_hrr.md` | Recruitment — 18+ tabel real; **dump lokal STALE**, sumber kebenaran ada di migration. |
| `db_hrm.md` | **Database fisik ada TAPI TIDAK DIPAKAI** — semua fitur HRM sebenarnya di tm2mst. |
| `db_pyr.md` | Payroll — database kosong, modul belum dibangun sama sekali. |
| `db_old.md` | Staging data lama ScriptCase V1 untuk migrasi — DDL tidak tersedia lokal. |
| `db_crp.md` | Portal Kandidat Plan B (MariaDB, host terpisah) — 3 tabel staging submission. |

---

## 4. UI/UX STANDARISASI & KATALOG KOMPONEN (docs/uistandard/, 6 file)

| File | Deskripsi |
|---|---|
| `uistandard_component_catalog_index.md` | **Mulai dari sini** — indeks konsolidasi seluruh komponen + cross-ref ke dokumen standar existing. |
| `uistandard_component_form.md` | Komponen `components/form/*` (input, select, textarea, checkbox, toggle, badge, date-picker). |
| `uistandard_component_grid.md` | Komponen `components/grid/*` (stat box, identity cell, action icon, toolbar, modal kolom/sort, pagination, spreadsheet-table). |
| `uistandard_component_print.md` | Komponen `components/print/*` (dokumen cetak "Nota"). |
| `uistandard_component_gantt_kanban.md` | Komponen `components/gantt/*`. Kanban belum punya komponen reusable. |
| `uistandard_component_other.md` | Komponen `components/{alert,auth,inquiry,org-chart,prodev}/*`. |

**Dokumen standar konsep (existing, sudah ada sebelum sesi ini — TIDAK diduplikasi, cuma di-cross-reference)**:
`griduistandard.md`, `printnotastandard.md`, `ganttboardstandard.md`, `kanbanboardstandard.md`,
`spreadsheetgridstandard.md`, `themestandard.md`, `admindashboardstandard.md`, `configurationstandard.md`,
`sidebarmenustandard.md`, `FORM_UI_STANDARD.md`, `uistandardindex.md` (peta lengkap kapan pakai standar yang mana).

---

## 5. DOKUMEN TIER / SPEC LAMA (docs/*.md, existing — referensi per modul)

Daftar filename saja (isi TIDAK dibaca ulang di sesi ini — dokumen ini sudah ada sebelum tugas dokumentasi
2026-09-05, cek langsung filenya kalau perlu detail):

**Arsitektur & Global**: `globalarchitecture_tier0.md`, `globalstarthere.md`, `Blueprint_Arsitektur_dan_Enterprise_Resource_Planning__ERP__Industri_Offset_Printing_dan_Packaging.md`, `prefixlist.md`, `security_brief.md`, `security_defaultpassword_spec.md`, `reportstandard.md`, `topmanagementreport_tier1.md`

**Inquiry/Sales**: `SALES_QUO_ORDER, CPDEL.md`, `sales_imposition_enhancement_backlog.md`, `customer_spec.md`, `customer_fix_nav_address.md`

**Procurement**: `procurement_tier1.md`..`procurement_tier4.md`, `purchasereceive_delta_multipo.md`

**Inventory**: `inventory_tier1.md`..`inventory_tier4.md`, `itemmaster_delta.md`, `matcategory_delta.md`

**Production**: `production_scheduling_tier1.md`..`tier4.md`, `production_scheduling_wo_generation_delta.md`, `productionprocess_lampiran_a.md`

**Finance**: `financeaccounting_tier1.md`, `tier2.md`, `tier4.md`, `financeaccounting_delta_partner_category_coa_v2.md`, `financeaccounting_delta_period_close.md`, `downpayment_tier1-4.md`

**HR/Attendance/Payroll**: `masteremployee_tier1.md`, `tier2.md`, `tier4.md`, `attendancepayroll_tier1.md`..`tier4.md`, `TIER_1_ATTENDANCE.md`, `payroll_encryption_decision.md`, `telma_onboarding.md`, `selfservice_panduan.md`

**Recruitment**: `recruitment_tier1.md`..`tier4.md`, `recruitment_delta_plan_ab_local.md`, `recruitmentportal_web_tier1.md`

**Master Product**: `masterproduct_tier1.md`, `tier2.md`, `tier4.md`, `geomaster_ringkasan.md`

**Dispatch**: `dispatch_tier1.md`..`tier4.md`

**Flow Engine**: `flowengine_tier1.md`..`tier4.md`

**Opening App (BANYAK SUDAH USANG, cek dulu ke DB sebelum percaya)**: `openingapp_tier1.md`..`tier4.md`, `openingapp_instruksi_layout.md`

**Meta/Tracking**: `BACKLOG.md` (indeks + lintas-modul) dan 10 pecahannya `backlog01_uiux_component.md` .. `backlog10_mobile_app.md` (kerjaan tertunda per domain), `LOG_JOB_DONE.md` (histori selesai), `checklistmockuperp.md`, `queue.md`, `qc_feedback.md`, `attachments_index.md`

---

## Catatan Penutup

- Total dokumen dari sesi 2026-09-05: **127 screen + 12 flow + 12 database + 6 uistandard = 157 file baru**, plus 5 file tambal-gap dibuat manual (Claude, bukan sub-agent) setelah beberapa sesi sub-agent kena rate-limit: `screen_auth_security_setup.md`, `screen_production_wo_entry.md`, `flow_master_data.md`, `flow_production_scheduling.md`, `flow_procurement.md`, `screen_pur_pr_approval.md`, `screen_pur_pr_index.md`, `screen_pur_pr_print.md`, `db_hrm.md`, `db_hrr.md`, `db_pyr.md`, `db_old.md`, `db_crp.md`.
- **Temuan penting yang perlu diperhatikan user**: (1) `co/list.blade.php::confirmOrder()` kemungkinan bypass auto-generate CPDEL yang ada di `co/entry.blade.php::confirmCo()` — potensi inkonsistensi, lihat `screen_co_list.md`. (2) Dump DDL `tm2hrr.sql` STALE (0 tabel) padahal modul Recruitment live dgn 18+ tabel — lihat `db_hrr.md`, disarankan refresh dump. (3) `tm2hrm` database fisik ada tapi 0 model memakainya — semua fitur HRM jalan di tm2mst.
- Semua file mengikuti prinsip "tulis apa yang dilihat di kode, tandai TBD kalau ragu" — BUKAN spek yang didikte duluan. Silakan koreksi langsung kalau ada yang menyimpang dari aplikasi sungguhan.
