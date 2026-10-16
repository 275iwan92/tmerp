# Kartu konsep: CRM Activity (aktivitas dan follow-up Sales)

> Ditulis 2026-10-16 (kelompok A). FAKTA = DB mockup (`ddt*` lewat `tm2*`, `--env=mockup`) dan kode bernomor baris, dilihat sesi ini. Tes `CrmTest` dijalankan dengan `--env=mockup`: 5 lulus, 52 assert; jumlah baris `crm_*` sama sebelum dan sesudah.

- **Jenis dokumen**: kartu konsep. **Modul/sub-modul**: Sales > CRM > Activities & Follow-up (`CRM_ACTIVITY`, `v2:crm-activity`, FAKTA `sys_app_menu`).
- **Nama kanonik**: CRM Activity = `tm2sdi.crm_activity`: satu catatan kontak Sales dengan customer (telepon, kunjungan, WhatsApp, e-mail, rapat), beserta tindak lanjut (follow-up).
- **Sinonim**: aktivitas Sales, kontak customer, follow-up, tindak lanjut, call log, `QuoActivity` (nama kelas lama untuk follow-up quotation).
- **BUKAN sama dengan**: Complaint (kartu `11_kartu_crm-complaint.md`), log status dokumen, `doc_state_log`.

## Fakta
| Butir | Isi | Bukti |
|---|---|---|
| Tabel | `crm_activity`: 15 baris, 8 customer. Kolom: `customer_id` (wajib), `contact_id`, `quotation_id` (FK ke `quo_quotation`, boleh kosong), `activity_type`, `topic`, `activity_date`, `contact_person`, `result`, `notes`, `next_action_date`, `next_action`, `next_done`, `next_done_at`, `next_done_by`. PK `id_activity`. | FAKTA, `show columns`; `key_column_usage` |
| Nilai | `activity_type`: CALL, VISIT, WHATSAPP, EMAIL, MEETING, OTHER. `topic`: QUOTATION, ORDER, PROSPECT, COMPLAINT, PAYMENT, RELATION. `result`: POSITIVE, NEUTRAL, NEGATIVE, NO_ANSWER. | FAKTA, `CrmActivity.php:14-18` |
| Follow-up terbuka | `next_action_date` terisi dan `next_done = 0`. Data: 12 baris punya tanggal tindak lanjut. Selesai lewat tombol Follow-up Done, atau dengan mencatat aktivitas baru dari follow-up itu (Log Result). | FAKTA, `CrmActivity.php:59-62`; `CrmActivities.php:11-13,114-155`; query |
| Warna jatuh tempo | Hari kerja: lewat = merah ("Late n days"), hari ini = oranye, H-1..H-n = kuning (n = konstanta `CRM_FOLLOWUP_SOON_DAYS`, bernilai 2), lebih jauh = polos; selesai = "Done". Pola sama dengan CPDEL. | FAKTA, `CrmActivities.php:46-74`; `sys_constant` |
| Hak ubah | Pembuat catatan atau admin yang boleh mengubah/menghapus; selain itu 403. | FAKTA, `CrmActivities.php:106-111,156-160` |
| Penyaringan masukan | Jenis, topik, hasil di luar daftar diganti OTHER / RELATION / NULL; kontak dan quotation hanya dari pilihan milik customer itu. | FAKTA, `CrmActivities.php:114-135` |
| Asal quotation | Layar Quotation memakai kelas turunan `QuoActivity` pada tabel yang SAMA; `customer_id` otomatis dari quotation bila tidak dikirim. | FAKTA, `QuoActivity.php:5-7`; `CrmActivity.php:33-41` |
| Harga | Tidak memuat harga. | FAKTA, `show columns` |

## Relasi
- **sumber**: input Sales; master customer dan kontak (`cust_customer`, `cust_customer_contact`, lintas-DB query terpisah); `quo_quotation` (FK dalam `tm2sdi`).
- **dipakai_oleh**: Customer 360 (kartu `12_kartu_customer-360.md`), form Quotation (follow-up), Win-Rate Sales (BELUM dicek apakah memakai aktivitas).
- **mengalir**: catat aktivitas -> bila ada tindak lanjut jadi follow-up terbuka -> selesai (Done atau Log Result) -> Customer 360 menampilkan kontak terakhir dan follow-up berikutnya.
- **zona beku**: flow dokumen (aturan beku semua alur). Aktivitas bukan dokumen berstatus; tidak ada `stat_confirm`.

## Temuan (dicatat, tidak diperbaiki)
1. **Tabel `quo_activity` sudah tidak ada** (error `Base table or view not found` dari `ddtsdi`), tetapi kelas `QuoActivity` dan dokumen kode ("Perluasan quo_activity") masih menyebutnya. Kelas turunan dengan daftar label ikon sendiri berbeda dari `CrmActivity::TYPES` (dua daftar jenis aktivitas). FAKTA.
2. **Dua daftar jenis aktivitas**: `QuoActivity::TYPES` (pasangan ikon dan label) dan `CrmActivity::TYPES` (label saja) memuat jenis yang sama (CALL, VISIT, WHATSAPP, EMAIL, MEETING, OTHER); duplikasi. FAKTA (kode).
3. **Tanpa jejak perubahan**: aktivitas diubah langsung (tidak ada riwayat edit); hanya `updated_by/at`. FAKTA (tidak ada tabel log).
4. **Data dummy**: 6 dari 15 aktivitas tanpa kontak, 9 tanpa quotation (sah, kolom opsional). Tidak ada temuan integritas lain. FAKTA.
5. **Aturan A.21b** ("`crm_activity` per customer") terpenuhi: `customer_id` NOT NULL. FAKTA.

## Sudah ada? Dicari di mana
Dicari: `crm_*`, `quo_activity`, `QuoActivity`, `CrmActivit*`, menu Sales > CRM, rute `v2.crm.*`. Hasil: SUDAH ADA; tidak perlu tabel baru. Tes terkait: `CrmTest::test_follow_up_is_coloured_by_due_date_and_closed_by_logging_its_result`, `test_quotation_follow_up_takes_the_customer_of_its_quotation` (lulus).
