# TIER 2 — MASTER EMPLOYEE + PERSONAL SELF-SERVICE (HRM) · BUSINESS LOGIC & RULES
**ERP Cetak Offset & Packaging — CV Telasih Makmur Group**

---

## PART A — HAPPY PATH

### A.1 Entri Employee Baru (HR Admin)
1. HR pilih `company_id` (selalu 1, CV Telasih Makmur) → pilih `division_id` (3 SBU) → cascading
   `department_id` → `position_id` (seperti AddressPicker).
2. Isi data pribadi wajib: `full_name`, `gender`, `birth_date`, `nik_ktp`, `phone`, `join_date`,
   `position_id`. Opsional: `religion`, `village_id_ktp`+`address_detail_ktp`. `village_id_domisili`
   boleh dikosongkan (berarti sama dengan KTP).
3. `employee_code` **auto-generate**, format `{company_code}-{sequence 5 digit}` (mis. `TM-00001`),
   per-company, read-only.
4. Simpan → `emp_employee.is_active=1`, `work_status='WORKING'` langsung, **tanpa approval**.
5. Kontrak awal **wajib** dibuat bersamaan: `employment_type`, `start_date`, `end_date` (kondisional,
   lihat A.2), `salary_type`+`salary_amount`, `is_probation` (opsional, jika ya isi
   `probation_end_date`). Baris `is_active=1`.
6. Data sekunder (family/document/bank account/BPJS/shift) boleh menyusul.

### A.2 Validasi Kontrak per Tipe
| employment_type | end_date | salary_type default |
|---|---|---|
| TETAP | harus NULL | PER_MONTH |
| KONTRAK | wajib diisi, > start_date | PER_MONTH |
| HARIAN | wajib diisi | PER_DAY |
| BORONGAN | wajib diisi | PER_UNIT |

`is_probation=1` **boleh** pada TETAP maupun KONTRAK (bukan tipe terpisah). Jika `is_probation=1`,
`probation_end_date` wajib > `start_date`.

### A.3 Perpanjangan/Perubahan Kontrak
- **Bukan edit** baris `emp_contract` existing. **INSERT baris baru**, set baris baru `is_active=1`,
  baris lama `is_active=0` (1 transaction).
- **Lolos probation**: `is_probation` di baris kontrak **aktif yang sama** diubah jadi 0 — TIDAK
  insert baris baru (bukan pergantian kontrak, cuma status probation-nya yang selesai).
- **Gagal probation**: jalankan alur A.5 (nonaktifkan employee).
- Proteksi hard "hanya 1 `is_active=1`" — **DELTA**, generated column+unique index vs disiplin
  app-level (transaction), belum diputuskan global.

### A.4 Mutasi Posisi/Department
- Ganti `position_id` **langsung update** (bukan versioned) — histori mutasi (`emp_position_history`)
  **DELTA**, tidak diimplementasi sekarang.

### A.5 Nonaktifkan Employee (Resign/Terminated) — HR Admin
1. Tombol `[Nonaktifkan]` → modal wajib isi `resign_date` + `resign_reason`.
2. Set `emp_employee.is_active=0`. Data **tidak dihapus** (snapshot pattern untuk signature dokumen
   lama).
3. Employee `is_active=0` tidak muncul di dropdown baru, tapi dokumen lama tetap tampil nama.
4. Jika ada `users` account aktif → otomatis dinonaktifkan (tidak dihapus, audit).

### A.6 Ubah work_status (Dirumahkan/Cuti)
- Field `work_status` diubah HR langsung (full-editable, tanpa approval — beda dengan pengajuan
  self-service). `work_status_since` terisi tanggal perubahan. `is_active` TIDAK ikut berubah.

### A.7 Shift Roster (Board PPIC — Offset Printing)
1. PPIC pilih periode (`date_from`-`date_to` bebas) → sistem buat/pakai `emp_shift_roster_batch`
   `status='DRAFT'`.
2. Baris shift di board **ditarik dari master**: `SELECT * FROM emp_shift WHERE department_id =
   {Produksi Offset Printing} AND is_active=1`, label = `shift_name` + ringkasan jam dari
   `emp_shift_detail`. **Board TIDAK hardcode nama shift generik.**
