---
name: uistandard_component_grid
description: Katalog komponen Blade reusable di resources/views/components/grid/ (stat box, identity cell, action icon, toolbar, modal kolom/sort, pagination, spreadsheet-table) beserta props dan contoh pakai nyata.
---

# Katalog Komponen — Grid

> Lihat juga dokumen konsep terkait: `docs/griduistandard.md` (WAJIB dibaca
> dulu — aturan visual grid Master & Worklist: kotak statistik pastel, kolom
> identitas, kolom Aksi, toolbar), `docs/spreadsheetgridstandard.md` (pola
> TERPISAH untuk grid spreadsheet-editable, opt-in master kecil saja).
> Dokumen ini murni katalog konkret nama tag + props tiap komponen.

## Konsep scope Blade anonymous component (gotcha lintas-komponen)

Beberapa komponen di sini (`export-dropdown`, `filters-row`, `toolbar-buttons`)
sengaja dipanggil via `@include('components.grid.xxx')`, **BUKAN**
`<x-grid.xxx />` — karena anonymous Blade component scope-nya terisolasi,
tidak bisa akses `$this`/properti Livewire milik parent. `@include` menjalankan
di scope Livewire pemanggil, jadi properti seperti `$gridVisibleColumns`
tetap terbaca. Perhatikan ini saat memakai komponen yang butuh state Livewire
langsung.

---

## `<x-grid.stat-row>`
**File**: `resources/views/components/grid/stat-row.blade.php`
**Props**: `count` (wajib, int — dipetakan ke class varian `two`/`three`/
`five`/polos)
**Slot**: default — diisi 1+ `<x-grid.stat-box>`
**Contoh pakai**:
```blade
<x-grid.stat-row :count="3">
    <x-grid.stat-box tone="warn" label="Menunggu Dibayar" :value="$this->metricWaitingApproval" />
    <x-grid.stat-box tone="active" label="Lunas" :value="$this->metricPaid" />
    <x-grid.stat-box tone="total" label="Sisa DP Belum Teralokasi" value="Rp {{ $this->metricOutstandingTotal }}" />
</x-grid.stat-row>
```
**Catatan**: class `.grid`/`two`/`three`/`five` adalah CSS custom di
`resources/css/app.css` (unlayered), **bukan** utility Tailwind `grid-cols-X`
— jangan tebak-tebak ganti ke `grid-cols-3` dsb.

## `<x-grid.stat-box>`
**File**: `resources/views/components/grid/stat-box.blade.php`
**Props**: `tone` ('total' | 'active' | 'warn' | 'critical' | 'extra'),
`label` (wajib), `value` (wajib), `subtitle` (null)
**Slot**: tidak ada
**Contoh pakai**: lihat contoh `<x-grid.stat-row>` di atas.
**Catatan**: 5 warna baku (LOCKED) — jangan bikin tone/warna baru sendiri.
Class Tailwind dalam `match()` harus string literal utuh (bukan hasil
concat) supaya lolos content-scan build-time Tailwind v4.

