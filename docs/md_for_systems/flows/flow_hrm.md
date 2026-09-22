---
name: flow_hrm
description: Alur kerja HRM -- Struktur Organisasi, Shift Roster, dan approval Cuti/Koreksi Data.
---

# Alur HRM

## Cakupan
Dokumen ini merangkum alur kerja 5 layar HRM yang sudah dibangun: Master Posisi, Struktur Organisasi (org-chart), Shift Roster, Approval Cuti/Sakit/Izin, dan Approval Koreksi Data Karyawan. Catatan arsitektur penting: kelima model yang mendasari layar-layar ini (`OrgPosition`, `EmpShiftRoster`, `EmpLeaveRequest`, `EmpCorrectionRequest`, dst.) semuanya berada di namespace `App\Models\Master` dan terhubung ke koneksi **`tm2mst`**, BUKAN `tm2hrm` — meski route dan folder file-nya `hrm/*`. Ini konsisten dengan CLAUDE.md §3 yang menempatkan prefix tabel `emp_`/`org_` di database Master (tm2mst), jadi bukan kesalahan, tapi penting diketahui karena penamaan folder/route bisa menyesatkan soal database fisik.

## Tahap -> Layar -> Aktor

| Tahap | Layar | Route | Aktor | Keterangan |
|---|---|---|---|---|
| 1a. Setup struktur jabatan | Master Posisi | `hrm.position` | Staf HR | Definisikan posisi/jabatan: kode, nama, departemen, level, dan field kunci `reports_to_position_id` ("Melapor Ke") — sumber tunggal hierarki organisasi. Validasi anti-siklus (`wouldCreateCycle()`) mencegah relasi atasan-bawahan melingkar. |
| 1b. Visualisasi struktur | Struktur Organisasi (Org Chart) | `hrm.org-chart` | HR, manajemen, siapa saja | Murni read-only — merender pohon dari `org_position.reports_to_position_id` yang diisi di tahap 1a. Fitur: zoom, search/highlight, filter departemen, cetak. Tidak ada input data di sini. |
| 2. Penjadwalan shift | Shift Roster & Alokasi Mesin | `hrm.shift-roster` | PPIC (papan mesin Offset Printing), HR (grid non-mesin Digital Printing/Indostry) | 2 mode terpisah: PPIC drag-drop pegawai ke sel mesin×shift×tanggal dengan lifecycle Draft→Confirm (validasi bentrok hard sebelum publish); HR klik-siklus shift per sel tanpa draft, langsung tersimpan. Bergantung data pegawai aktif per divisi/departemen dari tahap 1a (`org_department`→`org_division`). |
| 3. Approval Cuti/Sakit/Izin | Approval Cuti/Sakit/Izin | `hrm.leave-requests` | Staf HR | Karyawan submit lewat self-service (di luar batch layar ini) dengan validasi SOP sudah ditegakkan saat submit. Layar ini approve/reject: approve untuk CUTI biasa menambah `EmpLeaveBalance.used_days`; SAKIT/IZIN/Cuti Khusus tidak memotong saldo. Notifikasi dikirim ke karyawan baik approve maupun reject. |
| 4. Approval Koreksi Data Karyawan | Koreksi Data Karyawan | `hrm.correction-requests` | Staf HR | Karyawan ajukan koreksi data pribadi lewat self-service atau Setup Keamanan Akun. Approve untuk 6 field tertentu (tanggal lahir, NIK KTP, telepon, alamat KTP/domisili, kontak darurat) langsung update `emp_employee`; field lain (BPJS, rekening bank) approve-nya hanya menutup request — HR update manual di layar masing-masing. Notifikasi hanya dikirim saat reject. |

## Urutan Ketergantungan Data
1. **Master Posisi** (tahap 1a) harus terisi dulu — termasuk `department_id`, `level_id`, `reports_to_position_id` — sebelum **Org Chart** (1b) bisa menampilkan pohon yang bermakna (kalau semua posisi belum diatur "Melapor Ke", org-chart menampilkan pesan bantuan mengarahkan ke Master Posisi).
2. **Shift Roster** bergantung pada `org_division`/`org_department` (untuk memisahkan pool Offset Printing vs Digital Printing/Indostry) dan `emp_employee` aktif — tidak bergantung langsung pada hierarki `reports_to_position_id`.
3. **Approval Cuti** dan **Approval Koreksi Data** adalah 2 alur independen satu sama lain dan independen dari struktur organisasi/shift — keduanya hanya bergantung pada data pengajuan yang sudah dibuat lewat self-service (di luar batch dokumentasi ini) dan mengubah `emp_employee`/`emp_leave_balance` saat approve.

## Gap / Belum Terhubung
- **Approval Cuti & Koreksi Data sebelumnya tidak punya sisi approval sama sekali** — kedua layar ini dibangun khusus untuk menutup gap tersebut (2026-08-27), sebelum itu request menumpuk status PENDING selamanya dan tidak ada efek ke data karyawan/saldo cuti.
- **Shift Roster papan PPIC vs Grid HR** adalah 2 mekanisme berbeda total (lifecycle draft/confirm vs langsung tersimpan) — tidak ada 1 antarmuka terpadu untuk semua divisi.
- **Org Chart murni visualisasi** — tidak ada fitur edit di kanvas (drag-connect), version history, atau minimap; semua perubahan struktur harus lewat Master Posisi.
- Modul **Payroll** (tm2pyr, dengan enkripsi data gaji) dan **Time Attendance** disebut di CLAUDE.md sebagai bagian scope HRM tapi TIDAK termasuk dalam 5 layar yang didokumentasikan di sini — di luar cakupan batch ini.