3. PPIC drag nama karyawan ke sel (mesin × shift × tanggal). Simpan = `INSERT emp_shift_roster`
   dengan `batch_id` terisi, `assigned_by_role='PPIC'`.
4. **Validasi HARD saat simpan** (app-layer): jika `employee_id`+`roster_date`+`shift_id` sudah ada
   dengan `machine_id` beda → tolak (fisik tidak mungkin di 2 mesin sekaligus).
5. **Validasi SOFT**: jika `employee_id` sudah ada di `roster_date` sama tapi `shift_id` beda → boleh
   simpan, tampilkan flag oranye untuk ditinjau manual (mis. lanjut shift/lembur berurutan).
6. Tombol `[Save]` → `batch.status` tetap `DRAFT` (hanya PPIC lihat). Tombol `[Confirm]` → `status='CONFIRMED'`,
   `confirmed_by`+`confirmed_at` terisi — board terkunci read-only, published ke HR + portal
   self-service karyawan ("Jadwal Saya"). Tombol `[Unlock]` → balik `DRAFT` untuk revisi.
7. `[Print]` → cetak semua mesin 1 halaman untuk papan pengumuman, kolom tanda tangan PPIC + Plant
   Manager, memakai nama shift+jam yang sama dengan board.

### A.8 Grid HR Sederhana (Digital Printing & Indostry)
- HR assign langsung (`assigned_by_role='HR'`, `machine_id` NULL). **TIDAK** mengikuti master shift
  Offset — toggle jumlah shift aktif sederhana (S1/S2/S3), sengaja terpisah supaya tidak
  membingungkan saat tracking.

---

## PART B — SPECIAL CASES

### B.1 Login Tanpa Employee
**Tidak boleh terjadi.** `users.employee_id` NOT NULL — dibuat hanya dari tab "Akun" di detail
employee, **dan hanya role Admin App yang berhak** membuat/reset akun (lihat B.7).

### B.2 Dokumen Kadaluarsa
- Per-employee: `emp_document.expiry_date` ditampilkan dengan badge (kadaluarsa/segera).
- **Lintas-employee (BARU)**: dashboard HR "Dokumen Kedaluwarsa" agregat semua karyawan, filter
  jenis+status, tombol "Ingatkan Karyawan" (trigger notifikasi ke portal self-service, modul
  `08_security/notification`, di luar file ini).
- KTP WNI **tidak wajib** isi `expiry_date` (berlaku seumur hidup) — field tetap ada untuk kasus WNA.

### B.3 Rekening Bank Ganda
Saat `is_primary=1` di-set pada 1 rekening baru, aplikasi otomatis set rekening lain milik employee
yang sama jadi `is_primary=0` (1 transaction).

### B.4 Pengajuan Koreksi Data (Self-Service → HR)
1. Karyawan klik "Ajukan Koreksi" pada field tertentu → isi `new_value`+`reason` → `INSERT
   emp_correction_request status='PENDING'`. `requires_hr_approval` **selalu 1** untuk field NIK KTP
   dan Tanggal Lahir (tidak boleh auto-approve).
2. HR tinjau di "Approval Koreksi Data" → Setujui: **1 transaction** — `UPDATE emp_employee` (atau
   tabel terkait) field bersangkutan dengan `new_value`, lalu `UPDATE emp_correction_request
   status='APPROVED', reviewed_by, reviewed_at`. Tolak: hanya update status request, data master
   tidak berubah.
3. Field yang lewat mekanisme ini: Nama, NIK KTP, Telepon, Alamat KTP, Alamat Domisili, Agama,
   Tanggal Lahir, No. Rekening Bank, No. BPJS Kesehatan/Ketenagakerjaan.

### B.5 Pengajuan Cuti/Sakit/Izin/Cuti Khusus (Engine Cuti) — sesuai SOP 002/RP/TM/VII/2026
1. Karyawan pilih `leave_type`. Untuk **CUTI**: kalender hanya menampilkan tanggal ≥ H+7 dari hari
   ini sebagai bisa-dipilih; Minggu & hari besar **tidak muncul sebagai opsi** (bukan sekadar
   disabled visual — validasi ini WAJIB diulang di server, jangan percaya kalender klien saja).
   Maksimum 3 hari per pengajuan (validasi server, tolak jika lebih). `attachment_surat_cuti` wajib
   (syarat administrasi "Surat Cuti" per SOP).
