---
name: screen_recruitment_requisition_list
description: Grid daftar Permohonan Tenaga Kerja (Requisition) rekrutmen internal, titik masuk pembuatan dokumen baru.
---

# Permohonan Tenaga Kerja — Grid (L1)

**File**: `resources/views/pages/recruitment/requisition/list.blade.php`
**Route**: `hrm.recruitment.requisition.list` — `GET /hrm/recruitment/requisition` (middleware `auth`, `force.password`, `module:HRM`)
**Koneksi DB utama**: `tm2hrr` (nama koneksi Eloquent `tm2hrr`)
**Modul**: Recruitment (Internal) — L1 (grid), lihat komentar kode: `docs/recruitment_tier4.md` SECTION 6

## Tujuan
Grid worklist Permohonan Tenaga Kerja (requisition). Aktor: HR staff (buat dokumen baru & cari), approver (klik 2x untuk buka & putuskan di layar Entry — approval TIDAK diputuskan dari grid ini, pola sama seperti `pur/pr/index.blade.php`). Record baru dilahirkan langsung dari toolbar grid ini ("+ New Record"), lalu redirect ke layar Entry untuk diisi.

## Field & Kolom
**Filter/search**: `search` (live, debounce 300ms) — cari `requisition_no` atau `position_title`.

**Kotak statistik**: Total, Menunggu Approval (`status=SUBMITTED`), Approved, Ditolak (`status=REJECTED`).

**Kolom grid**:
- Permohonan — avatar inisial (gradient warna dari `avatarGradient()`) + `position_title` (fallback "(belum diisi)") + `requisition_no` (font mono)
- Departemen — lookup `department_id` ke `$this->departments` (label saja, lihat Catatan cross-DB)
- Track — `$r->track->name`
- Headcount (rata kanan)
- Status — badge warna (`DRAFT`/`SUBMITTED`/`APPROVED`,`CLOSED`/`REJECTED`)
- Aksi — tombol "Buka" (↗) ke route entry

## Aksi / Method
- `updatedSearch()` — reset pagination saat search berubah
- `createNew()` — membuat `RcrRequisition` baru: `requisition_no` auto-generate format `RCR{YY}{5-digit-seq}` (seq = hitung baris tahun berjalan + 1), default `id_track` = track aktif pertama, `department_id` = departemen aktif pertama, status `DRAFT`, `requested_by` = user login. Redirect ke `hrm.recruitment.requisition.entry`.
- `avatarInitials(string $name)` — 2 huruf pertama kata pertama nama posisi, fallback `RC`
- `avatarGradient(int $id)` — pilih gradient dari palet 6 warna berdasar modulus id

## Tabel & Model yang Disentuh
- `RcrRequisition` (`rcr_requisition`, `tm2hrr`, PK `id_requisition`) — utama, CRUD
- `RcrTrack` (`rcr_track`, `tm2hrr`) — sumber pilihan default track, filter `is_active=true`
- `OrgDepartment` (`tm2mst`, koneksi Eloquent `tm2mst`) — **lintas-DB**, dipakai untuk lookup nama departemen (`pluck` disimpan sbg array `$this->departments`, dicocokkan di PHP — pola default lintas-DB CLAUDE.md §2 pola 1)

## Alur / Business Logic Penting
Requisition lahir SELALU dari grid ini (tombol "+ New Record"), bukan dari layar Entry. Setelah dibuat berstatus `DRAFT`, HR lanjut mengisi & submit approval di layar Entry (`requisition/entry.blade.php`). Approval matrix & progres approval TIDAK ditampilkan di grid ini — cek di Entry.

## Catatan
- Tidak ada gap/TODO eksplisit di komentar kode untuk layar ini.
- Nomor dokumen `RCR{YY}{seq}` dihitung dari `count()` baris tahun berjalan + 1 — berpotensi race condition/gap kalau ada penghapusan baris di tengah tahun berjalan (TBD - perlu verifikasi apakah ini masalah nyata di produksi, tidak ada guard unik terlihat di kode selain kolom `requisition_no` itu sendiri).
