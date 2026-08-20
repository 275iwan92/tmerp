# TIER 4 — MASTER EMPLOYEE + PERSONAL SELF-SERVICE (HRM) · UI/UX & WIREFRAME
**ERP Cetak Offset & Packaging — CV Telasih Makmur Group**

> Konvensi `GLOBAL_RULES.yaml`: layout **TERPUSAT** (form/detail/portal), **FULL-PAGE** untuk list &
> board roster. Token navy `#2c3e69`, accent `#2f6fed`. 3 file mockup HTML terpisah (jangan
> digabung — beda domain, supaya tidak membingungkan saat tracking):
> - `mockup_master_employee_hr.html` — panel HR Admin
> - `mockup_master_produksi.html` — Master Mesin & Master Shift (domain PPIC/Produksi)
> - `mockup_personal_data_selfservice.html` — portal karyawan
> - `mockup_shift_roster.html` — board PPIC drag-drop

---

## FILE 1 — HR ADMIN (`mockup_master_employee_hr.html`)

### Sidebar
Role simulator (2 tombol toggle: **HR Officer** default / **Admin App**) di atas, lalu nav grup:
Kepegawaian (Master Employee, Struktur Organisasi) → Kinerja & Disiplin (Kedisiplinan, KPI) →
Monitoring (**Dokumen Kedaluwarsa**, baru) → Approval (Sakit/Izin/Cuti, Koreksi Data).

### LAYAR 1 — List Employee (FULL-PAGE)
```
┌────────────────────────────────────────────────────────────────────────────┐
│ Master Employee                                          [ + Tambah Employee]│
├────────────────────────────────────────────────────────────────────────────┤
│ Divisi:[Semua▾] Status:[Semua▾] 🔍[cari nama..]                              │
├───────────┬─────────────┬───────────┬──────────────┬──────────┬─────┬───────┤
│ Kode      │ Nama        │ Divisi    │ Posisi       │Status Krj│Status│ Aksi  │
├───────────┼─────────────┼───────────┼──────────────┼──────────┼─────┼───────┤
│ TM-00001  │ Budi Santoso│ Offset    │ Operator Cetak│ TETAP   │Aktif│[Lihat][Edit]│
│ TM-00003  │ Rudi Hartono│ Offset    │ Op. Coating  │PROBATION │Aktif│[Lihat][Edit]│
│ TM-00008  │Bambang Wijaya│ Offset   │Manager Produksi│ TETAP   │Aktif│[Lihat][Edit]│
└────────────────────────────────────────────────────────────────────────────┘
```
Badge status kepegawaian berwarna per tipe (tetap=biru, kontrak=amber, probation=amber, borongan=ungu,
dirumahkan=merah, pkl=biru). Klik `[Lihat]`/`[Edit]` → Layar 2, membawa `employee_code`.

### LAYAR 2 — Detail Employee (TERPUSAT, 8 subtab)
```
┌──────────────────────────────────────────────────────────┐
│ ← Kembali    [Avatar] Budi Santoso  ● Aktif                │
│              TM-00001 · Operator Cetak · Offset Printing   │
│              TETAP                        [Nonaktifkan]    │
├──────────────────────────────────────────────────────────┤
│[Profil][Kontrak][Keluarga][BPJS][Dokumen][Keterampilan][Rekening][Akun*]│
└──────────────────────────────────────────────────────────┘
```
Baris status kepegawaian (di bawah kode/posisi) **dinamis per employee**:
- TETAP → `TETAP` saja.
- KONTRAK/HARIAN/BORONGAN → `{TIPE} · Berakhir {end_date}`.
- PROBATION aktif → `PROBATION · s/d {probation_end_date} (dlm kontrak s/d {contract end_date})`.

`*Tab Akun` **hanya render bila role=Admin App** — untuk HR Officer, tab ini hilang total dari daftar
(bukan disabled/abu-abu).

**Tab Profil**: foto (dgn `[Ganti Foto]`+`[Cetak Kartu Pengenal]`), field Nama/Nama Panggilan/
Divisi/Posisi/Jenis Kelamin/Tempat-Tgl Lahir/**Agama**/NIK KTP/Telepon/Alamat/Tanggal Masuk/Default
Mesin/Default Shift — semua dengan `[Edit]` link (full-editable HR, tanpa approval).

