---
name: screen_recruitment_vacancy
description: Grid Posting Lowongan — publish lowongan dari requisition APPROVED dalam 1 aksi (channel+judul+deskripsi), dan tutup lowongan.
---

# Posting Lowongan — Grid + Modal Publish (L2)

**File**: `resources/views/pages/recruitment/vacancy.blade.php`
**Route**: `hrm.recruitment.vacancy` — `GET /hrm/recruitment/vacancy` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr`
**Modul**: Recruitment (Internal) — L2, lihat komentar kode: `docs/recruitment_tier4.md` SECTION 7

## Tujuan
"Buat Lowongan" dari requisition berstatus `APPROVED` = publish langsung dalam 1 aksi (pilih channel, isi judul+deskripsi, publish), sesuai alur spec ("pilih channel(s), isi judul+deskripsi -> publish" 1 aksi). Tutup Lowongan adalah aksi terpisah sesudahnya (bukan bagian modal publish).

## Field & Kolom
**Filter/search**: `search` (live, debounce 300ms) — cari `title` atau `vacancy_code`.

**Kotak statistik**: Total Lowongan, Aktif Terbuka (`is_published=true` & `closed_at` null), Total Pelamar Masuk (jumlah semua `AppApplication`).

**Kolom grid**: Lowongan (judul + `vacancy_code` font mono), Channel (label dari konstanta `CHANNELS`), Requisition (`position_title`), Pelamar (badge counter), Status (badge: DITUTUP abu / TERBUKA hijau / DRAFT kuning), Aksi (tombol "Tutup Lowongan" ⛔, hanya muncul kalau published & belum ditutup).

**Modal "Buat & Publish Lowongan"**:
- `id_requisition` (select, wajib) — sumber `approvedRequisitions()`: `RcrRequisition::where('status', 'APPROVED')`
- `channel` (select, default `PORTAL_SENDIRI`; opsi: Portal Sendiri/Jobstreet/LinkedIn/Referral/Lainnya)
- `title` (text, wajib)
- `description` (textarea)

## Aksi / Method
- `updatedSearch()` — reset pagination
- `channels()` [Computed] — konstanta `CHANNELS`
- `approvedRequisitions()` [Computed] — requisition berstatus `APPROVED`
- `stats()` [Computed] — 3 angka kotak statistik
- `rows()` [Computed] — `VacVacancy::with(['requisition', 'applications'])`, filter search, paginate 20
- `openNew()` — reset form, buka modal
- `publish()` — validasi `id_requisition` + `title` wajib; generate `vacancy_code` (`VAC{YY}{5-digit seq}`, seq = hitung baris tahun berjalan + 1); `VacVacancy::create()` langsung `is_published=true`, `published_at=now()`
- `closeVacancy(int $id)` — update `closed_at=now()` (dengan `wire:confirm`)

## Tabel & Model yang Disentuh
- `VacVacancy` (`vac_vacancy`, `tm2hrr`, PK `id_vacancy`) — utama, CRUD (create + update `closed_at`)
- `RcrRequisition` (`rcr_requisition`, `tm2hrr`) — sumber pilihan requisition APPROVED, relasi `requisition`
- `AppApplication` (`app_application`, `tm2hrr`) — dihitung via relasi `applications` untuk counter pelamar & statistik

## Alur / Business Logic Penting
Tidak ada state `DRAFT` sungguhan untuk vacancy di alur normal — `publish()` langsung set `is_published=true` begitu dibuat (badge "DRAFT" di grid untuk `is_published=false` tampaknya kondisi teoretis yang tidak pernah tercapai lewat UI ini, karena tidak ada tombol "simpan draft" terpisah — TBD, perlu verifikasi apakah ada jalur lain membuat vacancy dengan `is_published=false`). Setelah `closeVacancy()`, kandidat tidak bisa apply lagi ke lowongan itu (ditegakkan di sisi portal, bukan di layar ini).

## Catatan
- Badge status "DRAFT" (`is_published=false`) ada di kode grid tapi tidak ada jalur UI di layar ini yang menghasilkan vacancy dengan `is_published=false` — kemungkinan hanya berjaga-jaga untuk data yang dibuat lewat cara lain (seeder/tinker). TBD - perlu verifikasi.
- Nomor `VAC{YY}{seq}` dihitung dari `count()` tahun berjalan + 1 — potensi race condition sama seperti `requisition_no` di L1 (lihat catatan `screen_recruitment_requisition_list.md`).
