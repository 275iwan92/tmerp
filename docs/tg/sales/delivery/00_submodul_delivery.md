# Sub-modul: Delivery (Delivery Order, Delivery Notes, Proof of Delivery)

> Ditulis 2026-10-16 (kelompok A). Halaman ini hanya merangkum dan menautkan; fakta tabel/kolom ada di kartu.

- **Modul**: Sales > Delivery (`SALES_DLVGRP`). Menu (FAKTA, `sys_app_menu` mockup, aktif): Delivery Order (`v2:delivery-order`), Delivery Notes (`v2:delivery-notes`), Subcontract Delivery Note (`v2:subcon-delivery-note`), Proof of Delivery (`v2:proof-of-delivery`).
- **Tujuan**: dari jadwal kirim yang sudah Posted ke Produksi, membuat dokumen kirim fisik (DO/Surat Jalan tanpa harga), menandai Shipped, lalu mencatat kedatangan di customer dan qty yang diterima (dasar tagih).
- **Zona beku**: flow dokumen Delivery (aturan beku semua alur).
- **Aturan**: A.3 (DO/DN setelah POST, hasil produksi tetap wajib di-input), A.9 (harga hanya pemegang hak; Surat Jalan tanpa harga), A.21 (dasar tagih = qty diterima, DARI DOKUMEN).

## Relasi
- **sumber**: CPDEL (`co_order_delivery` via view `v_co_delivery_outstanding`), CO (harga, deskripsi), Master Customer/Supplier.
- **dipakai_oleh**: Customer Invoice (`qty_received`), KPI pengiriman tepat janji, Customer 360, Delivery Track (diagram rute).
- **tanda**: `do_delivery.status` (DRAFT/CONFIRMED/SHIPPED/CANCELLED), bukan `stat_confirm`; lihat kartu Status di `../order/12_kartu_status-dokumen.md`.

## Kartu dan engine di sub-modul ini
| Jenis | Berkas | Isi |
|---|---|---|
| Konsep | `10_kartu_delivery-order.md` | `do_delivery`, `do_line`, Surat Jalan = DO CONFIRMED/SHIPPED, subkon, harga |
| Konsep | `11_kartu_proof-of-delivery.md` | `do_delivery_pod`, janji tepat, qty diterima, KPI |
| ENGINE | belum ditulis | `DispatchService` (tarik/confirm/shipped/batal; dicakup di kartu DO), `DeliveryPromiseService` (aturan janji tepat dan qty diterima; dicakup di kartu POD), `DeliveryTrack` (diagram rute, BELUM dibaca) |

## Diagram
Belum dibuat.

## Panduan pemakai (UG) yang sudah ada
`docs/guides/sales/04_quotation_order_cpdel.md` (menyebut CPDEL; apakah mencakup DO/POD BELUM dicek).

## Temuan lintas kartu (ringkas; rincian di kartu)
DO CONFIRMED tanpa baris di dummy; POD tidak mengubah status DO; gerbang Post-to-Production hanya di layar v2; `price_change_approval_status` dan `split_from_line_id` tidak terpakai; tidak ada tes `DeliveryPromiseService`.
