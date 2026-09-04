---
name: screen_admin_hrm_cluster
description: Cluster navigasi Filament untuk modul Human Resources (level "Module" di sys_app_menu).
---

# HRM Cluster

**File**: `app/Filament/Clusters/Hrm/HrmCluster.php`
**Route/Panel URL**: TBD - perlu verifikasi (Cluster Filament tidak punya route sendiri; klik navigasinya biasanya redirect ke Resource/Page pertama di dalam cluster berdasarkan `navigationSort` -- lihat `getPages()`/`getResources()` bawaan Filament).
**Tipe**: Cluster
**Koneksi DB utama**: Tidak ada (class ini murni penampung navigasi, tidak menyentuh model/tabel).
**Modul**: Admin Dashboard (Filament) -- HRM

## Tujuan
`HrmCluster` adalah wadah navigasi Filament yang merepresentasikan level "Module" dari `sys_app_menu` (code `HRM`, label "Human Resources", `sort_order=45` -- lihat migration `2026_08_29_000004`). Semua Resource dan Page di dalam namespace `App\Filament\Clusters\Hrm\*` otomatis masuk ke 1 grup navigasi ini di sidebar/topbar panel `/admin`, mencerminkan hierarki lama Module->Group->Item dari shell custom tanpa merombaknya (syarat user 2026-08-30).

## Field & Kolom (Filament Schema)
Tidak ada form/table -- Cluster hanya deklarasi properti statis navigasi:
- `$navigationIcon` = `Heroicon::OutlinedUserGroup`.
- `$navigationLabel` = `'Human Resources'`.
- `$navigationSort` = `45`.

## Aksi / Method
Tidak ada method kustom -- seluruhnya property override bawaan `Filament\Clusters\Cluster`.

## Tabel & Model yang Disentuh
Tidak ada.

## Alur / Business Logic Penting
- Sub-grup navigasi di dalam cluster ini (Organization/Employee/Payroll/Time Attendance/Recruitment) TIDAK diatur di file ini -- masing-masing diatur lewat properti `$navigationGroup` di tiap Resource/Page anggota cluster (lihat `EmpEmployeeResource`, `OrgPositionResource`, `PipelineBoard`).
- Urutan grup navigasi (Organization < Employee < Payroll < Time Attendance < Recruitment) didaftarkan eksplisit di `AdminPanelProvider::panel()` via `->navigationGroups([...])`, karena Filament secara default mengurutkan grup berdasar urutan ditemukan saat discovery, bukan `sort_order` dari DB.

## Catatan
Tidak ada TODO/gap eksplisit di komentar kode file ini. Komentar file menegaskan ini bagian dari migrasi bertahap Filament (fase 2026-08-30) yang berjalan berdampingan dengan shell lama.
