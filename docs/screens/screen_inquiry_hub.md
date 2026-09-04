---
name: screen_inquiry_hub
description: Landing page Inquiry — pilih tipe dokumen (NEW/RECALC/REVISE), picker produk existing, dan worklist inkuiri tersimpan.
---

# Inquiry Hub

**File**: `resources/views/pages/inquiry/hub.blade.php`
**Route**: `inquiry.hub` (`/inquiry`)
**Koneksi DB utama**: sdi (tm2sdi)
**Modul**: Inquiry Product

## Tujuan
Halaman pertama modul Inquiry. Sales memilih salah satu dari 3 jalur dokumen — **NEW** (Produk Baru, dari nol), **RECALC** (Hitung Ulang Harga, spec sama cuma harga bahan berubah — lewati Design), **REVISE** (Ubah Spesifikasi, ada perubahan dimensi/bahan/warna/finishing) — lalu memulai dokumen baru ("+ Buat data baru") atau membuka salah satu inkuiri tersimpan ("📂 Pilih data tersimpan"). Untuk RECALC/REVISE, "+ Buat data baru" membuka picker modal untuk memilih inquiry existing sebagai sumber salinan (karena RECALC/REVISE mereorder produk yang sudah ada, bukan dari nol). Bagian bawah halaman adalah worklist tabel semua inkuiri tersimpan dengan filter search/status/tipe, klik 2x untuk membuka.

## Field & Kolom
FORM (state UI, bukan field dokumen):
- `fq` (string, Url-bound) — search worklist bawah (no. inkuiri/nama produk/prospek).
- `fst` (string, Url-bound) — filter status (`sub_state`: DRAFT/WAITING_COST/SUBMITTED/PRICE_CALCULATED/PRICE_APPROVED/QUOTED/PROVING/PROVE_APPROVED/CONVERTED/CANCELLED/LOST).
- `ftype` (string, Url-bound) — filter tipe dokumen (NEW/RECALC/REVISE).
- `selectedId` (?int) — baris terpilih di worklist bawah.
- `pickerMode` (?string) — mode modal picker produk existing (RECALC/REVISE), null = modal tertutup.
- `pickerQuery` (string) — search di dalam modal picker.
- `pickerSelectedId` (?int) — baris terpilih di modal picker.

GRID (worklist bawah "Inkuiri Tersimpan"): Produk/Customer (avatar+nama+prospek badge), No. Inkuiri, Tgl, Tipe Produk (part pertama), Qty tier utama, Status (badge sub_state), Jenis (badge inquiry_type), Aksi.

Modal picker ("Pilih produk existing"): daftar kandidat mirip worklist tapi ringkas (tipe part, nama produk, customer, qty, no. inkuiri, tanggal, status).

## Aksi / Method
- `pickSaved(string $type)` — set filter `ftype` lalu scroll ke worklist bawah ("📂 Pilih data tersimpan").
- `updatedFq/updatedFst/updatedFtype()` — reset `selectedId` saat filter berubah.
- `selectRow(int $id)` / `openSelected()` — pilih baris lalu buka via tombol footer.
- `openRow(int $id)` — buka langsung (klik 2x/ikon Aksi); routing berdasarkan `inquiry_type`: RECALC → `inquiry.recalc`, REVISE → `inquiry.revise`, default (NEW) → `inquiry.form`.
- `typeLabel(string $type)` — label Indonesia utk badge tipe.
- `#[Computed] inquiries()` — query worklist bawah (filter fq/fst/ftype, limit 200).
- `statusPillClass(string $state)` — helper warna pill status.
- `openPicker(string $mode)` / `closePicker()` — buka/tutup modal picker RECALC/REVISE.
- `selectPickerRow(int $id)` — pilih kandidat di modal.
- `confirmPicker()` — redirect ke `inquiry.recalc` atau `inquiry.revise` dengan param `source_id` = inquiry sumber salinan.
- `#[Computed] pickerCandidates()` — daftar kandidat modal (hanya inquiry yang sudah punya `inquiry_no`, limit 50).

## Tabel & Model yang Disentuh
- `App\Models\Sdi\IqpInquiry` (`iqp_inquiry`, koneksi sdi) — satu-satunya model dipakai di layar ini. Eager-load `customer` (lintas-DB ke tm2mst, pola 1), `tiers` (filter `is_primary=true`, dalam tm2sdi ke `iqp_tier`), `parts` (order `part_seq`, limit 1, dalam tm2sdi ke `iqp_part`).
- `App\Support\Grid\Avatar::gradient()/initials()` — helper tampilan avatar (dipakai inline di Blade, bukan via komponen `<x-grid.identity-cell>` seperti grid lain — worklist ini custom-styled, bukan `griduistandard.md` standar penuh).

## Alur / Business Logic Penting
- **RECALC/REVISE tidak bisa mulai dari nol** — komentar kode menegaskan: "Belum ada modul product/costing terpisah (Tier 1 CLAUDE.md gap), jadi sumbernya = inkuiri tersimpan yang sudah punya no. dokumen (proksi terbaik utk 'produk existing' saat ini)." Artinya picker RECALC/REVISE sebenarnya memilih dari inquiry lama sebagai proxy "Master Product", bukan dari tabel Master Product sungguhan.
- Routing buka inkuiri (`openRow`) bercabang berdasarkan `inquiry_type` tersimpan di baris — konsisten dengan CLAUDE.md §4 bahwa REVISI/REKALKULASI adalah layar Livewire terpisah (bukan mode di form.blade.php).
- Tombol buka baris di worklist berbeda ikon/tooltip tergantung `sub_state`: DRAFT → "✎ Buka untuk diedit", selain itu → "↗ Buka (lihat-saja)".

## Catatan
Komentar kode eksplisit menandai gap Tier 1: picker produk existing untuk RECALC/REVISE seharusnya idealnya menunjuk ke Master Product, tapi modul itu belum ada sehingga dipakai inquiry lama sebagai proksi.