**Tab Kontrak**: tabel riwayat, baris aktif punya kolom Gaji yang **bisa termasking**:
```
Tipe      Mulai       Selesai    Gaji                              Status  Aksi
TETAP     03-01-2024  –          •••••••• (Tipe TETAP, tersembunyi  Aktif  [Edit]
                                  dari HR)
```
Jika posisi `salary_hidden_from_hr=1` (mis. Manager) → teks masking beda: `•••••••• (Rahasia)` +
note box "Gaji level ini disembunyikan dari HR Officer... Hanya Admin App yang bisa melihat."
Toggle role ke Admin App → gaji langsung tampil apa adanya, tanpa masking.

**Tab Keluarga**: tabel Hubungan/Nama/Tgl Lahir/`[Edit]`/`[Hapus]` (HR bisa langsung ubah, beda
dengan sisi karyawan yang harus lewat pengajuan).

**Tab BPJS** *(baru)*: 2 card —
- BPJS Kesehatan: No. Kepesertaan, Kelas Rawat, Status, Tanggungan Terdaftar, catatan skema iuran.
- BPJS Ketenagakerjaan: No. Kepesertaan, Status, tabel 5 program (JKK/JKM/JHT/JP/JKP) × kolom
  Perusahaan/Karyawan/Status.
- Note box di atas: "Data master, bukan payroll engine."

**Tab Dokumen**: tabel Jenis/No. Dokumen/Berlaku s/d/Diupload Oleh (badge HR/Diri Sendiri)/`[Ganti]`
→ buka modal upload dgn preview file.

**Tab Keterampilan, Tab Rekening**: pola sama (tabel + `[Edit]`/`[+Tambah]`).

**Tab Akun** (Admin App saja): Username, Role, Login Terakhir, `[Reset Password]`,
`[Nonaktifkan Akun]`. Note box: "Menu ini hanya bisa diakses Admin App."

### LAYAR 3 — Struktur Organisasi
Tree Company → Divisi → Departemen → Posisi, tiap node ada `[Edit]` kecil. Departemen shared
(Security, Staff/Admin) render langsung di bawah company node, tidak di bawah divisi manapun.

### LAYAR 4 — Dokumen Kedaluwarsa *(baru)*
```
┌────────────────────────────────────────────────────────────────────────────┐
│ Dokumen Kedaluwarsa                                                          │
│ [2 Sudah Kedaluwarsa] [3 Segera ≤30 hari] [1 Menunggu Review Perpanjangan]  │
├───────────────┬────────────┬────────────┬─────────────┬──────────┬──────────┤
│ Employee      │ Jenis      │Berlaku s/d │ Sisa Hari   │ Status   │ Aksi     │
├───────────────┼────────────┼────────────┼─────────────┼──────────┼──────────┤
│ Rudi Hartono  │ SKCK       │ 15 Jan 2025│ -580 hari   │Kedaluwarsa│[Ingatkan]│
│ Budi Santoso  │ Passport   │ 04 Nov 2026│ 78 hari lagi│ Segera   │(diajukan)│
└────────────────────────────────────────────────────────────────────────────┘
```
Note box menjelaskan KTP WNI seumur hidup (lihat Tier 1 §9).

### LAYAR 5 — Approval Sakit/Izin/Cuti & Approval Koreksi Data
Tabel dengan kolom relevan + `[Setujui]`(hijau)/`[Tolak]`(merah) per baris PENDING; baris
APPROVED/REJECTED tampil status final tanpa tombol aksi. Kolom SAKIT tambah indikator dokumen
lengkap/tidak (badge) + tombol `[Konversi ke Izin]` jika dokumen tidak lengkap/telat (SOP C.4).
Kolom IZIN tambah `izin_category` (Musibah/Instansi Resmi/Kepengurusan Musibah) + link lihat
dokumen pendukung.

### LAYAR 5b — Approval Izin Keluar *(BARU)*
Tabel: Karyawan/Tanggal/Jam Rencana/Keperluan/Status Atasan/Status HRD. **Dual approval** — baris
punya 2 tombol terpisah `[Setujui sbg Atasan]` dan `[Setujui sbg HRD]` (masing-masing hanya aktif
sesuai role login), status keseluruhan APPROVED hanya jika kedua-duanya sudah disetujui.

### LAYAR 5c — Kedisiplinan *(diperluas dgn DDL, sebelumnya cuma UI statis)*
List `emp_disciplinary` (Karyawan/Tanggal/Tipe badge Teguran-Verbal/SP1/SP2/SP3/Alpha/Alasan/
Diterbitkan Oleh) + `[+ Catat Kedisiplinan Baru]` → modal manual input. Baris yg `related_leave_request_id`
terisi (auto-generated dari pelanggaran izin) ditandai ikon 🔗 + link ke pengajuan terkait, TIDAK
bisa dihapus (hasil sistem, bukan input manual).