2. **[SOP] Untuk IZIN — validasi WAJIB sebelum form ditampilkan/submit**: cek
   `emp_leave_balance` tahun berjalan. Jika `entitlement_days + carried_over_days - used_days > 0`
   (masih ada saldo), **opsi Izin di-disable** dengan pesan "Anda masih punya sisa cuti {N} hari,
   silakan ajukan Cuti". Izin **hanya bisa** diajukan bila saldo = 0. Jika izin terjadwal (bukan
   mendadak): minimal H-7. Maksimum 3 hari berturut-turut per pengajuan DAN maksimum 3x pengajuan
   izin per bulan kalender — lebih dari batas ini, hari/pengajuan ke-4 dst **otomatis dicatat
   sebagai ALPHA** (lihat B.6c).
3. **[SOP] Untuk IZIN MENDADAK** — `izin_category` wajib dipilih salah satu:
   - `MUSIBAH`: keluarga kandung meninggal/kecelakaan/bencana alam/darurat lain. Tidak perlu
     `izin_relasi_pemohon`/`izin_instansi_asal`.
   - `INSTANSI_RESMI`: wajib isi `izin_relasi_pemohon` (diri sendiri/suami-istri/anak
     kandung/anak sambung 1-KK/orang tua kandung/mertua) dan `izin_instansi_asal`
     (BPJS/RS/Sekolah/Kepolisian/pemerintah lain). `attachment_path` (surat/dokumen resmi instansi)
     wajib.
   - `KEPENGURUSAN_MUSIBAH`: tidak bisa diwakilkan, `attachment_path` (bukti dokumentasi) wajib.
   - Dokumen pendukung untuk ketiga kategori: deadline upload **H+1** sejak hari pertama
     ketidakhadiran. Di luar 3 kategori ini → tidak bisa submit sebagai Izin Mendadak (arahkan ke
     Alpha/hubungi HR manual).
4. **[SOP] Untuk SAKIT**: `attachment_surat_dokter` (SKD) DAN `attachment_kwitansi_resep` **keduanya
   wajib**, ATAU `attachment_jkn_screenshot` sebagai alternatif tunggal (peserta BPJS via app JKN).
   `sakit_lapor_jam` wajib diisi, dengan validasi klien+server: **jika > 07:30 WIB pada hari
   ketidakhadiran, tampilkan warning** "Pelaporan melewati batas 07:30 WIB" (tetap bisa submit,
   tapi HR akan melihat flag ini saat review). Form diajukan **setelah karyawan masuk kerja
   kembali** — tanggal `date_from`/`date_to` boleh tanggal lampau. Jika sakit >1 hari ATAU tanggal
   sakit berurutan dengan Minggu/tanggal merah (sebelum/sesudah): `attachment_surat_dokter` **wajib
   ada** (tidak boleh hanya kwitansi) — jika tidak ada, tampilkan warning keras sebelum submit.
5. Untuk **CUTI_KHUSUS**: `khusus_event_type` wajib dipilih, `attachment_path` sesuai jenis peristiwa.
6. Saat submit, aplikasi hitung `is_paid` dan `deducts_leave_balance`:
   - CUTI → `is_paid=1`, `deducts_leave_balance=1`.
   - SAKIT → `is_paid=1` (dengan asumsi lampiran akan divalidasi HR saat approval), `deducts_leave_balance=0`.
   - IZIN → `is_paid=0`, `deducts_leave_balance=0`.
   - CUTI_KHUSUS → `is_paid=1`, `deducts_leave_balance=0`.
7. HR/atasan approve di "Approval Sakit/Izin/Cuti" → **jika CUTI disetujui**, 1 transaction:
   `UPDATE emp_leave_balance SET used_days = used_days + total_days WHERE employee_id=? AND
   year=YEAR(date_from)`. Field lain hanya update status.
8. **[SOP] Saat HR review SAKIT**: jika dokumen tidak lengkap (SKD atau kwitansi/resep kosong,
   dan tidak ada `attachment_jkn_screenshot`) ATAU diserahkan melebihi hari-H masuk kerja → HR set
   `converted_to_izin=1` dan proses sebagai IZIN (bukan tolak mentah — `leave_type` tetap SAKIT
   untuk histori, tapi `is_paid`/`deducts_leave_balance` mengikuti aturan IZIN).
