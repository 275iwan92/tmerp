---
name: screen_hrm_org_chart
description: Visualisasi pohon Struktur Organisasi read-only, dari data reports_to_position_id di Master Posisi.
---

# Struktur Organisasi (Org Chart)

**File**: `resources/views/pages/hrm/org-chart.blade.php`
**Route**: `hrm.org-chart` (`/hrm/org-chart`)
**Koneksi DB utama**: mst (tm2mst) — lihat catatan penamaan di bawah
**Modul**: HRM

## Tujuan
Layar visualisasi chart organisasi interaktif (instruksi user 2026-08-29, referensi screenshot produk org-chart SaaS). Scope v1 yang disepakati: chart pohon rapi + zoom in/out + search/filter + cetak — TANPA minimap, pan-kanvas-bebas, version-history, atau edit-langsung-di-kanvas (dianggap scope jauh lebih besar, ditunda). Hierarki 100% berasal dari `org_position.reports_to_position_id` yang diisi manual lewat Master Posisi (`position.blade.php`) — layar ini MURNI read-only, tidak ada input/edit relasi di sini. Aktor: siapa saja yang perlu melihat struktur organisasi (HR, manajemen).

## Field & Kolom
Tidak ada form input data — hanya kontrol tampilan:
- `search` — cari nama karyawan/posisi (live debounce 300ms), meng-highlight node yang cocok (BUKAN filter/hide, garis pohon tetap utuh)
- `filterDepartmentId` — select departemen (`OrgDepartment::where('activate',true)`), juga meng-highlight bukan menyembunyikan
- `zoom` — float 0.4–1.6, step 0.1, dikontrol tombol +/−/Reset

### Struktur data render
- `roots()` — seluruh pohon posisi dibangun 1x di PHP jadi struktur bersarang (bukan query per node, mencegah N+1), berbasis `OrgPosition::with('department')->where('activate',true)`. Setiap node: `{position, occupant, children}` — `occupant` diambil dari `EmpEmployee` aktif+`work_status=WORKING` per `position_id` (kalau ada beberapa, ambil yang pertama)
- `highlightIds()` — daftar `position_id` yang cocok search/filter, dipakai komponen `<x-org-chart.node>` untuk highlight visual
- `branchColor(int $index)` — 8 warna cabang (siklus) untuk membedakan sub-pohon root secara visual

## Aksi / Method
- `zoomIn()` / `zoomOut()` — increment/decrement 0.1, dibatasi 0.4–1.6
- `zoomReset()` — kembali ke 1.0
- `branchColor(int $index)` — helper warna cabang

## Tabel & Model yang Disentuh
- `App\Models\Master\OrgPosition` — tabel `org_position` (koneksi **`tm2mst`**, PK `position_id`), sumber hierarki via `reports_to_position_id`
- `App\Models\Master\OrgDepartment` — tabel `org_department`, koneksi `tm2mst`, untuk filter dropdown
- `App\Models\Master\EmpEmployee` — tabel `emp_employee`, koneksi `tm2mst`, untuk data occupant per posisi (nama karyawan yang menempati)
- Komponen Blade `<x-org-chart.node>` (rekursif) — merender node + garis penghubung CSS (`.org-chart-children`)

## Alur / Business Logic Penting
- **Read-only murni**: tidak ada method create/update/delete di file ini — semua perubahan struktur (siapa melapor ke siapa) dilakukan di `hrm.position` (Master Posisi), layar ini hanya menampilkan hasilnya.
- **Grouping manual (bukan `Collection::groupBy`)**: komentar kode eksplisit menjelaskan groupBy manual dipakai karena `Collection::groupBy` mengubah key `null` jadi string kosong `''`, bukan `null` — gotcha nyata yang dihindari dengan key literal `'ROOT'` untuk posisi tanpa atasan.
- **Guard kedalaman rekursi**: `build()` closure dibatasi `depth > 12` untuk mencegah rekursi tak terhingga bila ada data siklik yang lolos (meski Master Posisi punya validasi cegah siklus saat simpan).
- **Highlight, bukan filter**: pencarian/filter departemen tidak menyembunyikan node — garis struktur organisasi tetap utuh, hanya menyorot node yang cocok. Ini keputusan desain eksplisit (dijelaskan di komentar kode).
- **Cetak**: tombol "🖨 Cetak" memanggil `window.print()` browser native; CSS `@media print` menyembunyikan toolbar (`.org-chart-noprint`) dan membatalkan transform scale/zoom (`.org-chart-canvas { transform: none !important; }`).

## Catatan
- **Ketidaksesuaian penamaan koneksi vs CLAUDE.md** (sama seperti layar HRM lain di batch ini): `OrgPosition`/`OrgDepartment`/`EmpEmployee` terhubung ke `tm2mst`, bukan `tm2hrm` — sesuai CLAUDE.md §3 (prefix `org_`/`emp_` = Master).
- Pesan bantuan ditampilkan jika semua root tidak punya children (belum ada relasi "Melapor Ke" diisi sama sekali), mengarahkan user ke Master Posisi.
- Jika satu posisi punya lebih dari satu occupant aktif (`work_status=WORKING`), hanya baris pertama dari grup yang ditampilkan (`->first()`) — tidak ada indikasi visual kalau ada occupant lain yang "tersembunyi".
