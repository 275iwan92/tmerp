# Sub-modul: Order (Customer Order dan CPDEL)

> Ditulis 2026-10-16 (kelompok A). Halaman ini hanya merangkum dan menautkan; fakta tabel/kolom ada di kartu.

- **Modul**: Sales > Order (`SALES_ORDGRP`). Menu (FAKTA, `sys_app_menu` mockup, aktif): Customer Order (`v2:customer-order`), Customer Plan Delivery (`v2:cpdel`), CO Approval Center (`v2:approval-co`), grup Report (Customer Order Summary, Customer Order Report, Customer Order Shipment Detail), grup General Report (tiga laporan yang sama tanpa harga, `GENERAL_*`). Item v1 `grid_so.php`, `rpt_sales.php`, `grid_monitoring_order.php` berstatus tidak aktif.
- **Tujuan**: mengubah penawaran yang menang (atau order langsung) menjadi Customer Order yang disetujui, lalu jadwal kirim (CPDEL) yang menjadi dasar Work Order dan Delivery Order.
- **Zona beku**: flow dokumen CO dan CPDEL (aturan beku semua alur). Dibaca: `docs/claude_vital_flows.md` (bagian Quotation dan Customer Order), `docs/ENGINE-ATTENTION.md` (tidak ada butir untuk CO/CPDEL).
- **Aturan**: A.1 Quotation wajib integrated; A.2 CO boleh direct (INSTANT); A.4 1 Quotation = 1 CO; A.10 due date CO; A.11 approval CO; A.13 snapshot; A.15 satu qty per produk; A.16 Post to Production; A.20 tanda konfirmasi.

## Relasi
- **sumber**: Quotation `PUBLISHED` (Inquiry & Quotation; kartu penulis lain), Master Product dan Customer, ACC Digital produk, tier ProCost (hanya toleransi).
- **dipakai_oleh**: Delivery (Delivery Order menarik jadwal CPDEL yang sudah Posted), Production (WO dari baris CPDEL), Material Planning (CO Confirmed = firm), Customer Invoice, CRM (Customer 360, Complaint).
- **tanda**: `co_order.doc_status` dan `stat_cancel` (bukan `stat_confirm`); lihat kartu Status.

## Kartu dan engine di sub-modul ini
| Jenis | Berkas | Isi |
|---|---|---|
| Konsep | `10_kartu_customer-order.md` | `co_order`, `co_order_item`, status, due date, toleransi, approval, batal |
| Konsep | `11_kartu_cpdel.md` | `co_order_delivery`, `co_delivery_negotiation`, split/gabung/post |
| Konsep | `12_kartu_status-dokumen.md` | status semua dokumen Sales, tanda A.20, `doc_state_log` (tidak ada) |
| Konsep | `13_kartu_snapshot.md` | peta pembekuan master di Sales (A.13) |
| ENGINE | `20_engine_co-order-builder.md` | WIN -> CO, CO langsung, submit/confirm (CPDEL), batal |
| ENGINE | `21_engine_cpdel-promise.md` | dua janji: slot customer vs siap produksi, warna jatuh tempo |
| ENGINE | belum ditulis | `CoDueDate` (dicakup di kartu engine CoOrderBuilder), `FlowEngineService` (approval, milik modul Flow) |

## Diagram
Belum dibuat (kembaran teks ada di bagian "Alur" kartu engine).

## Panduan pemakai (UG) yang sudah ada
`docs/guides/sales/04_quotation_order_cpdel.md`. TG tidak mengulang isinya.

## Temuan lintas kartu (ringkas; rincian di kartu)
CO2600021 CONFIRMED sekaligus `stat_cancel=1` dengan jadwal masih tampil di CPDEL; jalur v1 `confirm` tanpa approval; konflik A.10 (proof FISIK) dengan kode (ACC Digital); `sales_person_id` kosong di semua CO; ESCALATED kalah dari LATE di engine janji; gabung baris menghapus log negosiasi.