9. Saldo cuti (`emp_leave_balance`) di-generate 1 baris per employee per tahun (default
   `entitlement_days=12`) — mekanisme generate baris tahun baru **DELTA**, belum didetailkan
   (kandidat: job scheduler awal tahun, atau lazy-create saat pengajuan pertama tahun tsb).

### B.6 Tukar Shift (Approval Bertahap, deadline H-1 sesuai SOP)
1. Karyawan A ajukan tukar `requester_roster_id` dengan jadwal karyawan B (`target_roster_id`) →
   `INSERT emp_shift_swap_request partner_status='PENDING', ppic_status='PENDING'`.
   **[SOP]** Aplikasi hitung `submitted_on_time`: 0 jika tanggal pengajuan **kurang dari H-1** sebelum
   `requester_roster_id.roster_date` — flag ini TIDAK memblokir submit (tetap bisa diajukan), tapi
   ditampilkan ke HR sebagai dasar pertimbangan teguran verbal (SOP H.3).
2. Karyawan B (rekan) approve/reject di portalnya → update `partner_status`. **Jika REJECTED, selesai
   di sini** — `ppic_status` tidak diproses.
3. Jika `partner_status='APPROVED'`, PPIC baru bisa proses `ppic_status` di panel HR/PPIC.
4. Jika `ppic_status='APPROVED'` → 1 transaction: **swap** `machine_id`+`shift_id` antara
   `requester_roster_id` dan `target_roster_id` di `emp_shift_roster`.
5. Berlaku untuk **seluruh karyawan tanpa kecuali**, termasuk Leader/Supervisor (SOP H.4) — tidak
   ada logic pengecualian jabatan di sistem.

### B.6b Surat Izin Keluar pada Jam Kerja (BARU, SOP poin I)
1. Karyawan isi `emp_out_permit_request` (tanggal, rencana jam keluar, keperluan) **sebelum**
   meninggalkan area — bukan retroaktif.
2. **Dual approval wajib**: `atasan_approved_by/at` **dan** `hrd_approved_by/at` harus terisi
   keduanya sebelum status jadi `APPROVED`. Beda dari request lain (yang cukup 1 approval) — ini
   architecture pengecualian, karena SOP eksplisit mensyaratkan tanda tangan Atasan Langsung DAN HRD.
3. `fingerprint_out_at`/`fingerprint_in_at` adalah **placeholder** — TIDAK diisi otomatis oleh sistem
   ini (integrasi mesin fingerprint = domain Attendance, ditunda total). Kolom disiapkan agar skema
   siap saat modul Attendance akhirnya dikerjakan.
4. Aturan "< 4,5 jam kerja efektif = dihitung Izin" (SOP I.4) **TIDAK dihitung otomatis** sekarang —
   HR menilai manual berdasar data fingerprint fisik yang ada di luar sistem, sampai integrasi
   Attendance tersedia. Dicatat sebagai DELTA di `masteremployee_state.yaml`.
5. Tanpa Surat Izin Keluar yang disetujui, penegakan fisik (Satpam menolak karyawan keluar) terjadi
   di luar sistem (tidak ada logic aplikasi yang bisa memaksa ini) — sistem hanya menyediakan data
   status pengajuan yang bisa dicek Satpam secara manual/read-only.

### B.6c Kedisiplinan & Eskalasi Alpha (BARU, SOP poin F & K)
1. Insert ke `emp_disciplinary` bisa terjadi **manual** (HR input langsung dari layar Kedisiplinan)
   ATAU **otomatis** (trigger app-layer) dari pelanggaran aturan izin:
   - Izin hari ke-4 dst dalam 1 pengajuan (>3 hari berturut) → auto-insert `type='ALPHA'`,
     `related_leave_request_id` terisi.
   - Izin ke-4 dst dalam 1 bulan kalender yang sama → auto-insert `type='ALPHA'`.
   - Tidak hadir tanpa pemberitahuan dalam 1×24 jam → `type='ALPHA'` (input manual HR, sistem tidak
     bisa mendeteksi "tidak ada pemberitahuan" secara otomatis).