---

## FILE 2 — MASTER PRODUKSI (`mockup_master_produksi.html`)

### Master Mesin
List (Kode/Nama/Jenis/Merk-Model/Status) → **klik baris** → modal editable lebar 720px, 3 seksi:
Identitas (kode+nama, jenis+divisi), Aset (merk/model+tahun, no. seri), Spesifikasi Teknis (max
kertas+jumlah warna, kecepatan max+kapasitas/shift, gramatur min+max, catatan). Tombol icon
✏️/🗑️ di list. `[+ Tambah Mesin]` buka modal sama dalam keadaan kosong.

### Master Shift
List (Nama Shift/Departemen/Ringkasan Jam/Status) → **klik baris** → modal editable lebar 720px,
TANPA scroll: Nama Shift + Departemen sejajar 2 kolom, lalu tabel 7 hari (Senin-Minggu) × kolom
Masuk/Ist.Mulai/Ist.Selesai/Pulang, tiap sel `<input type=time>`, ikon 🗑️ per hari (kosongkan) /
➕ (isi hari yang belum ada jamnya). `[+ Tambah Shift]` buka modal kosong.

---

## FILE 3 — SHIFT ROSTER BOARD (`mockup_shift_roster.html`)

### Board PPIC (Offset Printing)
```
┌────────────────────────────────────────────────────────────────────────────┐
│ Shift Roster — Offset Printing         Periode: [10 Agu ▾] – [16 Agu ▾]     │
│ Mesin: [Cetak 1 ▾]              [Save] [Confirm] [Unlock] [Print]           │
├─────────────────────┬──────┬──────┬──────┬──────┬──────┬──────┬────────────┤
│ Shift                │ Sen  │ Sel  │ Rab  │ Kam  │ Jum  │ Sab  │ Ming       │
├─────────────────────┼──────┼──────┼──────┼──────┼──────┼──────┼────────────┤
│ TM Production Shift 1│ Siti │ Siti │      │      │      │      │ Lina(OT)   │
│ 05:00–13:15          │+Joko │      │      │      │      │      │            │
├─────────────────────┼──────┼──────┼──────┼──────┼──────┼──────┼────────────┤
│ TM Production Shift 2│      │      │Rudi  │      │      │      │            │
│ 13:15–21:15          │      │      │      │      │      │      │            │
└────────────────────────────────────────────────────────────────────────────┘
```
**Baris shift ditarik dari master** (`emp_shift` × `emp_shift_detail`, department=Produksi Offset)
— label = nama shift + ringkas jam, BUKAN "Shift 1/2/3" generik. Drag nama karyawan dari panel
kanan ke sel. Sel dgn >1 orang ditumpuk. Konflik HARD = merah+block, SOFT = oranye+boleh. Lembur
ditandai "(OT)" merah. `[Print]` hasilkan tata letak sama, siap ditempel.

### Grid HR Sederhana (Digital Printing / Indostry)
Toggle "Shift aktif minggu ini: [1][2][3]" + grid nama × hari, sel diklik siklus 0→S1→S2→S3→0.
Legend warna per shift + lembur.

---

## FILE 4 — PORTAL SELF-SERVICE (`mockup_personal_data_selfservice.html`)

### Struktur: topbar (nama+kode login) + 8 tab horizontal-scroll (mobile-first, lebar terpusat ~640px)

**Tab "Data Saya"**: card Profil (Nama/Tgl Lahir*/NIK KTP/Agama/Telepon, tiap field ada
`[Ajukan Koreksi]` — Tgl Lahir diberi badge amber "wajib approval HR"), card Alamat (KTP vs
Domisili terpisah, masing-masing `[Ajukan Koreksi]`), card BPJS (read-only + `[Ajukan Koreksi]`
nomor saja), card Riwayat Pengajuan Koreksi, card merah "Pengunduran Diri" → `[Ajukan Pengunduran Diri]`.

**Tab "Keluarga & Status"**: tabel anggota keluarga (kolom Status: Hidup/"Meninggal — {tanggal}"),
`[+ Ajukan Kelahiran Baru]`, `[Tandai Anggota Meninggal]`; card Status Pernikahan, Rekening Bank,
Kontak Darurat — masing-masing `[Ajukan Perubahan]`/`[Edit]` → modal form sesuai jenis.