## `<x-grid.identity-cell>`
**File**: `resources/views/components/grid/identity-cell.blade.php`
**Props**: `id` (wajib, dipakai `App\Support\Grid\Avatar::gradient()`),
`name` (wajib), `code` (null)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-grid.identity-cell :id="$v->voucher_id" :name="$v->partner_name ?: '—'" :code="$v->voucher_number" />
```
**Catatan**: render `<td>` avatar-gradient + Nama + Kode (font mono) —
dipakai persis sama di semua grid Master & Worklist (griduistandard.md §3).
Helper warna avatar statis di `App\Support\Grid\Avatar` (bukan method
Livewire) karena scope anonymous component terisolasi.

## `<x-grid.action-icon>`
**File**: `resources/views/components/grid/action-icon.blade.php`
**Props**: `icon` (wajib), `title` (wajib), `action` (wajib — string method
call Livewire apa adanya, mis. `"openOrder(123)"`), `variant` ('default' |
'danger' | 'success'), `confirm` (null — dialog `wire:confirm`), `bordered`
(false — HANYA 2 layar pilot `co/list` & `raw-material/index`, jangan
digeneralisasi tanpa tanya)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-grid.action-icon icon="✎" title="Edit" action="openModal('edit', {{ $a->account_id }})" />
<x-grid.action-icon icon="⏻" :title="$a->activate ? 'Nonaktifkan' : 'Aktifkan'"
    action="toggleActive({{ $a->account_id }})" :variant="$a->activate ? 'danger' : 'default'"
    confirm="{{ $a->activate ? 'Nonaktifkan' : 'Aktifkan' }} akun {{ $a->account_code }}?" />
```
**Catatan**: grid Master = 2 ikon wajib (✎ Edit, ⏻ Toggle); grid non-Master
= ↗ Buka + ✓ Confirm kondisional, TIDAK PERNAH toggle aktif/nonaktif
(griduistandard.md §7).

## `<x-grid.toggle-switch>`
**File**: `resources/views/components/grid/toggle-switch.blade.php`
**Props**: `on` (wajib, bool), `action` (wajib), `confirm` (null), `title`
(null)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-grid.toggle-switch :on="$u->activate" action="toggleUserActive({{ $u->id_user }})"
    confirm="Ubah status pengguna {{ $u->name }}?" title="Toggle aktif" />
```
**Catatan**: pilot (2026-08-30) — visual switch ala Filament/PowerGrid,
tapi TETAP wajib `wire:confirm` (tmerp sengaja tidak toggle instan). Mekanisme
sama persis dengan `<x-grid.action-icon icon="⏻">`, cuma visualnya beda.

## `<x-grid.action-dropdown>`
**File**: `resources/views/components/grid/action-dropdown.blade.php`
**Props**: `actions` (wajib — array of
`['icon','label','action','confirm'=>null,'variant'=>'default'|'success'|'danger','tooltip'=>null]`)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-grid.action-dropdown :actions="[
    ['icon' => '↗', 'label' => 'Buka', 'action' => 'openOrder('.$o->id.')'],
    ['icon' => '✓', 'label' => 'Confirm', 'action' => 'confirmOrder('.$o->id.')', 'variant' => 'success', 'confirm' => 'Confirm order ini?'],
]" />
```
**Catatan**: pilot (2026-08-30) — 1 tombol "⋮" mengumpulkan semua aksi
per-baris jadi dropdown ala Filament, menggantikan ikon sejajar. Posisi kolom
tetap di KIRI. `x-data` lokal per-baris (bukan Livewire state).

## `<x-grid.table-shell>`
**File**: `resources/views/components/grid/table-shell.blade.php`
**Props**: tidak ada
**Slot**: default — isi `<table>` penuh
**Contoh pakai**:
```blade
<x-grid.table-shell>
    <table class="w-full text-[12.5px]">
        <thead><tr><th class="sticky top-0 z-[1] ...">...</th></tr></thead>
        <tbody>...</tbody>
    </table>
</x-grid.table-shell>
```
**Catatan**: wrapper scroll+sticky-header standar (`max-h-[65vh] overflow-auto`).
`<th>` di dalam masih wajib class `sticky top-0 z-[1]` manual — jumlah/lebar
kolom beda-beda per grid, tak cukup digeneralisasi jadi 1 slot kompleks.

## `<x-grid.empty-row>`
**File**: `resources/views/components/grid/empty-row.blade.php`
**Props**: `colspan` (wajib), `message` ('Belum ada data.')
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-grid.empty-row :colspan="7" message="Belum ada tagihan uang muka." />
```

## `<x-grid.group-header-row>`
**File**: `resources/views/components/grid/group-header-row.blade.php`
**Props**: `label` (wajib), `value` (wajib), `colspan` (wajib)
**Slot**: tidak ada
**Contoh pakai**:
```blade
@foreach ($groups as $groupValue => $rowsInGroup)
    <x-grid.group-header-row label="Status" :value="$groupValue" :colspan="7" />
    @foreach ($rowsInGroup as $row) ... @endforeach