2. **Eskalasi** (dinilai manual oleh HR, sistem hanya menyediakan data untuk dicek, TIDAK ada
   otomasi eskalasi penuh — DELTA untuk versi mendatang):
   - Alpha 3 hari kerja berturut-turut → HR input `type='SP1'` (atau SP berikutnya sesuai riwayat).
   - Izin+Alpha >5x dalam 2 bulan berturut-turut → HR input SP.
   - Tidak hadir tanpa keterangan 5 hari kerja berturut-turut → proses lanjut ke PHK sesuai PP
     (di luar sistem — hanya `emp_employee.is_active=0` + `resign_reason` dicatat saat final).
3. `sanksi_referensi` diisi teks informatif sesuai SOP (lihat `masteremployee_state.yaml` →
   `sop_perizinan_resmi.ketentuan_alpha_dan_disiplin.sanksi_nominal_referensi`) — **bukan** field
   yang dihitung aplikasi; potongan gaji aktual tetap diproses manual/Payroll (ditunda).

### B.7 Kontrol Akses HR Officer vs Admin App
- Tab "Akun" di detail employee **disembunyikan sepenuhnya** (bukan sekadar disabled) dari HR Officer
  di level UI, **dan divalidasi ulang di server** — endpoint terkait harus reject request dari role
  HR Officer meski URL diakses langsung.
- Gaji di tab Kontrak: server menentukan `showSalary = (contract.employment_type IN ('KONTRAK',
  'BORONGAN') OR role='ADMIN_APP') AND (role='ADMIN_APP' OR position.salary_hidden_from_hr=0)`.
  Response API untuk role HR Officer **tidak boleh mengirim nilai gaji mentah** ke client sama sekali
  saat kondisi masking terpenuhi (bukan cuma disembunyikan di CSS/JS) — cegah kebocoran via network tab.

### B.8 Pengajuan Keluarga (Lahir/Meninggal/Pasangan)
1. **LAHIR/PASANGAN**: karyawan isi `relation`+`full_name`+`birth_date`(+lampiran) →
   `emp_family_request`. HR approve → `INSERT emp_family` baris baru.
2. **MENINGGAL**: karyawan pilih `family_id` existing miliknya + `deceased_date` + lampiran surat
   kematian → HR approve → `UPDATE emp_family SET is_deceased=1, deceased_date=?` — **BUKAN DELETE**.

### B.9 Pengajuan Foto Badge
1. Karyawan upload foto → aplikasi (client-side) cek dimensi aktual file: minimal 600×900px, rasio
   ≈2:3 → tampilkan warning jika belum memenuhi (tidak memblokir submit, hanya peringatan — HR yang
   putuskan final saat approval).
2. `INSERT emp_badge_photo_request` dengan `width_px`/`height_px` tersimpan untuk referensi HR.
3. HR approve → `UPDATE emp_employee.photo_path` ke file baru. Cetak fisik kartu tetap manual oleh HR
   (di luar sistem).

### B.10 Pengajuan Resign (Self-Service)
1. Karyawan isi `effective_date`+`reason` → `INSERT emp_resign_request status='PENDING'`.
2. HR proses (termasuk serah terima di luar sistem) → set `status='PROCESSED'`, lalu 1 transaction:
   `UPDATE emp_employee SET is_active=0, resign_date=effective_date, resign_reason=reason`.

### B.11 BPJS — Batas Tanggung Jawab Sistem
`emp_bpjs_kesehatan`/`emp_bpjs_ketenagakerjaan` **hanya data status**. Sistem ini **TIDAK** menghitung
potongan gaji dari persentase iuran yang tersimpan — field persentase di DDL adalah dokumentasi/
komentar referensi, bukan kolom yang dipakai kalkulasi. Karyawan hanya bisa ajukan koreksi nomor
kepesertaan (lewat B.4, generic correction) — status/program tetap dikelola HR di panel Admin.

---

## PART C — STATE, FORM, BUTTON

### C.1 State Employee
```
ACTIVE ──[Nonaktifkan + alasan]──► INACTIVE
INACTIVE ──[Aktifkan kembali]──► ACTIVE   (rehire, resign_date+reason di-null-kan)
```
`work_status` bergerak independen: `WORKING ⇄ DIRUMAHKAN ⇄ CUTI` (field bebas diubah HR, tidak
terikat ke state ACTIVE/INACTIVE di atas).

