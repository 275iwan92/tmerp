# STANDAR — GANTT BOARD (KOMPONEN REUSABLE)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-08-28, ekstraksi dari `production_scheduling_tier4.md` §3 (Scheduling
> Board) atas permintaan user: "akan banyak Gantt chart serupa" ke depan (kandidat
> berikutnya: `PROD_MTN_SCH` Schedule Maintenance, masih placeholder). Dokumen ini
> **wajib dibaca** sebelum bikin/ubah layar Gantt apa pun — pola sama dengan
> `griduistandard.md` untuk grid dan `FORM_UI_STANDARD.md` untuk form.

---

## 1. KAPAN PAKAI INI

Layar yang butuh **jadwal berbasis waktu per resource** — baris = resource (mesin,
orang, ruangan, apa pun), kolom = jam/waktu, blok = pekerjaan yang bisa digeser
(drag), diubah durasi (resize tepi kanan), diklik untuk detail (popover), dan
di-dobel-klik untuk pindah resource/kategori (modal). **Bukan** untuk grid
tabel biasa (pakai `griduistandard.md`) atau form input (pakai
`FORM_UI_STANDARD.md`).

---

## 2. ARSITEKTUR — PEMBAGIAN TANGGUNG JAWAB (WAJIB DIPAHAMI)

```
┌─────────────────────────────────────────────────────────────┐
│  HALAMAN (Livewire page, mis. production/scheduling-board)   │
│  - Query data domain (WO, mesin, employee, dst)              │
│  - Resolve WARNA, LABEL STATUS, IKON per domain-nya sendiri  │
│  - Hitung posisi PIXEL (left/width) dari jam mulai/durasi    │
│  - Implementasi 3 method Livewire: move/resize/reassign      │
│  - TIDAK tahu cara Alpine drag/resize bekerja                │
└───────────────────────┬───────────────────────────────────────┘
                         │ passes resolved row/block VM (array biasa)
                         ▼
┌─────────────────────────────────────────────────────────────┐
│  KOMPONEN (resources/views/components/gantt/*)                │
│  board.blade.php → row.blade.php → task-block.blade.php      │
│  - Render ruler jam, baris, blok dari VM yang SUDAH JADI      │
│  - Drag/resize/klik/dblklik (Alpine, murni mekanika UI)       │
│  - Panggil $wire.call(<nama method dari prop>, ...)           │
│  - TIDAK tahu apa itu WO/mesin/proses/domain apa pun           │
└─────────────────────────────────────────────────────────────┘
```

**Aturan keras:** komponen `gantt/*` tidak boleh diisi pengetahuan domain
(nama tabel, enum status, peta warna proses, dll). Kalau butuh tambah field
domain-spesifik, tambahkan ke VM di level HALAMAN, bukan di komponen.

---

## 3. KONTRAK DATA — ROW VM

Array biasa (bukan object/Eloquent), dikirim ke prop `:rows` / `:secondary-rows`:

```php
[
    'resource' => 'm1',              // string unik, dipakai jadi wire:key & default modal
    'label'    => 'Cetak 1',         // judul baris (nama mesin/orang)
    'sublabel' => '👤 Budi (utama)', // opsional, baris kedua kecil di bawah label
    'blocks'   => [ /* Block VM, lihat §4 */ ],
    'idle'     => [                  // opsional, gap kosong per baris (dihitung HALAMAN)
        ['left' => 280, 'width' => 140, 'flag' => true], // flag = highlight "idle" dipicu
    ],
]
```

`idle[].left`/`width` **sudah dalam pixel** (hasil `(jam-timelineStart)*pxPerHour`),
komponen tidak menghitung ulang.

---

## 4. KONTRAK DATA — BLOCK VM

