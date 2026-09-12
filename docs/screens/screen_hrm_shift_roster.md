---
name: screen_hrm_shift_roster
description: Roster Shift Mingguan — papan per-shift (drag pegawai = 1 minggu penuh) + Master Shift + cetak, mengikuti mockup_roster_shift.html.
---

# Roster Shift Mingguan & Master Shift

**File**: `resources/views/pages/hrm/shift-roster.blade.php`
**Cetak**: `resources/views/pages/hrm/shift-roster-print.blade.php`
**Route**: `hrm.shift-roster` (`/hrm/shift-roster`), `hrm.shift-roster.print` (`/hrm/shift-roster/print?mode=roster|shift&schedule=&week=`)
**Koneksi DB**: mst (tm2mst)
**Modul**: HRM
**Acuan UI**: `docs/mockups/mockup_roster_shift.html` (keputusan user 2026-09-12: "mockup untuk jadwal roster HARUS BISA", "termasuk semua detail dan behaviour nya", "include print out", "theme mengikuti pilihan", "workcenter dan department dari master yang ada di table")

## Tujuan
Menjadwalkan pegawai ke shift per MINGGU, bukan per tanggal satu-satu. Menggantikan
desain lama yang memisahkan "Papan PPIC" (mesin × tanggal × shift) dan "Grid HR"
(klik-siklus tanpa mesin) — sekarang satu model untuk semua departemen.

**Model penempatan**: 1 pegawai = 1 shift untuk 1 minggu penuh di dalam 1 Jadwal.
Satu penempatan ditulis menjadi **1 baris `emp_shift_roster` per hari aktif shift**
(hari libur shift tidak dibuatkan baris), sehingga
`AttendanceCalculationService::resolveShiftDetail()` — yang cuma membaca
`employee_id` + `roster_date` + `shift_id` — tetap bekerja tanpa perubahan.

## Struktur layar
2 view dipilih lewat tab di bar atas:
1. **Roster Mingguan** — bar Jadwal (pilih/buat/edit/hapus/cetak), papan
   baris-per-shift, panel Pool Pegawai di kanan yang di bagian atasnya memuat
   filter **Departemen → Unit Kerja**.
   Revisi user 2026-09-12: 3 kotak statistik (Pool Tampil/Terjadwal/Belum)
   **dihapus**, dan filter Departemen/Unit Kerja **dipindah** dari strip di atas
   papan ke atas daftar pegawai (karena filter itu yang menentukan isi Pool).
   Angka "belum terjadwal"/"terjadwal" tetap ada sebagai 1 baris info di panel itu.
2. **Master Shift** — kartu per shift: kode, nama, warna, kuota/unit, total jam/minggu,
   grid 7 hari (jam masuk/pulang/istirahat/LIBUR), aksi Ubah/Duplikat/Nonaktifkan/Hapus.

### Papan (per baris shift)
- Kolom kiri: titik warna + nama shift, ringkasan jam digabung ("Sen-Jum 05:00-13:15",
  "Sab ..."), `n hari · Xj/minggu`, bar kuota `n/kuota` (warna: kurang=warn, penuh=ok,
  lewat=bad), badge **"jam kosong"** kalau shift belum punya `emp_shift_detail`.
- Kolom kanan: strip 7 hari berisi jam masuk/pulang per hari (`+1` bila lintas hari)
  atau LIBUR, lalu area chip pegawai (drop zone).
- Chip: avatar inisial, nama, kode+jabatan, badge jumlah hari, tombol × (lepas).
  Chip bergaris/berlatar kuning + ⚠ bila pegawai belum bersertifikat unit tsb.
- Dobel-klik kolom kiri = buka modal ubah shift.
- Tombol **👁** di kolom kiri = **sembunyikan shift** dari papan. Shift tersembunyi
  dikumpulkan di bar kuning di atas papan (klik nama = tampilkan lagi, atau
  "Tampilkan semua"), diberi peringatan merah bila shift yang disembunyikan masih
  punya penempatan di minggu tsb. Kartu di view Master Shift ikut menandai
  "disembunyikan" + punya tombol tampil/sembunyi yang sama.
  **Setelan ini per user dan ikut terbawa ke cetakan** (lihat bagian Cetak).

### Pool Pegawai
Pencarian nama/kode, kelompok **Jabatan / Skill Unit Kerja / Golongan**
(golongan = `org_position.level`), checkbox "Hanya yang punya skill unit ini",
header grup bisa collapse + centang-semua-grup, bar seleksi ("N pegawai dipilih",
"Pilih semua tampil", "Batal") yang bisa di-drag sebagai satu blok.
Menyeret item yang sudah tercentang ikut membawa seluruh seleksi.

## Aksi / Method utama
- `goWeek(±1)` — geser minggu, toast status isi/kosong minggu tujuan.
- `assignToShift(shiftId, employeeId|null, fromBoard)` — inti penempatan.
  `employeeId=null` → pakai `$selection` (drag blok / bulk). `fromBoard=true` →
  pindah antar shift (lepas dulu lalu tulis ulang). Guard: roster terkunci,
  belum pilih unit, **shift tanpa hari aktif**, dan invarian 1 orang 1 shift/minggu
  (duplikat dilewati + dilaporkan di toast). Kuota lewat DIIZINKAN tapi diberi
  peringatan (sama seperti mockup).
