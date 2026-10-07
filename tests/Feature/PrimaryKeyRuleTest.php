<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Aturan PK (user 2026-09-30, CLAUDE.md §3): setiap tabel punya PK tunggal BIGINT auto number.
// Tabel baru yang melanggar langsung gagal di sini.
class PrimaryKeyRuleTest extends TestCase
{
    // Tabel bawaan framework Laravel (format ditentukan framework, disetujui user dikecualikan).
    private const FRAMEWORK = ['migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    private const CONNECTIONS = ['tm2mst', 'tm2sdi', 'tm2pur', 'tm2inv', 'tm2prd', 'tm2acc', 'tm2hrm', 'tm2hrr', 'tm2pyr', 'tm2mnt', 'tm2sys'];

    public function test_every_table_has_single_bigint_auto_increment_primary_key(): void
    {
        $bad = [];
        foreach (self::CONNECTIONS as $conn) {
            $db = DB::connection($conn);
            $rows = $db->select("SELECT t.TABLE_NAME n, COUNT(k.COLUMN_NAME) pk_n, MAX(c.DATA_TYPE) dtype, MAX(c.EXTRA) extra
                FROM information_schema.TABLES t
                LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON k.TABLE_SCHEMA = t.TABLE_SCHEMA AND k.TABLE_NAME = t.TABLE_NAME AND k.CONSTRAINT_NAME = 'PRIMARY'
                LEFT JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = k.TABLE_SCHEMA AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME
                WHERE t.TABLE_SCHEMA = ? AND t.TABLE_TYPE = 'BASE TABLE' GROUP BY t.TABLE_NAME", [$db->getDatabaseName()]);
            foreach ($rows as $r) {
                // <tabel>__oldpk = cadangan tukar PK (App\Support\Database\PkSwap), dihapus atas perintah user.
                if (in_array($r->n, self::FRAMEWORK, true) || str_ends_with($r->n, '__oldpk')) {
                    continue;
                }
                if ((int) $r->pk_n !== 1 || $r->dtype !== 'bigint' || ! str_contains((string) $r->extra, 'auto_increment')) {
                    $bad[] = "$conn.$r->n";
                }
            }
        }

        $this->assertSame([], $bad, 'Tabel tanpa PK tunggal BIGINT auto number: '.implode(', ', $bad));
    }
}
