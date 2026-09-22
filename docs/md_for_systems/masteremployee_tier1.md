# TIER 1 — MASTER EMPLOYEE + PERSONAL SELF-SERVICE (HRM) · BUSINESS REQUIREMENTS
**ERP Cetak Offset & Packaging — CV Telasih Makmur Group · Modul 08_security terkait (login) + HRM baru**

> **Catatan scope (KOREKSI 2026-09-22):** dokumen ini awalnya ditulis saat HR
> (recruitment/attendance/payroll penuh) resmi ditunda di luar 10 minggu aktif.
> **DEC-SCOPE-OPEN (2026-08-26, lihat `globalstarthere.md`)** mencabut
> pembatasan itu. Sejak itu ketiganya **SUDAH DIBANGUN AKTIF**, bukan lagi
> sekadar "boleh dikerjakan": Recruitment (`tm2hrr`) L1-L9 selesai & teruji,
> Payroll (`tm2pyr`) skema+enkripsi+lifecycle+PPh21 bulanan+GPS clock-in+slip
> gaji+transfer bank sudah live, Attendance punya layar presensi harian &
> roster nyata. **Semua kata "ditunda" di SISA dokumen ini adalah riwayat
> keputusan LAMA (sebelum 2026-08-26) — sengaja dibiarkan apa adanya sebagai
> catatan sejarah, BUKAN status terkini.** Untuk status Payroll/Attendance/
> Recruitment yang benar, cek Tier doc modul masing-masing atau
> `docs/LOG_JOB_DONE.md`, jangan percaya frasa "ditunda" di bawah ini.
> Modul Master Employee + Personal Self-Service (termasuk engine cuti) tetap
> fondasi yang harus jalan lebih dulu karena modul lain bergantung padanya.
>
> **2026-08-24 — Time Attendance: implementasi dicabut (REVERT), bukan lagi live.** §13 di bawah
> mendeskripsikan implementasi lama (`emp_attendance_log` + route `hrm.attendance.*`) yang sempat
> dibangun lalu di-revert hari yang sama karena bertentangan dengan rancangan baru yang jauh lebih
> detail di `docs/TIER_1_ATTENDANCE.md` (masih draft, menunggu review Telma). JANGAN pakai §13
> sebagai acuan implementasi ulang — lihat `masteremployee_state.yaml → time_attendance`.

---

## 1. Tujuan
Menyediakan **satu sumber kebenaran data pegawai** yang dipakai lintas modul, sekaligus **portal
mandiri** tempat karyawan mengelola data & pengajuannya sendiri, untuk:
- Autentikasi login (`users` ↔ `emp_employee`, 1 user WAJIB tertaut 1 employee).
- Signature/approval di dokumen lintas modul (Inquiry, Master Card, Quotation, dst).
- Referensi struktur organisasi untuk matriks approval di modul lain (data disiapkan, engine di luar scope).
- Data operasional produksi: shift & mesin (khusus Offset Printing), untuk kebutuhan PPIC.
- **Mengurangi beban entri HR** — karyawan mengelola sebagian datanya sendiri via pengajuan yang
  tetap terkontrol lewat approval, bukan HR mengetik semuanya secara manual.

## 2. Struktur Perusahaan: 1 CV, 3 SBU
CV Telasih Makmur Group = **1 entitas legal (CV)** dengan **3 SBU/unit bisnis**: Offset Printing,
Digital Printing, Indostry (furniture corrugated paper). Direpresentasikan sebagai `org_company`
(1 baris) + `org_division` (3 baris). Struktur organisasi:
```
org_company → org_division (3 SBU) → org_department → org_position
```
- `org_department.division_id` **NULLABLE** — departemen **shared/lintas-SBU** (mis. Security,
  Staff/Admin) menempel langsung ke company (`division_id` NULL), bukan ke 1 SBU tertentu.
  `company_id` tetap wajib selalu diisi.
- `org_level` = grade/tingkat jabatan lintas company (STAFF, SUPERVISOR, MANAGER, DIREKSI, dst),
  independen dari struktur company/division/department.
- `org_position` dipakai juga sebagai **titik konfigurasi kerahasiaan gaji** (lihat §7).
- Employee menempel ke 1 `position_id` aktif (current, bukan versioned — histori mutasi di luar scope).

## 3. Status Kepegawaian
4 tipe (`emp_contract.employment_type`): **TETAP** (`end_date` NULL), **KONTRAK** (PKWT, `end_date`
wajib), **HARIAN** (buruh lepas, `salary_type=PER_DAY`), **BORONGAN** (`salary_type=PER_UNIT`).

