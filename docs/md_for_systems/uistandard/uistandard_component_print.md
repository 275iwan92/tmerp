---
name: uistandard_component_print
description: Katalog komponen Blade reusable di resources/views/components/print/ (dokumen cetak "Nota" — document, sheet, header, parties, section, signatures, footer) beserta props dan contoh pakai nyata.
---

# Katalog Komponen — Print (Nota)

> Lihat juga dokumen konsep terkait: `docs/printnotastandard.md` (WAJIB
> dibaca dulu — kapan pakai "Nota" vs "Laporan", pembagian tanggung jawab
> halaman vs komponen, aturan style konten tetap di halaman masing-masing).
> Dokumen ini murni katalog konkret props/slot tiap komponen turunan.

**Aturan keras** (ditegaskan ulang dari `printnotastandard.md`): komponen
`print/*` TIDAK BOLEH diisi pengetahuan domain (nama tabel, field spesifik
dokumen kayak Quotation/Inquiry/DO). Style konten spesifik (tabel item, kartu
harga, kotak bank, badge tipe) tetap di `<style>` milik halaman pemanggil,
menempel ke class generik `.print-doc`.

Struktur nesting: `document` → `sheet` (1+, multi-halaman) →
`header`/`parties`+`party`/`section`/`signatures`+`signature`/`footer`.

---

## `<x-print.document>`
**File**: `resources/views/components/print/document.blade.php`
**Props**: `theme` ([] — array override warna key TANPA prefix `--print-`,
mis. `accent`, `accent-soft`, `ink`, `muted`, `line`, `warn`, `danger`),
`printLabel` ('🖶 Cetak / Simpan PDF')
**Slot**: default = isi dokumen (1+ `<x-print.sheet>`); `toolbar` = tombol/
link tambahan di bar cetak; `info` = teks kanan bar
**Contoh pakai**:
```blade
<x-print.document :theme="['accent' => '#1f6f4a', 'accent-soft' => '#e9f6ef']"
    info="{{ $this->order->co_no }} · {{ $this->order->doc_status }}">
    <x-print.sheet>...</x-print.sheet>
</x-print.document>
```
**Catatan**: bar cetak (`window.print()`), page A4 potrait 210mm, `@media
print` menyembunyikan bar. Warna tema per-dokumen (nota hijau, quotation
navy, WO navy) via prop `theme`, satu set CSS rule sama untuk semua dokumen.

## `<x-print.sheet>`
**File**: `resources/views/components/print/sheet.blade.php`
**Props**: tidak ada
**Slot**: default = isi 1 halaman A4 fisik
**Contoh pakai**: dokumen 2 halaman (mis. Nota Inquiry) pakai
`<x-print.sheet>` dua kali berurutan di dalam `<x-print.document>`.

## `<x-print.header>`
**File**: `resources/views/components/print/header.blade.php`
**Props**: `company` (null, model `OrgCompany`), `docTitle` (wajib), `docNo`
(wajib)
**Slot**: `meta` = baris tanggal/revisi/badge (bebas HTML)
**Contoh pakai**:
```blade
<x-print.header :company="$this->company" doc-title="NOTA CUSTOMER ORDER" :doc-no="$this->order->co_no">
    <x-slot:meta>Tanggal: {{ $this->order->co_date->format('d F Y') }}</x-slot:meta>
</x-print.header>
```
**Catatan**: logo = 2 huruf pertama `company_code` (fallback "TM"), nama
default "PT Telasih Makmur" kalau `$company` null.

## `<x-print.parties>`
**File**: `resources/views/components/print/parties.blade.php`
**Props**: `columns` (null — nilai `grid-template-columns` mentah, mis.
`"1.2fr 1fr"`, default sama rata)
**Slot**: default = 1+ `<x-print.party>`
**Contoh pakai**:
```blade
<x-print.parties columns="1.2fr 1fr">
    <x-print.party label="KEPADA YTH.">...</x-print.party>
    <x-print.party label="ALAMAT PENGIRIMAN">...</x-print.party>
</x-print.parties>
```

## `<x-print.party>`
**File**: `resources/views/components/print/party.blade.php`
**Props**: `label` (wajib)
**Slot**: default = konten kotak (nama, detail — bebas HTML, mis. `<br>`)
**Contoh pakai**: lihat contoh `<x-print.parties>` di atas.

## `<x-print.section>`
**File**: `resources/views/components/print/section.blade.php`
**Props**: `number` (wajib), `title` (wajib), `hint` (null, opsional)
**Slot**: default = konten section (tabel/kartu spesifik domain)
**Contoh pakai**:
```blade
<x-print.section number="1" title="Bill of Process" hint="kolom putih terisi rencana, kolom kosong isi manual di lapangan">
    <table>...</table>
</x-print.section>
```

## `<x-print.signatures>`
**File**: `resources/views/components/print/signatures.blade.php`
**Props**: `title` ('PENGESAHAN & PERSETUJUAN'), `columns` (null, default
menyesuaikan jumlah `<x-print.signature>` anak)
**Slot**: `intro` = paragraf pembuka (opsional); default = 1+
`<x-print.signature>`
**Contoh pakai**:
```blade
<x-print.signatures title="PENGESAHAN LAPANGAN">
    <x-print.signature role="Dibuat oleh" sub="PPIC Officer" />
    <x-print.signature role="Operator Mesin" sub="Nama jelas &amp; paraf" />
    <x-print.signature role="Diperiksa QC" sub="Nama jelas &amp; paraf" />
</x-print.signatures>
```

## `<x-print.signature>`
**File**: `resources/views/components/print/signature.blade.php`
**Props**: `role` (wajib), `company` (null), `name` (null), `sub` (null)
**Slot**: tidak ada
**Contoh pakai**: lihat contoh `<x-print.signatures>` di atas.

## `<x-print.footer>`
**File**: `resources/views/components/print/footer.blade.php`
**Props**: `page` (null, mis. "Halaman 1 dari 2")
**Slot**: default = teks info kiri
**Contoh pakai**:
```blade
<x-print.footer page="Halaman 1 dari 1">
    Dicetak oleh {{ auth()->user()->name }} · {{ now()->format('d/m/Y H:i') }}
</x-print.footer>
```
**Catatan**: posisi absolute ke bawah `print.sheet`.
