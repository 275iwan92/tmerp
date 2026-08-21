# GRID_UI_STANDARD.md — Acuan Tampilan Grid Master

> Ditulis 2026-08-21 setelah menyeragamkan 8 grid Master (Customer, Supplier, UoM,
> Mesin, Shift, Employee, Item Bahan Baku, Warehouse) atas instruksi langsung user.
> **Baca file ini dulu sebelum membuat atau mengubah layar grid apa pun** — jangan
> menebak polanya lagi dari satu contoh, semua keputusan di bawah ini sudah final
> dan diverifikasi lintas 8 layar berbeda.

Berlaku untuk semua grid **Master Data** (Customer/Supplier/UoM/Mesin/Shift/
Employee/Item/Warehouse, dan modul master lain yang menyusul). Grid transaksi
(PR/PO/PV, dst) TIDAK otomatis ikut — itu domain berbeda (state dokumen, approval,
badge status majemuk) dan belum diminta diseragamkan.

---

## 1. Tidak ada header halaman

Shell (`layouts.shell`, lihat `docs/FIX_MASTER_CUSTOMER_NAV_ADDRESS.md` §A) sudah
menampilkan nama halaman lewat tab di atas. Grid **tidak boleh** punya bar
judulnya sendiri (ikon+nama+subtitle+tombol pada background terpisah) — itu
duplikat chrome. Halaman grid langsung mulai dari kotak statistik.

Ini perluasan dari `NAV-SHELL-CONTRACT` yang sudah ada — dulu bar tipis untuk
satu tombol aksi utama masih dianggap boleh, sekarang **tidak lagi**: tombol aksi
utama ("+ New Record") pindah ke ujung kanan baris toolbar (§3), bukan bar sendiri.

---

## 2. Kotak statistik — pastel, bukan gradient mencolok

4 kotak (kadang bisa lebih/kurang sesuai kebutuhan data), `grid grid-cols-2
lg:grid-cols-4 gap-3 mb-3.5` (atau `mb-3`/`mb-4`, ikuti spacing sekitar).

**JANGAN** pakai `bg-gradient-to-br ... text-white` (draf pertama sesi ini, DITOLAK
user — terlalu mencolok, teks putih di atas warna jenuh susah dibaca). **PAKAI**
latar pastel solid + teks gelap senada, selaras dengan navy/biru menu:

```html
<div class="rounded-lg p-3.5 shadow-sm bg-[BG] border border-[BORDER]">
    <p class="text-[10.5px] font-bold uppercase text-[LABEL]">Label</p>
    <p class="text-xl font-extrabold text-[VALUE] mt-0.5">{{ $angka }}</p>
</div>
```

Palet baku (5 warna, pilih sesuai makna metrik — jangan bikin warna baru):

| Makna | bg | border | label (teks kecil) | value (angka) |
|---|---|---|---|---|
| Total / primer | `#eaf2ff` | `#c8ddfb` | `#3b5b99` | `#1d4ed8` |
| Aktif / baik | `#e9f9ef` | `#c8ecd6` | `#2f7a52` | `#16a34a` |
| Dasar / perlu perhatian ringan | `#fef6e7` | `#f5e0b3` | `#8a6d00` | `#b45309` |
| Nonaktif / terblokir / kritis | `#fdecec` | `#f5c6c6` | `#a3453f` | `#c0392b` |
| Metrik tambahan (kategori ke-5) | `#f1ecfc` | `#ddd0f7` | `#6b4fa0` | `#6d28d9` |

Kalau butuh subtitle kecil di bawah angka (mis. "MATERIAL", "RM"), pakai
`text-[10px]` warna senada label (`text-[LABEL]/70` kalau perlu redup sedikit).

---

## 3. Kolom identitas — avatar + Nama (tebal) + Kode (subtitle abu)

**Jangan lagi bikin kolom "Kode" dan "Nama X" terpisah.** Gabung jadi satu kolom
identitas paling kiri:

```html
<td class="px-2.5 py-2 border-b border-[#d7dbe2]">
    <div class="flex items-center gap-2.5">
        <div class="w-8 h-8 rounded-md bg-gradient-to-br {{ $this->avatarGradient($row->id) }} text-white text-[11px] font-bold flex items-center justify-center shrink-0">{{ $this->avatarInitials($row->name) }}</div>
        <div>
            <div class="font-semibold text-[13px]">{{ $row->name }}</div>
            <div class="text-[11px] text-muted font-mono">{{ $row->code }}</div>
        </div>
    </div>
</td>
```

Gradient avatar (beda dari gradient kotak statistik yang dilarang di §2 — badge
kecil 32×32px dengan gradient tetap enak dilihat, itu yang ditolak user cuma
kotak statistik besar). Tiap komponen punya method sendiri (single-file component,
tak ada shared partial di codebase ini):

