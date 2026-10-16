# Peta besar ERP TM (TG Fase 3)

> 2026-10-16. Modul dan sub-modul = pohon `sys_app_menu` (FAKTA, query mockup: 9 modul, 394 baris menu aktif). Aliran antarmodul hanya yang tertulis di kartu TG Sales (sudah diverifikasi kartu itu sendiri). Modul tanpa kartu TG ditandai "belum"; jangan membaca ketiadaan panah sebagai ketiadaan aliran. Diagram: [img/peta-besar.svg](./img/peta-besar.svg) (kembaran teks = tabel dan daftar aliran di bawah).

## Modul dan sub-modul
| Modul (kode) | Sub-modul (jumlah layar) | TG |
|---|---|---|
| Sales (SALES) | Inquiry & Quotation (23), Order (9), Delivery (4), CRM (6) | **ada**: [sales/](./sales/) |
| Management Reports (MGMT) | Summary (1), Reports (6) | belum |
| Procurement (PROC) | Requisition (3), Order (4), Receive (4), Return (2) | belum |
| Inventory (INV) | Stock (56), Warehouse (19) | belum (ada Design Notes `docs/design-notes/inventory`) |
| Manufacture (PROD) | Planning (11), PPIC (13), Production (1), Maintenance (19), QA (2) | belum (ada Design Notes ppic, maintenance; Guides production) |
| Human Resources (HRM) | Organization (5), Employee (4), Time Attendance (6), Payroll (8), Recruitment (11) | belum |
| Finance & Accounting (FINACC) | Control Center (2), Transaction Flows (8), Accounting (5), Controls & Tax (2), Financial Reports (7) | belum |
| Master Data (MDATA) | 21 item (Customer, Sales Person, Product, Raw Material, Supplier, UoM, Warehouse, Shift, Currency, dst) | sebagian: kartu Customer dan Sales PIC |
| Setup (SETUP) | Security, Support & Ticket, Help and Support, Language, Dummy Data, Constants, Dashboard, Menu, Identification, Others | belum |

Catatan: sub-modul Sales di TG memakai folder `inquiry-product`, `product-costing`, `prodev`, `quotation`, `order`, `delivery`, `crm`, `master-data`. Menu Sales > Inquiry & Quotation membuat Pro-Dev dan Pro-Cost grup di dalamnya; folder TG memisahkannya agar kartu engine dekat dengan pemakainya. Pemeta ulang ke pohon menu persis ditunda ke Fase 4 (pemeriksa).

## Aliran antarmodul (terverifikasi di kartu Sales)
1. Master Data -> Sales: `prod_type` (snapshot dieline), customer (`payment_term`, `currency_code`), sales person. Kartu: [Tipe produk](./sales/inquiry-product/10_kartu_tipe-produk.md), [Customer](./sales/master-data/10_kartu_customer.md), [Sales PIC](./sales/master-data/11_kartu_sales-pic.md).
2. Sales hulu: Inquiry -> ProDev -> ProCost -> Quotation -> CO (tanda A.20). Kartu: [Inquiry](./sales/inquiry-product/11_kartu_inquiry-dan-part.md), [ProDev](./sales/prodev/10_kartu_antarmuka-prodev.md), [ProCost](./sales/product-costing/11_kartu_antarmuka-procost.md), [Quotation](./sales/quotation/10_kartu_quotation.md), [CO](./sales/order/10_kartu_customer-order.md).
3. Sales hilir: CO -> CPDEL -> Delivery Order -> POD. Kartu: [CPDEL](./sales/order/11_kartu_cpdel.md), [DO](./sales/delivery/10_kartu_delivery-order.md), [POD](./sales/delivery/11_kartu_proof-of-delivery.md).
4. Sales -> Manufacture: slot CPDEL ke WO (`prd_wo.source_delivery_id`), CO ke WO (`prd_wo.source_co_id`), Planning PPIC (`PlanEvents::replan`); CO Confirmed = firm untuk Material Planning; ProDev -> `forWorkOrder`; Master Card diwarisi PPIC. Sisi Manufacture: belum ada kartu (relasi sepihak).
5. Sales -> Finance: POD `do_line.qty_received` menjadi dasar tagihan (A.21, DARI DOKUMEN, belum dicek); retur dari Complaint (BELUM dicek). Sisi Finance: belum ada kartu.
6. Sales -> Inventory/QA: `QcTraceService` membaca DO; Complaint muncul di meja QA (Production > Complaint Review). Sisi Inventory/QA: belum ada kartu.
7. Sales -> Master Data (arah balik): saat WIN, transaksi mengisi Master Product (`ProductMasterSync`).

Daftar relasi lengkap per pasangan: [02_daftar-relasi.md](./02_daftar-relasi.md). Peta engine: [01_peta-engine.md](./01_peta-engine.md).

## Zona beku
Inquiry Product, ProDev, ProCost, Quotation, Customer Order, CPDEL, Delivery (flow dan dokumen). Menyentuhnya: peringatkan Jo lebih dulu (CLAUDE.md butir 1).
