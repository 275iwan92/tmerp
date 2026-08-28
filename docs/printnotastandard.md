# STANDAR — DOKUMEN CETAK "NOTA" (KOMPONEN REUSABLE)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-08-28, ekstraksi dari `inquiry/nota.blade.php` dan
> `quotation/print.blade.php` — dua layar itu ternyata strukturnya identik
> (kop perusahaan, kotak pihak, section bernomor, tanda tangan, footer),
> beda cuma warna tema & konten isi. Dokumen ini **wajib dibaca** sebelum
> bikin/ubah layar cetak dokumen transaksi apa pun (DO/Surat Jalan, Nota PV,
> dst) — pola sama dengan `griduistandard.md`/`FORM_UI_STANDARD.md`.

---

## 1. KAPAN PAKAI INI — "Nota" vs "Laporan"

Ada **dua keluarga** layar cetak di codebase ini, JANGAN dicampur:

| | Nota (dokumen ini) | Laporan (`inventory/print/*`) |
|---|---|---|
| Sifat | Dokumen transaksi ke pihak luar (customer) — kop+parties+tanda tangan | Laporan data internal (tabel besar, landscape) |
| Orientasi | A4 potrait, 210mm | A4 landscape, 297mm |
| Chrome layar | `<x-print.document>` (bar cetak sendiri, tanpa header navy) | Header navy + tombol Back standar (`FORM_UI_STANDARD.md`) |
| Contoh | Nota Inquiry, Surat Penawaran (Quotation), calon: Nota PV, DO/Surat Jalan | Laporan Master Stok, Kartu Stok |

Dokumen ini **hanya untuk keluarga Nota**. Laporan tetap ikut pola
`FORM_UI_STANDARD.md` (header navy + Back) dan tidak direfactor ke sini.

---

## 2. ARSITEKTUR — PEMBAGIAN TANGGUNG JAWAB

```
┌───────────────────────────────────────────────────────────────┐
│  HALAMAN (Livewire page, mis. quotation/print.blade.php)      │
│  - Query data domain (quotation/inquiry/dst), hitung totals    │
│  - Isi slot kop/parties/section/signature dgn KONTEN spesifik  │
│  - <style> LOKAL cuma utk elemen spesifik dokumen ini           │
│    (tabel item, kartu harga, kotak bank, badge tipe, dst)      │
└───────────────────────┬─────────────────────────────────────────┘
                         │ slot & prop
                         ▼
┌───────────────────────────────────────────────────────────────┐
│  KOMPONEN (resources/views/components/print/*)                 │
│  document → sheet → header/parties+party/section/               │
│                     signatures+signature/footer                │
│  - Kerangka visual bersama: bar cetak, page A4, @media print   │
│  - TIDAK tahu apa isi dokumennya (quotation/inquiry/DO/dst)     │
└───────────────────────────────────────────────────────────────┘
```

**Aturan keras:** komponen `print/*` tidak boleh diisi pengetahuan domain
(nama tabel, field spesifik dokumen). Style KONTEN (tabel harga, tabel item,
kartu pricing, badge status, dst) tetap di `<style>` milik halaman masing2,
menempel ke class generik `.print-doc` (lihat §5).

---

## 3. KOMPONEN & PROPS

| Komponen | Props | Slot |
|---|---|---|
| `print.document` | `theme` (array override warna, key TANPA prefix `--print-`), `printLabel` | default = isi dokumen (1+ `print.sheet`); `toolbar` = tombol/link tambahan di bar; `info` = teks kanan bar |
| `print.sheet` | — | default = isi 1 halaman A4 fisik |
| `print.header` | `company` (model `OrgCompany`/null), `docTitle`, `docNo` | `meta` = baris tanggal/revisi/badge (bebas HTML) |
| `print.parties` | `columns` (nilai `grid-template-columns` mentah, default sama rata) | default = 1+ `print.party` |
| `print.party` | `label` | default = konten kotak (nama, detail — bebas HTML) |
| `print.section` | `number`, `title`, `hint` (opsional) | default = konten section |
| `print.signatures` | `title` (default "PENGESAHAN & PERSETUJUAN"), `columns` | `intro` = paragraf pembuka (opsional); default = 1+ `print.signature` |
| `print.signature` | `role`, `company` (opsional), `name` (opsional), `sub` (opsional) | — |
| `print.footer` | `page` (mis. "Halaman 1 dari 2") | default = teks info kiri |

**Contoh minimal** (lihat `quotation/print.blade.php` untuk contoh lengkap):