### C.2 State Kontrak
```
(kontrak baru) ──INSERT is_active=1──► AKTIF
AKTIF ──[Perpanjang/Ubah Tipe → INSERT baris baru]──► baris lama is_active=0 (Berakhir, terkunci)
AKTIF + is_probation=1 ──[Lolos probation]──► is_probation=0 (baris SAMA, tanpa INSERT baru)
AKTIF + is_probation=1 ──[Gagal probation]──► employee dinonaktifkan (lihat A.5)
```

### C.3 State Pengajuan Self-Service (pola seragam semua tabel `emp_*_request`)
```
PENDING ──[HR/pihak berwenang Setujui]──► APPROVED (data master ter-update, 1 transaction)
PENDING ──[Tolak]──► REJECTED (data master TIDAK berubah)
```
Khusus **Tukar Shift**: 2 tahap berurutan (`partner_status` lalu `ppic_status`), lihat B.6.
Khusus **Resign**: `PENDING → PROCESSED` (bukan APPROVED/REJECTED, karena resign selalu diproses,
bukan ditolak — HR hanya menentukan tanggal efektif final bila perlu negosiasi, di luar sistem).

### C.4 Tombol per Layar (HR Admin)
| Layar | Tombol |
|---|---|
| List Employee | `[+ Tambah Employee]`, filter divisi/status, `[Lihat]`/`[Edit]` per baris |
| Detail Employee (8 subtab: Profil/Kontrak/Keluarga/BPJS/Dokumen/Keterampilan/Rekening/Akun*) | Edit-link per field, `[+ Kontrak Baru]`, `[Nonaktifkan]`, `[Cetak Kartu Pengenal]`, `[Ganti Foto]` |
| *Tab Akun | **hanya render jika role=Admin App** |
| Struktur Organisasi | Tree dgn edit-link per node, `[+ Tambah]` |
| Dokumen Kedaluwarsa | Filter jenis/status, `[Ingatkan Karyawan]` |
| Approval Sakit/Izin/Cuti | `[Setujui]`/`[Tolak]` per baris, `[+ Input Manual]` |
| Approval Koreksi Data | `[Setujui]`/`[Tolak]` per baris |
| Shift Roster (Offset) | `[Save]`, `[Confirm]`, `[Unlock]`, `[Print]`, drag-drop antar sel |

### C.5 Tombol per Layar (Portal Self-Service)
| Tab | Tombol |
|---|---|
| Data Saya | `[Ajukan Koreksi]` per field, `[Ajukan Pengunduran Diri]` |
| Keluarga & Status | `[Ajukan Kelahiran Baru]`, `[Tandai Anggota Meninggal]`, `[Ajukan Perubahan]` (status nikah/rekening/kontak darurat) |
| Dokumen & Pendidikan | `[Ganti]`/`[Upload]`/`[Ajukan Perpanjangan]` per dokumen, `[+ Tambah Riwayat Pendidikan]`, `[+ Tambah Keterampilan]` |
| Cuti/Sakit/Izin | Pilih jenis → form dinamis, kalender custom (CUTI), `[Kirim Pengajuan]` |
| Tukar Shift | Pilih jadwal sendiri + rekan tujuan, `[Kirim Pengajuan Tukar]` |
| Jadwal Saya | View-only (CONFIRMED saja) |
| Kartu Pengenal | View digital, `[Ajukan Foto Baru]` |

### C.6 Badge Status (ikut konvensi `GLOBAL_RULES.badge_system`)
- ACTIVE → hijau solid "Aktif" · INACTIVE → abu-abu "Nonaktif"
- Status kepegawaian di header detail: PROBATION (amber) tampilkan `s/d {probation_end_date}`,
  KONTRAK/HARIAN/BORONGAN (biru/amber) tampilkan `Berakhir {end_date}`, TETAP (biru) tanpa tanggal.
- Pengajuan: PENDING (amber "Menunggu"), APPROVED (hijau "Disetujui"), REJECTED (merah "Ditolak").
- Dokumen kadaluarsa: merah "Kedaluwarsa", amber "Segera (≤30 hari)".
