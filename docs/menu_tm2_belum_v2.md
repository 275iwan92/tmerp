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

Catatan: kelompok C berisi sisa Finance (9 layar), sebagian HRM Payroll / Time Attendance, MPS (3 layar), dan beberapa item Setup. Kelompok A dan B bisa selesai tanpa membangun apa pun; kelompok C yang menentukan kapan v1 benar-benar bisa diarsipkan. Sebagian item Setup di C mungkin sudah punya padanan v2 dengan kode menu lain (pembagian ulang grup Setup 2026-10-04); itu belum saya cocokkan.
