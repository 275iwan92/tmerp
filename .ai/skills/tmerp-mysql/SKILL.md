---
name: tmerp-mysql
description: Keahlian MySQL 8 / InnoDB untuk TMERP — desain skema, indexing, EXPLAIN / EXPLAIN ANALYZE, tuning query lintas-database (tm2*/ddt*), transaksi & deadlock, online DDL untuk migration, dan cara aman membaca database (read-only). Pakai setiap kali menulis/menilai migration, index, query mentah / VIEW lintas-DB, query lambat, deadlock, atau bertanya "isi tabel X".
---

# TMERP — MySQL (InnoDB) playbook

Diadaptasi dari skill komunitas `planetscale/database-skills` (mysql) lalu **disesuaikan keputusan TMERP**. Bila bertentangan, keputusan TMERP (CLAUDE.md, `tmerp-stack-rules`) menang.
Server: MySQL 8.0.x lokal, InnoDB, `utf8mb4` / **`utf8mb4_unicode_ci`** (tetap — jangan ganti ke `0900_ai_ci`).

## 0. Aturan keselamatan (utama)
- **Membaca = bebas, menulis = hati-hati.** Untuk "isi tabel / hitung baris / cek skema": pakai Laravel Boost MCP `database-query` (hanya SELECT), `database-schema`, `database-connections` — jangan `tinker` + `update`.
- **Tidak pernah menulis ke `tm2*` saat kerja UAT / mockup.** Uji tulis di `ddt*` (`--env=mockup`). `tm2old` hanya baca.
- `DROP` / `TRUNCATE` / `DELETE` tanpa `WHERE` / `ALTER` pada tabel besar = **minta persetujuan user dulu**. Data REAL (mesin, proses, pegawai) tidak disentuh (CLAUDE.md §6B).
- Selalu tulis rencana **rollback + verifikasi** untuk perubahan skema di tm2 (jumlah baris sebelum/sesudah, `SHOW CREATE TABLE`).
- Alur kerja: (1) ukur beban & batasan → (2) baca referensi → (3) usulkan perubahan paling kecil + trade-off → (4) **buktikan dengan EXPLAIN / metrik**, bukan firasat → (5) rollback + verifikasi.

## 1. Skema (selaras LOCKED CLAUDE.md §3)
- PK tunggal `BIGINT UNSIGNED AUTO_INCREMENT` (`$table->id('id_x')`); UUID / kode bisnis = kolom `UNIQUE` biasa, bukan PK (clustered index acak = page split + tabel membengkak).
- Secondary index otomatis memuat PK → PK lebar membengkakkan semua index.
- `NOT NULL` + default bila memang wajib; `DECIMAL` untuk uang / qty (tidak pernah `FLOAT`); tanggal ISO.
- **Tipe kolom FK harus identik dengan PK induknya** (`BIGINT UNSIGNED` ↔ `BIGINT UNSIGNED`; collation sama) — beda tipe = FK gagal / index tidak terpakai pada JOIN.
- Normalisasi 3NF; denormalisasi hanya dengan bukti ukuran (snapshot transaksi sudah sah = prinsip §4).
- JSON: jangan untuk kolom yang di-filter / di-JOIN; bila terpaksa, generated column + index.
- **FK lintas-DB dilarang** (§2). FK dalam satu DB tetap fisik; kolom FK **harus ber-index** (MySQL membuatnya otomatis, jangan dihapus).

## 2. Indexing
- Composite: kolom **equality dulu**, lalu range / ORDER BY. Kolom setelah kondisi range tidak terpakai.
- Aturan leftmost-prefix: index `(a,b)` membuat `(a)` redundan.
- Covering index (semua kolom SELECT ada di index → `Using index`) untuk grid / laporan panas.
- Prefix index untuk string panjang (`name(50)`), `UNIQUE` pada kode bisnis.
- 1–5 index per tabel normal; > 6 = audit redundansi. Tiap index menambah biaya INSERT/UPDATE/DELETE.
- Uji hapus aman: `ALTER TABLE t ALTER INDEX i INVISIBLE;` (bisa dikembalikan `VISIBLE`) sebelum `DROP INDEX`.
- Deteksi: `sys.schema_unused_indexes`, `sys.schema_redundant_indexes` (counter reset saat restart — pastikan satu siklus bisnis).
- Cari data: `LIKE 'abc%'` memakai index, `LIKE '%abc'` tidak. Full-text hanya bila kebutuhan nyata.

## 3. Query: anti-pola & perbaikan
| Anti-pola | Perbaikan |
|---|---|
| `WHERE YEAR(tgl)=2026` / fungsi di kolom ber-index | rentang: `tgl >= '2026-01-01' AND tgl < '2027-01-01'` |
| `WHERE kode_varchar = 123` (konversi tipe implisit) | samakan tipe: `'123'` |
| `price * 1.1 > 100` | `price > 100 / 1.1` |
| `OR` lintas kolom | `UNION ALL` dua query ber-index |
| `OFFSET 100000` | keyset: `WHERE id < :last ORDER BY id DESC LIMIT n` |
| `SELECT *` di grid panas | pilih kolom (lihat skill `laravel-best-practices` select-columns) |
| `NOT IN (subquery)` (NULL → hasil kosong) | `NOT EXISTS` |
| N+1 di Eloquent | `with()` / `whereIn` (lintas-DB: query terpisah + cocokkan di PHP) |
| `INSERT` satu-satu dalam loop | `insert()` batch 500–5000 baris |
| `UNION` padahal tak butuh dedup | `UNION ALL` |
| `COUNT(*)` besar di tiap render | cache / hitung terpisah, atau perkiraan |