```php
public function avatarInitials(string $name): string
{
    $words = array_values(array_filter(explode(' ', trim($name))));
    if (count($words) >= 2) {
        return mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
    }
    return mb_strtoupper(mb_substr($name, 0, 2));
}

private const AVATAR_PALETTE = [
    'from-[#2563eb] to-[#1d4ed8]', 'from-[#10b981] to-[#059669]', 'from-[#ef4444] to-[#dc2626]',
    'from-[#8b5cf6] to-[#6d28d9]', 'from-[#f59e0b] to-[#d97706]', 'from-[#0ea5e9] to-[#0284c7]',
];

public function avatarGradient(int $id): string
{
    return self::AVATAR_PALETTE[$id % count(self::AVATAR_PALETTE)];
}
```

`avatarGradient($id)` dipanggil dengan PK baris (auto-increment int) supaya warna
konsisten tiap render tanpa perlu disimpan — modulo warna pool.

**Kalau entitasnya tidak punya kolom kode** (mis. `emp_shift.shift_name` saja,
tanpa `shift_code`) — jangan mengarang kode. Cukup avatar + Nama saja, tanpa
baris subtitle kedua. Jangan pakai field lain (departemen, dsb.) sebagai kode
palsu.

**Kalau kode adalah field utama/pendek** (mis. UoM `uom_code` = "PCS", "KG") dan
nama cuma label deskriptif — pola tetap sama (avatar dari nama, Nama tebal di
atas, Kode mono di bawah), meski secara teknis kode itu PK-nya. Konsistensi
visual menang di sini, bukan "kode itu penting jadi taruh atas".

Kolom lain (departemen, kategori, dsb.) tetap kolom terpisah seperti biasa —
yang digabung HANYA identitas nama+kode.

---

## 4. Interaksi baris: klik = pilih (kalau perlu), klik 2x = buka detail

- **Klik 2x (`wire:dblclick` atau `ondblclick` untuk grid yang edit-nya halaman
  terpisah, bukan modal)** pada baris = **satu-satunya** cara buka form/detail.
  Tambahkan `title="Klik 2x untuk buka detail"` dan `cursor-pointer` pada `<tr>`.
- **Jangan** pasang kolom radio-button atau checkbox select terpisah lagi (pola
  lama Customer/Supplier sebelum sesi ini — DIHAPUS). Kalau toolbar grid itu
  masih punya tombol lain yang butuh "baris terpilih" (mis. Supplier punya
  "Blokir"/"Detail"), klik tunggal (`wire:click="selectRow(...)"`) tetap boleh
  dipertahankan untuk itu SAJA — highlight lewat class kondisional
  (`{{ $selectedId === $row->id ? 'bg-[#e8f0fe]' : '' }}`), bukan radio glyph.
  Kalau toolbar grid tidak butuh baris terpilih sama sekali (mayoritas grid),
  tidak usah pasang `wire:click` select apa pun — cukup `wire:dblclick`.
- Tombol ikon di kolom Aksi (§5) pakai `wire:click.stop`/`wire:click.stop` (atau
  event modifier setara) supaya klik ikon tidak ikut memicu select/dblclick baris.

---

## 5. Kolom Aksi — selalu tepat 2 ikon: Edit + Toggle-aktif

```html
<td class="px-3 py-2 border-b border-[#eef1f5] text-right whitespace-nowrap">
    <button type="button" wire:click.stop="edit({{ $row->id }})" title="Edit"
        class="inline-flex items-center justify-center w-7 h-7 rounded-md text-accent hover:bg-[#eef3fb] cursor-pointer">✎</button>
    <button type="button" wire:click.stop="toggleActive({{ $row->id }})"
        wire:confirm="{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $entityLabel }} {{ $row->name }}?"
        title="{{ $row->is_active ? 'Nonaktifkan' : 'Aktifkan' }}"
        class="inline-flex items-center justify-center w-7 h-7 rounded-md cursor-pointer {{ $row->is_active ? 'text-[#c0392b] hover:bg-[#fdeceb]' : 'text-accent hover:bg-[#eef3fb]' }}">⏻</button>
</td>
```

- Urutan tetap: Edit (✎) dulu, baru toggle (⏻) — jangan dibalik, jangan diselingi
  tombol lain.
- Ikon TEKS (`Edit`, `Nonaktifkan`) **dilarang** di kolom Aksi grid — selalu ikon
  polos + `title` tooltip.