```php
[
    'id'          => 231,               // PK dari record domain (dikirim balik ke method mutasi)
    'resource'    => 'm1',               // resource SAAT INI (default value dropdown modal)
    'title'       => '🔥 WO-0231 · Cetak', // teks utama blok (halaman yang gabungkan ikon+kode+dst)
    'subtitle'    => 'Cetak',            // opsional, baris "Detail" di popover
    'timeLabel'   => '08:00–12:00',      // teks jam (halaman yang format)
    'statusLabel' => 'Berjalan',         // teks status utk popover (sudah diterjemahkan)
    'left'        => 0,                  // px, (jamMulai-timelineStart)*pxPerHour
    'width'       => 280,                // px, durasiJam*pxPerHour
    'progress'    => 65,                 // 0-100, dipakai progress bar bawah blok
    'bg'          => '#bfdbfe',          // warna latar (HALAMAN yang resolve dari domain)
    'text'        => '#1e3a8a',          // warna teks
    'urgent'      => false,              // true -> outline merah tebal (mis. hot job)
    'dashed'      => false,              // true -> border putus-putus + cursor not-allowed
    'risk'        => false,              // true -> ikut ter-highlight saat stat "risk" diklik
    'locked'      => false,              // true -> TIDAK bisa drag/resize (server & client)
]
```

`locked` **wajib** juga dicek ulang di method Livewire (`move`/`resize`) sisi
server — komponen cuma mencegah secara visual/JS, bukan pengganti guard server.

---

## 5. PEMAKAIAN — PROPS `<x-gantt.board>`

| Prop | Default | Keterangan |
|---|---|---|
| `rows` | `[]` | Array Row VM (§3), baris utama |
| `secondary-rows` | `[]` | Array Row VM kedua (mis. "Manual Job"), dipisah divider |
| `secondary-label` | `null` | Teks divider sebelum `secondary-rows`; `null` = tak ada divider |
| `resource-header-label` | `'Resource'` | Judul kolom kiri (mis. "Mesin / Crew") |
| `timeline-start` / `timeline-end` | `8` / `20` | Jam mulai/akhir ruler (integer, jam) |
| `px-per-hour` | `70` | Skala horizontal |
| `move-method` | `'moveGanttBlock'` | Nama method Livewire dipanggil saat drag selesai — signature `(int $id, float $newStartHour)` |
| `resize-method` | `'resizeGanttBlock'` | Nama method saat resize selesai — `(int $id, float $newDurationHour)` |
| `reassign-method` | `'reassignGanttBlock'` | Nama method saat Simpan di modal — `(int $id, string $resourceKey, string $label)` |
| `resource-options` | `[]` | `[['value'=>'m1','label'=>'Cetak 1'], ...]` — isi dropdown "Resource" di modal |
| `label-options` | `[]` | `['Cetak','Laminasi', ...]` — isi dropdown kedua di modal (nama fieldnya diatur `label-field-name`) |
| `label-field-name` | `'Kategori'` | Judul dropdown kedua (mis. "Proses" di Scheduling Board) |
| `empty-message` | `'Tidak ada data.'` | Teks saat `rows` kosong |

**Contoh pakai** (dari `production/scheduling-board.blade.php`, jangan disalin
literal — sesuaikan nama method/label ke domain layar baru):

```blade
<x-gantt.board
    :rows="$board['machineRows']"
    :secondary-rows="$board['manualRows']"
    secondary-label="MANUAL JOB (per orang)"
    resource-header-label="Mesin / Crew"
    :timeline-start="8" :timeline-end="20" :px-per-hour="70"
    move-method="moveOperation" resize-method="resizeOperation" reassign-method="reassignOperation"
    :resource-options="$this->resourceOptions()" :label-options="$this->processOptions->all()"
    label-field-name="Proses" empty-message="Tidak ada mesin aktif."
/>
```

---

## 6. HIGHLIGHT LINTAS-BOUNDARY — ALPINE STORE `gantt`

Kartu statistik di HALAMAN (di luar `<x-gantt.board>`) perlu memicu highlight
blok DI DALAM komponen. Karena keduanya elemen DOM terpisah, dipakai
**Alpine store global** (didaftarkan sekali oleh `gantt/board.blade.php` lewat
`@once`), bukan `x-data` lokal:

```blade
{{-- di HALAMAN, di luar komponen --}}
<div @click="$store.gantt.toggle('risk')" :class="{ 'ring-2 ring-[#92400e]': $store.gantt.mode === 'risk' }">
    <x-grid.stat-box tone="warn" label="Berisiko Telat" :value="$count" />
</div>
```

`$store.gantt.mode` bisa `null` (tak ada sorotan), `'risk'`, `'idle'`, atau
mode baru apa pun — komponen `task-block`/`row` sudah baca `$store.gantt.mode`
untuk kelas `dimmed-out`/`risk-match` mengikuti flag `risk` (block) / `flag`
(idle gap) di VM. **Satu store global = satu papan Gantt aktif per halaman.**
Kalau nanti ada halaman dengan >1 papan Gantt sekaligus, store ini perlu
di-namespace (`Alpine.store('gantt_' + id, ...)`) — belum dibutuhkan sekarang,
jangan dibangun preventif.

`x-data` LOKAL milik `<x-gantt.board>` sendiri (drag state, popover, modal,
toast) tidak perlu diakses dari luar — itu sebabnya cukup di local scope,
tidak ikut ditaruh di store.

---

## 7. FILE

| File | Isi |
|---|---|
| `resources/views/components/gantt/board.blade.php` | Root: ruler, loop baris, popover, modal edit, toast, `<style>`, `<script>` (Alpine store `gantt` + factory `ganttBoard()`) — di dalam `@once` supaya aman dipakai berkali-kali dalam satu request |
| `resources/views/components/gantt/row.blade.php` | Satu baris resource: label/sublabel + idle gap + loop block |
| `resources/views/components/gantt/task-block.blade.php` | Satu blok: posisi, warna, progress bar, handler drag/resize/klik/dblklik |
| `resources/views/pages/production/scheduling-board.blade.php` | **Referensi implementasi** — cek `operationVm()`/`idleGapsVm()`/`buildRows()` utk pola resolve domain->VM, dan `moveOperation()`/`resizeOperation()`/`reassignOperation()` utk pola method mutasi |

---

## 8. GOTCHA — JANGAN TULIS NAMA TAG KOMPONEN DI DALAM KOMENTAR

**Insiden nyata 2026-08-28:** komentar (baik `{{-- --}}` Blade, `//` PHP, maupun
`//` JS di dalam `<script>`) yang menulis nama tag komponen dengan kurung sudut
literal — mis. `<x-gantt.board>` — bikin **Blade tag compiler ikut mengompilasinya
sebagai component tag sungguhan**, merusak seluruh sisa file (error: `syntax
error, unexpected end of file, expecting "elseif" or "else" or "endif"`).
Blade tidak "tahu" itu ada di dalam komentar/`<script>` — dia jalan sebagai
pass regex atas seluruh teks file.

**Aturan:** kalau mau menyebut nama komponen di komentar/dokumentasi inline,
tulis TANPA kurung sudut — `komponen gantt.board`, `x-gantt.board` (tanpa `<`),
atau `gantt.board` — bukan `<x-gantt.board>`. Berlaku juga untuk kasus serupa
di komponen lain (`x-grid.*`, `x-form.*`) — jangan tulis `<x-grid.stat-box>`
literal di komentar mana pun.

---

## 9. YANG SENGAJA TIDAK DITANGANI KOMPONEN INI

- Backward-scheduling / auto-assign resource (itu domain engine, mis. PDR di
  `production_scheduling_tier2.md` §4 — di luar scope komponen tampilan).
- Validasi bentrok/konflik (crew ganda, kapasitas mesin) — HALAMAN yang wajib
  cek di method mutasi sebelum `->update()`, komponen cuma UI.
- Zoom/scroll-time-range dinamis — `timeline-start`/`timeline-end`/`px-per-hour`
  statis per render (cukup untuk kebutuhan saat ini).
- Multi-papan Gantt dalam satu halaman (lihat catatan store §6).