Dua status TAMBAHAN yang **BUKAN** tipe kontrak baru:
- **PROBATION** = flag `is_probation` + `probation_end_date` di `emp_contract` (menempel pada
  kontrak TETAP/KONTRAK yang berlaku, bukan baris terpisah). Lolos → `is_probation=0`. Gagal →
  nonaktifkan employee.
- **DIRUMAHKAN** = field `work_status` (WORKING/DIRUMAHKAN/CUTI) di `emp_employee`, **terpisah**
  dari `is_active` — employee tetap `is_active=1` saat dirumahkan.

Kontrak disimpan versioned (riwayat, bukan overwrite): hanya 1 baris `is_active=1` per employee.

## 4. Shift & Mesin (khusus produksi)
- **Hanya Offset Printing** memakai board PPIC drag-drop dengan assignment mesin presisi
  (`prod_machine` + `emp_shift_roster.machine_id` terisi). Digital Printing & Indostry pakai grid
  HR sederhana tanpa mesin.
- **Master shift** (`emp_shift` + `emp_shift_detail`) direplikasi dari struktur legacy nyata
  (`m_shifts`/`m_shifts_details`): 1 shift = 1 header + jam kerja **per hari** (Senin-Minggu bisa
  beda, mis. Sabtu lebih pendek) + **jam istirahat** (`break_out`/`break_in`). Shift terikat
  `department_id` (menggantikan `emp_type` legacy yang di-drop).
- **`prod_machine`** menyimpan spesifikasi teknis lengkap: jenis mesin, merk/model, tahun, no seri,
  max ukuran kertas, jumlah warna, kecepatan max, kapasitas/shift, gramatur min-max.
- Roster punya alur **Save (DRAFT, hanya PPIC) → Confirm (CONFIRMED, terkunci + published ke HR &
  portal karyawan) → Unlock (balik DRAFT)** via `emp_shift_roster_batch`.
- Konflik 2 tingkat: **HARD** (1 orang, mesin beda, shift+tanggal sama → tolak) vs **SOFT** (1
  orang, shift beda, hari sama → boleh + flag, tinjau manual).

## 5. Portal Personal Self-Service (BARU — inti perluasan scope)
Karyawan login sendiri mengelola profil & mengajukan perubahan/izin lewat portal terpisah. **Prinsip
mutlak:**
1. Portal **hanya bisa dibuka pemilik akun sendiri**.
2. Data pribadi **read-only** di sisi karyawan — semua perubahan lewat **pengajuan**, disetujui HR.
3. Semua pengajuan **memicu notifikasi ke HR** (modul `08_security/notification`).
4. Prinsip upah **"no present no pay"**, KECUALI Sakit (dengan surat dokter valid) dan Cuti — keduanya
   tetap dibayar.

Kategori pengajuan (detail lengkap di `selfservice_panduan.md`):
- **Waktu/Kehadiran**: Cuti Tahunan (lihat engine di §6), Sakit, Izin, Cuti Khusus Berbayar
  (menikah/melahirkan/dst), Tukar Shift (approval bertahap: rekan dulu, baru PPIC).
- **Perubahan Data**: koreksi ejaan (termasuk field sensitif Agama & Tanggal Lahir — WAJIB approval
  HR), **alamat KTP vs alamat Domisili disimpan terpisah** (kasus perantau), anggota keluarga
  (kelahiran baru, **meninggal ditandai bukan dihapus**, tambah pasangan), status pernikahan,
  rekening bank, kontak darurat.
- **Dokumen & Portofolio**: dokumen identitas (KTP/SIM/KK/SKCK/Passport/NPWP — dokumen upload-sendiri
  bisa diganti sendiri, dokumen upload-HR terkunci read-only di sisi karyawan), **Pendidikan &
  Pengembangan** dengan **nilai investasi employee** (biaya + dibiayai-oleh + ikatan dinas),
  keterampilan, **foto kartu pengenal** (ketentuan 4×6cm/600×900px/rasio 2:3, tervalidasi otomatis).
- **Resign**: pengajuan formal dengan tanggal efektif.
- **View-only**: Jadwal (hanya versi CONFIRMED), Kartu Pengenal digital, Saldo Cuti.