- Warna toggle berubah sesuai state saat ini (merah = klik untuk nonaktifkan,
  accent/biru = klik untuk aktifkan) — bukan glyph yang berganti (⛔↔✓), cukup
  `⏻` konsisten, warna+tooltip yang bicara.
- **Setiap grid Master wajib punya `toggleActive()` (atau
  `toggleXxxActive()` kalau ada beberapa entitas dalam satu file, mis. UoM punya
  `toggleUnitActive()` + `toggleConversionActive()`)** — kalau modelnya belum
  punya kolom `is_active`, itu tanda modelnya perlu delta dulu, bukan alasan
  melewati kolom Aksi.
- Kalau ada tombol Edit/Aksi lain yang jadi redundan setelah kolom ini + klik 2x
  ada (mis. tombol "✎ Edit" terpisah di toolbar), **hapus yang redundan** —
  jangan biarkan dua cara berbeda untuk hal yang sama.

---

## 6. Toolbar — satu baris, tidak pernah wrap ke bawah

```html
<div class="flex items-center gap-2 bg-white border ... px-2.5 py-2 overflow-x-auto">
    <input type="text" wire:model.live.debounce.300ms="search" placeholder="..."
        class="w-56 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">
    <select wire:model.live="filterX" class="w-36 shrink-0 px-2.5 py-1.5 border border-[#d7dbe2] rounded text-xs">...</select>
    <label class="flex items-center gap-1.5 text-xs cursor-pointer shrink-0">
        <input type="checkbox" wire:model.live="hideInactive"> Sembunyikan nonaktif
    </label>
    <span class="flex-1"></span>
    <div class="text-xs text-muted shrink-0">{{ $count }} baris · klik 2x untuk buka</div>
    <button type="button" wire:click="openNew" class="shrink-0 bg-accent text-white text-xs px-3.5 py-1.5 rounded-md font-semibold cursor-pointer hover:brightness-110">+ New Record</button>
</div>
```

- **`flex-wrap` DILARANG** di baris toolbar — pakai `overflow-x-auto` (geser
  horizontal kalau panel sempit) supaya search/filter/tombol **selalu satu
  baris**, tidak pernah jatuh ke baris sendiri-sendiri (bug yang ditemukan user
  di panel sempit: tiap `<select>` tanpa lebar tetap melebar penuh satu baris
  sendirian saat wrap).
- Setiap elemen filter (input, select, checkbox label, tombol) **wajib** punya
  `shrink-0` + lebar eksplisit (`w-XX`) — jangan biarkan tanpa lebar di dalam
  flex row yang bisa scroll, nanti collapse jadi 0 atau melebar tak terkontrol.
- `<span class="flex-1"></span>` sebagai spacer mendorong sisanya (hitungan
  baris, tombol New Record) ke kanan — hanya efektif kalau toolbar masih cukup
  lebar; kalau overflow, urutan DOM tetap dipertahankan (scroll, bukan wrap).
- Tombol tambah data **SELALU** di ujung paling kanan, teksnya **SELALU**
  `+ New Record` (ASCII `+`, bahasa Inggris, sama persis di semua grid — bukan
  "+ Tambah X", bukan "+ New" saja, bukan nama entitas).
- **Tombol Hapus (delete) DILARANG ada di toolbar grid.** Kalau grid itu
  memang butuh aksi hapus permanen, taruh di panel form/detail (bukan grid),
  seperti Supplier (`deleteSupplier()` dipanggil dari form, bukan grid). Kalau
  tak ada pemanggil lain setelah tombolnya dihapus dari grid, method-nya jadi
  dead code — hapus juga methodnya (dan import yang ikut jadi tak terpakai),
  jangan dibiarkan menggantung.

---

## 7. Ringkasan checklist tiap kali bikin/ubah grid Master

- [ ] Tidak ada `<header>` bar di atas kotak statistik.
- [ ] Kotak statistik pastel (§2), bukan gradient/putih polos.
- [ ] Kolom identitas = avatar + Nama tebal + Kode subtitle (§3), bukan 2 kolom
      terpisah.
- [ ] Baris: `wire:dblclick`/`ondblclick` buka detail; radio/checkbox select
      hanya kalau toolbar benar-benar butuh baris terpilih.
- [ ] Kolom Aksi = persis 2 ikon (✎ Edit, ⏻ toggle), `wire:click.stop`, ada
      `title` + `wire:confirm` di toggle.
- [ ] Toolbar satu baris (`overflow-x-auto`, tiap elemen `shrink-0` + lebar
      tetap), tombol "+ New Record" di ujung kanan, tidak ada tombol Hapus.
- [ ] `colspan` baris kosong (`@empty`) disesuaikan ulang setelah kolom
      digabung/dihapus — ini yang paling sering kelewat.
