# Item menu tm2sys yang belum v2

Dibuat 2026-10-07 dari `tm2sys.sys_app_menu` (item aktif, bukan grup) dibandingkan dengan `ddtsys` (mockup). Syarat sebelum kode v1 bisa diarsipkan: semua item di bawah dipindah ke v2 atau diputuskan dibiarkan. Item dengan target `/v2/...` (Flow, Guide) sudah v2 dan tidak dihitung.

## A. Sudah v2 di ddtsys, tinggal ubah target di tm2sys (target lama berkas ScriptCase)

Cukup satu migration saat rilis: set `target` ke nilai ddtsys.

| Kode | Label | Grup | Target tm2 | Target ddt |
|---|---|---|---|---|
| SALES_QUOT | Quotation | Inquiry & Quotation | form_quotation.php | v2:quotation |
| SALES_INQ | All Inquiries | Inquiry Product | form_inquiry.php | v2:inquiry |
| SALES_COST | Pro-Cost New | Pro-Cost | form_costing.php | v2:sales-procost |
| SALES_PMKT | Master Product Market | Pro-Dev | lookup.product-market | v2:sales-product-market |
| SALES_SO | Customer Order | Sales Order | form_customer_order.php | v2:customer-order |
| PROC_PR | Purchase Requisition (PR) | Requisition | form_pr.php | v2:purchase-requisition |
| PROC_PO | Purchase Order | Order | form_po.php | v2:purchase-order |
| PROC_RCV | Purchase Receive | Receive | form_purchase_receive.php | v2:purchase-receive |
| MD_CUST, PRE_MD_CUST | Master Customer | Master Data | form_customer.php | v2:md-customer |
| MD_SUPP | Master Supplier | Master Data | form_supplier.php | v2:md-supplier |
| MD_PROD, PRE_MD_PROD | Master Product | Master Data | form_product.php | v2:master-product |
| MD_RAW, PRE_MD_RAW | Master Raw Material | Master Data | form_material.php | v2:md-raw |
| MD_WH | Master Warehouse | Master Data | form_warehouse.php | v2:md-warehouse |

## B. Target kosong di tm2sys, v2 sudah ada di ddtsys

Di tm2 item ini dibuka lewat peta kode `MenuRoutes::map()` (layar v1). Perlu migration set `target = v2:...`.

| Kode | Label | Target ddt |
|---|---|---|
| ACC_COA | Master of Accounts | v2:acc-coa |
| SALES_DO | Delivery Order | v2:delivery-order |
| SALES_DN | Delivery Notes | v2:delivery-notes |
| SALES_CPDEL | Customer Plan Delivery | v2:cpdel |
| SALES_IMPO | Pro-Dev Manual | v2:prodev |
| SALES_WINRATE | Win-Rate Sales | v2:sales-win-rate |
| HRM_EMPLOYEE | Master Employee | v2:hrm-employee |
| HRM_ORG_STRUCTURE | Master Position | v2:hrm-position |
| HRM_SOP | SOP & Work Instruction | v2:hrm-sop |
| HRM_CORRECTION | Employee Data Correction | v2:hrm-correction |
| HRM_CONTACT_GAP | Employees Without WhatsApp No. | v2:hrm-contact-gap |
| HRM_ATTENDANCE | Attendance Log | v2:hrm-attendance |
| HRM_DISCIPLINE_REPORT | Daily Discipline Report | v2:hrm-discipline |
| HRM_LEAVE_APPROVAL | Leave Approval | v2:hrm-leave |
| HRM_GPS_APPROVAL | GPS Location Approval | v2:hrm-gps |
| HRM_PAYROLL_CALC | Payroll Calculation | v2:hrm-payroll-run |
| HRM_PAYROLL_PAYSLIP | Print & Send Payslips | v2:hrm-payslip |
| HRM_PAYROLL_SALARY | Employee Salary & Tax | v2:hrm-salary |
| HRM_PAYROLL_BANK | Employee Bank Accounts | v2:hrm-bank |
| MD_SHIFT | Master Shift | v2:md-shift |
| MD_MACHINE, PRE_MD_MACHINE | Master Machine | v2:prod-machine |
| PROD_MST_PROCESS, PRE_MD_PROCESS | Master Process | v2:prod-process |
| PROD_MST_CREW | Master Crew | v2:prod-crew |
| PROD_ROUTING_TPL | Master Routing Template | v2:prod-routing |
| PROD_SCRAP_TYPE | Scrap Types | v2:prod-scrap |
| MD_MATCAT, PRE_MD_MATCAT, INV_REF_MATCAT | Material Category | v2:md-matcat |
| INV_REF_RAW | Raw Material Master | v2:md-raw |
| INV_REF_PROD | Product Master | v2:master-product |
| INV_REF_WH | Warehouse & Location | v2:md-warehouse |

