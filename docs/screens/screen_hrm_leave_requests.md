---
name: screen_hrm_leave_requests
description: Layar HR untuk approve/reject pengajuan Cuti/Sakit/Izin/Cuti Khusus dari self-service karyawan.
---

# Approval Cuti/Sakit/Izin

**File**: `resources/views/pages/hrm/leave-requests.blade.php`
**Route**: `hrm.leave-requests` (`/hrm/leave-requests`)
**Koneksi DB utama**: mst (tm2mst) — lihat catatan penamaan di bawah
**Modul**: HRM

## Tujuan
Layar review HR untuk menyetujui/menolak pengajuan cuti (CUTI/SAKIT/IZIN/Cuti Khusus) yang diajukan lewat self-service (`resources/views/pages/self-service/index.blade.php`). Self-service sudah bisa submit sejak awal dan sudah menegakkan aturan SOP (H+7/H-7, maksimal 3 hari, dokumen wajib, lock Izin selama saldo cuti > 0, dst.) tapi TIDAK PERNAH punya sisi approval — request menumpuk PENDING dan `used_days` saldo cuti tidak pernah bertambah (pola gap yang sama seperti `EmpCorrectionRequest`, lihat `screen_hrm_correction-requests.md`). Approve di layar ini yang benar-benar menambah `EmpLeaveBalance.used_days` — hanya untuk request yang `deducts_leave_balance` (CUTI biasa; Sakit/Izin/Cuti Khusus tidak memotong saldo, sesuai logika `submitLeave()` di self-service). Aktor: staf HR.

## Field & Kolom

### Grid (read-only)
- Kolom: Karyawan (identity-cell), Jenis (badge CUTI/SAKIT/IZIN/lainnya + label event untuk Cuti Khusus/Izin kategori), Periode (`date_from`—`date_to` + total hari, ditandai "(potong saldo)" jika `deducts_leave_balance`), Lampiran (daftar link dokumen), Alasan (truncate+tooltip), Diajukan, Status (badge), Aksi
- Stat box: Menunggu Review, Disetujui, Ditolak
- Urutan sama seperti correction-requests: PENDING dulu, lalu `requested_at` terbaru

### Modal Tolak
- `rejectReason` — textarea wajib, max 255

## Aksi / Method
- `eventLabel(EmpLeaveRequest $r)` — resolve label event dari `khusus_event_type` (MENIKAH, MENIKAHKAN_KHITAN_ANAK, ISTRI_MELAHIRKAN, KELUARGA_MENINGGAL, IBADAH) atau `izin_category` (MUSIBAH, INSTANSI_RESMI, KEPENGURUSAN_MUSIBAH)
- `attachments(EmpLeaveRequest $r)` — kumpulkan link lampiran non-kosong: Surat Cuti, Surat Dokter, Kwitansi/Resep, Screenshot JKN, Lampiran umum (`attachment_path`)
- `approve(int $requestId)` — hanya PENDING; jika `deducts_leave_balance`, `firstOrCreate` `EmpLeaveBalance` (default `entitlement_days=12`) lalu `increment('used_days', $req->total_days)`; set status APPROVED + kirim notifikasi sukses ke karyawan
- `openReject(int $requestId)` / `reject()` — validasi alasan wajib, set status REJECTED + `review_note`, kirim notifikasi warning
- `notifyEmployee(EmpLeaveRequest $req, string $title, string $message, string $type)` — private, cari `SecUser` via `employee_id`, buat `SysNotification`

## Tabel & Model yang Disentuh
- `App\Models\Master\EmpLeaveRequest` — tabel `emp_leave_request` (koneksi **`tm2mst`**, PK `request_id`)
- `App\Models\Master\EmpLeaveBalance` — saldo cuti tahunan per karyawan (koneksi `tm2mst`, kunci unik `employee_id`+`year`, kolom `entitlement_days`/`used_days`/`carried_over_days`)
- `App\Models\Sys\SecUser`, `App\Models\Sys\SysNotification` — koneksi `tm2sys`, lintas-DB untuk notifikasi
- `EmpLeaveRequest::employee` relationship → `emp_employee` (sama-DB `tm2mst`)

## Alur / Business Logic Penting
- **Potong saldo hanya untuk CUTI biasa**: field `deducts_leave_balance` (ditentukan saat submit di self-service, bukan di layar ini) menentukan apakah approve menambah `used_days`. SAKIT/IZIN/Cuti Khusus tidak memotong saldo cuti tahunan.
- **`firstOrCreate` saldo tahun berjalan**: kalau `EmpLeaveBalance` untuk employee+tahun belum ada, dibuat otomatis dengan `entitlement_days=12` sebagai default saat approve pertama kali — bukan proses inisialisasi terpisah.
- **Notifikasi 2 arah**: baik approve maupun reject mengirim `SysNotification` ke karyawan pemohon (beda dari correction-requests yang hanya notif saat reject).
- **Validasi SOP (H+7/H-7, maks 3 hari, dsb.) TIDAK ada di file ini** — semua validasi pengajuan sudah dilakukan di self-service saat submit; layar approval ini murni mengubah status + efek saldo, tanpa validasi ulang aturan SOP.

## Catatan
- **Ketidaksesuaian penamaan koneksi vs CLAUDE.md** (sama seperti correction-requests): model `EmpLeaveRequest`/`EmpLeaveBalance` terhubung ke `tm2mst`, bukan `tm2hrm`, meski folder/route "hrm" — sesuai CLAUDE.md §3 yang menempatkan prefix `emp_` di database Master.
- Tidak ada fitur cetak/lampiran wajib validasi ulang di layar approval ini — hanya menampilkan link file yang sudah diupload di self-service.
