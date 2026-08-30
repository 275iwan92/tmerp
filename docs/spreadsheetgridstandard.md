# spreadsheetgridstandard.md — Grid Spreadsheet-Editable (Pola Reusable)

> Dibuat 2026-08-30. Adopsi perilaku **AutoFill** DataTables.net (drag-fill
> nilai antar sel ala Excel) — versi ringan buatan sendiri (Alpine + Livewire),
> BUKAN library asli. Alasan: DataTables Editor (dibutuhkan asli utk inline-
> edit yang menyimpan ke DB) berbayar & user belum punya license key; DataTables
> juga mengambil-alih penuh DOM tabel yang ditempelinya, bentrok dengan cara
> Livewire re-render (mirip kasus "Livewire `<select>` morph bug", cuma skala
> tabel penuh). Keputusan user 2026-08-30: bangun versi sendiri, gaya visual
> otomatis nyambung ke design system tmerp, tanpa dependency/lisensi baru.

**Baca file ini dulu sebelum bikin/ubah grid spreadsheet-editable apa pun.**
Ini pola TERPISAH dari `griduistandard.md` (grid Master/Worklist biasa) —
**opt-in per grid**, bukan pengganti standar grid biasa.

---

## 1. Kapan pakai ini (dan kapan JANGAN)

**Cocok** untuk master kecil/sederhana:
- Baris sedikit (puluhan, bukan ribuan) — semua baris dirender client-side
  TANPA pagination, jadi tak masuk akal untuk tabel besar.
- Kolom scalar murni (teks, angka, boolean, pilihan tetap) — bukan relasi
  cross-DB, bukan file upload, bukan rich text.
- Tidak ada approval/state-machine dokumen yang menggerbangi edit (prinsip
  "snapshot/beku" CLAUDE.md §4 — spreadsheet-edit cocok utk MASTER, bukan
  transaksi).

Pilot pertama (2026-08-30): **Master Currency** (`master/currency.blade.php`,
layar baru) dan **Master UOM** — list Satuan (`master/uom.blade.php`, retrofit
kolom kiri; panel Konversi di kanan TETAP tabel biasa, relasional & bukan
kandidat spreadsheet-edit).

**JANGAN** pakai untuk: grid dengan approval/confirm workflow (CO, Quotation,
PR/PO/PV, dst — field-nya butuh validasi lintas-DB/approval yang tak masuk
akal dilakukan sel-per-sel), grid dengan ratusan+ baris (Customer, Supplier,
Raw Material), atau kolom yang butuh picker relasi.

---

## 2. Perbedaan sengaja dari griduistandard.md

Karena ini pola terpisah, beberapa aturan grid biasa **sengaja dilanggar** di
sini — bukan inkonsistensi, tapi trade-off sadar dari "batch-edit ala
spreadsheet" vs "form terpisah + toggle instan":

| Aspek | Grid biasa (griduistandard.md) | Grid Spreadsheet |
|---|---|---|
| Edit | Klik 2x buka form/modal terpisah | Klik sel langsung jadi editable, inline |
| `activate` | Toggle ikon + `wire:confirm` instan | Checkbox biasa, baru efektif saat "Simpan Semua" |
| Pagination | Selalu ada (footer 3-bagian) | TIDAK ADA — semua baris sekaligus |
| Tambah record | "+ New Record" → form/modal | "+ Baris" → baris kosong langsung di tabel |
| Kolom Aksi | Selalu ada (Edit/Toggle) | Tidak ada kolom Aksi standar — opsional kolom `type: 'action'` utk aksi per-baris yang BUKAN edit sel (mis. buka panel relasi) |

Kolom identitas avatar+nama+kode (§3 griduistandard.md), toolbar search (§6),
dan interaksi lain di LUAR grid (mis. panel Konversi UOM) tetap ikut standar
biasa — yang beda cuma badan tabelnya.

---

## 3. Mode simpan: BATCH, bukan autosave

**Keputusan 2026-08-30**: semua editan ditampung di client (Alpine), baru
dikirim ke server sekaligus lewat tombol **"💾 Simpan Semua"**. Alasan:
lebih sedikit request, ada jeda konfirmasi sebelum masuk DB (beda dari
autosave-per-sel yang instan tapi rawan race-condition kalau edit cepat
banyak sel). Baris yang punya editan disorot (bg kuning muda utk baris baru;
baris existing yang diedit belum ada indikator visual per-sel di v1 — cukup
counter di tombol Simpan).

Kalau nanti dibutuhkan mode autosave-per-sel (didiskusikan tapi DITUNDA user
2026-08-30), itu perubahan pada `resources/js/spreadsheet-grid.js::saveAll()`
+ kemungkinan endpoint baru per-sel di trait — bukan sekadar konfigurasi,
karena arsitektur batch-vs-realtime beda cukup jauh (debouncing, optimistic
UI, dst). Jangan improvisasi sendiri, tanya user dulu.

---

## 4. AutoFill — versi sederhana, BUKAN Excel-series-detection

