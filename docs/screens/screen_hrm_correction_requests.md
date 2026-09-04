---
name: screen_hrm_correction_requests
description: Layar HR untuk approve/reject pengajuan koreksi data karyawan yang diajukan lewat self-service.
---

# Koreksi Data Karyawan (Approval)

**File**: `resources/views/pages/hrm/correction-requests.blade.php`
**Route**: `hrm.correction-requests` (`/hrm/correction-requests`)
**Koneksi DB utama**: mst (tm2mst) — lihat catatan penamaan di bawah
**Modul**: HRM

## Tujuan
Layar review HR untuk menyetujui/menolak pengajuan koreksi data karyawan (mis. tanggal lahir, NIK KTP, telepon, alamat, kontak darurat) yang masuk dari fitur self-service "Ajukan Koreksi". Tabel `emp_correction_request` sudah ada sejak self-service dibangun, tapi sebelumnya tidak pernah punya layar approve/reject sehingga request menumpuk status PENDING selamanya — layar ini menutup gap tersebut (keputusan user 2026-08-27, dipicu temuan field readonly di security-setup). Juga dipakai oleh `SecurityRecoveryService::syncIdentityToEmployee()` untuk mengisi data kosong lewat Setup Keamanan Akun. Berbeda dari alur PR/PO (form asli + approval terpisah), correction request tidak punya dokumen induk — approve/reject langsung terjadi di grid ini tanpa layar detail terpisah. Aktor: staf HR.

## Field & Kolom

### Grid (read-only tabel, tanpa form input tambahan)
- Kolom: Karyawan (identity-cell: nama+kode), Field (nama field yang dikoreksi), Lama → Baru (nilai lama dicoret, nilai baru bold), Alasan (truncate + tooltip), Diajukan (`requested_at`), Status (badge PENDING/APPROVED/REJECTED), Aksi
- Stat box: Menunggu Review (PENDING), Disetujui (APPROVED), Ditolak (REJECTED)
- Urutan: PENDING dulu (`orderByRaw FIELD(status,...)`), lalu berdasarkan `requested_at` terbaru

### Modal Tolak
- `rejectReason` — textarea, wajib, max 255 (dikirim sebagai notifikasi ke karyawan)

## Aksi / Method
- `approve(int $requestId)` — hanya berlaku jika status masih PENDING; jika field termasuk `FIELD_COLUMN_MAP` (auto-apply), langsung update kolom terkait di `emp_employee`; set status `APPROVED` + `reviewed_by`/`reviewed_at`. Field di luar mapping (mis. BPJS/rekening bank — tabel relasi terpisah) approve-nya hanya menutup request, HR tetap harus update manual di layar masing-masing.
- `openReject(int $requestId)` — buka modal reject
- `reject()` — validasi alasan wajib diisi, update status `REJECTED` + `review_note`, kirim `SysNotification` ke user karyawan (dicari via `SecUser::where('employee_id', ...)`)
- `autoApplyColumn(string $fieldName)` — protected, lookup di `FIELD_COLUMN_MAP`:
  - Tanggal Lahir → `birth_date`
  - NIK KTP → `nik_ktp`
  - Telepon / WA → `phone`
  - Alamat KTP → `address_detail_ktp`
  - Alamat Domisili → `address_detail_domisili`
  - Kontak Darurat → `emergency_contact_name`

## Tabel & Model yang Disentuh
- `App\Models\Master\EmpCorrectionRequest` — tabel `emp_correction_request` (koneksi **`tm2mst`**, PK `request_id`) — perhatikan: berada di namespace `App\Models\Master`, terhubung ke koneksi `tm2mst`, BUKAN `tm2hrm`, meski route-nya di grup `/hrm/*`
- `App\Models\Sys\SecUser` — tabel `sec_user`, koneksi `tm2sys`, lintas-DB (lookup user by `employee_id`)
- `App\Models\Sys\SysNotification` — tabel `sys_notification`, koneksi `tm2sys`, untuk kirim notifikasi ke karyawan
- `EmpCorrectionRequest::employee` relationship → `emp_employee` (koneksi `tm2mst`, sama-DB)

## Alur / Business Logic Penting
- **Auto-apply terbatas**: hanya 6 field scalar di `emp_employee` yang otomatis diterapkan saat Approve; field yang butuh tabel relasi terpisah (BPJS, rekening bank, dst.) approve-nya cuma administratif (tutup request), tidak menulis data — HR harus update manual di layar lain.
- **Tanpa dokumen induk**: tidak ada state machine approval bertingkat seperti PR/PO — 1 klik Approve/Reject langsung final di grid.
- **Notifikasi hanya saat Reject**: `SysNotification` dikirim ke karyawan hanya pada reject (memberi alasan), tidak ada notifikasi eksplisit saat approve terlihat di kode ini.

## Catatan
- **Ketidaksesuaian penamaan koneksi vs CLAUDE.md**: dokumen ini berada di grup route `hrm.*` dan folder `pages/hrm/`, tapi model `EmpCorrectionRequest` sebenarnya terhubung ke koneksi `tm2mst` (bukan `tm2hrm`). Ini konsisten dengan CLAUDE.md §3 yang menempatkan prefix `emp_` (employee) di database Master (tm2mst), bukan tm2hrm — jadi bukan bug, tapi penting dicatat karena nama folder/route "hrm" bisa menyesatkan soal database fisik yang dipakai.
- Componentization 2026-08-27: `avatarInitials()`/`avatarGradient()` sudah dipindah ke `App\Support\Grid\Avatar`, dipakai lewat `<x-grid.identity-cell>` — tidak ada logic avatar lokal lagi di file ini.