## C. Belum ada v2 sama sekali

Perlu dibangun atau diputuskan dibiarkan v1. Kolom terakhir = rute layar v1 dari `MenuRoutes` atau target lama.

| Kode | Label | Grup | Status | Layar v1 / target |
|---|---|---|---|---|
| SALES_GRID_SO | Sales Order List | Sales Order | NOT_BUILT | grid_so.php |
| SALES_MON_ORD | Monitoring Order | Sales Order | NOT_BUILT | grid_monitoring_order.php |
| SALES_RPT | Sales Reports | Sales Order | NOT_BUILT | rpt_sales.php |
| PRE_COST_INSTANT | Pro-Cost Instant | Pro-Cost | READY_FOR_QC | inquiry.costing.instant.list |
| MGMT_EXEC | Open Management Reports | Summary | READY_FOR_QC | report.executive |
| PROD_MPS | MPS - Gantt Chart | Planning | READY_FOR_QC | form_mps.php |
| PROD_MON_WO | MPS - Kanban Board | Planning | READY_FOR_QC | grid_monitoring_wo.php |
| PROD_MPS_SHEET | MPS - Spreadsheet | Planning | READY_FOR_QC | grid_mps_spreadsheet.php |
| PROD_PROCESS_RATE | Target Rate: Process x Machine / Employee | Planning | READY_FOR_QC | production.process-rate |
| HRM_ROSTER, PROD_SHIFT_ROSTER | Shift Roster / Shift Schedule | Time Attendance / Support | READY_FOR_QC | hrm.shift-roster |
| HRM_CLOCKIN | GPS Clock-In (Mobile) | Time Attendance | READY_FOR_QC | self-service.clock-in |
| HRM_OVERTIME | Overtime | Time Attendance | NOT_BUILT | belum ada layar |
| HRM_ATTENDANCE_UPLOAD | Attendance Upload | Payroll | READY_FOR_QC | hrm.attendance-upload |
| HRM_PAYROLL_BANKXFER | Bank Transfer Generator | Payroll | READY_FOR_QC | hrm.payroll.bank-transfer |
| HRM_PAYROLL_CASHROSTER | Cash Roster & Denominations | Payroll | READY_FOR_QC | hrm.payroll.cash-roster |
| HRM_ORG_CHART | Organization Chart | Organization | READY_FOR_QC | hrm.org-chart |
| HRM_SELFSERVICE | Employee Self-Service | Employee | READY_FOR_QC | self-service.index (profil v2 sudah ada di menu akun) |
| HRM_RCR_PIPELINE | Pipeline Board | Recruitment | READY_FOR_QC | hrm.recruitment.pipeline-board |
| HRM_RCR_PORTAL | Portal Candidate | Recruitment | READY_FOR_QC | careers.landing (portal publik, tetap) |
| FIN_DASH | Dashboard | Control Center | READY_FOR_QC | finance.dashboard |
| FIN_ENGINE | Process Engine | Control Center | READY_FOR_QC | finance.engine |
| FIN_BANK | Bank & Reconciliation | Transaction Flows | READY_FOR_QC | finance.bank |
| ACC_CLOSE | Period Closing | Controls & Tax | READY_FOR_QC | finance.closing |
| ACC_TAX | Indonesian Tax | Controls & Tax | READY_FOR_QC | finance.tax |
| ACC_FS | Financial Statements | Financial Reports | READY_FOR_QC | finance.statements |
| ACC_BS | Balance Sheet | Financial Reports | READY_FOR_QC | finance.balance-sheet |
| ACC_PL | Income Statement | Financial Reports | READY_FOR_QC | finance.income-statement |
| ACC_CF | Cash Flow | Financial Reports | READY_FOR_QC | finance.cash-flow |
| INV_SCAN_ITEM1 | Scan Transaction (Mobile Camera) | Scan Center | NOT_BUILT | belum ada layar |
| SET_CONFIG | Identification | Identification | READY_FOR_QC | setup.identification |
| SET_DATA_ORIGIN | Dummy & Legacy Data | Dummy Data & Migration | READY_FOR_QC | setup.data-origin |
| SET_MIGRATION | Migration | Dummy Data & Migration | READY_FOR_QC | migration |
| SET_LOCALIZATION | Localization | Language and Localization | READY_FOR_QC | setup.localization |
| SET_PERM | Groups on Modules | Security | READY_FOR_QC | security.php?tab=perms |
| SET_LANG | Language | Language and Localization | READY_FOR_QC | security.php?tab=lang |