**Tab "Dokumen & Pendidikan"**: card Dokumen Identitas (upload-row per dokumen, kunci 🔒 utk
upload-HR, tombol Ganti/Upload/Ajukan Perpanjangan → modal upload+preview); card Pendidikan &
Pengembangan (tabel Kegiatan/Tanggal/Biaya/Dibiayai — badge Perusahaan/Pribadi + badge Ikatan
Dinas, `[+ Tambah Riwayat Pendidikan]` → modal lengkap jenis/nama/tanggal/durasi/biaya/dibiayai-oleh/
sertifikat); card Keterampilan.

**Tab "Cuti/Sakit/Izin"** *(sesuai SOP 002/RP/TM/VII/2026)*: card Saldo Cuti (3 angka:
Jatah/Terpakai/Sisa); card Ajukan Pengajuan — dropdown jenis mengganti blok form:
- **Cuti**: note H+7+maks3hari, **kalender kustom** (grid 7 kolom, sel `disabled`=abu-tercoret
  sebelum H+7, `holiday`=merah-tercoret (Minggu/hari besar), `selectable`=bisa diklik, klik ke-4
  memicu alert "Maksimal 3 hari"); counter "Terpilih: N hari (maks 3)"; upload "Surat Cuti"
  (`attachment_surat_cuti`, wajib).
- **Izin**: **[BARU] Jika saldo cuti > 0, dropdown opsi "Izin" di-disable** + tampil note "Anda
  masih punya sisa cuti {N} hari — ajukan Cuti, bukan Izin". Jika saldo = 0, form terbuka: toggle
  Terjadwal (note H-7) vs Mendadak (pilih `izin_category`: Musibah — tanpa field tambahan / Instansi
  Resmi — dropdown relasi pemohon + dropdown instansi asal + upload dokumen resmi / Kepengurusan
  Musibah — upload bukti dokumentasi), note maks 3 hari berturut & maks 3x/bulan, note deadline
  dokumen H+1, note no-present-no-pay.
- **Sakit**: upload **2 dokumen wajib** (SKD via kamera/file + kwitansi/resep via kamera/file) ATAU
  toggle "Peserta BPJS JKN" → 1 upload screenshot riwayat aplikasi; input jam lapor (`sakit_lapor_jam`)
  dengan warning merah jika diisi >07:30; note "wajib SKD jika sakit >1 hari atau berurutan dgn
  Minggu/tanggal merah"; date range (boleh tanggal lampau, diajukan setelah masuk kerja).
- **Cuti Khusus**: dropdown jenis peristiwa, date range, upload lampiran.
- Card Riwayat Pengajuan (kolom Dibayar Ya/Tidak + Status + badge "Dikonversi ke Izin" jika
  `converted_to_izin=1`).

**Tab "Tukar Shift"**: note **"Deadline H-1 sebelum jadwal kerja"** (bukan H+7 — beda dari Cuti);
pilih jadwal sendiri (dropdown) + pilih rekan tujuan (dropdown) + alasan → `[Kirim Pengajuan Tukar]`
— jika tanggal submit kurang dari H-1, tampil warning non-blocking "Pengajuan mendekati/melewati
H-1, berpotensi teguran verbal sesuai SOP"; card Riwayat (status "Menunggu Rekan"/"Menunggu PPIC"/final).

**Tab "Izin Keluar"** *(BARU, tab ke-8)*: form Surat Izin Keluar — tanggal, jam rencana keluar, jam
rencana kembali (opsional), keperluan (textarea) → `[Kirim Pengajuan]`. Note: **"Wajib disetujui
Atasan Langsung DAN HRD sebelum keluar area"**; status ditampilkan 2 baris terpisah (Atasan:
Menunggu/Disetujui, HRD: Menunggu/Disetujui). Card Riwayat Pengajuan Izin Keluar. Note kecil di
bawah: "Fingerprint keluar/masuk dicatat oleh mesin absensi di lokasi — tidak melalui aplikasi ini".

**Tab "Jadwal Saya"**: list read-only per hari (tanggal/nama shift/mesin/flag lembur), hanya
CONFIRMED.

**Tab "Kartu Pengenal"**: preview digital badge; card "Ajukan Foto Baru" dgn checklist ketentuan
(4×6cm/600×900px/rasio 2:3/dst) → modal upload dgn **preview + validasi dimensi real-time** (cek
`img.width`/`img.height` aktual, tampilkan warning jika belum memenuhi).

