# Opening Dashboards — referensi dari ERP lain

Permintaan user 2026-10-02: *"FIND and BUILD IT references from another ERP or another software for Offset printing or ERP
popular"*. Riset dilakukan lewat dokumentasi resmi vendor; yang dicatat di sini hanya yang benar-benar tertulis di
halaman sumbernya. Nomor `[n]` = sumber di bagian akhir.

## Yang TIDAK berhasil dikonfirmasi (jangan dijadikan acuan)

- **EFI Pace / Monarch / ePS, Avanti Slingshot, printIQ, Hiflow:** halaman publiknya hanya menyebut "dashboard / KPI", tanpa
  nama widget.
- **Tharstern** dan **Optimus dash:** hanya terlihat di ringkasan hasil pencarian, halamannya tidak bisa dibuka.
- **OEE, spoilage, on-time delivery** sebagai widget: tidak ada vendor print MIS yang menuliskannya di halaman publik; yang
  terkonfirmasi hanya di Business Central dan Odoo.
- **SAP Business One**, kartu HR / Payroll ERPNext, dan dashboard Sales / Payroll Odoo: tidak ditemukan di dokumentasi resmi.

## Widget yang berulang di banyak produk, dan padanannya di papan kita

| Bidang | Widget di referensi | Terlihat di | Widget kita |
|---|---|---|---|
| Overall | Penjualan periode ini vs sebelumnya | Business Central [1][3], NetSuite [17], Accurate [28] | Order Value, Order Value: Target vs Actual |
| Overall | Laba bersih dan margin | Business Central [2][3], Jurnal [29], Accurate [28] | Net Profit |
| Overall | Pendapatan vs biaya per bulan | Zoho Books [18], Business Central [11] | Income vs Expense |
| Overall | Top customer | Business Central [1][4] | Top Customers |
| Sales | Quotation terbuka (jumlah dan nilai) | Business Central [1][5], NetSuite [17], PrintVis [24], PrintPLANR [27] | Open Quotations |
| Sales | Konversi quotation menjadi order | PrintVis [24][25], SAP [23], PrintPLANR [27] | Win Rate |
| Sales | Order masuk per periode | SAP [23], NetSuite [17], Accurate [28] | New Customer Orders, Sales Pipeline |
| Sales | Penjualan per salesperson vs target | Business Central [4], NetSuite [17], PrintVis [24] | Target Achievement per Sales, Targets by Area |
| Sales | Order terlambat / backorder | SAP [23] | Deliveries Due & Late |
| Sales | Ketepatan pengiriman | SAP [23] | On-time Delivery |
| Purchasing | Nilai pembelian per periode | Odoo [12], SAP [22], Business Central [6] | Purchase Value |
| Purchasing | PO belum diterima | Business Central [1][6], SAP [22] | Open Purchase Orders |
| Purchasing | Top vendor | Odoo [12], Business Central [6], SAP [22] | Top Suppliers |
| Purchasing | Requisition terbuka | SAP [22] | Purchase Requisitions Waiting |
| AR & AP | Umur piutang / umur hutang | Business Central [2], SAP [21] | Receivables Aging, Payables Aging |
| AR & AP | Piutang / hutang lewat jatuh tempo, jatuh tempo minggu depan | Business Central [1], Zoho Books [18], Accurate [28] | Overdue Receivables, Overdue Payables |
| AR & AP | 10 debitur terbesar | SAP [21] | Top Debtors |
| AR & AP | Pelacak penerimaan kas | SAP [21] | Customer Payments Received, Supplier Payments Made |
| Cashflow | Kas masuk vs keluar per periode | Zoho Books [18], Business Central [11], Jurnal [29], Accurate [28] | Cash In vs Cash Out, Net Cash Flow |
| Cashflow | Saldo per akun kas / bank | Accurate [28], Zoho Books [18], NetSuite [17] | Cash & Bank Balance |
| Cashflow | Biaya terbesar | Zoho Books [18], Jurnal [29], Accurate [28] | Top Expenses |
| Payroll | Ringkasan biaya gaji: bersih, potongan | Zoho Payroll [19], NetSuite [17] | Payroll Net Pay, Payroll Components |
| Payroll | Status pay run berjalan | Zoho Payroll [19] | Payroll Runs |
| Payroll | Tren gaji bersih | Odoo [31] | Net Pay per Payroll Run |
| HR | Headcount, masuk, keluar | Zoho People [20], NetSuite [17], Frappe HR [30] | Headcount |
| HR | Headcount per departemen, komposisi gender | Zoho People [20] | Headcount by Department, Headcount by Gender |
| HR | Ringkasan kehadiran | Frappe HR [30] | Attendance |
| HR | Cuti menunggu | Zoho People [35], Frappe HR [30] | Leave Requests Waiting |
| HR | Pipeline rekrutmen | Odoo [15] | Recruitment Pipeline |
| Produksi | Work order per status | ERPNext [16], Business Central [10] | Work Orders by Status |
| Produksi | Job terlambat / jatuh hari ini | PrintPLANR [27] | Late Work Orders |
| Stok | Nilai persediaan, stok rendah | Business Central [7], PrintPLANR [27] | Stock Value, Material Shortage, Deadstock Value |

