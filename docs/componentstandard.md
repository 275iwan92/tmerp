# STANDAR KOMPONEN UI (OOP) — tmerp

> Dibuat 2026-09-23 atas permintaan user: *"UI UX tidak object oriented ... saat
> saya ubah induk object-nya semuanya ikut berubah. Bukan lagi butuh MANY TOKENS
> buat merubah WHOLE inside this APP."*
> Ini **fondasi** di bawah semua standar lain (`griduistandard.md`,
> `FORM_UI_STANDARD.md`, dst). Standar lain mengatur *susunan* layar; dokumen
> ini mengatur *dari bahan apa* layar itu disusun.

---

## 1. Masalah yang diselesaikan (audit 2026-09-23)

| Pola | Jumlah di `resources/views/pages` |
|---|---|
| `<button>` mentah | 1.022 (280 kombinasi class berbeda, 4 gaya hidup berdampingan: `.btn`, `.lg-btn`, `.io-btn`, Tailwind langsung) |
| Hex literal (`#e6eaf0`, dll) | 5.944 |
| Modal buatan tangan (`fixed inset-0`) | 71 |
| `<table>` mentah | 254 |

Akibatnya ganti warna tombol "primary" = edit ratusan file. Setelah standar
ini: **edit 1 baris**.

---

## 2. Empat lapis

```
Layer 0  Token        resources/css/app.css @theme  --color-*
                      SATU-SATUNYA tempat nilai warna. Ikut sys_theme.
Layer 1  Primitive    app/View/Components/Ui/*  (tag x-ui.*)
                      class PHP turunan UiComponent; variant & size = peta di 1 method
Layer 2  Komposit     x-form.*, x-grid.*, x-print.*, x-alert.* ...
                      disusun DARI Layer 1 (bukan class sendiri)
Layer 3  Layar        resources/views/pages/**  (make:tm-screen)
                      hanya merangkai Layer 1-2 + logika Livewire
```

Aturan arah: lapis atas boleh memakai lapis bawah, **tidak pernah** menulis
ulang gaya lapis bawah (mis. halaman menulis `bg-[#2c7be5] px-4 py-2` sendiri).

---

## 3. Warisan (ini inti "object oriented"-nya)

```
UiComponent (abstract)       base() + variants() + sizes() + extra() -> classes()
├── Button                   variant: primary|secondary|neutral|navy|success|danger|
│   │                                 outline|ghost|link|chip|action*|action-plain*
│   │                        size: md|sm|xs|square      preset: save|cancel|close|confirm|apply|delete|new
│   └── IconButton           mewarisi SEMUA variant Button, hanya sizes() persegi
├── Badge                    variant = tone (Tone::SOFT)
│   └── BadgeButton          tampilan Badge, elemen <button>
├── Card                     section|panel|muted|plain
├── Tabs, Table, Modal, Progress, Metric, ActionBar, PanelHeader
├── Window                   jendela popup geser/perkecil/perbesar/tutup (menu profil)
Tone (final)                 palet semantik tunggal: neutral|muted|code|accent|info|
                             success|warn|danger|highlight|extra  -> SOFT/SOLID/TEXT
```

Contoh efek berantai:

| Ubah di... | Yang ikut berubah |
|---|---|
| `--color-accent` (app.css / `sys_theme`) | semua tombol primary, tab aktif, judul Card, field fokus |
| `Button::variants()['primary']` | semua `x-ui.button`, `x-ui.icon-button variant=primary`, preset `save` |
| `Button::sizes()` | semua tombol termasuk pagination & modal grid |
| `Tone::SOFT['success']` | semua badge/badge-button hijau, badge tab |
| `.tm-table th` (app.css) | header semua `x-ui.table` |
| `x-grid.action-icon` → `IconButton` | 105 tombol Aksi di 57 layar, tanpa menyentuh layarnya |

---

## 4. Katalog cepat

Detail props ada di `resources/views/components/_manifest.yaml` bagian `ui:`.