## 6. Engine Cuti (pengecualian scope eksplisit)
Payroll sudah dibangun sebagai modul terpisah (`tm2pyr`); **engine cuti** di file ini dibangun lebih dulu karena diminta eksplisit.
Parameter saat ini (BISA BERUBAH, disimpan sebagai data konfigurasi bukan hardcode):
| Aturan | Nilai saat ini |
|---|---|
| Jatah cuti tahunan | 12 hari kerja/tahun |
| Jarak minimum pengajuan | H+7 dari tanggal form dibuka (kalender hard-block, bukan sekadar warning) |
| Maksimum per pengajuan | 3 hari kerja (boleh ajukan lagi terpisah) |
| Hari bisa dipilih | Senin–Sabtu |
| Hari tidak bisa dipilih | Minggu + hari besar/libur nasional |

## 6b. SOP Perizinan Resmi (sumber otoritatif — No. 002/RP/TM/VII/2026, efektif 6 Juli 2026)
Perusahaan punya SOP tertulis resmi (ditandatangani HR/Ops Manager/GM/Direktur Utama) yang **mengoreksi
dan memperkaya** desain awal di atas. Poin-poin **koreksi penting**:

1. **Izin BUKAN pilihan bebas** — hanya boleh diajukan jika **saldo cuti tahun berjalan sudah habis (0
   hari)**. Selama masih ada saldo, ketidakhadiran non-sakit **wajib** pakai Cuti (dibayar), bukan Izin.
2. **Sakit butuh 2 dokumen**: Surat Keterangan Dokter (SKD) **dan** kwitansi/resep obat/bukti pembelian
   — bukan cuma "surat dokter" generik. Alternatif: screenshot riwayat aplikasi JKN (peserta BPJS).
   Wajib lapor ke atasan+HRD **paling lambat pukul 07.30 WIB** pada hari ketidakhadiran. Dokumen tidak
   lengkap/telat → dicatat ulang sebagai **Izin**.
3. **Tukar Shift** deadline **H-1** (bukan H-7) — beda dari Cuti/Izin. Wajib isi "Form Tukar Shift",
   berlaku untuk semua karyawan termasuk Leader/Supervisor.
4. **Izin Mendadak** hanya sah dalam 3 kategori: **Musibah** (keluarga kandung meninggal, kecelakaan,
   bencana alam, darurat lain), **Instansi Resmi Non-Musibah** (BPJS/RS/Sekolah/Kepolisian/pemerintah,
   untuk diri sendiri/suami-istri/anak kandung/anak sambung 1-KK/orang tua kandung/mertua), atau
   **Kepengurusan Musibah** (tidak bisa diwakilkan + bukti). Di luar 3 kategori ini → **Alpha**.
5. **Surat Izin Keluar** (modul BARU) — karyawan yang keluar area perusahaan pada jam kerja wajib
   pengajuan + persetujuan **Atasan Langsung DAN HRD** (dual approval) SEBELUM keluar. Terkait
   fingerprint (finger out/in) — placeholder untuk integrasi ke modul Attendance (sudah ada, tapi
   integrasinya ke Surat Izin Keluar belum disambungkan), tidak dihitung otomatis sekarang. Satpam berhak menolak karyawan keluar tanpa surat izin yang sudah disetujui.
6. **Eskalasi disiplin** — Alpha 3 hari kerja berturut-turut → Surat Peringatan (SP); Izin+Alpha >5x
   dalam 2 bulan berturut-turut → SP; tidak hadir tanpa keterangan 5 hari kerja berturut-turut → proses
   PHK sesuai Peraturan Perusahaan. Dokumen palsu untuk dasar izin → verbal hingga SP.
7. **Dirumahkan** tetap memperoleh **gaji pokok** (bukan 0, bukan gaji penuh+tunjangan) — aturan bisnis
   dicatat untuk konteks, perhitungan aktual tetap ranah Payroll.

**Di luar scope FILE INI** (dicatat sebagai referensi saja, TIDAK dibangun di modul Master Employee):
nominal sanksi (denda Alpha Rp100rb, potongan SP1/SP2 50% pendapatan, dst) dan matriks komponen gaji
(Gaji Pokok + 5 jenis tunjangan checklist per skenario kehadiran) — keduanya perhitungan **Payroll**,
yang sudah dibangun sebagai modul sendiri (`tm2pyr`), bukan lagi ditunda.
Detail lengkap semua aturan di `masteremployee_state.yaml` → `sop_perizinan_resmi`.

