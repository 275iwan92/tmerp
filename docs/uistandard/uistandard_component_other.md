---
name: uistandard_component_other
description: Katalog komponen Blade reusable di resources/views/components/{alert,auth,inquiry,org-chart,prodev}/ beserta props dan contoh pakai nyata.
---

# Katalog Komponen — Alert, Auth, Inquiry, Org-Chart, ProDev

> Lihat juga dokumen konsep terkait: `docs/themestandard.md` (alert warna
> ikut token tema shell lama), `docs/configurationstandard.md` (branding
> nama/logo dipakai komponen `auth/*`), CLAUDE.md §4 "InqProd Engine" &
> "ProDev/ProCost 2-mode" (konteks bisnis komponen `inquiry/*` dan
> `prodev/*`). Dokumen ini murni katalog konkret props/slot — bukan folder
> yang punya 1 dokumen standar sendiri di `/docs`, jadi dikumpulkan di sini.

---

## Alert

### `<x-alert.toast>`
**File**: `resources/views/components/alert/toast.blade.php`
**Props**: tidak ada
**Slot**: tidak ada
**Contoh pakai**: dipasang SEKALI per layout (`layouts/app.blade.php` &
`layouts/shell.blade.php`), bukan dipanggil per-halaman.
```blade
<x-alert.toast />
```
**Catatan**: kontainer toast global. Kontrak pemanggilan dari Livewire mana
pun: `$this->dispatch('app-alert', type: 'success', message: '...')` — lihat
store Alpine di `resources/js/app-alerts.js` (`appAlerts()`).

### `<x-alert.banner>`
**File**: `resources/views/components/alert/banner.blade.php`
**Props**: `type` ('info' | 'success' | 'warning' | 'danger'), `message`
(null — kalau null, banner tidak dirender sama sekali)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-alert.banner type="success" :message="session('flash')" />
<x-alert.banner type="danger" :message="session('error')" />
```
**Catatan**: varian inline (2026-08-31) pengganti banner hijau/merah yang
dulu copy-paste manual tiap halaman transaksi. Tetap baca `session()`
langsung lewat prop `:message`, tidak ada logika PHP baru di
controller/Livewire pemanggil.

---

## Auth (branding halaman login/register)

### `<x-auth.hero-brand>`
**File**: `resources/views/components/auth/hero-brand.blade.php`
**Props**: tidak ada (baca langsung dari `App\Support\Branding\Brand`,
sumber `tm2sys.sys_constant`)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-auth.hero-brand />
```
**Catatan**: brand besar di panel hero login/register — logo gradient badge
+ nama + tagline. Sumber diedit via layar Configuration, bukan hardcode.

### `<x-auth.top-brand>`
**File**: `resources/views/components/auth/top-brand.blade.php`
**Props**: tidak ada (sama sumber dengan `hero-brand`)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-auth.top-brand />
```
**Catatan**: brand kecil di atas kartu halaman auth sekunder
(forgot-password, password-change, security-setup). Logo fallback emoji 📦
kalau belum ada logo upload (kartu terlalu sempit untuk badge gradient
penuh seperti `hero-brand`).

---

## Inquiry

### `<x-inquiry.readonly-summary>`
**File**: `resources/views/components/inquiry/readonly-summary.blade.php`
**Props**: `inquiry` (null — model `IqpInquiry`, null kalau job Mode #2
manual tanpa Inquiry), `parts` ([] — array `[['part' => IqpPart, 'summary' =>
array], ...]`, `summary` dihitung induk lewat method `partSummary()`)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-inquiry.readonly-summary :inquiry="$this->inquiry" :parts="$this->partSummaries" />
```
**Catatan**: ringkasan Inquiry Product cross-nav READ-ONLY, dipakai di
ProDev/Costing/Master Card supaya officer bisa lihat konteks job asal tanpa
balik ke form input. SENGAJA komponen TERPISAH dari `inquiry/form.blade.php`
(bukan cabang kondisional di file yang sama) — ditemukan bug nyata 2026-09-04
saat mode lihat vs mode edit sempat merender KEDUANYA sekaligus di file
>1900 baris; pola pisah-komponen ini mitigasi terverifikasi. Render diecut
ASLI via `window.TMDieline.svg()` (DSL sama dengan preview live form input,
lihat `resources/js/tmdieline.js`).

---

## Org-Chart

### `<x-org-chart.node>`
**File**: `resources/views/components/org-chart/node.blade.php`
**Props**: `node` (wajib), `depth` (0), `color` (`'#94a3b8'`), `highlightIds`
([])
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-org-chart.node :node="$root" :depth="0" :color="$this->branchColor($i)" :highlight-ids="$this->highlightIds" />
```
**Catatan**: komponen REKURSIF — anonymous Blade component boleh include
dirinya sendiri by tag name, dipakai render pohon struktur organisasi tak
terbatas kedalaman (dibatasi depth di PHP lewat `OrgChart::tree()`). Garis
penghubung pakai `<ul><li>::before/::after`, warna per-cabang via inline
`style` (BUKAN class Tailwind dinamis — build-time scan tak mendeteksi hex
yang di-generate runtime).

---

## ProDev

### `<x-prodev.wizard-bar>`
**File**: `resources/views/components/prodev/wizard-bar.blade.php`
**Props**: `current` (wajib — key step aktif: `inquiry`|`development`|
`costing`|`mastercard`|`analytics`), `steps` (wajib — array keyed step key
→ `{label, url, reason?}`; `url` null = step tidak reachable untuk job aktif,
tapi tombolnya TETAP bisa diklik untuk lihat alasan di popover, jangan pernah
bikin nav item mati total), `jobs` ([] — daftar job untuk dropdown), `jobs`
opsional, `selectedJobId` (null), `jobSwitchMethod` ('switchJob'), `backUrl`
(null), `backLabel` ('Kembali ke Grid')
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-prodev.wizard-bar current="development" :steps="$this->wizardSteps"
    :jobs="$this->jobOptions" :selected-job-id="$this->partId"
    :back-url="route('production.prodev.list')" />

<x-prodev.wizard-bar current="costing" :steps="$this->wizardSteps" :back-url="route('inquiry.costing.list')" />
```
**Catatan**: wizard step-bar lintas modul (Inquiry Product, Pro-Dev, Product
Costing, Master Card) supaya officer bisa lompat antar tahap job yang SAMA
tanpa balik ke grid dulu. SATU tombol Back seragam dipasang di sini (bukan
diulang per-layar) — insiden nyata 2026-09-04: cabang kondisional di
`inquiry/form.blade.php` sempat tak punya tombol back sama sekali sebelum
disentralisasi. Warna pakai `bg-navy`/`text-accent` yang otomatis ikut
`tm2sys.sys_theme` global, bukan palet statis dari mockup asli.