@endforeach
```
**Catatan**: dipakai saat fitur "Group By" grid (dari `<x-grid.column-modal>`)
aktif — 1 baris pemisah per grup di dalam `<tbody>`.

## `<x-grid.pagination-footer>`
**File**: `resources/views/components/grid/pagination-footer.blade.php`
**Props**: menerima `$paginator` (instance `LengthAwarePaginator`) via
variabel scope (bukan `@props`, komponen ini pakai variabel implisit dari
`@include`/context Livewire — cek pemanggilan nyata sebelum reuse)
**Slot**: tidak ada
**Contoh pakai**: footer 3-bagian ala ScriptCase (Go to page + View
baris/halaman di kiri, navigasi di tengah, info total di kanan) — cek
`co/list.blade.php` untuk pemakaian lengkap.
**Catatan**: butuh method Livewire `gotoPage`, `setPerPage`, `previousPage`,
`nextPage`, dan `$this->gridPageRange($paginator)` di komponen pemanggil.

## `<x-grid.toolbar>`
**File**: `resources/views/components/grid/toolbar.blade.php`
**Props**: tidak ada eksplisit (`@props([])`, tapi `$attributes->merge()`
dipakai — boleh tambah `class` dari luar, mis. layar report butuh
`flex-wrap`)
**Slot**: default (search box, dievaluasi di scope Livewire pemanggil, BUKAN
scope komponen), named slot `tools` (tombol Filters/Kolom/Sort/Export),
named slot `newRecord`
**Contoh pakai**:
```blade
<x-grid.toolbar>
    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Cari..." class="...">
    <x-slot:tools>
        @include('components.grid.toolbar-buttons')
    </x-slot:tools>
    <x-slot:newRecord>
        <button wire:click="openModal('create')" class="...">+ New Record</button>
    </x-slot:newRecord>