- `removePlacement(employeeId)` — hapus seluruh baris minggu itu.
- `saveDraft()` / `confirmBatch()` / `unlockBatch()` — lifecycle DRAFT → CONFIRMED
  per (schedule, minggu) di `emp_shift_roster_batch`, `attachBatchToWeek()` menandai
  `batch_id` semua baris minggu tsb. **Sengaja dipertahankan** walau mockup tidak
  punya, karena roster ini jadi sumber jam kerja attendance/payroll.
- Jadwal: `openScheduleModal(edit)`, `saveSchedule()`, `deleteSchedule()` (hapus jadwal
  ikut melepas seluruh baris roster-nya, dikonfirmasi lewat modal + hitungan baris).
- Master Shift: `openShiftModal(id|null)`, `fillDays(5|6)` ("Samakan Sen-Jum/Sen-Sab"),
  `saveShift()`, `duplicateShift()`, `deleteShift()` (tolak bila masih dipakai),
  `toggleShiftActive()` (tolak menonaktifkan bila masih dipakai).
- Rotasi: `openRotate()` + `rotatePreview` + `runRotate()` — salin roster antar minggu
  dengan pola **swap 2 shift / rotasi berputar / salin sama persis**, cakupan
  seluruh unit atau 1 unit, opsi timpa, pratinjau jumlah penempatan + bentrok.
- Visibilitas shift: `toggleShiftVisibility(shiftId)`, `showAllShifts()`,
  computed `visibleShifts` (= `shifts` minus `hiddenShifts`), `hiddenWithPlacements()`.
  Hide/show adalah setelan TAMPILAN: data penempatan shift tersembunyi tetap utuh,
  dan operasi data (assign, rotasi, cek kuota) tetap memakai daftar shift penuh.
- Cetak: link ke `hrm.shift-roster.print` (mode `roster` = definisi shift + penempatan
  per unit kerja; mode `shift` = hanya master definisi shift), pakai `components/print/*`.

### Penyimpanan setelan hide/show (per user)
Menumpang tabel preferensi yang sudah ada: `tm2sys.sys_grid_preference` dengan
`grid_key = 'hrm.shift-roster.hidden-shifts'`. Isi kolom JSON `visible_columns`
untuk key ini adalah daftar **`shift_id` yang DISEMBUNYIKAN** (bukan yang tampil) —
dipilih begitu supaya shift yang baru dibuat otomatis ikut terlihat tanpa perlu
diatur ulang. Halaman cetak membaca baris yang sama milik user yang sedang login,
jadi dokumen cetak tidak mungkin melenceng dari papan yang dilihat user; kalau ada
shift yang disembunyikan, cetakan membubuhkan catatan jumlahnya di bawah tabel
definisi jam.

## Tabel & Model
| Tabel | Peran |
|---|---|
| `emp_shift_roster` | baris roster per pegawai per TANGGAL (kolom baru `work_center_id`, `schedule_id`) |
| `emp_shift_roster_batch` | lifecycle DRAFT/CONFIRMED (kolom baru `schedule_id`) |
| `emp_shift` | katalog shift global (kolom baru `shift_code`, `color_hex`, `capacity_per_wc`; `department_id` kini nullable) |
| `emp_shift_detail` | jam per hari (kolom baru `break_minutes`) |
| `emp_work_center` | **baru** — unit kerja: 1:1 dari `prod_machine` untuk mesin, atau unit non-mesin per departemen |
| `emp_roster_schedule` + `..._department` | **baru** — "Jadwal" + cakupan departemennya |
| `emp_employee_work_center_skill` | **baru** — sertifikasi pegawai per unit kerja |
| `emp_employee`, `org_department`, `org_position`, `prod_machine` | master sumber (tidak diubah) |

Migration: `2026_09_12_000001_create_roster_workcenter_schedule_tables.php`,
`..._000002_seed_roster_workcenter_schedule_data.php`,
`..._000003_make_roster_division_nullable.php`.

## Tema
Layar ini theme-aware: blok token CSS lokal (`--rs-*`) di root diisi dari
`App\Support\ActiveTheme::resolve()` (mode terang/gelap + `primary_color`),
pola sama dengan `production/prodev.blade.php`. Tidak ada warna surface/teks
yang di-hardcode.

## Catatan & temuan
- **4 dari 8 shift di DB belum punya `emp_shift_detail`** (TDP1, TDP2, IND1, IND2) →
  0 hari aktif. Tanpa penjagaan, penempatan ke shift seperti ini menghasilkan 0 baris
  (seolah berhasil padahal kosong). Sekarang ditolak + diberi badge "jam kosong".
  Jam sebenarnya harus diisi HR di Master Shift — sengaja tidak dikarang di seeder.
- Pemetaan mesin → departemen di seeder pakai pencocokan kata kunci nama mesin
  (`prod_machine` tidak punya `department_id`). Kalau ada yang nyasar, koreksi datanya
  di `emp_work_center`, bukan di kode.
- Skill pegawai di-seed = semua unit kerja di departemennya sendiri (asumsi netral,
  bukan spesialisasi karangan). HR yang mempersempit.
- `production/scheduling-board.blade.php` ikut disesuaikan: dropdown "operator manual"
  sekarang hanya menerima roster unit non-mesin yang departemennya ada di divisi
  OFFSET, supaya staf non-produksi yang kini bisa dijadwalkan tidak bocor ke papan
  produksi.
- Fitur **Tukar Shift antar pegawai (ESS)** yang ditandai "NOT-IMPLEMENTED" di komentar
  mockup ternyata SUDAH ADA di `self-service/index.blade.php` (`emp_shift_swap_request`).
  Komentar di mockup itu usang — jangan dibangun ulang di layar ini.