### Pecahan tugas kelompok C (35 baris = 10 tugas)

Urutan disarankan: dari yang paling murah (mungkin cuma pencocokan) ke yang butuh keputusan user.

| Tugas | Isi (kode) | Jml | Sifat | Catatan |
|---|---|---|---|---|
| C1 | SET_CONFIG, SET_DATA_ORIGIN, SET_MIGRATION, SET_LOCALIZATION, SET_PERM, SET_LANG | 6 | Cocokkan dulu | Cari padanan v2 (kode menu lain sejak pembagian Setup 2026-10-04). Bila ada: cukup migration target. Bila tidak: bangun layar. |
| C2 | ACC_FS, ACC_BS, ACC_PL, ACC_CF | 4 | Bangun (laporan) | Empat laporan keuangan, satu keluarga: satu data class dasar, empat turunan. |
| C3 | FIN_DASH, FIN_ENGINE, FIN_BANK, ACC_CLOSE, ACC_TAX | 5 | Bangun (campuran) | Dashboard, engine, bank, tutup periode, pajak. Dokumentasikan jurnal dengan jelas (aturan finance). |
| C4 | SALES_GRID_SO, SALES_MON_ORD, SALES_RPT, PRE_COST_INSTANT | 4 | Bangun (grid + laporan) | Sales Order List / Monitoring = turunan `DocListGrid`. Laporan tanpa harga bagi Sales. Zona beku: Pro-Cost Instant = tanya user dulu. |
| C5 | MGMT_EXEC | 1 | Cocokkan / bangun | Mungkin cukup diarahkan ke Executive Summary yang sudah ada; putuskan dulu. |
| C6 | HRM_ROSTER (+PROD_SHIFT_ROSTER), HRM_CLOCKIN, HRM_OVERTIME, HRM_ATTENDANCE_UPLOAD | 4 | Bangun | Overtime belum punya layar sama sekali. Clock-In = mobile. |
| C7 | HRM_PAYROLL_BANKXFER, HRM_PAYROLL_CASHROSTER | 2 | Bangun | Payroll wajib terenkripsi (belum didesain): minta konfirmasi desain dulu. |
| C8 | HRM_ORG_CHART, HRM_SELFSERVICE, HRM_RCR_PIPELINE, HRM_RCR_PORTAL | 4 | Bangun + keputusan | Portal Candidate = publik, tetap di luar v2 (usul: dibiarkan). Self-Service: profil v2 sudah ada. |
| C9 | PROD_MPS, PROD_MON_WO, PROD_MPS_SHEET, PROD_PROCESS_RATE | 4 | BLOKIR keputusan user | MPS sengaja ditelantarkan; dirancang ulang meniru ProDev/Inquiry. Jangan mulai tanpa arahan. |
| C10 | INV_SCAN_ITEM1 | 1 | Bangun (mobile) | NOT_BUILT, scan kamera; butuh journey dulu. |