</x-grid.toolbar>
```
**Catatan**: grid Master selalu ada "+ New Record"; grid Worklist tergantung
apakah record lahir dari grid ini (griduistandard.md §7).

## `<x-grid.column-modal>`
**File**: `resources/views/components/grid/column-modal.blade.php`
**Props**: tidak ada `@props` eksplisit — bergantung penuh pada properti
Livewire pemanggil (`$showColumnModal`, `$columnDefs`, `$columnModalSelected`,
`$columnModalSearch`, `$gridGroupBy`) → HARUS di-`@include`, bukan `<x-.../>`
**Slot**: tidak ada
**Contoh pakai**: `@include('components.grid.column-modal')` di dalam layar
grid yang pakai trait `HasCustomizableGrid`.
**Catatan**: dual-listbox Tersedia/Tampil (klik pindah kolom) + pemilihan
Group By dari kolom yang tampil. Method Livewire terkait:
`addColumnToSelection`, `removeColumnFromSelection`, `addAllColumns`,
`removeAllColumns`, `setGridGroupBy`, `resetGridPreference`,
`applyColumnSelection`.

## `<x-grid.sort-modal>`
**File**: `resources/views/components/grid/sort-modal.blade.php`
**Props**: tidak ada `@props` — bergantung pada `$showSortModal`,
`$gridVisibleColumns`, `$columnDefs`, `$sortModalColumn`, `$sortModalDirection`
→ `@include`, bukan `<x-.../>`
**Slot**: tidak ada
**Contoh pakai**: `@include('components.grid.sort-modal')`.
**Catatan**: pilih 1 kolom (dari yang sedang tampil) + arah ASC/DESC, lalu
`applySortSelection`.

## `<x-grid.filters-panel>`
**File**: `resources/views/components/grid/filters-panel.blade.php`
**Props**: tidak ada `@props` — pakai `$showSearchDropdown`, `$searchColumns`
dari scope Livewire → `@include`
**Slot**: tidak ada (ini hanya TOMBOL "🔽 Filters", bukan panelnya)
**Contoh pakai**: ditaruh di dalam `<x-grid.toolbar>` slot `tools`, sebelum
grup Kolom/Sort/Export.
**Catatan**: PILOT ala PowerGrid, HANYA dipakai `co/list.blade.php` — jangan
roll out ke grid lain tanpa ditanya user dulu.

## `<x-grid.filters-row>`
**File**: `resources/views/components/grid/filters-row.blade.php`
**Props**: butuh variabel `$columnDefs` dikirim eksplisit lewat
`@include('components.grid.filters-row', ['columnDefs' => $this->gridSearchColumnDefs()])`
— juga pakai `$showSearchDropdown`, `$searchModalColumns`,
`$searchModalCondition` dari scope Livewire
**Slot**: tidak ada
**Contoh pakai**: dirender TERPISAH sesudah `<x-grid.toolbar>` (bukan di
dalam slot manapun) karena toolbar induk `flex` tanpa wrap.
**Catatan**: isi & state SAMA PERSIS dengan `search-box.blade.php` versi lama
(caret popover, tetap dipakai di grid lain) — cuma tata letak beda. HANYA
`co/list.blade.php`.

## `<x-grid.toolbar-buttons>`
**File**: `resources/views/components/grid/toolbar-buttons.blade.php`
**Props**: tidak ada `@props` — WAJIB `@include`, bukan `<x-.../>`
**Slot**: tidak ada
**Contoh pakai**: `@include('components.grid.toolbar-buttons')` di dalam
slot `tools` milik `<x-grid.toolbar>`.
**Catatan**: grup tombol ☰ Kolom (`openColumnModal`), ↕ Sort
(`openSortModal`), lalu `@include('components.grid.export-dropdown')`.

## `<x-grid.export-dropdown>`
**File**: `resources/views/components/grid/export-dropdown.blade.php`
**Props**: tidak ada `@props` — butuh `$gridVisibleColumns` dari scope
Livewire → `@include`
**Slot**: tidak ada
**Contoh pakai**: sudah otomatis ter-include lewat `toolbar-buttons.blade.php`
di atas; bisa juga di-include manual terpisah.
**Catatan**: dropdown ringan CSV/Excel/PDF — export SELALU ikut kolom yang
sedang TAMPIL di grid (`$gridVisibleColumns`), tidak ada lagi modal pilih
kolom export terpisah. Method Livewire: `exportCsv`/`exportExcel`/`exportPdf`
di trait `HasCustomizableGrid`.

## `<x-grid.spreadsheet-table>`
**File**: `resources/views/components/grid/spreadsheet-table.blade.php`
**Props**: `gridKey` (wajib), `columns` (wajib, array def per kolom:
`type` text/number/select/boolean/action, `label`, dst), `rows` (wajib),
`newRowDefaults` (wajib), `errors` ([]), `version` (0 — WAJIB naik tiap
`saveSpreadsheetBatch()` supaya `wire:key` berubah dan Alpine full re-init)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-grid.spreadsheet-table gridKey="currency" :columns="$this->spreadsheetColumns"
    :rows="$this->spreadsheetRows" :new-row-defaults="$this->newRowDefaults"
    :errors="$this->spreadsheetErrors" :version="$this->spreadsheetVersion" />
```
**Catatan**: lihat `docs/spreadsheetgridstandard.md` — pola TERPISAH,
opt-in HANYA untuk master kecil/kolom scalar (pilot: Master Currency, list
Satuan UOM). Semua render+state edit di client (Alpine,
`resources/js/spreadsheet-grid.js`); server dipanggil 1x saat "Simpan Semua"
lewat kontrak `App\Livewire\Concerns\HasSpreadsheetGrid`. Kolom tipe
`'action'` bukan sel edit — tombol yang manggil 1 method Livewire dengan
1 parameter (`col.paramKey`, default `'id'`).