```blade
<x-print.document :theme="['accent' => '#2c3e69']" info="{{ $doc->doc_no }}">
    <x-print.sheet>
        <x-print.header :company="$company" doc-title="SURAT JALAN" :doc-no="$doc->do_no">
            <x-slot:meta>Tanggal: {{ $doc->ship_date->format('d F Y') }}</x-slot:meta>
        </x-print.header>

        <x-print.parties columns="1fr 1fr">
            <x-print.party label="DIKIRIM KE">...</x-print.party>
            <x-print.party label="DIKIRIM DARI">...</x-print.party>
        </x-print.parties>

        <x-print.section number="1" title="Rincian Barang">
            {{-- tabel item, spesifik dokumen ini --}}
        </x-print.section>

        <x-print.signatures>
            <x-print.signature role="Penerima" sub="Nama jelas & stempel" />
            <x-print.signature role="Pengirim" sub="Checker Gudang" />
        </x-print.signatures>

        <x-print.footer page="Halaman 1 dari 1">{{ $doc->do_no }} · dicetak {{ now()->format('d/m/Y H:i') }}</x-print.footer>
    </x-print.sheet>

    <style>
        /* hanya style konten spesifik dokumen ini, target .print-doc .kelas-anda */
    </style>
</x-print.document>
```

---

## 4. TEMA WARNA — prop `theme`, bukan class per dokumen

Tiap dokumen boleh punya warna identitas sendiri (Nota Inquiry hijau,
Quotation navy) TANPA menulis ulang CSS rule — cukup override variabel lewat
prop `theme`:

```blade
<x-print.document :theme="['accent' => '#0d5c46', 'accent-soft' => '#eaf3f0']">
```

Variabel yang bisa dioverride (default ada di `print/document.blade.php`):
`ink`, `muted`, `line`, `line2`, `accent`, `accent-soft`, `warn`, `gold`,
`danger`. Style konten lokal boleh (dan sebaiknya) pakai `var(--print-accent)`
dkk supaya ikut tema, bukan hex mentah.

**Mode alternatif berbasis CLASS** (bukan warna) — kalau butuh transformasi
STRUKTURAL, bukan cuma ganti warna (mis. mode hemat-tinta Nota Inquiry: kartu
solid berwarna → outline transparan, bukan cuma solid-jadi-hitam), pakai
`class` tambahan di `<x-print.document>` (komponen forward `$attributes`) +
tulis rule `.print-doc.NAMA_CLASS ...` di `<style>` lokal halaman. Lihat
`inquiry/nota.blade.php` (`.print-doc.bw`) sebagai contoh.

---

## 5. CLASS GENERIK YANG BOLEH DIPAKAI LANGSUNG (tanpa komponen)

Beberapa pola layout generik cukup umum dipakai LINTAS section tapi tidak
cukup terstruktur untuk jadi komponen sendiri — sudah tersedia sbg class CSS
polos di style bersama, tinggal pakai `<div>` biasa:

- `.print-two` — grid 2 kolom sama rata (dipakai utk 2 kotak info bersebelahan).
- `.print-box` (+ `<h5>` di dalamnya) — kotak berbingkai + judul kecil.
- `ol.print-terms` — list bernomor rapat (syarat & ketentuan).
- `.print-warn` (+ `.t`/`.d`) — kotak peringatan putus-putus (mis. "harga
  belum tersedia").

---

## 6. REFERENSI IMPLEMENTASI

- `resources/views/pages/quotation/print.blade.php` — 1 halaman, tema navy,
  tabel item + totals + alternatif qty.
- `resources/views/pages/inquiry/nota.blade.php` — 2 halaman, tema hijau +
  mode hemat-tinta (class `.bw`), kartu pricing hero, render dieline SVG.

---

## 7. GOTCHA — SAMA DENGAN `ganttboardstandard.md` §8

Jangan tulis nama tag komponen berkurung sudut (`<x-print.document>`) di
dalam komentar mana pun (`{{-- --}}`, `//` PHP, `//` JS) — Blade tag compiler
ikut mengompilasinya dan merusak file. Tulis `print.document`/`x-print.document`
(tanpa `<`) kalau perlu menyebutnya di komentar. Lihat
[[feedback_blade_component_tag_in_comment_gotcha]] / `ganttboardstandard.md` §8.

---

## 8. YANG SENGAJA TIDAK DITANGANI KOMPONEN INI

- Konten section (tabel harga, tabel item, dieline, dst) — itu domain
  masing-masing dokumen, bukan tanggung jawab komponen.
- Multi-tema per elemen dalam SATU dokumen (mis. 2 warna aksen berbeda di
  section berbeda) — `theme` cuma 1 set per `print.document`. Kalau perlu,
  tambah CSS var lokal tambahan di `<style>` halaman (lihat `--print-accent`
  vs warna tambahan lokal seperti dicontohkan komentar di
  `quotation/print.blade.php`).
- Ide "edit konten kop/parties/section/signature lewat GUI seolah-olah edit
  print-out-nya" (dibahas 2026-08-28) — TIDAK dibangun di sesi ini. Label
  statis (peran tanda tangan, teks footer) cocok jadi `sys_constant` per
  tipe dokumen (pola sudah ada: `BANK_NAME`/`BANK_ACCOUNT_NAME`/
  `BANK_ACCOUNT_NO` di `quotation/print.blade.php`) — tapi WYSIWYG editor
  penuh utk konten section (yang terikat data terhitung, bukan teks bebas)
  adalah modul terpisah (mesin template/mail-merge), butuh keputusan scope
  sendiri, jangan dibangun diam-diam menempel ke komponen ini.
