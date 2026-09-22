---
name: screen_recruitment_talent_pool
description: Pencarian kandidat existing (semua yang pernah melamar) dengan riwayat lamaran dan aksi undang ke lowongan baru tanpa isi ulang data.
---

# Talent Pool — Grid + Expand + Modal (L3)

**File**: `resources/views/pages/recruitment/talent-pool.blade.php`
**Route**: `hrm.recruitment.talent-pool` — `GET /hrm/recruitment/talent-pool` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr`
**Modul**: Recruitment (Internal) — L3, lihat komentar kode: `docs/recruitment_tier4.md` SECTION 8

## Tujuan
Pencarian kandidat existing (semua yang pernah melamar, dari `cnd_candidate`) beserta riwayat lamaran, dengan aksi "Undang ke Lowongan" untuk membuat `AppApplication` baru tanpa perlu kandidat mengisi ulang data pribadi (Tier2 §6 / F8 Tier1). Aktor: HR staff.

## Field & Kolom
**Filter/search**: `search` (live, debounce 300ms) — cari `name` atau `national_id`.

**Kotak statistik**: Total Kandidat, Lamaran Aktif (status `APPLIED`/`IN_PROGRESS`/`OFFERING`), Sudah Diangkat (status `HIRED`).

**Kolom grid**: toggle expand (▸/▾), Kandidat (avatar inisial + nama + `national_id` font mono), Kontak (email · phone), Aksi (tombol "Undang ke Lowongan" ✉).

**Baris expand** (klik toggle): riwayat lamaran kandidat — judul lowongan, tanggal `applied_at`, badge status.

**Modal Undang**: `inviteVacancyId` (select, wajib) — sumber `openVacancies()`.

## Aksi / Method
- `updatedSearch()` — reset pagination
- `stats()` [Computed] — 3 angka kotak statistik
- `rows()` [Computed] — `CndCandidate` filter search, urut nama, paginate 20
- `openVacancies()` [Computed] — lowongan published & belum ditutup
- `toggleExpand(int $id)` — toggle baris riwayat lamaran
- `historyFor()` [Computed] — riwayat lamaran kandidat yang sedang di-expand
- `openInvite(int $candidateId)` — buka modal undang
- `invite()` — validasi lowongan dipilih; `AppApplication::create()` (`current_stage_seq=1`, `status=APPLIED`, `applied_at=now()`) — TANPA membuat ulang `CndCandidate` (reuse `id_candidate` existing)
- `avatarInitials(string $name)` / `avatarGradient(int $id)` — helper tampilan avatar

## Tabel & Model yang Disentuh
- `CndCandidate` (`cnd_candidate`, `tm2hrr`) — utama, read-only (sumber grid)
- `AppApplication` (`app_application`, `tm2hrr`) — dibaca (statistik & riwayat) dan ditulis (`invite()` membuat baris baru)
- `VacVacancy` (`vac_vacancy`, `tm2hrr`) — sumber pilihan lowongan tujuan undang

## Alur / Business Logic Penting
"Undang" = jalur masuk alternatif ke pipeline selain apply mandiri lewat portal — kandidat lama (talent pool) bisa langsung didaftarkan ke lowongan baru tanpa mengisi ulang data pribadi/keluarga/pendidikan yang sudah ada di `cnd_candidate`. Application baru langsung mulai dari `current_stage_seq=1` (tahap awal pipeline), sama seperti apply normal dari portal.

## Catatan
- Tidak ada guard mencegah undang kandidat yang sudah punya application aktif untuk lowongan yang sama (duplikat) — TBD, tidak ada validasi unique terlihat di `invite()`.
