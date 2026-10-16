# Kartu konsep: Sales PIC (Sales Person)

> Kelompok B, 2026-10-16. FAKTA = dilihat sendiri (DB mockup `ddtmst`/`ddtsdi`, kode dengan baris).

- **Modul/sub-modul**: Master Data (layar `md-sales-person`), dipakai Sales > Inquiry & Quotation, Customer Order, KPI.
- **Nama kanonik**: Sales PIC = `tm2mst.sales_person` (label layar "Sales Person"); di dokumen tersimpan sebagai NAMA teks.
- **Sinonim**: PIC Sales, salesman, sales_pic, pic_sales, agen/freelance (EXTERNAL).
- **BUKAN sama dengan**: user login (`sec_user`), pegawai (`emp_employee`; hanya tautan opsional `employee_id`).

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Master | `sales_person` 5 baris, semua aktif: Johannes Iwan, Siti Rahma, Budi Santoso (INTERNAL); Agen Mitra Jaya (freelance), CV Perantara Cetak (EXTERNAL). `employee_id` NULL di kelimanya. Kolom: `sales_name`, `sales_type` (INTERNAL/EXTERNAL), `employee_id`, `phone`, `activate`. | FAKTA, query 2026-10-16; `show columns` |
| Layar input | `md-sales-person` (`SalesPersonLookup`): nama yang sudah dipakai dokumen tidak bisa diganti, hanya dinonaktifkan; menu muncul dua kali (id 1182 Master Data, id 1192 Sales > CRM > Master Data). | FAKTA, `SalesPersonLookup.php:10-37`; `sys_app_menu` |
| Inquiry | Lookup `sales_person` di Inquiry New; bawaan = NAMA pengguna login (`auth()->user()->name`); disimpan ke `iqp_inquiry.pic_sales` varchar(100) sebagai teks, bukan id. | FAKTA, `inquiry-new.blade.php:47`, `Lookups.php:17-18`, `Sales.php:35-40` |
| Quotation | `quo_quotation.sales_pic` diisi dari `pic_sales` inquiry pertama; boleh diubah (field header). | FAKTA, `QuotationBuilder.php:72`, `Data/Quotation.php:24` |
| Customer Order | `co_order.sales_person_id` (id) diisi dengan mencocokkan NAMA `pic_sales` ke `sales_person.sales_name`; tidak cocok -> NULL ("daripada menebak"). | FAKTA, `CoOrderBuilder.php:289-299` |
| KPI | Target per sales (`SysKpiTarget.sales_person_id`) dipakai papan angka; pilihan nama dari `sales_person`. | FAKTA bahwa `Board/Numbers.php:152-163,277-288` merujuk; angka KPI BELUM dicek |
| Data di dokumen | `iqp_inquiry.pic_sales`: Firman 17, Budi Tester 1, Demo Preview 1, kosong 4. `quo_quotation.sales_pic`: Firman 13, nama yang cocok master 5 (Johannes Iwan, Budi Santoso, Siti Rahma, CV Perantara Cetak, Agen Mitra Jaya), kosong 2. `co_order.sales_person_id`: 0 dari 25 terisi. | FAKTA, query 2026-10-16 |

## Relasi
- **sumber**: master `sales_person` (diisi user), nama bawaan dari akun login.
- **dipakai_oleh**: Inquiry, Quotation, Customer Order, KPI/Win-Rate Sales, cetak Inquiry ("Sales").
- **mengalir**: Inquiry `pic_sales` (teks) -> Quotation `sales_pic` (teks) -> CO `sales_person_id` (id, via pencocokan nama). Satu orang disimpan dalam dua bentuk (teks dan id); tautannya hanya kesamaan ejaan.
- **zona beku**: Inquiry (field header). Hanya dicatat.

## Temuan (dicatat, tidak diperbaiki)
1. **Tidak ada satu pun `pic_sales` Inquiry yang cocok dengan master**: 19 baris non-NULL, semuanya berisi nama yang tidak ada di `sales_person` ("Firman", "Budi Tester", "Demo Preview"). Akibatnya `co_order.sales_person_id` NULL untuk seluruh 25 CO dan KPI per sales tidak bisa menghitung dari CO.
2. **Bawaan layar = nama login**, bukan pilihan dari master; bila nama login tidak ada di `sales_person`, nilai tetap tersimpan (validasi hanya `string|max:100`, `InquiryPayload.php:18`), bukan `exists`. Itu yang menjelaskan temuan 1; perilaku komponen lookup di layar TIDAK diuji.
3. **Pencocokan nama sebagai kunci** rapuh (spasi, ejaan); kode sendiri mengakui "teks bebas (bukan FK)" (`CoOrderBuilder.php:293-296`). Perubahan ke id = perubahan skema Inquiry (zona beku): butuh izin Jo.
4. **`employee_id` kosong untuk 3 sales INTERNAL**: tautan ke pegawai belum dipakai; jangan mengisi dari `employee_id=1`.
## Sudah ada? Dicari di mana, hasil
Dicari: `sales_person`, `pic_sales`, `sales_pic`, `sales_person_id` di `ddtmst`, `ddtsdi`, `ddtsys` (`information_schema`) dan `grep` `app/`, `resources/views/v2`. Hasil: konsep SUDAH ADA (master + tiga tempat simpan). Tidak perlu tabel baru; yang kurang hanyalah kesepakatan satu bentuk simpan.