Drag titik biru di pojok kanan-bawah sel aktif ke bawah → **copy nilai sel
sumber ke semua baris yang dilewati** (bukan deteksi pola/increment ala Excel
asli, mis. 1,2,3,... — itu jauh lebih kompleks dan tidak dibutuhkan kasus
UOM/Currency: rate/kode yang sama diulang ke banyak baris adalah kasus umum
yang paling sering dipakai). Kalau kebutuhan increment/series muncul nyata,
itu perluasan terpisah — jangan diasumsikan sudah ada.

---

## 5. Kontrak PHP — `App\Livewire\Concerns\HasSpreadsheetGrid`

Komponen pemakai WAJIB implementasikan (semua `protected`, dipanggil balik
dari Blade via `$this->` — pola sama seperti `HasCustomizableGrid`, SFC
Livewire di proyek ini mengizinkan akses protected dari template-nya sendiri):

```php
protected function spreadsheetKey(): string;                           // identitas unik grid ini
protected function spreadsheetColumns(): array;                        // ['col' => ['label'=>.., 'type'=>'text'|'number'|'boolean'|'select', 'options'=>[], 'editableWhenExisting'=>bool default true, 'required'=>bool]]
protected function spreadsheetQuery();                                 // Eloquent builder, SEMUA baris (TANPA paginate)
protected function spreadsheetRowId($row): int|string;                 // PK atau natural key, konsisten dgn query update
protected function spreadsheetNewRowDefaults(): array;                 // nilai default kolom utk baris baru ("+ Baris")
protected function spreadsheetValidateRow(array $data, $existingId): array;  // throw ValidationException, return data bersih
protected function spreadsheetPersistRow(array $data, $existingId): void;   // create/update 1 baris (existingId null = baris baru)
```

Trait menyediakan `spreadsheetRows()` (baca data utk JS) dan
`saveSpreadsheetBatch(array $rows)` (Livewire-callable, dipanggil dari
`$wire.saveSpreadsheetBatch(...)` di Alpine) — loop tiap baris, validasi+
simpan 1-per-1, kumpulkan error per baris (`$this->spreadsheetErrors`,
key = row id) TANPA menggagalkan baris lain yang valid. `$this->spreadsheetVersion`
naik tiap kali dipanggil.

Kolom bertipe `'action'` (mis. ikon 🔁 Konversi di UOM) BUKAN sel yang bisa
diedit — didefinisikan dgn `icon`, `title`, `method` (nama method Livewire
publik yang dipanggil), `paramKey` (default `'id'`, kolom row mana yang jadi
argumen), opsional `hideWhenColumn`/`hideWhenValue` utk sembunyikan
kondisional (mis. `dimension === 'SPEC'` di UOM).

---

## 6. Kontrak Blade — `<x-grid.spreadsheet-table>`

```blade
<x-grid.spreadsheet-table
    grid-key="{{ $this->spreadsheetKey() }}"
    :columns="$this->spreadsheetColumns()"
    :rows="$this->spreadsheetRows()"
    :new-row-defaults="$this->spreadsheetNewRowDefaults()"
    :errors="$this->spreadsheetErrors"
    :version="$this->spreadsheetVersion" />
```

`wire:key` internal komponen ini **WAJIB** ikut `$version` — inilah cara
Alpine di-reinit total sesudah "Simpan Semua" (state dirty client otomatis
bersih, data segar/error dari server ke-load ulang), pola yang sama
filosofinya dengan trik `unset($this->computedProp)` yang dipakai grid biasa
untuk memaksa recompute.

---

## 7. `resources/js/spreadsheet-grid.js` — `Alpine.data('spreadsheetGrid', ...)`

Semua render baris & state edit (aktif/dirty/fill-drag) murni client-side.
Server CUMA dipanggil 1x lewat `$wire.saveSpreadsheetBatch(this.rows)`.
Terdaftar di `resources/js/app.js` (`import './spreadsheet-grid.js'`) — ikut
Vite production build seperti file JS lain, **`npm run build` wajib** sesudah
ubah file ini (CLAUDE.md §7.8, sama alasannya dgn class Tailwind baru).

---

## 8. Yang SENGAJA belum ditangani (v1)

- Tidak ada navigasi arrow-key antar sel (cuma Tab/Shift+Tab/Enter) — arrow
  key dibiarkan jadi kursor teks normal di dalam input, menghindari ambiguitas
  caret-vs-cell-navigation.
- Tidak ada multi-cell range select/copy-paste ala Excel — cuma 1 sel aktif
  + drag-fill 1 kolom ke bawah.
- Tidak ada indikator visual per-sel yang sudah diedit (baru ada counter
  total di tombol Simpan) — kalau dibutuhkan, tambahkan highlight per-sel
  lewat `isDirty(r, key)` yang sudah ada di JS, tinggal dipakai di kelas CSS.
- Tidak ada undo/redo client-side — batal isi 1 baris baru pakai tombol ✕,
  batal edit sel existing sebelum "Simpan Semua" harus diketik ulang manual.
