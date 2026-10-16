# Kartu konsep: CRM Complaint dan Sales Return

> Ditulis 2026-10-16 (kelompok A). FAKTA = DB mockup (`ddt*` lewat `tm2*`, `--env=mockup`) dan kode bernomor baris, dilihat sesi ini. Tes `CrmTest` (5 lulus, 52 assert; `--env=mockup`) mencakup alur komplain; jumlah baris `crm_*` sama sebelum dan sesudah.

- **Jenis dokumen**: kartu konsep (dokumen berstatus). **Modul/sub-modul**: Sales > CRM > Complaint & Return (`CRM_COMPLAINT`, `v2:crm-complaint`). Jawaban mutu di Production: "Complaint Review (QA)" (`v2:qc-complaint-review`). FAKTA, `sys_app_menu`.
- **Nama kanonik**: CRM Complaint = `tm2sdi.crm_complaint` (header, nomor `CCyyNNNNN`) + `crm_complaint_item` (produk yang dikeluhkan).
- **Sinonim**: komplain, retur penjualan, Sales Return, keluhan customer, claim, `complaint_no`.
- **BUKAN sama dengan**: `qc_complaint_review` (jawaban QA, tabel `tm2prd`), Non-Conformance internal, CRM Activity (catatan kontak, termasuk topik COMPLAINT).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Header | `crm_complaint`: 4 baris. PK `id_complaint`; `complaint_no` UNIK (NULL selama Draft), `complaint_date`, `complaint_type` (COMPLAINT / RETURN), `customer_id` (wajib), `contact_id`, `contact_person`, `received_via`, `category`, `needs_qa`, `co_order_id` (FK ke `co_order`), `ref_doc_no`, `description`, `requested_action`, `resolution`, `resolution_note`, `stat_confirm`+`confirmed_at/by`, `stat_close`+`closed_at/by`, `stat_cancel`+`cancel_reason`. | FAKTA, `show columns`; `key_column_usage` |
| Baris | `crm_complaint_item`: 4 baris (FK CASCADE dari header). `product_id`, `product_name`, `qty_complaint`, `qty_return`, `qty_unit`, `defect_note`. | FAKTA, `show columns`; `CrmComplaints.php:198-203` |
| Nilai | Kategori: QUALITY, QUANTITY, DELIVERY, WRONG_ITEM, DOCUMENT, SERVICE, OTHER. Permintaan: REPLACE, REWORK, PRICE_CUT, REFUND, EXPLANATION. Penyelesaian: REPLACED, REWORKED, PRICE_CUT, REFUNDED, EXPLAINED, NOT_ACCEPTED. | FAKTA, `CrmComplaint.php:17-25` |
| Status | `status()`: CANCELLED bila `stat_cancel`, CLOSED bila `stat_close`, CONFIRMED bila `stat_confirm`, selain itu DRAFT. Data: Draft 1, Confirmed 2, Closed 1, Cancelled 0. | FAKTA, `CrmComplaint.php:59-62`; query |
| Confirm | Hanya dari Draft. Syarat: deskripsi terisi; minimal satu produk; tiap produk `qty_complaint > 0`; untuk RETURN jumlah `qty_return` > 0; `qty_return` tidak melebihi `qty_complaint`. Nomor `CC` + 2 digit tahun + 5 digit urut lahir saat Confirm. | FAKTA, `CrmComplaints.php:136-172`; `CrmComplaint.php:70-76` |
| QA | `needs_qa` = 1 bila kategori QUALITY (dipaksa) atau dicentang. Komplain Confirmed yang `needs_qa` muncul di meja QA; jawaban QA = `qc_complaint_review` (1 baris per komplain, UNIK `complaint_id`, `verdict`, `root_cause`, `corrective_action`, `preventive_action`, `disposition`, `stat_confirm`). Tahap: NONE / WAITING / ANSWERED. | FAKTA, `CrmComplaints.php:96,225-240`; `CrmComplaint.php:64-68`; `show columns qc_complaint_review` |
| Close | Hanya dari Confirmed; bila tahap QA masih WAITING ditolak; `resolution` wajib. | FAKTA, `CrmComplaints.php:176-187` |
| Cancel | Hanya dari Confirmed, alasan wajib (maks 255 karakter). Draft tidak dibatalkan melainkan dihapus permanen (`delete`, hanya Draft; baris ikut terhapus). | FAKTA, `CrmComplaints.php:188-203` |
| Tes | `test_complaint_goes_to_qa_and_is_closed_by_sales_after_the_answer`, `test_complaint_without_qa_is_cancelled_with_a_reason_and_draft_can_be_deleted` lulus. | FAKTA, `CrmTest` dijalankan |
| Data | 4 komplain (RETURN 1, COMPLAINT 3); `co_order_id` kosong di semuanya; `ref_doc_no` memuat nomor numerik sistem lama (mis. `10262643`). QA: 1 baris `qc_complaint_review`, Confirmed. | FAKTA, query hari ini |
| Harga | Tidak memuat harga/HPP. | FAKTA, `show columns` |

## Relasi
- **sumber**: input Sales; master customer dan produk (lintas-DB, query terpisah); CO baru (`co_order_id`, hanya CO tidak-batal, 200 terbaru: `CrmComplaints.php:61-63`) atau nomor dokumen lama (`ref_doc_no`).
- **dipakai_oleh**: meja QA (Production > Complaint Review), Customer 360 (tab Complaints, hitungan komplain terbuka), Customer Invoice (retur; BELUM dicek).
- **mengalir**: Draft -> Confirm (nomor lahir; bila `needs_qa` muncul di QA) -> QA menjawab -> Sales Close dengan penyelesaian, atau Cancel dengan alasan.
- **zona beku**: flow dokumen (aturan beku semua alur). Aturan A.21b: informasi komplain ke Sales, mutu ke QA (DARI DOKUMEN, indeks ringkas).

## Temuan (dicatat, tidak diperbaiki)
1. **Tanpa waktu/pelaku Cancel**: ada `confirmed_at/by` dan `closed_at/by` tetapi tidak `cancelled_at/by`; log memakai `updated_at` dan `updated_by` untuk baris "Cancelled" (`CrmComplaints.php:215`), sehingga waktu batal bergeser bila baris diubah lagi. FAKTA.
2. **Tautan ke CO baru kosong**: semua komplain mengacu `ref_doc_no` numerik sistem lama tanpa FK; CO baru tidak ditautkan (0 dari 4). FAKTA.
3. **Pesan galat `act()` sebagian tidak lewat `t()`**: hanya daftar galat Confirm yang dibungkus `t()` (baris 168); pesan status dan pesan Close/Cancel mengembalikan string Inggris langsung (baris 165, 176, 179-192). FAKTA (kode); dampak ke pengguna bahasa lain belum diuji.
4. **Status `stat_confirm` ganda arti**: `crm_complaint.stat_confirm` (Sales) dan `qc_complaint_review.stat_confirm` (QA) bernama sama pada dua tabel; `qaStage()` membedakan lewat relasi. FAKTA.
5. **Referensi Customer Invoice untuk retur**: BELUM dicek apakah Sales Return mengubah `qty_invoiced`/tagihan.

## Sudah ada? Dicari di mana
Dicari: `crm_complaint*`, `qc_complaint_review`, `CrmComplaint*`, `CrmComplaints`, menu Sales > CRM dan Production, `doc_state_log`. Hasil: SUDAH ADA; tidak perlu tabel baru. Dokumen siklus Confirmed/Cancelled/Closed terpenuhi (A.12) di kartu ini.
