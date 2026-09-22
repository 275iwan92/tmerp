# STANDAR — KANBAN BOARD (POLA REUSABLE)
**ERP Cetak Offset & Packaging — PT. Telasih Makmur**

> Dibuat 2026-08-30, ekstraksi dari 2 implementasi nyata yang independen tapi
> ternyata pakai mekanika drag-drop IDENTIK: `recruitment/pipeline-board.blade.php`
> (kartu kandidat digeser antar tahap seleksi) dan
> `production/monitoring-dashboard.blade.php` (kartu task WO digeser antar
> mesin/Antrean). Dokumen ini **wajib dibaca** sebelum bikin/ubah layar Kanban
> (kolom = kategori/status, kartu = record yang bisa digeser antar kolom) —
> pola sama semangatnya dengan `griduistandard.md`/`ganttboardstandard.md`.

---

## 1. KAPAN PAKAI INI — Kanban vs Gantt vs Grid

| | Kanban (dokumen ini) | Gantt (`ganttboardstandard.md`) | Grid (`griduistandard.md`) |
|---|---|---|---|
| Sumbu | Kolom = kategori/status diskrit (bukan waktu) | Kolom = jam/waktu kontinu | Tidak ada sumbu spasial, baris tabel |
| Kartu/blok | Ukuran seragam, MELOMPAT ke kolom lain saat digeser | Lebar = durasi, geser = ubah waktu mulai | Baris, tidak bisa digeser |
| Contoh nyata | Pipeline Board (tahap seleksi kandidat), Monitoring Dashboard (mesin/Antrean) | Scheduling Board (jadwal mesin per jam) | Semua layar Master + worklist |

**Belum ada komponen Blade reusable** (`resources/views/components/kanban/*`)
untuk pola ini — beda dari Gantt/Print yang sudah diekstrak jadi komponen.
Kedua implementasi nyata di atas cukup mirip untuk didokumentasikan sebagai
KONVENSI bersama, tapi belum cukup banyak/beragam untuk dipaksa jadi satu
komponen generik (CLAUDE.md §"jangan desain untuk kebutuhan hipotetis" —
ekstraksi komponen menyusul kalau kanban ke-3 muncul dan pola-nya masih
identik). Sampai saat itu, **copy pola di bawah manual per halaman**, jangan
bikin abstraksi baru sendiri.

---

## 2. STRUKTUR HTML/ALPINE — POLA WAJIB

```blade
<div x-data="{ draggedId: null }"> {{-- root halaman/section, SATU papan per scope --}}
    <div class="flex gap-3 overflow-x-auto pb-2"> {{-- baris kolom, scroll horizontal kalau kolom banyak --}}
        @foreach ($columns as $column)
            <div class="w-[280px] shrink-0 bg-[#f4f6fa] rounded-lg p-2.5"
                x-on:dragover.prevent
                x-on:drop="$wire.moveCard(draggedId, {{ $column->key }}); draggedId = null"
                x-bind:class="draggedId ? 'ring-2 ring-[#93c5fd]' : ''">
                <div class="flex items-center justify-between mb-2 px-1">
                    <p class="text-[11px] font-bold text-navy uppercase">{{ $column->label }}</p>
                    <span class="text-[10px] font-bold text-muted bg-white px-1.5 py-0.5 rounded-full">{{ $cards->count() }}</span>
                </div>
                <div class="space-y-2 min-h-[60px]">
                    @forelse ($cardsInColumn as $card)
                        <div wire:key="card-{{ $card->id }}" draggable="true"
                            x-on:dragstart="draggedId = {{ $card->id }}"
                            class="bg-white border border-[#d7dbe2] rounded-md p-2.5 shadow-sm cursor-grab active:cursor-grabbing hover:shadow-md">
                            {{-- isi kartu: identitas + tombol aksi non-drag --}}
                        </div>
                    @empty
                        <p class="text-[10.5px] text-muted text-center py-4">Kosong</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
```

**Elemen wajib, jangan diubah namanya sembarangan** (biar konsisten lintas layar):
- `x-data="{ draggedId: null }"` — SATU state per papan aktif, di root, bukan per-kartu.
- Kartu: `draggable="true"` + `x-on:dragstart="draggedId = <id>"` — cuma simpan ID, tidak ada logika lain di sini.
- Kolom: `x-on:dragover.prevent` (wajib, browser menolak drop tanpa ini) + `x-on:drop="$wire.<method>(draggedId, <kolomKey>); draggedId = null"`.
- `x-bind:class="draggedId ? 'ring-2 ring-...' : ''"` di kolom — highlight visual ringan selagi ada kartu terangkat (tidak perlu tahu kolom mana target-nya, cukup tahu "ada drag berlangsung").
- Kartu jumlah (`{{ $cards->count() }}`) di header kolom — badge putih kecil, konsisten dgn Pipeline Board & Monitoring Dashboard.