Total: 6+4+5+4+1+4+2+4+4+1 = 35.

**C1 SELESAI (2026-10-07, ddtsys; migration `2026_10_07_00001..00005*` dijalankan di tm2sys saat rilis):**
SET_LANG dan SET_LOCALIZATION dinonaktifkan (Languages + Translation Dictionary; teks login pindah ke `sys_dictionary` lewat `AuthText`,
kunci `sys_translations` sisanya milik layar v1 dan ikut pensiun bersama v1). SET_CONFIG -> `v2:setup-identification`, SET_PERM ->
`v2:security-permissions` (matriks `x-dl::permission-matrix` + prop baru `:save-url`), SET_DATA_ORIGIN -> `v2:setup-data-origin`,
SET_MIGRATION -> `v2:setup-migration` (17 runner). Fitur v1 dipertahankan; layar v1 tetap ada sampai v1 diarsipkan.

**C4 SELESAI (2026-10-07, ddtsys; migration `2026_10_07_000060`):** PRE_COST_INSTANT -> `v2:sales-procost-instant` (`CostingInstantGrid`;
daftar costing mode INSTANT, buka baris = form Instant v1 apa adanya karena zona beku, + New Record = draft kosong baru / pakai ulang draft kosong
milik sendiri lewat `v2.procost-instant.new`). SALES_GRID_SO, SALES_MON_ORD, SALES_RPT TIDAK dibangun: ketiganya baris warisan ScriptCase yang
sudah `activate=0`, tidak pernah punya layar v1, dan fungsinya dipegang layar v2 yang aktif (Customer Order, Customer Order Summary / Report /
Shipment Detail di Sales > Order > Report). Diusulkan dibiarkan nonaktif / dihapus saat rilis, tidak dihitung sebagai utang v2.
**C5 SELESAI (2026-10-07, ddtsys; migration `2026_10_07_000070`):** MGMT_EXEC (label di menu = Executive Summary) -> `v2:mgmt-executive`
(`ExecutiveSummary` di atas `ManagementReportService`: 5 KPI, tren omset, komposisi customer, kuartal, customer teratas, kesehatan data,
perhatian manajemen, tren pembelian). Modul MGMT ditambahkan ke `Screens`. Catatan: enam laporan MGMT lain (Sales / Purchase / Accounting /
Production / HR / Warehouse Details) masih layar v1 (`report.*`) dan tidak termasuk daftar C di dokumen ini; tautan "rincian" mengarah ke sana.
**C6 SELESAI SEBAGIAN (2026-10-07, ddtsys; migration `2026_10_07_000080..000110`):** HRM_ATTENDANCE_UPLOAD -> `v2:hrm-attendance-upload`;
HRM_CLOCKIN -> `v2:hrm-clockin` (objek DevLib baru `x-dl::geo-clock`, milik sendiri = GENERAL); HRM_OVERTIME -> `v2:hrm-overtime` (dulu tanpa layar);
HRM_ROSTER + PROD_SHIFT_ROSTER -> `v2:hrm-roster` (papan mingguan, lingkup jadwal / departemen / unit, Draft -> Publish & Lock -> Unlock,
CRUD jadwal, rotasi swap / cycle / copy, tampil / sembunyi shift; katalog shift = Master Shift). **Sisa Shift Roster (belum dipindah, v1 masih dipakai):**
tampilan Formasi Harian (`RosterFormationService`, editor baris, mockup `docs/mockups/mockup_roster_formasi_harian.jpg`) dan cetak roster
(`hrm.shift-roster.print`; tombol Print di layar v2 membuka layar v1 itu). Catatan: rotasi v2 menolak minggu tujuan yang sudah CONFIRMED (v1 tidak memeriksa).
**C7 SELESAI (2026-10-07, ddtsys; migration `2026_10_07_000120`):** HRM_PAYROLL_BANKXFER -> `v2:hrm-bank-transfer`, HRM_PAYROLL_CASHROSTER -> `v2:hrm-cash-roster`
(`PayrollDisbursement`: pegawai berekening utama aktif = transfer bank + unduh CSV batch; pegawai tanpa rekening = tunai dengan rincian amplop dan jumlah lembar
per pecahan; cetak serah terima = `x-dl::print-doc` jenis `cash-roster`). Enkripsi gaji TIDAK perlu dibangun: `pyr_payslip.total_pokok / total_gaji_bersih` dan
`pyr_employee_salary.basic_salary_monthly` sudah AES-256-GCM (`App\Casts\PayrollEncrypted`, `docs/payroll_encryption_decision.md`); model mendekripsi otomatis.
Catatan CLAUDE.md §2 ("pyr belum diimplementasikan") sudah usang. Kolom lain (BPJS, PPh21, lembur, tunjangan, denda) tetap plaintext sesuai keputusan awal.
Seperti v1, layar ini hanya memuat periode CONFIRMED / PAID / CLOSED dan semua pemilik modul HRM bisa membukanya (tidak dibatasi `canViewPayrollAmounts`).
**Hasil pencocokan C1 (2026-10-07, dari ddtsys + kode):**