```blade
<x-ui.button preset="save" />                                  {{-- Simpan / Save, type=submit --}}
<x-ui.button preset="cancel" wire:click="showGrid" />          {{-- Batal / Cancel --}}
<x-ui.button preset="new" size="sm" wire:click="openModal('new')" />  {{-- + New Record --}}
<x-ui.button variant="danger" size="xs" icon="fa-ban">Lepas</x-ui.button>
<x-ui.button variant="chip" size="xs" :active="$days === 30" wire:click="setDays(30)">30 hr</x-ui.button>
<x-ui.icon-button icon="fa-pen" variant="action" />
<x-ui.badge tone="success" pill>AKTIF</x-ui.badge>
<x-ui.badge :tone="\App\View\Components\Ui\Tone::forScore($ready)" mono>{{ $ready }}%</x-ui.badge>
<x-ui.badge-button tone="highlight" wire:click="togglePrimary(1)">★ Utama</x-ui.badge-button>
<x-ui.card title="Kebutuhan Kru"><x-slot:intro>Penjelasan <strong>kaya</strong>.</x-slot:intro> ... </x-ui.card>
<x-ui.tabs model="activeTab" :active="$activeTab" :tabs="['a' => '1. Satu', 'b' => '2. Dua']" />
<x-ui.table size="sm" framed> <thead><tr><th>Kolom</th></tr></thead> ... </x-ui.table>
<x-ui.modal title="Kontak Baru" close="closeContactModal" size="sm"> ... <x-slot:footer>...</x-slot:footer></x-ui.modal>
<x-ui.progress :value="$pct" tone="info" :marker="$targetPct" />
<x-ui.metric label="MTTR aktual">1,5 jam</x-ui.metric>
<x-ui.panel-header title="Edit Mesin"><x-slot:aside>2 proses</x-slot:aside></x-ui.panel-header>
<x-ui.action-bar> <x-ui.button preset="save" /> <x-ui.button preset="cancel" /> </x-ui.action-bar>
<x-ui.window open="configWinOpen" id="config" title="Configuration" :width="760" :height="520"> ... </x-ui.window>
```

`x-ui.window` (2026-09-23): perilaku di `resources/js/ui-window.js` (Alpine
`tmWindow` + store `tmWin` untuk z-index & dok). Buka/tutup = flag Alpine milik
shell (`appShell` & `reportShell`), jadi item `sys_user_menu` bertipe `modal`
cukup menunjuk nama flag-nya. Jendela yang diperkecil disembunyikan lewat class
`is-min` di CSS — **bukan** `x-show` pada body, karena `x-show` itu terbukti
tidak bereaksi bila body membungkus komponen Livewire anak.

**Preset tombol** mengunci label baku CLAUDE.md §3 (Simpan/Batal/Tutup ikut
`lang_code`, kunci terjemahan `shared_btn_*`). Slot mengalahkan label preset
bila memang perlu teks lain — tapi untuk aksi umum, jangan.

Tombol preset `save` ber-`type=submit`. Di dalam `<form>` untuk aksi yang BUKAN
submit form (mis. simpan baris editor), tulis `type="button"` eksplisit.

---

## 5. Aturan wajib

1. **Halaman baru**: dilarang `<button class=...>`, hex literal, `fixed inset-0`,
   `<table class=...>` mentah. Pakai `x-ui.*`.
2. **Warna baru** = token baru di `@theme` + (kalau bertone) entri di `Tone`.
   Jangan pernah `bg-[#...]` di Blade.
3. **Variant baru** ditambah di class primitive-nya (1 tempat), bukan lewat
   `class="..."` yang menimpa di pemanggil. `class` di pemanggil hanya untuk
   tata letak (margin, lebar, flex), bukan warna.
4. **Ratchet** — `tests/Feature/UiRatchetTest.php` menghitung pola mentah di
   halaman. Angka **tidak boleh naik**. Kalau turun (hasil migrasi), turunkan
   `BASELINE` di test itu ke angka baru.
5. **Angka selalu rata kanan, teks rata kiri** (keputusan user 2026-09-23).
   Judul kolom DAN sel yang berisi angka (qty, harga, subtotal, gaji, tarif,
   durasi, persen) diberi class `num` = rata kanan + angka lebar tetap.
   Kode/nomor dokumen/NIK/telepon/tahun = identitas, bukan angka → tetap kiri.
   Aturan default tabel lama di `app.css` kini di `@layer base`, jadi class per
   sel (`num`, `text-right`, `px-*`) berlaku di semua layar — dulu tidak.
6. **Tailwind**: class di primitive ditulis utuh (bukan dirangkai `"bg-{$x}"`)
   supaya terpindai build. Tetap `npm run build` sesudah mengubah primitive.

---

## 6. Migrasi layar lama — "kulit saja, esensi tetap"

Keputusan user 2026-09-23 (termasuk zona beku Inquiry/ProDev/ProCost): boleh
dimigrasi ke komponen **asal tidak mengubah cara kerja secara esensi**.

Esensi = alur, field & urutannya, perhitungan, binding `wire:*`, validasi,
fungsi tiap tombol, data. Yang boleh berubah hanya markup/gaya.

Prosedur per layar:
1. Sebelum menyentuh zona beku: `git tag` baseline + screenshot.
2. Migrasi markup → `x-ui.*`.
3. **Bukti esensi**: render versi HEAD & versi baru dengan `Livewire::test`
   (aksi baca saja), bandingkan multiset atribut `wire:*`, jumlah field input,
   atribut `disabled`, dan teks tampil. Syarat lulus: 0 `wire:*` hilang, teks
   SAMA, field SAMA.
4. Cek visual, `npm run build`, turunkan baseline ratchet, commit per layar.

Status: pilot `master.machine` & `production.master-process` — lulus bukti
esensi 2026-09-23.