---

## 3. SERVER-SIDE — FILOSOFI VALIDASI (WAJIB, INI YANG PALING PENTING)

**Drag-drop di sisi visual itu PERMISIF (kartu bisa "dilepas" di kolom
manapun), tapi method Livewire yang dipanggil WAJIB validasi ketat — jangan
percaya kolom tujuan dari client begitu saja.** Pola dari kedua implementasi
nyata:

1. Method mutasi (`moveCard`/`moveOperation`, dst) terima `(int $cardId, <tipe kolom> $targetColumnKey)`.
2. Validasi bisnis dulu (contoh Pipeline Board: cuma boleh maju TEPAT 1 tahap,
   tidak bisa lompat; contoh Monitoring Dashboard: cuma task `PENDING`/`ASSIGNED`
   yang boleh digeser, task `RUNNING`/selesai/breakdown ditolak).
3. **Kalau melanggar aturan: TOLAK, JANGAN update apa pun, kasih pesan
   (`$this->addError(...)` atau `$this->dispatch('toast', ...)`), lalu
   `unset($this->computedProp)` yang menyuplai kartu/kolom.** Karena kartu di
   Blade dibaca dari `#[Computed]` yang query ulang DB, kartu otomatis
   "kembali" ke kolom asal saat re-render — TIDAK PERNAH ada state optimistik
   di client yang perlu di-rollback manual. Ini pola paling penting: **DB
   selalu jadi satu-satunya sumber kebenaran posisi kartu**, drag di layar
   cuma "usulan" sampai server mengonfirmasi via re-render.
4. Kalau valid: `->update(...)` record, lalu `unset($this->computedProp)` juga
   (supaya kartu pindah kolom di render berikutnya).

Guard tambahan yang WAJIB (bukan opsional) kalau kartu punya `locked`/status
yang mencegah pindah — cek di SERVER, bukan cuma sembunyikan drag secara
visual (`draggable="false"` di Blade itu cuma cegah UX iseng, bukan validasi).

---

## 4. TOMBOL AKSI NON-DRAG — SELALU SEDIAKAN

Drag-drop **bukan satu-satunya jalan** — kedua implementasi nyata tetap
punya tombol eksplisit di tiap kartu (mis. "Lanjutkan →", "Reject",
"Withdraw" di Pipeline Board) yang memanggil method Livewire yang SAMA
persis dengan yang dipanggil drag (`advance()` di Pipeline Board internally
memanggil `moveCard()`). Alasan: drag-drop butuh mouse presisi & JS aktif;
tombol adalah fallback aksesibilitas + jalur lebih cepat untuk aksi yang
sudah pasti (maju 1 tahap) dibanding "cari dan geser ke kolom sebelah".
**Jangan bikin layar Kanban yang HANYA bisa dioperasikan lewat drag.**

---

## 5. FILE REFERENSI

| File | Peran |
|---|---|
| `resources/views/pages/recruitment/pipeline-board.blade.php` | Referensi implementasi #1 — kolom dinamis dari `app_pipeline_stage_config` (beda track = beda jumlah kolom), guard "cuma maju 1 tahap" + guard assessment wajib sebelum lanjut |
| `resources/views/pages/production/monitoring-dashboard.blade.php` | Referensi implementasi #2 — kolom = mesin/Antrean, drop memicu **engine scheduling sungguhan** (`PdrEngine::backwardScheduleAndPersist`), bukan cuma update field sederhana — contoh drop bisa berefek luas (re-hitung task lain di WO yang sama) |

---

## 6. YANG SENGAJA TIDAK DITANGANI POLA INI

- Drag kartu ANTAR PAPAN Kanban berbeda (mis. dari Pipeline Board ke papan
  lain) — tidak ada kasus nyata, di luar scope.
- Reorder kartu DI DALAM satu kolom yang sama (urutan vertikal) — kedua
  implementasi nyata tidak butuh ini, kartu urut dari query DB (mis. by
  tanggal), bukan urutan manual drag.
- Komponen Blade reusable `<x-kanban.board>` — lihat §1, sengaja ditunda
  sampai ada kanban ke-3 dengan pola serupa.
