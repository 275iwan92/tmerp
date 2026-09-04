---
name: db_old
description: tm2old — staging data lama dari ScriptCase V1 untuk keperluan migrasi, DDL tidak tersedia lokal
---

# Database: tm2old (koneksi Eloquent: `old`)

**Sumber DDL**: tidak tersedia lokal (tidak ada file `docs/ddl/tm2old.sql`).
**Jumlah tabel**: TBD

## Tujuan
Per CLAUDE.md §2: "Old data for migration" — tempat staging data dari sistem lama (ScriptCase V1) selama proses migrasi bertahap ke V2. Contoh pemakaian yang terlihat di komentar kode lain: `prod_process` (tm2mst) hasil migrasi mencatat `created_by = 'migration:tm2old.m_process'` — menunjukkan tabel sumbernya bernama `m_process` di tm2old.

## Daftar Tabel
TBD — tidak ada dump DDL lokal untuk database ini. Kalau perlu detail skema, cek langsung ke server MySQL (`SHOW TABLES FROM tm2old`) atau cari referensi `migration:tm2old.*` di kolom `created_by`/komentar migration lain di seluruh codebase.

## Catatan
Database ini bersifat sumber data mentah utk migrasi satu-arah (V1 -> V2), bukan database aktif yang dibaca/ditulis aplikasi V2 sehari-hari — tidak ditemukan model Eloquent dengan `$connection = 'old'` di `app/Models/**`.