| Kode | Temuan | Jalan keluar |
|---|---|---|
| SET_LANG | Digantikan `SET_LANGUAGES` (`v2:languages`, saklar Active). Menu dobel. | Nonaktifkan / alihkan ke `v2:languages` (satu migration). |
| SET_LOCALIZATION | BUKAN padanan `SET_DICTIONARY`: v1 mengedit `sys_translations` (kamus kunci v1, dipakai halaman login / register lewat `SysTranslation::forLang`), v2 memakai `sys_dictionary`. | Putusan user: pindahkan kunci login ke `sys_dictionary` lalu pensiunkan, atau bangun layar v2 untuk `sys_translations`. |
| SET_CONFIG | Belum ada v2. Form 2 kartu (Identitas Aplikasi, Identitas Perusahaan + 2 logo) di `pages/setup/identification.blade.php`. | Bangun: dua `x-dl::form`, simpan ke `sys_constant` + `org_company` TM. |
| SET_PERM | Belum ada v2. Matriks grup x modul di tab `perms` `pages/security/index.blade.php`. Grup, modul, akun sudah v2. | Bangun matriks grup x modul. |
| SET_DATA_ORIGIN | Belum ada v2. v1 579 baris, developer-only (`pages/setup/data-origin.blade.php`). Ada rute v2 `dummy.*` (baseline / stage) yang mungkin sudah menutup sebagian. | Cocokkan dengan `/v2/dummy` dulu. |
| SET_MIGRATION | Belum ada v2. Tab `migration` berisi 17 tombol runner (`App\Services\Migration\*`) dan laporan hasil. | Bangun satu layar runner; laporan = objek tabel. |

Catatan: kelompok C berisi sisa Finance (9 layar), sebagian HRM Payroll / Time Attendance, MPS (3 layar), dan beberapa item Setup. Kelompok A dan B bisa selesai tanpa membangun apa pun; kelompok C yang menentukan kapan v1 benar-benar bisa diarsipkan. Sebagian item Setup di C mungkin sudah punya padanan v2 dengan kode menu lain (pembagian ulang grup Setup 2026-10-04); itu belum saya cocokkan.
