# Diagnostic queries (read-only)

Ganti `tm2sdi` dengan DB yang diperiksa. Semua hanya SELECT / SHOW.

```sql
-- Tabel terbesar (data + index, MB)
SELECT table_schema, table_name, table_rows,
       ROUND((data_length + index_length)/1024/1024, 1) AS total_mb,
       ROUND(index_length/1024/1024, 1) AS index_mb
FROM information_schema.tables
WHERE table_schema LIKE 'tm2%'
ORDER BY (data_length + index_length) DESC LIMIT 20;

-- Tabel TANPA primary key (melanggar aturan PK BIGINT auto number)
SELECT t.table_schema, t.table_name
FROM information_schema.tables t
LEFT JOIN information_schema.table_constraints c
  ON c.table_schema = t.table_schema AND c.table_name = t.table_name
 AND c.constraint_type = 'PRIMARY KEY'
WHERE t.table_type = 'BASE TABLE' AND t.table_schema LIKE 'tm2%' AND c.constraint_name IS NULL;

-- PK yang BUKAN bigint unsigned
SELECT table_schema, table_name, column_name, column_type
FROM information_schema.columns
WHERE table_schema LIKE 'tm2%' AND column_key = 'PRI'
  AND column_type NOT LIKE 'bigint%unsigned%';

-- Kolom FK-like (*_id / id_*) tanpa index di depan
SELECT c.table_schema, c.table_name, c.column_name
FROM information_schema.columns c
LEFT JOIN information_schema.statistics s
  ON s.table_schema = c.table_schema AND s.table_name = c.table_name
 AND s.column_name = c.column_name AND s.seq_in_index = 1
WHERE c.table_schema = 'tm2sdi' AND c.column_name LIKE 'id\_%' AND s.index_name IS NULL;

-- Index redundan / tak terpakai (butuh sys schema; counter reset saat restart)
SELECT * FROM sys.schema_redundant_indexes WHERE table_schema = 'tm2sdi';
SELECT * FROM sys.schema_unused_indexes   WHERE object_schema = 'tm2sdi';

-- Query paling berat sejak restart
SELECT query, db, exec_count, total_latency, avg_latency, rows_examined_avg, no_index_used_count
FROM sys.statement_analysis ORDER BY total_latency DESC LIMIT 15;

-- Query yang tak memakai index
SELECT query, db, exec_count, rows_examined_avg FROM sys.statements_with_full_table_scans LIMIT 15;

-- Siapa memblokir siapa
SELECT * FROM sys.innodb_lock_waits;

-- Transaksi yang sedang jalan lama
SELECT trx_id, trx_started, TIMESTAMPDIFF(SECOND, trx_started, NOW()) AS sec, trx_rows_locked, trx_query
FROM information_schema.innodb_trx ORDER BY trx_started;

-- Koneksi
SHOW STATUS LIKE 'Threads_connected';
SHOW VARIABLES LIKE 'max_connections';
SHOW FULL PROCESSLIST;

-- Collation / charset menyimpang dari standar (utf8mb4_unicode_ci)
SELECT table_schema, table_name, column_name, character_set_name, collation_name
FROM information_schema.columns
WHERE table_schema LIKE 'tm2%' AND collation_name IS NOT NULL
  AND collation_name <> 'utf8mb4_unicode_ci' LIMIT 100;

-- Orphan check dalam-DB (contoh: part tanpa inquiry)
SELECT p.id_part FROM tm2sdi.iqp_part p
LEFT JOIN tm2sdi.iqp_inquiry i ON i.id_inquiry = p.inquiry_id
WHERE i.id_inquiry IS NULL LIMIT 20;
```

Pola bukti sebelum / sesudah menambah index: simpan `EXPLAIN FORMAT=TREE` (atau `EXPLAIN ANALYZE` untuk SELECT) kedua versi di pesan commit / catatan migration.