**Lintas-DB (tm2*):** Eloquent `join()`/`whereHas()` lintas koneksi = DILARANG (§2 CLAUDE.md). Untuk performa baca: VIEW read-only di DB tujuan dengan SQL mentah `db.tabel` (via `App\Support\DbName::table('tm2mst.x')`) — hanya bila ada alasan performa terukur.

## 4. EXPLAIN — cara baca
```sql
EXPLAIN SELECT ...;                 -- estimasi
EXPLAIN FORMAT=TREE SELECT ...;     -- pohon (8.0+)
EXPLAIN ANALYZE SELECT ...;         -- MENJALANKAN query, waktu & baris nyata (8.0.18+)
ANALYZE TABLE nama_tabel;           -- segarkan statistik bila estimasi jauh dari nyata
```
- Urutan akses terbaik → terburuk: `system` → `const` → `eq_ref` → `ref` → `range` → `index` → `ALL`. Target ≥ `ref`; `ALL` pada > ~1000 baris = curiga index hilang.
- `key_len` kecil pada composite = hanya sebagian kolom terpakai. `rows × filtered/100` = ukuran hasil perkiraan.
- Merah: `Using filesort` (index ORDER BY), `Using temporary` (index GROUP BY), `Using join buffer` (index kondisi JOIN), `filtered` rendah, `rows` ≠ `actual rows` jauh.
- `EXPLAIN ANALYZE` pada UPDATE/DELETE tidak boleh sembarangan — ia sungguh dijalankan; untuk SELECT saja, atau di `ddt*`.

## 5. Transaksi, lock, deadlock
- Default `REPEATABLE READ` → next-key / gap lock. Rentang `FOR UPDATE` mengunci seluruh gap-nya dan memblokir INSERT.
- Kolom WHERE UPDATE/DELETE **tanpa index = seluruh tabel terkunci** (InnoDB tak mengeskalasi lock, tapi pindai penuh mengunci semua baris yang dipindai).
- Deadlock (error 1213): akses baris **urutan konsisten**, transaksi pendek, **tanpa I/O / HTTP / Livewire call di dalam transaksi**, retry dengan backoff (`DB::transaction($fn, attempts: 3)`).
- `INSERT ... ON DUPLICATE KEY UPDATE` pada key berdekatan = sumber deadlock; pertimbangkan `upsert()` batch terurut.
- Penomoran dokumen / stok: kunci baris induk (`lockForUpdate()`) dalam transaksi, urut kunci tetap (lihat `StockCardChain`, builder dokumen).
- Isolation: `READ COMMITTED` hanya bila bukti kontensi tinggi dan disetujui — default tetap.
- Diagnosa: `SHOW ENGINE INNODB STATUS\G` (LATEST DETECTED DEADLOCK), `sys.innodb_lock_waits`, `performance_schema.data_locks`.

## 6. Perubahan skema (migration) aman
- Selalu nyatakan algoritma: `ALTER TABLE t ADD COLUMN c ..., ALGORITHM=INSTANT, LOCK=NONE;` — gagal keras bila tak bisa, bukan diam-diam jadi COPY.
- INSTANT (8.0.12+/8.0.29): tambah / hapus / rename kolom. **Tidak** instan: tambah index (INPLACE), ubah tipe kolom (COPY), perpanjang VARCHAR melewati batas byte-length.
- Tabel besar & rame ⇒ jadwalkan di luar jam kerja; transaksi panjang lain menghambat fase commit DDL.
- Migration TMERP mencerminkan dump `docs/tm2*.sql`; jalankan lebih dulu di `ddt*` (`--env=mockup`), baru tm2 saat rilis.
- Ubah PK lama ke BIGINT auto-number (audit 2026-09-30): urutan = tambah kolom baru → isi → swap; cek semua FK pemakai dulu (dalam-DB fisik, lintas-DB logis).

## 7. Operasional
- `max_connections` / pool: Livewire + queue + scheduler tiap proses membuka koneksi ke ±12 DB logis — pantau `SHOW PROCESSLIST`, `Threads_connected`.
- Query lambat: `performance_schema.events_statements_summary_by_digest` (atau `sys.statement_analysis`) urut `total_latency`.
- Ukuran: `information_schema.TABLES` (`data_length + index_length`), `mysql.innodb_index_stats`.
- Backup sebelum operasi masal: `mysqldump --single-transaction --routines tm2xxx tabel`.

## 8. Resep diagnosa cepat
Lihat [references/diagnostic-queries.md](references/diagnostic-queries.md) (siap-tempel, semuanya read-only).

## 9. Menjawab "tanya database"
1. `database-connections` → pilih koneksi yang benar (nama koneksi = nama DB).
2. `database-schema` untuk kolom — jangan menebak nama kolom (`created_by` vs `update_by` legacy).
3. `database-query` SELECT dengan `LIMIT`; hindari mengutip data pribadi / gaji (tm2pyr terenkripsi — requirement).
4. Sebut DB mana yang dibaca (dummy `ddt*` vs real `tm2*`).
