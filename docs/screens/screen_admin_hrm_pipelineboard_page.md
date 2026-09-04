---
name: screen_admin_hrm_pipelineboard_page
description: Custom Filament Page pembungkus navigasi untuk Pipeline Board rekrutmen (kanban drag-drop), reuse komponen Livewire lama apa adanya.
---

# Pipeline Board (Filament Page)

**File**: `app/Filament/Clusters/Hrm/Pages/PipelineBoard.php`
**Route/Panel URL**: TBD - perlu verifikasi (Custom Page Filament, biasanya `/admin/hrm/pipeline-board` mengikuti slug default dari nama class; tidak ada override `getSlug()`/`getRouteName()` di file ini).
**Tipe**: Page (Custom Page, bukan Resource)
**Koneksi DB utama**: Tidak langsung -- delegasi penuh ke komponen Livewire lama (lihat bagian Alur).
**Modul**: Admin Dashboard (Filament) -- HRM > Recruitment

## Tujuan
Halaman ini adalah contoh konversi layar dashboard non-CRUD (kanban drag-drop, bukan grid/form standar) ke Filament Custom Page TANPA menulis ulang logikanya. Class ini murni pintu navigasi + routing Filament; isi sesungguhnya tetap komponen Livewire v4 asli (`pages::recruitment.pipeline-board`, file `resources/views/pages/recruitment/pipeline-board.blade.php`) -- state, drag-drop, dan validasi server sama persis, tidak disentuh.

## Field & Kolom (Filament Schema)
Bukan form/table Filament -- properti navigasi statis saja:
- `$view` = `'filament.clusters.hrm.pages.pipeline-board'` (view wrapper).
- `$navigationIcon` = `Heroicon::OutlinedViewColumns`.
- `$cluster` = `HrmCluster::class`.
- `$navigationGroup` = `'Recruitment'`.
- `$navigationLabel` = `'Pipeline Board'`.
- `$navigationSort` = `40`.
- `$title` = `'Pipeline Board'`.

Data referensi `sys_app_menu` menurut komentar kode: parent group `HRM_RECRUITMENT` (label "Recruitment", `sort_order=50`), item `HRM_RCR_PIPELINE` (label "Pipeline Board", `sort_order=40`).

## Aksi / Method
Tidak ada method kustom di class ini. View wrapper `resources/views/filament/clusters/hrm/pages/pipeline-board.blade.php` cuma memanggil `@livewire('pages::recruitment.pipeline-board')` di dalam `<x-filament-panels::page>` -- seluruh logic aksi (pindah kandidat antar kolom, dst.) ada di komponen Livewire lama tersebut, di luar cakupan file yang didokumentasikan sesi ini.

## Tabel & Model yang Disentuh
Tidak ada di file ini secara langsung -- lihat komponen Livewire `pages::recruitment.pipeline-board` untuk model/tabel sesungguhnya (di luar scope dokumentasi ini).

## Alur / Business Logic Penting
- Pola reuse: Filament Page hanya menyediakan navigasi (cluster/group/sort/icon) dan view shell (`<x-filament-panels::page>`), tanpa mengubah komponen Livewire SFC yang sudah ada.
- Berada di dalam `HrmCluster`, grup navigasi "Recruitment" -- posisi/urutan dipertahankan persis sesuai `sys_app_menu` lewat properti statis di file ini (bukan angka bebas).

## Catatan
Tidak ada TODO/gap eksplisit di komentar file ini selain penegasan bahwa ini "contoh konversi" -- pola yang sama (Custom Page + view wrapper `@livewire(...)`) kemungkinan dipakai lagi untuk migrasi layar non-CRUD lain ke Filament.
