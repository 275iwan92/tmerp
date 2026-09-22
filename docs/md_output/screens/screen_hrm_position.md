---
name: screen_hrm_position
description: Master Posisi/Jabatan — grid+modal CRUD dengan field "Melapor Ke" sumber hierarki Struktur Organisasi.
---

# Master Posisi

**File**: `resources/views/pages/hrm/position.blade.php`
**Route**: `hrm.position` (`/hrm/position`)
**Koneksi DB utama**: mst (tm2mst) — lihat catatan penamaan di bawah
**Modul**: HRM

## Tujuan
Master data posisi/jabatan (grup sidebar "Organization", kode `HRM_ORG`). Berisi field kunci `reports_to_position_id` ("Melapor ke") yang menjadi sumber hierarki untuk layar Struktur Organisasi (`org-chart.blade.php`). Desain dipilih "form dropdown per posisi" (bukan drag-connect kanvas) — keputusan user 2026-08-29 — supaya org-chart tetap murni tampilan read-only dari data ini. Aktor: staf HR yang mengelola struktur jabatan.

## Field & Kolom

### Grid (dengan pagination, 20/halaman)
- Search: `search` (live debounce 300ms) — cari `position_name`/`position_code`, reset ke halaman 1
- Kolom: Posisi (nama + kode font-mono), Departemen, Level, Melapor Ke, Aksi (Edit + Toggle Aktif)
- Stat box (3): Total Posisi, Aktif, Belum Ada Atasan (`reports_to_position_id IS NULL AND activate=true`)
- Klik 2x baris → buka modal edit

### Modal Tambah/Edit
- `position_code` — wajib
- `position_name` — wajib
- `department_id` — select dari `OrgDepartment::where('activate',true)`, wajib
- `level_id` — select dari `OrgLevel::where('activate',true)->orderBy('level_rank')`, wajib
- `reports_to_position_id` — select dari `OrgPosition::where('activate',true)`, mengecualikan diri sendiri saat edit; opsional (kosong = puncak organisasi)

## Aksi / Method
- `updatedSearch()` — reset halaman pagination
- `openNew()` — reset semua field form
- `edit(int $id)` — load posisi ke form
- `wouldCreateCycle(int $editingId, ?int $newReportsTo)` — protected, menelusuri rantai `reports_to_position_id` ke atas (maks 50 langkah, guard) untuk mendeteksi apakah `newReportsTo` akhirnya kembali ke `editingId` sendiri — mencegah siklus tak terhingga di chart
- `save()` — validasi field wajib manual (bukan `$this->validate()` Livewire, pakai `addError('form', ...)`); tolak jika `wouldCreateCycle()` true; insert/update `OrgPosition` (insert set `activate=true` otomatis)
- `toggleActive(int $id)` — toggle kolom `activate`

## Tabel & Model yang Disentuh
- `App\Models\Master\OrgPosition` — tabel `org_position` (koneksi **`tm2mst`**, PK `position_id`)
- `App\Models\Master\OrgDepartment` — tabel `org_department`, koneksi `tm2mst`
- `App\Models\Master\OrgLevel` — tabel `org_level`, koneksi `tm2mst`, urut berdasar `level_rank`
- Relasi `OrgPosition::reportsTo` (self-referencing ke `org_position` yang sama)

## Alur / Business Logic Penting
- **Cegah siklus 2 lapis**: (1) UI mengecualikan diri sendiri dari daftar pilihan "Melapor Ke" saat edit (cegah self-loop trivial), (2) server-side `wouldCreateCycle()` menelusuri rantai atasan untuk menolak siklus tidak langsung (A→B→C→A) — kalau lolos, chart di `org-chart.blade.php` akan loop tak terhingga.
- **Sumber tunggal hierarki**: field `reports_to_position_id` di sinilah satu-satunya tempat relasi struktur organisasi diedit; `org-chart.blade.php` murni membaca dari sini.
- **Link antar-layar**: tombol "🌳 Lihat Struktur Organisasi" mengarah ke `hrm.org-chart`; org-chart juga punya link balik "← Master Posisi" ke layar ini.

## Catatan
- **Ketidaksesuaian penamaan koneksi vs CLAUDE.md** (sama seperti layar HRM lain di batch ini): `OrgPosition`/`OrgDepartment`/`OrgLevel` terhubung ke `tm2mst`, bukan `tm2hrm` — sesuai CLAUDE.md §3 (prefix `org_` = Master).
- Validasi form pakai pengecekan manual (`trim(...) === ''`), bukan Livewire `$this->validate()` dengan rule array — tidak ada pesan error per-field, hanya 1 pesan gabungan `@error('form')`.
- `save()` memakai `Auth::user()->login ?? 'system'` sebagai fallback — mengindikasikan kemungkinan dipanggil tanpa user login (mis. seeder/testing), meski di jalur normal Livewire selalu ada user.
