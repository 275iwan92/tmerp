---
name: screen_careers_landing
description: Halaman utama Portal Karir publik, menampilkan daftar lowongan terbuka.
---

# Landing Karir

**File**: `resources/views/pages/careers/landing.blade.php`
**Route**: `careers.landing` (GET `/hrm/recruitment/portal`, di luar middleware `auth`/`portal.candidate` -- publik penuh)
**Koneksi DB utama**: MySQL `tm2hrr` (koneksi `tm2hrr`) via `App\Models\Hrr\VacVacancy` -- BUKAN Firestore. Vacancy adalah data internal, dibaca langsung karena portal & internal masih 1 proses Laravel yang sama (lihat komentar kode baris 9-11).
**Modul**: Recruitment Portal (Publik)

## Tujuan
Halaman pertama yang dilihat calon kandidat (belum login) saat membuka Portal Karir. Menampilkan daftar semua lowongan yang berstatus `is_published = true` dan belum `closed_at`, diurutkan dari `id_vacancy` terbaru. Tidak ada aksi login/apply langsung di sini -- kandidat klik salah satu lowongan untuk masuk ke `screen_careers_vacancy-detail`.

## Field & Kolom
Tidak ada form input di layar ini. Satu-satunya data computed:
- `vacancies` (`#[Computed]`) -- koleksi `VacVacancy` yang `is_published=true` dan `closed_at` null, `orderByDesc('id_vacancy')`.

## Aksi / Method
Tidak ada public method selain computed property `vacancies()`. Navigasi murni via `<a href>` ke `careers.vacancy-detail`.

## Tabel & Model / Firestore Collection yang Disentuh
- **MySQL `tm2hrr.vac_vacancy`** (model `App\Models\Hrr\VacVacancy`, PK `id_vacancy`) -- baca saja (`where is_published`, `whereNull closed_at`).
- Tidak menyentuh Firestore.

## Alur / Business Logic Penting
- Kalau `vacancies` kosong, tampil pesan "Belum ada lowongan terbuka saat ini."
- Setiap kartu lowongan menampilkan `vacancy_code`, `title`, `description` (dipotong `line-clamp-2`), dan link "Lihat detail & lamar" ke `careers.vacancy-detail` dengan parameter `vacancy_code`.
- Layout: `layouts::portal`, title: "Karir".

## Catatan
Tidak ada gap/TODO eksplisit di komentar kode untuk layar ini.