## 7. Kerahasiaan Gaji & Kontrol Akses Berbasis Peran (BARU)
Dua role disimulasikan di panel HR Admin: **HR Officer** (default) dan **Admin App**.
- **Tab "Akun"** (kelola login karyawan) **hanya bisa diakses Admin App** — HR Officer tidak berhak.
- **Visibilitas gaji** di tab Kontrak: HR Officer hanya lihat gaji kontrak tipe **KONTRAK/BORONGAN**;
  gaji **TETAP/HARIAN disembunyikan** dari HR Officer. Terlepas dari tipe, gaji posisi tertentu
  (`org_position.salary_hidden_from_hr=1` — usulan: Direksi/GM/Manager/Ops Manager, **perlu
  konfirmasi Telma per posisi riil**) **selalu** disembunyikan dari HR Officer. Admin App selalu
  lihat semua tanpa masking.

## 8. BPJS (data master, BUKAN engine payroll)
Menyimpan status kepesertaan **BPJS Kesehatan** (no. kepesertaan, kelas rawat, tanggungan) dan
**BPJS Ketenagakerjaan** (5 program: JKK, JKM, JHT, JP, JKP) sebagai referensi, termasuk skema iuran
regulasi saat ini (Kesehatan: Perusahaan 4%+Karyawan 1%; JHT: 3,7%+2%; JP: 2%+1%; JKK/JKM/JKP: mayoritas
perusahaan). **Perhitungan potongan gaji aktual TIDAK dibangun di sini** — itu ranah modul Payroll
(`tm2pyr`, sudah dibangun sebagai modul sendiri). HR kelola status di panel Admin; karyawan
hanya bisa ajukan koreksi nomor kepesertaan.

## 9. Monitoring Dokumen Kedaluwarsa (BARU)
Dashboard HR lintas-karyawan (bukan cuma per-orang) menampilkan dokumen legal (Passport, SIM, SKCK,
dst) yang sudah/segera kedaluwarsa, untuk kepentingan kepatuhan & administrasi kantor. **Catatan
faktual**: e-KTP WNI berlaku **seumur hidup** sejak aturan Kemendagri 2011 — field `expiry_date`
untuk KTP bersifat opsional, tidak semua karyawan akan punya tanggal kedaluwarsa KTP (relevan
terutama untuk WNA berdokumen KITAS/KITAP).

## 10. Siapa Mengisi & Approval
| Peran | Kontribusi |
|---|---|
| **HR Officer** | entri & maintain seluruh data emp_*/org_*, approve sebagian besar pengajuan self-service, TIDAK berhak kelola akun login & sebagian gaji |
| **Admin App** | akses penuh tanpa masking, termasuk kelola akun login karyawan |
| **PPIC** | assign shift roster Offset Printing, approval tahap-2 tukar shift |
| **Karyawan (self-service)** | kelola profil terbatas via pengajuan, upload dokumen miliknya |
| **Sistem (saat user pertama login)** | wajib pilih/tertaut ke 1 `employee_id` sebelum akun aktif |

**Master Employee (sisi HR Admin) TIDAK memakai workflow approval** untuk edit langsung HR — HR
full-editable. **Sisi karyawan (self-service) SELALU lewat pengajuan+approval** — ini pembeda utama
kedua sisi sistem.

## 11. Lifecycle Employee (ringkas — detail Tier 2)
`ACTIVE` (default saat entri) ⇄ `INACTIVE` (resign/terminated, `resign_date`+`resign_reason` wajib).
`work_status` (WORKING/DIRUMAHKAN/CUTI) bergerak independen dari `is_active`.

## 12. Non-Goals (eksplisit di luar scope FILE INI — bukan "di luar scope aplikasi")
- Recruitment pipeline (`tm2hrr`) — **SUDAH DIBANGUN**, cuma di luar scope file
  Master Employee ini (modulnya sendiri, lihat Tier doc Recruitment).
- Payroll calculation & slip gaji, termasuk perhitungan potongan BPJS aktual
  (`tm2pyr`) — **SUDAH DIBANGUN**, di luar scope file ini. Data BPJS di sini
  murni referensi utk Master Employee.
- Time attendance/absensi (clock-in/out) — **SUDAH DIBANGUN** (modul Attendance
  tersendiri), di luar scope file ini.
- Matriks approval berjenjang otomatis berdasar `org_level` — data disiapkan, engine belum.
- RBAC (role/permission) detail granular — bagian modul `08_security`; di sini hanya disinggung
  sebatas 2 role simulasi (HR Officer/Admin App) untuk kebutuhan tab Akun & masking gaji.
- Akrual cuti proporsional pegawai baru & carryover sisa cuti tahun lalu — kerangka dasar saja,
  detail perhitungan belum final.
- Engine rotasi shift otomatis — roster diinput manual PPIC/HR.
