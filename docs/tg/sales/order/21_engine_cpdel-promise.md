# Kartu ENGINE: CpdelPromiseService (dua janji CPDEL)

> Ditulis 2026-10-16 (kelompok A). Sumber: `app/Services/Sales/CpdelPromiseService.php` (254 baris) dibaca penuh, `app/Support/V2/Data/Cpdel.php`, rute `routes/v2.php:1608-1650`. Tes dijalankan: `CpdelDueColourTest` (2 lulus, 15 assert). Tabel/kolom: lihat kartu konsep `11_kartu_cpdel.md`. Tidak ada bagian rahasia (tanpa harga/HPP/margin).

- **Nama kanonik**: CpdelPromiseService (engine "CPDEL Two Promises"). **Kelas**: `App\Services\Sales\CpdelPromiseService` (final, method statis). **Registry**: key `cpdel-promise`, mode `flow`, grup Sales Order. **Modul/sub-modul**: Sales > Order > Customer Plan Delivery.
- **Status beku**: flow CPDEL beku. Engine ini mencatat kesepakatan manusia; ia tidak memutuskan jadwal (A.7).

## Pemicu (tidak ada background / jadwal)
Dicari di `routes/console.php` (tidak ada), observer, job, dan semua pemanggil. FAKTA: hanya dari layar CPDEL.
| Method | Pemicu | Berkas:baris |
|---|---|---|
| `state`, `gapDays`, `due` | Layar render grid dan form | `Cpdel.php:56,67,72` (grid), `Cpdel.php:156,163` (form) |
| `slotProblems` | Form baris, menampilkan masalah slot | `Cpdel.php:185` |
| `commitReady` | POST `v2.cpdel.ready` (PPIC) | `routes/v2.php:1614-1615` |
| `reschedule` | POST `v2.cpdel.reschedule` (Sales) | `routes/v2.php:1620-1621` |
| `customerRejected` | POST `v2.cpdel.reject` (Sales) | `routes/v2.php:1626-1627` |
| `opsDecision` | POST `v2.cpdel.decide` (Ops Manager) | `routes/v2.php:1632-1633` |
| `merge` | POST `v2.cpdel.merge` (Sales) | `routes/v2.php:1638-1641` |
| `canPlan/canSell/canDecide` | Gerbang rute dan tampilan tombol | `routes/v2.php:1614,1620,1626,1632,1638`; `Cpdel.php:167,186,201` |
| `STATES` | Warna badge grid | `app/Support/V2/Grid/Sales/CpdelListGrid.php:65` |

## Input / Output
- **Input**: satu `CoOrderDelivery`; isian form (tanggal, jam, alasan, saluran, kontak, catatan, qty split, keputusan); login pelaku; `sys_office_hour`, `sys_holiday`, `cust_receiving_hour` (lewat `DeliveryPromiseService::receivingHours`), `co_delay_reason`, konstanta `CPDEL_DUE_SOON_DAYS`.
- **Output (menulis ke DB)**: `co_order_delivery` (`ready_at/_source/_by`, `ship_time`, `original_date`, `escalated_at`, dan baris baru/hapus lewat `Cpdel::split/merge`), `co_delivery_negotiation` (satu baris log tiap aksi).
- **Hanya ditampilkan**: `state`, warna jatuh tempo (`due`), selisih hari (`gapDays`), daftar masalah slot.

## Alur (kembaran teks; diagram belum dibuat)
Status baris dihitung (tidak disimpan) oleh `state` (baris 38-50), urutan evaluasi:
1. `status = SHIPPED` -> SHIPPED.
2. `ship_date` ada dan lebih awal dari hari ini -> LATE.
3. `escalated_at` terisi -> ESCALATED.
4. `ready_at` kosong -> WAITING_PPIC.
5. `ready_at` lebih besar dari slot -> NEED_NEGOTIATION.
6. Selain itu -> ALIGNED.

