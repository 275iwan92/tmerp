---
name: uistandard_component_gantt_kanban
description: Katalog komponen Blade reusable di resources/views/components/gantt/ (board, row, task-block) beserta props dan contoh pakai nyata. Kanban BELUM punya komponen reusable (konvensi manual saja).
---

# Katalog Komponen — Gantt & Kanban

> Lihat juga dokumen konsep terkait: `docs/ganttboardstandard.md` (WAJIB
> dibaca dulu — pembagian tanggung jawab halaman/komponen, kontrak data Row
> VM & Block VM, mekanisme highlight lintas-boundary via Alpine store),
> `docs/kanbanboardstandard.md` (pola Kanban — BELUM ada komponen Blade,
> masih konvensi copy-manual per halaman).

## Gantt — 3 komponen reusable

Diekstrak dari Scheduling Board. Layar pemanggil WAJIB sudah resolve semua
warna/label/posisi-px ke bentuk row/block generik (array biasa, bukan
Eloquent) — komponen ini TIDAK tahu apa itu WO/mesin/proses domain apa pun.

### `<x-gantt.board>`
**File**: `resources/views/components/gantt/board.blade.php`
**Props**: `rows` ([]), `secondaryRows` ([]), `secondaryLabel` (null),
`resourceHeaderLabel` ('Resource'), `timelineStart` (8), `timelineEnd` (20),
`pxPerHour` (70), `moveMethod` ('moveGanttBlock'), `resizeMethod`
('resizeGanttBlock'), `reassignMethod` ('reassignGanttBlock'),
`resourceOptions` ([]), `labelOptions` ([]), `labelFieldName` ('Kategori'),
`emptyMessage` ('Tidak ada data.')
**Slot**: tidak ada (render internal dari prop `rows`/`secondaryRows`)
**Contoh pakai**:
```blade
<x-gantt.board
    :rows="$this->ganttRows"
    :timeline-start="7" :timeline-end="19" :px-per-hour="70"
    move-method="moveGanttBlock" resize-method="resizeGanttBlock" reassign-method="reassignGanttBlock"
    :resource-options="$this->machineOptions" :label-options="$this->processLabels" />
```
(lihat `resources/views/pages/production/scheduling-board.blade.php` untuk
pemakaian lengkap + method `operationVm()`/`idleGapsVm()` yang resolve VM)
**Catatan**: interaksi drag geser/resize/klik/dblklik memanggil method
Livewire di LAYAR PEMANGGIL lewat nama method dari prop (`move-method` dst)
— komponen tidak perlu tahu nama domain method aslinya. Highlight
lintas-boundary (stat card di halaman → blok di komponen) lewat Alpine
store global `Alpine.store('gantt')`, bukan `x-data` lokal, supaya halaman
pemanggil bisa memicu highlight dari LUAR komponen.

### `<x-gantt.row>`
**File**: `resources/views/components/gantt/row.blade.php`
**Props**: `row` (wajib — bentuk
`['resource','label','sublabel'?,'blocks'=>[...],'idle'=>[...]]`; `idle[]`
sudah berisi `left`/`width` px terhitung dari layar pemanggil)
**Slot**: tidak ada
**Contoh pakai**: dipanggil internal oleh `<x-gantt.board>`
(`@foreach ($rows as $row) <x-gantt.row :row="$row" /> @endforeach`) — jarang
dipanggil langsung dari halaman.

### `<x-gantt.task-block>`
**File**: `resources/views/components/gantt/task-block.blade.php`
**Props**: `b` (wajib — bentuk `['id','left','width','bg','text','title',
'subtitle'?,'timeLabel','progress','statusLabel','resource','locked'?,
'urgent'?,'dashed'?,'risk'?]`, semua warna/label sudah diresolve halaman
pemanggil)
**Slot**: tidak ada
**Contoh pakai**: dipanggil internal oleh `<x-gantt.row>` — jarang dipanggil
langsung.
**Catatan**: murni render + interaksi (drag/resize/klik/dblklik), tidak tahu
domain. `startDrag`/`startResize`/`showPopover`/`openEdit`/`$store.gantt`
datang dari `x-data="ganttBoard(...)"` di ancestor DOM (`<x-gantt.board>`),
bukan Blade scope.

---

## Kanban — belum ada komponen reusable

**Status (per `docs/kanbanboardstandard.md` §1)**: 2 implementasi nyata
(`recruitment/pipeline-board.blade.php`, `production/monitoring-dashboard.blade.php`)
punya mekanika drag-drop IDENTIK, tapi BELUM diekstrak jadi komponen Blade
generik — didokumentasikan sebagai KONVENSI bersama saja (copy pola manual
per halaman), menunggu implementasi Kanban ke-3 yang masih mirip sebelum
diekstrak. Jangan bikin `resources/views/components/kanban/*` sendiri tanpa
tanya user dulu.

Pola wajib (ringkas — detail lengkap di `docs/kanbanboardstandard.md` §2):
root `x-data="{ draggedId: null }"` per papan, kolom `x-on:dragover.prevent`
+ `x-on:drop="$wire.moveCard(draggedId, ...)"`, kartu `draggable="true"` +
`x-on:dragstart="draggedId = ..."`, validasi bisnis WAJIB di server (DB =
satu-satunya sumber kebenaran posisi kartu).
