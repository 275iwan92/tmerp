# Daftar relasi antarmodul (TG Fase 3)

> 2026-10-16. Diambil dari blok Relasi kartu Sales (`sumber`, `dipakai_oleh`, `mengalir`). Setiap baris menyebut kartu asalnya; fakta tabel/kolom ada di kartu. Kolom Sisi tujuan: **ada** bila modul tujuan sudah punya kartu yang menulis relasi sebaliknya; **sepihak** bila belum (aturan Fase 4: relasi sepihak ditandai pemeriksa).

| Dari | Ke | Yang mengalir | Kartu asal | Sisi tujuan |
|---|---|---|---|---|
| Master `prod_type` | Inquiry (part) | `type_code` + `dieline_spec` dibekukan di `iqp_part.dieline_spec` | tipe-produk | ada |
| Master Customer | Inquiry, Quotation, CO | `payment_term`, `currency_code` disalin lalu dibekukan (A.13) | customer | ada |
| Master Sales Person | Inquiry, Quotation, CO | `pic_sales` (teks) -> `sales_pic` (teks) -> `sales_person_id` (id via nama) | sales-pic | ada |
| Master pendukung (finishing, jenis sambungan) | Inquiry | nama dibekukan (`finishing_name`, `rawmat_name`) | master-pendukung-inquiry | ada |
| Inquiry | ProDev | `sendToProDev`: ukuran dalam cm ke `prod_part`; Confirm Sales | inquiry-dan-part, antarmuka-prodev | ada |
| ProDev | Inquiry | layout/BOP/BOM/tinta kembali ke `iqp_part_*`; `stat_confirm_prodev` | antarmuka-prodev | ada |
| ProDev | ProCost | konteks baca-saja; `stat_confirm_procost` dihitung dari costing published | antarmuka-prodev, antarmuka-procost | ada |
| ProCost | Quotation | harga per tier (`unit_price`), deklasifikasi saat publish | antarmuka-procost, quotation | ada |
| ProCost | CO | `tolerance_pct` tier dibekukan | antarmuka-procost, customer-order | ada |
| Quotation | CO | WIN: baris Quotation disnapshot ke `co_order_item` | quotation, customer-order | ada |
| Inquiry | Master Product | `ProductMasterSync::fromInquiry` saat WIN | inquiry-dan-part, snapshot | sepihak (kartu Master Product belum) |
| Master Card Collection | Inquiry | kartu lama menjadi Inquiry sumber `MASTER CARD` | inquiry-dan-part | ada |
| Reverse Costing | Inquiry | Inquiry INSTANT; menulis `stat_confirm_*` | inquiry-dan-part, reverse-costing | ada |
| CO | CPDEL | CO CONFIRMED melahirkan baris slot (engine CO) | customer-order, cpdel | ada |
| CPDEL | Delivery Order | `do_line.co_delivery_id` (FK RESTRICT); tarik slot ke DO | cpdel, delivery-order | ada |
| Delivery Order | POD | kedatangan dan `qty_received` | delivery-order, proof-of-delivery | ada |
| CPDEL | Manufacture (WO, PPIC) | `prd_wo.source_delivery_id`; `PlanEvents::replan`; janji pertama | cpdel | sepihak |
| CO | Manufacture (WO) | `prd_wo.source_co_id` | customer-order | sepihak |
| CO | Material Planning (Planning) | CO Confirmed = firm | customer-order, material-planning | ada (kartu engine) |
| ProDev | Manufacture (WO, PPIC) | `forWorkOrder`; Master Card diwarisi | antarmuka-prodev, dieline-dan-master-card | sepihak |
| Dieline/Master Card | ProDev, ProCost | `dieline_spec`/flat untuk imposisi dan geometri | dieline-dan-master-card | ada |
| Material Source | ProCost, SpecEngine | pemilik bahan per baris; disalin ke BOM | material-source | ada |
| POD | Finance (Customer Invoice) | dasar tagih `do_line.qty_received` (A.21, DARI DOKUMEN) | proof-of-delivery | sepihak, belum dicek |
| Complaint | QA (Production) dan Finance | meja QA; retur (BELUM dicek) | crm-complaint | sepihak |
| Delivery Order | Inventory/QA | `QcTraceService` membaca DO | delivery-order | sepihak |
| Customer 360 | (baca saja) | hanya menampilkan: Customer, Inquiry/Quotation, CO, DO, Activity, Complaint | customer-360 | tidak menulis |

## Relasi satu arah antar kartu yang perlu dicek Jo/Fase 4
1. Kartu Quotation menyebut Master Product "lahir saat WIN"; kartu Customer Order dan Snapshot menyebutnya dari sisi lain, tetapi kartu Master Product belum ada.
2. Win-Rate Sales: kartu CRM Activity menulis "BELUM dicek apakah memakai aktivitas".
