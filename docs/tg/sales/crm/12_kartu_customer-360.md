# Kartu konsep: Customer 360

> Ditulis 2026-10-16 (kelompok A). FAKTA = DB mockup (`ddt*` lewat `tm2*`, `--env=mockup`) dan kode bernomor baris (`app/Support/V2/Data/Crm.php`, 244 baris, dibaca penuh). `CrmTest::test_customer_360_profile_reads_one_customer` lulus (bagian 5 tes `CrmTest`, 52 assert).

- **Jenis dokumen**: kartu konsep (layar ringkasan, bukan dokumen; tidak punya tabel sendiri). **Modul/sub-modul**: Sales > CRM > Customer 360 (`CRM_CUST360`, `v2:crm-customer`, FAKTA `sys_app_menu`).
- **Nama kanonik**: Customer 360 = kelas `App\Support\V2\Data\Crm` (daftar `rows()`, halaman satu customer `profile()` / `card()`), hanya membaca.
- **Sinonim**: profil customer, tampilan 360, relationship view, segmen customer (Active / Sleeping / Prospect / No order).
- **BUKAN sama dengan**: Master Customer (`cust_customer`, tempat data diubah), CRM Activity, CRM Complaint. Customer 360 hanya merangkum (aturan A.21b: "Customer 360 hanya membaca").

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Sumber data | Membaca: `tm2mst.cust_customer` (+kontak, cabang, termin), `tm2sdi.quo_quotation` + item + inquiry, `co_order` (baru), `co_order_legacy` + `_detail` (arsip), `do_delivery`, `co_delivery_legacy`, `crm_activity`, `crm_complaint`. Lintas-DB lewat query terpisah dicocokkan di PHP. Tidak ada tabel sendiri. | FAKTA, `Crm.php:17-19,38-60,97-149` |
| Segmen | Dari tanggal order terakhir (maksimum dari arsip non-CANCELLED dan CO baru dengan `stat_cancel=0`): kosong -> `Prospect` bila `is_prospect` selain itu `No order`; lebih lama dari `CRM_SLEEPING_DAYS` (180 hari) -> `Sleeping`; selain itu `Active`. Hasil hitung dengan aturan yang sama atas 882 customer aktif di mockup hari ini: Active 165, Sleeping 545, No order 172, Prospect 0 (tidak ada customer `is_prospect=1`). | FAKTA, `Crm.php:32-35,75-82`; query SQL yang meniru aturan |
| Daftar | Satu baris per customer aktif: order 12 bulan terakhir, quotation terbuka (PUBLISHED belum WIN/LOST/batal), CO terbuka (DRAFT/SUBMITTED/CONFIRMED), kontak terakhir, follow-up berikutnya dan warnanya, komplain terbuka (Confirmed belum Closed/Cancelled). Urutan: follow-up terlambat/hari ini/segera dulu, lalu yang punya komplain terbuka, lalu order 12 bulan terbanyak. | FAKTA, `Crm.php:38-73,84-89` |
| Halaman customer | Tab Overview, Activities, Quotations, Orders, Deliveries, Complaints, Contacts; angka kunci First/Last Order, Orders (all time), Order Value, Last Contact; grafik 24 bulan; 15 product paling sering dipesan (arsip + baru). Tombol: + Activity, + Complaint, Master Customer, Back. | FAKTA, `Crm.php:151-243` |
| Batas daftar | Tab dimuat dengan batas: quotation 100, order baru 100, arsip order 200, DO 100, arsip surat jalan 100; angka ringkasan dihitung terpisah (tanpa batas). | FAKTA, `Crm.php:103-107` |
| Harga | Nilai rupiah hanya bila `Auth::user()->canViewSalesPrice()` (A.9): `Order Value`, total per baris order/quotation, nilai per bulan pada grafik; selain itu `null` dan kolom disembunyikan. Disaring di server di `profile()`, bukan di tampilan. | FAKTA, `Crm.php:27-30,117-128,138-139,169,205-207` |
| Data pendukung | Konstanta `CRM_SLEEPING_DAYS` = 180, `CRM_FOLLOWUP_SOON_DAYS` = 2. Arsip order lama 20.669 baris (jumlah keempat kelompok) (CONFIRMED 18.079, DRAFT 2.354, UNKNOWN 120, CANCELLED 116). | FAKTA, `sys_constant`; query |

## Relasi
- **sumber**: Master Customer; Inquiry/Quotation; CO (baru dan arsip); DO dan Surat Jalan (baru dan arsip); Activity; Complaint. Lihat kartu masing-masing.
- **dipakai_oleh**: pengguna Sales (layar saja); tombol pintas ke Activity, Complaint, Master Customer.
- **mengalir**: tidak menulis data apa pun. Tidak ada engine; tidak ada jadwal; tidak ada pemanggil lain di `routes/console.php`.
- **zona beku**: tidak ada (hanya baca); flow dokumen sumbernya beku.

## Temuan (dicatat, tidak diperbaiki)
1. **Arsip DRAFT dan UNKNOWN dihitung sebagai order** untuk segmen dan "Last Order": hanya CANCELLED yang dikecualikan (`Crm.php:42,110,156`). Arsip memuat 2.354 DRAFT dan 120 UNKNOWN; bisa membuat customer tampak Active padahal tidak ada order sah. Dampak ke segmen tidak dihitung. FAKTA (kode+data).
2. **CO REJECTED terhitung order**: filter `stat_cancel = 0` (`Crm.php:44,112,158`) tidak mengecualikan `doc_status=REJECTED` (kartu CO temuan 2); sebaliknya CO2600021 yang CONFIRMED tetapi `stat_cancel=1` dianggap batal. FAKTA.
3. **Segmen Prospect tak terjangkau di data**: 0 customer `is_prospect=1` di mockup, jadi cabang Prospect belum pernah teruji dengan data. FAKTA.
4. **Daftar terpotong diam-diam** pada customer dengan banyak dokumen (batas 100/200) tanpa penanda "ada lagi". DARI KODE (baris 103-107), tidak diuji di layar.
5. **Beban baca**: `rows()` membaca seluruh customer aktif (882) dan seluruh follow-up terbuka tanpa batas pada tiap pembukaan. Waktu muat tidak diukur.
6. Tanggal arsip `co_date` diperlakukan sebagai teks (`substr(…, 0, 10)`), tipe kolom arsip tidak diperiksa di sesi ini. DARI KODE.

## Sudah ada? Dicari di mana
Dicari: `Crm`, `CrmActivities`, `CrmComplaints`, `x-mp::sales.customer-card`, menu Sales > CRM, rute `v2.crm.*`. Hasil: SUDAH ADA; tidak perlu tabel baru (aturan "Customer 360 hanya membaca" terpenuhi: tidak ada penulisan di `Crm.php`).