---

## Binding Table (field → kolom, ringkas)

| Area | Field UI | Kolom DB |
|---|---|---|
| List Employee | Kode/Nama/Divisi/Posisi | `emp_employee` JOIN `org_division`/`org_position` |
| Detail — status header | Baris status kepegawaian | `emp_contract WHERE employee_id=? AND is_active=1` (tipe, end_date, is_probation, probation_end_date) |
| Detail — Profil | Agama, Alamat KTP/Domisili | `emp_employee.religion`, `.village_id_ktp`+`.address_detail_ktp`, `.village_id_domisili`+`.address_detail_domisili` |
| Detail — Kontrak, Gaji masking | Kolom Gaji | `emp_contract.salary_amount`, ditentukan tampil/masking oleh `emp_contract.employment_type` + `org_position.salary_hidden_from_hr` + role sesi |
| Detail — BPJS | Kepesertaan & program | `emp_bpjs_kesehatan`, `emp_bpjs_ketenagakerjaan` |
| Detail — Akun (Admin App saja) | Username, Role | `tm2sys.users WHERE employee_id=?` (cross-DB, Eloquent logical FK) |
| Dokumen Kedaluwarsa | Tabel agregat | `emp_document WHERE expiry_date IS NOT NULL` JOIN `emp_employee`, filter `expiry_date <= CURDATE()+30` atau `< CURDATE()` |
| Approval Koreksi Data | Tabel pending | `emp_correction_request WHERE status='PENDING'` |
| Approval Sakit/Izin/Cuti | Tabel pending | `emp_leave_request WHERE status='PENDING'` |
| Board Roster — baris shift | Nama+jam per baris | `emp_shift WHERE department_id=? AND is_active=1` JOIN `emp_shift_detail` |
| Board Roster — sel | Occupants per sel | `emp_shift_roster WHERE machine_id=? AND shift_id=? AND roster_date=?` |
| Board Roster — Save/Confirm | Status batch | `emp_shift_roster_batch.status`, `emp_shift_roster.batch_id` |
| Portal — Saldo Cuti | 3 angka | `emp_leave_balance WHERE employee_id=? AND year=YEAR(CURDATE())` |
| Portal — Kalender Cuti | Tanggal disabled/selectable | Dihitung app-layer: `CURDATE()+7` (hari kerja) sbg batas bawah; hari libur dari lookup hari besar (di luar scope file ini, sumber data TBD) |
| Portal — Riwayat Pengajuan | Tabel per jenis | `emp_leave_request`/`emp_correction_request`/`emp_family_request`/`emp_shift_swap_request`/`emp_badge_photo_request`/`emp_resign_request WHERE employee_id=?` |
| Portal — Pendidikan | Tabel | `emp_education WHERE employee_id=?` |
| Portal — Ajukan Foto Badge | Dimensi validasi | `emp_badge_photo_request.width_px`/`.height_px`, dicek klien saat upload (JS `Image.onload`) |
| Portal — Izin: validasi saldo | Disable opsi Izin | App-layer cek `emp_leave_balance` sisa; jika >0, disable + pesan arahkan ke Cuti |
| Portal — Izin Mendadak | Kategori+relasi+instansi | `emp_leave_request.izin_category`/`.izin_relasi_pemohon`/`.izin_instansi_asal` |
| Portal — Sakit: 2 dokumen | Upload SKD+kwitansi | `emp_leave_request.attachment_surat_dokter`+`.attachment_kwitansi_resep` ATAU `.attachment_jkn_screenshot` (alternatif tunggal) |
| Portal — Sakit: jam lapor | Input jam + warning | `emp_leave_request.sakit_lapor_jam`, warning app-layer jika >07:30 (PARAMETER) |
| Portal — Tab Izin Keluar | Form + status dual approval | `emp_out_permit_request` (atasan_approved_*, hrd_approved_*) |
| HR Admin — Approval Izin Keluar | Tabel dual approval | `emp_out_permit_request WHERE status='PENDING'` |
| HR Admin — Kedisiplinan | Tabel + auto-insert | `emp_disciplinary`, auto dari trigger app-layer saat Izin >3hr berturut/>3x bulan (lihat TIER_2 B.6c) |
| Tukar Shift — flag telat | Warning non-blocking | `emp_shift_swap_request.submitted_on_time`, dihitung app-layer thd `requester_roster_id.roster_date - 1 hari` |

