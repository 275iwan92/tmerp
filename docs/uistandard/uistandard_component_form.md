---
name: uistandard_component_form
description: Katalog komponen Blade reusable di resources/views/components/form/ (input, select, textarea, checkbox, toggle, badge, date-picker) beserta props dan contoh pakai nyata.
---

# Katalog Komponen — Form

> Lihat juga dokumen konsep terkait: `docs/griduistandard.md` (form modal di
> dalam grid), `docs/ganttboardstandard.md`/`docs/kanbanboardstandard.md`
> (form dalam modal Gantt/Kanban), `docs/spreadsheetgridstandard.md` (grid
> spreadsheet-editable, TIDAK pakai komponen form/* ini — punya input inline
> sendiri). Dokumen ini murni katalog konkret nama tag + props, bukan
> penjelasan konsep "dialek legacy vs Tailwind" (baca komentar tiap komponen
> untuk detail keputusan desainnya).

## Konsep bersama semua komponen di folder ini

Semua komponen form (kecuali `badge`) berbagi 2 mekanisme:

1. **`$attributes` diteruskan apa adanya** ke elemen input asli (`wire:model`,
   `wire:change`, `disabled`, `readonly`, `class`, dst) — komponen TIDAK tahu
   mode binding yang dipakai pemanggil.
2. **`:legacy=true` / `:legacy="'namaClass'"`** — mode dialek CSS global lama
   (`app.css`, class `.f`/`.fld`/`.hint`) untuk layar yang belum migrasi ke
   Tailwind (mis. `inquiry/form.blade.php`, `.secpage`). Default `legacy=false`
   = dialek Tailwind (wrapper `flex flex-col gap-1` + label `text-[10px]
   text-muted uppercase font-semibold`). `:col2`/`:col3`/`:col4` menambah
   modifier lebar kolom ke wrapper dialek legacy `"f"`.

---

## `<x-form.input>`
**File**: `resources/views/components/form/input.blade.php`
**Props**: `label` (null), `name` (null), `type` ('text'), `hint` (null),
`error` (null, fallback ke `$errors->first($name)` kalau `name` diisi),
`legacy` (false), `col2` (false), `col3` (false), `col4` (false)
**Slot**: tidak ada (semua via props + `$attributes` ke `<input>`)
**Contoh pakai**:
```blade
<x-form.input label="No. CO" readonly value="{{ $this->order->co_no }}" class="bg-[#f8fafc]" />
<x-form.input legacy="mfield" label="Nama lengkap" wire:model="profile_name" name="profile_name" />
```
**Catatan**: label/hint/error otomatis mengikuti dialek `legacy` (class
`.hint` + `style="color:#e55353"` utk error saat legacy, `text-[10.5px]
text-red-600` saat Tailwind).

## `<x-form.textarea>`
**File**: `resources/views/components/form/textarea.blade.php`
**Props**: `label` (null), `name` (null), `hint` (null), `error` (null),
`legacy` (false), `col2`/`col3`/`col4` (false), `autogrow` (false — mulai
1 baris lalu tinggi mengikuti isi otomatis via Alpine, tanpa scrollbar/resize
handle)
**Slot**: tidak ada — isi awal SENGAJA tidak diinterpolasi ke dalam tag,
Livewire yang mengisi via `wire:model` hydration
**Contoh pakai**:
```blade
<x-form.textarea label="Catatan" wire:model="notes" :disabled="$readOnly" rows="2" />
<x-form.textarea wire:model="resolution_note" rows="2" class="mt-3" placeholder="What was fixed / why closed…">
    <x-slot:label>Resolution note</x-slot:label>
</x-form.textarea>
```
**Catatan**: label bisa lewat prop `label` ATAU `<x-slot:label>` (Blade named
slot override otomatis kalau ada isinya).

## `<x-form.select>`
**File**: `resources/views/components/form/select.blade.php`
**Props**: `label` (null), `name` (null), `options` ([] — array
`value => label`), `selected` (null, dipakai `@selected` loop internal),
`placeholder` ('— pilih —'), `error` (null), `legacy` (false), `col2` (false)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-form.select label="Termin Pembayaran" :disabled="$this->quotation->doc_status !== 'DRAFT'"
    wire:change="updateHeader('payment_term', $event.target.value)" class="disabled:bg-[#f8fafc]"
    :options="$this->paymentTermOptions" :selected="$this->quotation->payment_term" />
```
**Catatan (GOTCHA PENTING)**: pakai `:placeholder="false"` (bukan `null`)
untuk menghilangkan opsi "— pilih —" — anonymous Blade component `@props()`
membuang prop bernilai `null` secara diam-diam (fallback ke default), jadi
`:placeholder="null"` TIDAK pernah bekerja. Insiden nyata 2026-08-27: 41
titik pemanggilan di 15 file salah semua sebelum ditemukan.

## `<x-form.checkbox>`
**File**: `resources/views/components/form/checkbox.blade.php`
**Props**: `label` (null), `labelClass` ('text-muted')
**Slot**: default, opsional — dipakai bila butuh markup kaya (mis. `<b>`)
alih-alih `label` string polos; slot menang kalau ada isinya
(`$slot->isNotEmpty() ? $slot : $label`)
**Contoh pakai**:
```blade
<x-form.checkbox wire:model="isUrgent" labelClass="text-red-600 font-semibold">Tandai urgent</x-form.checkbox>
```
**Catatan**: `$attributes` (mis. `wire:model`) menempel ke `<input
type="checkbox">`, bukan ke `<label>`.

## `<x-form.toggle-group>`
**File**: `resources/views/components/form/toggle-group.blade.php`
**Props**: `label` (null), `hint` (null), `legacy` (false), `col2` (false)
**Slot**: default — diisi 1+ `<x-form.toggle-option>`
**Contoh pakai**: (lihat `inquiry/form.blade.php` — workaround resmi utk bug
`<select>` Livewire v4 full-page-morph)
```blade
<x-form.toggle-group label="Sumber Bahan" legacy="f">
    <x-form.toggle-option :active="$material_source === 'CUSTOMER'" wire:click="setMaterialSource('CUSTOMER')" legacy>Customer</x-form.toggle-option>
    <x-form.toggle-option :active="$material_source === 'OWN'" wire:click="setMaterialSource('OWN')" legacy>Perusahaan</x-form.toggle-option>
</x-form.toggle-group>
```
**Catatan**: dipakai sebagai pengganti `<select>` untuk pilihan 2-3 opsi
karena bug morph Livewire v4 (lihat memory `feedback_livewire_select_morph_bug.md`).

## `<x-form.toggle-option>`
**File**: `resources/views/components/form/toggle-option.blade.php`
**Props**: `active` (false), `legacy` (false)
**Slot**: default — label tombol
**Contoh pakai**: lihat contoh `<x-form.toggle-group>` di atas.
**Catatan**: `wire:click`/`disabled`/`style` diteruskan lewat `$attributes`
persis pola manual lama (`wire:click="setProspect(true)"`).

## `<x-form.badge>`
**File**: `resources/views/components/form/badge.blade.php`
**Props**: `tone` ('default' — salah satu dari `active`/`success`, `warn`,
`danger`, `info`, atau default abu-abu)
**Slot**: default — teks badge (mis. "DRAFT", "CONFIRMED")
**Contoh pakai**:
```blade
<x-form.badge tone="success">CONFIRMED</x-form.badge>
<x-form.badge tone="warn">{{ $order->doc_status }}</x-form.badge>
```
**Catatan**: palet tone SAMA persis dengan `<x-grid.stat-box>` (5 warna baku)
supaya bahasa warna konsisten lintas grid & form — jangan bikin warna baru.

## `<x-form.date-picker>`
**File**: `resources/views/components/form/date-picker.blade.php`
**Props**: `label` (null), `name` (null), `mode` ('date' | 'datetime'),
`hint` (null), `error` (null), `legacy` (false), `col2` (false), `min` (null,
format `Y-m-d`), `max` (null)
**Slot**: tidak ada
**Contoh pakai**:
```blade
<x-form.date-picker label="Tanggal Baru *" wire:model="splitDate" />
<x-form.date-picker label="CO Date" value="{{ $this->order->co_date?->format('Y-m-d') }}"
    :disabled="$this->order->doc_status !== 'DRAFT'" wire:change="..." />
```
**Catatan**: pengganti standar `<input type="date"|"datetime-local">` native
sejak keputusan user 2026-09-02 (lihat `docs/FORM_UI_STANDARD.md` §3). Logic
kalender ada di `resources/js/date-picker.js` (`Alpine.data('tmDatePicker', ...)`).
`wire:model`/`name` menempel ke `<input type="hidden" x-ref="model">`
tersembunyi (nilai selalu format ISO), bukan ke input teks tampilan yang
`readonly`.
