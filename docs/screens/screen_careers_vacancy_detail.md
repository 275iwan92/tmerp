---
name: screen_careers_vacancy-detail
description: Halaman detail 1 lowongan publik, titik masuk ke alur apply atau register.
---

# Detail Lowongan (Vacancy Detail Karir)

**File**: `resources/views/pages/careers/vacancy-detail.blade.php`
**Route**: `careers.vacancy-detail` (GET `/hrm/recruitment/portal/lowongan/{vacancy_code}`, di luar middleware `auth`/`portal.candidate` -- publik)
**Koneksi DB utama**: MySQL `tm2hrr` (koneksi `tm2hrr`) via `App\Models\Hrr\VacVacancy` -- BUKAN Firestore.
**Modul**: Recruitment Portal (Publik)

## Tujuan
Menampilkan detail 1 lowongan (kode, judul, deskripsi lengkap) berdasarkan `vacancy_code` di URL. Bertindak sebagai gerbang sebelum apply: kalau kandidat belum login, diarahkan ke Daftar/Masuk; kalau sudah login, tombol "Lamar Posisi Ini" langsung ke halaman apply.

## Field & Kolom
Tidak ada form input. Public property:
- `vacancy_code` (string) -- diisi dari route parameter di `mount()`.

Computed property (pakai `getVacancyProperty()` gaya lama, bukan atribut `#[Computed]`):
- `vacancy` -- `VacVacancy::where('vacancy_code', $vacancy_code)->where('is_published', true)->firstOrFail()`.

## Aksi / Method
- `mount(string $vacancy_code)`: set `$this->vacancy_code`.
- Tidak ada method aksi lain (navigasi murni `<a href>`).

## Tabel & Model / Firestore Collection yang Disentuh
- **MySQL `tm2hrr.vac_vacancy`** (model `App\Models\Hrr\VacVacancy`) -- baca saja, `firstOrFail()` (404 kalau kode tidak ada / belum dipublish).
- Tidak menyentuh Firestore.

## Alur / Business Logic Penting
- Kondisional tombol aksi berdasar `session('portal_candidate_uid')`:
  - Ada sesi (sudah login) -- tombol hijau "Lamar Posisi Ini" ke `careers.apply` dengan parameter `vacancy_code`.
  - Tidak ada sesi -- tombol navy "Daftar untuk Melamar" ke `careers.register`, plus link kecil "Sudah punya akun? Masuk" ke `careers.login`.
- Tidak ada pengecekan `email_verified_at` di layar ini -- itu baru dicek di halaman apply (`emailVerified` computed).
- Layout: `layouts::portal`, title: "Detail Lowongan".

## Catatan
Tidak ada gap/TODO eksplisit di komentar kode untuk layar ini.