Aksi (semua menolak bila baris bukan PLANNED, `assertOpen` baris 242-247):
1. **PPIC `commitReady`**: wajib `ready_date` + `ready_time`; bila lebih lambat dari slot, `reason_code` wajib. Menulis `ready_at`, `ready_source=MANUAL`, `ready_by`; log `READY_COMMITTED`.
2. **Sales `reschedule`**: wajib tanggal, jam, saluran (WhatsApp/Phone call/Email/Meeting), kontak customer; slot harus lolos `slotProblems`; alasan wajib kecuali pertama kali jam disepakati (tanggal tidak berubah, tanpa split, `ship_time` masih kosong). Bila baris sudah `posted_at`, tanggal dan qty terkunci (hanya jam). `original_date` diisi sekali dan tidak berubah lagi. Bila tanggal berubah atau split: `Cpdel::split`. `escalated_at` dikosongkan. Log `SLOT_TIME_SET`/`RESCHEDULED`/`SPLIT` (split = dua log).
3. **Sales `customerRejected`**: catatan wajib; `escalated_at = now()`; log `CUSTOMER_REJECTED`.
4. **Ops Manager `opsDecision`**: keputusan (Overtime, Re-prioritize production, Subcontract, Make customer a new offer) + instruksi wajib; `escalated_at` dikosongkan; `ready_at` dikosongkan kecuali keputusan NEW_OFFER (PPIC harus mengisi ulang); log `OPS_DECISION`.
5. **Sales `merge`**: `Cpdel::merge` lalu log `MERGED` pada baris yang masih ada.

Hak (baris 84-98): `canPlan` = admin atau modul PROD `can_input`; `canSell` = admin atau modul SALES `can_input`; `canDecide` = admin atau `maxMgmtLevel() >= 4`.

## Formula (satu tempat; apa adanya dari kode)
- `slot` (baris 33-36): `ship_date` + `ship_time`, tanpa jam = `23:59:00`.
- `gapDays` (baris 79-82): `ship_date.startOfDay` ke `ready_at.startOfDay` dalam hari, bertanda; positif = produksi lebih lambat dari janji.
- `due` (baris 55-76), dalam HARI KERJA (`WorkCalendar`): SHIPPED -> muted; tanpa `ship_date` -> LATER; slot < hari ini -> OVERDUE ("Late n days", n = `workdaysBetween`, minimum 1); slot = hari ini -> TODAY; selain itu `n = max(1, workdaysBetween(hari ini, slot))`, `n <= soon` -> SOON (kuning), lebih jauh -> LATER. `week` = 1 bila slot paling lambat hari ini + 6 hari kalender.
- `slotProblems` (baris 106-128): kosong = sah. Tanggal libur (`SysHoliday`), kantor tutup atau jam di luar jam kantor, customer tidak menerima atau jam di luar jam terima (dengan jeda istirahat).
- Contoh angka dari tes yang dijalankan (`CpdelDueColourTest`, hari ini = Jumat 02-Okt-2026, Sabtu kerja, Minggu libur, soon = 2): slot 29-Sep -> OVERDUE (merah, teks memuat 3 hari); slot 02-Okt -> TODAY (oranye); 03-Okt dan 05-Okt (Senin) -> SOON; 06-Okt -> LATER, tetapi SOON bila soon = 3; slot 15-Okt -> `week` 0; SHIPPED -> muted. `workdaysBetween(02-Okt, 05-Okt) = 2` dan hari besar 06-Okt tidak dihitung.

## Kasus tepi
- Server tidak memeriksa status asal untuk `customerRejected`, `opsDecision`, dan `commitReady`: semua baris PLANNED diterima walau layar hanya menawarkan tombolnya pada status tertentu (`Cpdel.php:199-202`). DARI KODE (baris 203-224), tidak diuji.
- `reschedule` tidak memeriksa tanggal baru >= hari ini; hanya `slotProblems` (libur/jam).
- `ESCALATED` kalah dari `LATE` (baris 44 sebelum 45): baris tereskalasi yang slotnya lewat tampil LATE dan tombol keputusan Ops tidak muncul (`Cpdel.php:201`). Lihat kartu CPDEL temuan 1.
- Jam default form PPIC 07:30, jam default slot = jam buka customer atau 08:00 (`Cpdel.php:189,195`); hanya isian awal form.

## Pemakai
Layar CPDEL (grid + form `x-mp::sales.promise-book`), `CpdelListGrid`. KPI ketepatan janji kirim tersendiri di `DeliveryPromiseService` (kartu belum ditulis; lihat sub-modul Delivery).

## Butir terbuka
Tidak ada nomor `ATTENTION-ENGINE` untuk CPDEL (lihat `docs/ENGINE-ATTENTION.md`, dicari 'cpdel'). Temuan kartu CPDEL nomor 1, 2, 5 relevan.

## Tes
`tests/Feature/CpdelDueColourTest.php` (2 tes: hitung hari kerja, warna baris; lulus). Terdaftar di registry. Tidak ada tes untuk `state`, `slotProblems`, `commitReady`, `reschedule`, `customerRejected`, `opsDecision`, `merge` (grep `tests/`: nol).