## Ada di referensi, BELUM kita bangun (dan kenapa)

| Widget referensi | Terlihat di | Yang kurang |
|---|---|---|
| Estimasi vs biaya nyata per job | Business Central [10], PrintVis [26] | Biaya nyata per Work Order belum dicatat; HPP juga rahasia (Costing + Direksi) |
| OEE, utilisasi mesin, scrap %, downtime | Odoo [14], Business Central [8][9], ERPNext [16] | Data hasil produksi dan breakdown masih sangat sedikit |
| DSO / DPO (hari rata-rata tagih / bayar) | Business Central [1][3], SAP [21] | Perlu definisi yang disepakati (basis penjualan kredit, periode) |
| Proyeksi arus kas | Business Central [11] | Perlu aturan: order dan PO mana yang ikut diproyeksikan |
| Barang diterima belum ditagih supplier | Business Central [6] | Kaitan penerimaan barang dengan invoice supplier belum lengkap |
| Ketepatan kirim supplier, lead time | Odoo [12] | Tanggal janji supplier per baris PO belum konsisten terisi |
| Kewajiban BPJS / PPh 21 yang harus disetor | Zoho Payroll [19] | Belum ada pencatatan setoran |
| Budget vs realisasi buku besar | Business Central [2] | Belum ada anggaran per akun |

## Sumber

1. https://learn.microsoft.com/en-us/dynamics365/business-central/application/base-application/table/microsoft.rolecenters.activities-cue
2. https://learn.microsoft.com/en-us/dynamics365/business-central/finance-powerbi-app
3. https://learn.microsoft.com/en-us/dynamics365/business-central/finance-powerbi-financial-overview
4. https://learn.microsoft.com/en-us/dynamics365/business-central/sales-powerbi-app
5. https://learn.microsoft.com/en-us/dynamics365/business-central/sales-powerbi-sales-quote-overview
6. https://learn.microsoft.com/en-us/dynamics365/business-central/purchases-powerbi-purchases-overview
7. https://learn.microsoft.com/en-us/dynamics365/business-central/inventory-powerbi-app
8. https://learn.microsoft.com/en-us/dynamics365/business-central/manufacturing-powerbi-app
9. https://learn.microsoft.com/en-us/dynamics365/business-central/manufacturing-powerbi-work-center-statistics
10. https://learn.microsoft.com/en-us/dynamics365/business-central/manufacturing-powerbi-production-order-overview
11. https://learn.microsoft.com/en-us/dynamics365/business-central/finance-analyze-cash-flow
12. https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/purchase/advanced/purchase_dashboard.html
13. https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/inventory/warehouses_storage/reporting/dashboards.html
14. https://www.odoo.com/documentation/19.0/applications/inventory_and_mrp/manufacturing/reporting/oee.html
15. https://www.odoo.com/documentation/19.0/applications/hr/recruitment/applicant_analysis.html
16. https://docs.frappe.io/erpnext/user/manual/en/manufacturing-dashboard
17. https://docs.oracle.com/en/cloud/saas/netsuite/ns-online-help/section_N613450.html
18. https://www.zoho.com/us/books/help/home/
19. https://www.zoho.com/in/payroll/help/employer/basics/dashboard.html
20. https://help.zoho.com/portal/en/kb/people/administrator-guide/reports/articles/employee-information-reports
21. https://blog.sap-press.com/finance-operations-based-analytics-in-sap-s4hana
22. https://learning.sap.com/courses/business-processes-in-sap-s-4hana-sourcing-procurement/using-the-procurement-overview-app
23. https://learning.sap.com/courses/implementing-sap-s-4hana-cloud-public-edition-sales-automation-and-analytics/describing-sap-fiori-analytical-apps-for-sales-1bs-_ab89c610-3ff1-4018-bf03-b8511c9b3b3f
24. https://printvis.com/article/power-bi/
25. https://printvis.com/article/pv16-power-bi/
26. https://learn.printvis.com/PowerBI/
27. https://www.printplanr.com/print-mis-dashboard/
28. https://help.accurate.id/product/accurate-online/fitur-aol/dashboard/widget-kas/
29. https://www.jurnal.id/id/blog/2017-monitor-keuangan-bisnis-anda-dalam-satu-genggaman/
30. https://docs.frappe.io/hr/human-resources-reports
31. https://www.odoo.com/documentation/19.0/applications/hr/payroll/payroll_analysis.html
35. https://www.zoho.com/people/help/adminguide/reports.html
