<?php

namespace App\Services\Data;

use App\Models\Sys\SysConstant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

// Flag asal data (BACKLOG §18.7, keputusan user 2026-09-17):
//   LIVE   = data kerja nyata (default setiap baris baru)
//   DUMMY  = data demo/UAT -- bisa DISEMBUNYIKAN dan DI-RESET ke keadaan semula
//   LEGACY = hasil migrasi sistem lama -- bisa disembunyikan demi kompatibilitas
// Tujuannya menyembunyikan, BUKAN menghapus. Siapa DUMMY/LEGACY ditentukan
// aturan yang diatur admin (sys_data_origin_rule), karena penanda di data lama
// tidak seragam. Klasifikasi hanya menaikkan LIVE -> DUMMY/LEGACY, tidak pernah
// menurunkan -- flag yang sudah diberi tidak hilang saat aturan dijalankan ulang.
class DataOriginService
{
    public const DATABASES = ['tm2mst', 'tm2sdi', 'tm2pur', 'tm2inv', 'tm2prd', 'tm2acc', 'tm2hrr', 'tm2pyr'];

    public const ORIGINS = ['LIVE', 'DUMMY', 'LEGACY'];

    public const CACHE_KEY = 'data_origin.tables';

    // Tabel yang sengaja tidak diberi flag: arsip legacy mentah, log/jejak audit,
    // cadangan -- isinya bukan data kerja yang ditampilkan.
    private const EXCLUDE = '/(_legacy|_log|_bak|_backup|_history)$|^(migrations|jobs|failed_jobs|cache|sessions)$/';

    /**
     * Tabel ber-primary-key di database lingkup, beserta kolom pembuat.
     *
     * @return array<int, array{db: string, table: string, pk: array, creator: ?string, has_flag: bool}>
     */
    public function tables(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CACHE_KEY);
            Cache::forget('data_origin.models');
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            $rows = DB::connection('tm2sys')->select("
                SELECT t.table_schema db, t.table_name tbl,
                       GROUP_CONCAT(CASE WHEN c.column_key = 'PRI' THEN c.column_name END ORDER BY c.ordinal_position) pk,
                       MAX(c.column_name = 'created_by') cb, MAX(c.column_name = 'create_by') cb2,
                       MAX(c.column_name = 'data_origin') flag
                FROM information_schema.tables t
                JOIN information_schema.columns c ON c.table_schema = t.table_schema AND c.table_name = t.table_name
                WHERE t.table_type = 'BASE TABLE' AND t.table_schema IN ('".implode("','", self::DATABASES)."')
                GROUP BY t.table_schema, t.table_name
                ORDER BY t.table_schema, t.table_name");

            return collect($rows)
                ->filter(fn ($r) => $r->pk && ! preg_match(self::EXCLUDE, $r->tbl))
                ->map(fn ($r) => [
                    'db' => $r->db,
                    'table' => $r->tbl,
                    'pk' => explode(',', $r->pk),
                    'creator' => $r->cb ? 'created_by' : ($r->cb2 ? 'create_by' : null),
                    'has_flag' => (bool) $r->flag,
                ])
                ->values()->all();
        });
    }

    /** "db.table" => true untuk tabel yang sudah punya kolom data_origin. */
    public function flaggedTables(): array
    {
        return collect($this->tables())->filter(fn ($t) => $t['has_flag'])
            ->mapWithKeys(fn ($t) => [$t['db'].'.'.$t['table'] => true])->all();
    }

    // ===================== SETTING =====================

    public function hiddenOrigins(): array
    {
        return array_values(array_filter([
            SysConstant::number('DATA_SHOW_DUMMY', 1) ? null : 'DUMMY',
            SysConstant::number('DATA_SHOW_LEGACY', 1) ? null : 'LEGACY',
        ]));
    }

    public function newRowsAsDummy(): bool
    {
        return (bool) SysConstant::number('DATA_NEW_AS_DUMMY', 0);
    }

    // ===================== KLASIFIKASI =====================

    public function rules(): array
    {
        return DB::connection('tm2sys')->table('sys_data_origin_rule')
            ->where('activate', 1)->orderBy('priority')->orderBy('id_rule')->get()->all();
    }

    /**
     * Terapkan aturan ke baris LIVE. $dryRun = hanya hitung.
     *
     * @return array<string, array{DUMMY: int, LEGACY: int}> per "db.table" yang berubah
     */
    public function classify(bool $dryRun = true): array
    {
        $result = [];

        foreach ($this->tables() as $t) {
            if (! $t['has_flag']) {
                continue;
            }

            foreach ($this->rules() as $rule) {
                $where = $this->ruleWhere($rule, $t);
                if ($where === null) {
                    continue;
                }

                $q = DB::connection($t['db'])->table($t['table'])
                    ->where('data_origin', 'LIVE')->where('data_origin_manual', 0)->whereRaw($where[0], $where[1]);
                $n = $dryRun ? $q->count() : $q->update(['data_origin' => $rule->origin]);

                if ($n > 0 && $rule->origin !== 'LIVE') {
                    $key = $t['db'].'.'.$t['table'];
                    $result[$key] ??= ['DUMMY' => 0, 'LEGACY' => 0];
                    $result[$key][$rule->origin] += $n;
                }
            }
        }

        foreach ($this->propagateDummy($dryRun) as $key => $n) {
            $result[$key] ??= ['DUMMY' => 0, 'LEGACY' => 0];
            $result[$key]['DUMMY'] += $n;
        }

        return $result;
    }

    // Anak dari baris DUMMY ikut DUMMY (mis. iqp_part milik Inquiry dummy),
    // supaya disembunyikan & di-reset bersama induknya. Diulang sampai stabil.
    private function propagateDummy(bool $dryRun): array
    {
        $fks = collect(DB::connection('tm2sys')->select("
            SELECT k.table_schema db, k.table_name child, k.column_name col, k.referenced_table_name parent, k.referenced_column_name pcol
            FROM information_schema.key_column_usage k
            WHERE k.referenced_table_name IS NOT NULL AND k.table_schema = k.referenced_table_schema
              AND k.table_schema IN ('".implode("','", self::DATABASES)."')"))
            ->map(fn ($f) => [$f->db, $f->child, $f->col, $f->db, $f->parent, $f->pcol]);

        // Tautan lintas database (bukan FK fisik, CLAUDE.md §2), format sama:
        // [anak db, anak tabel, kolom anak, induk db, induk tabel, kolom induk].
        $links = $fks->all();
        $links[] = ['tm2sdi', 'iqp_part', 'prod_part_id', 'tm2mst', 'prod_part', 'id_part'];
        $links[] = ['tm2mst', 'prod_part', 'id_part', 'tm2sdi', 'iqp_part', 'prod_part_id'];
        $links[] = ['tm2mst', 'prod_part', 'product_id', 'tm2mst', 'prod_product', 'id_product'];

        $flagged = $this->flaggedTables();
        $out = [];

        for ($pass = 0; $pass < 6; $pass++) {
            $changed = 0;

            foreach ($links as [$cdb, $child, $col, $pdb, $parent, $pcol]) {
                if (! isset($flagged["$cdb.$child"], $flagged["$pdb.$parent"]) || "$cdb.$child" === "$pdb.$parent") {
                    continue;
                }

                $cond = "c.data_origin = 'LIVE' AND c.data_origin_manual = 0 AND p.data_origin = 'DUMMY'";
                $join = "`$cdb`.`$child` c JOIN `$pdb`.`$parent` p ON p.`$pcol` = c.`$col`";

                $n = $dryRun
                    ? (int) DB::connection('tm2sys')->selectOne("SELECT COUNT(*) n FROM $join WHERE $cond")->n
                    : DB::connection('tm2sys')->update("UPDATE $join SET c.data_origin = 'DUMMY' WHERE $cond");

                if ($n) {
                    $out["$cdb.$child"] = ($out["$cdb.$child"] ?? 0) + $n;
                    $changed += $n;
                }
            }

            // Dry-run tidak mengubah apa pun, jadi putaran berikutnya tidak menemukan hal baru.
            if ($dryRun || ! $changed) {
                break;
            }
        }

        return $out;
    }

    /** @return array{0: string, 1: array}|null */
    private function ruleWhere(object $rule, array $t): ?array
    {
        if ($rule->db_name && $rule->db_name !== $t['db']) {
            return null;
        }
        if ($rule->table_name && $rule->table_name !== $t['table']) {
            return null;
        }

        switch ($rule->match_type) {
            case 'CREATED_BY':
                return $t['creator'] ? ["`{$t['creator']}` LIKE ?", [$rule->match_value]] : null;

            case 'COLUMN_LIKE':
            case 'COLUMN_FILLED':
                $col = (string) $rule->column_name;
                if (! preg_match('/^\w+$/', $col) || ! $this->hasColumn($t['db'], $t['table'], $col)) {
                    return null;
                }

                return $rule->match_type === 'COLUMN_LIKE'
                    ? ["`{$col}` LIKE ?", [$rule->match_value]]
                    : ["`{$col}` IS NOT NULL AND `{$col}` <> ''", []];
        }

        return null;
    }

    private function hasColumn(string $db, string $table, string $col): bool
    {
        static $cache = [];

        return $cache["$db.$table.$col"] ??= (bool) DB::connection('tm2sys')->selectOne(
            'SELECT 1 x FROM information_schema.columns WHERE table_schema = ? AND table_name = ? AND column_name = ?',
            [$db, $table, $col]);
    }

    /** @return array<string, array{LIVE: int, DUMMY: int, LEGACY: int}> */
    public function counts(): array
    {
        $out = [];

        foreach ($this->tables() as $t) {
            if (! $t['has_flag']) {
                continue;
            }

            $c = DB::connection($t['db'])->table($t['table'])
                ->selectRaw('data_origin, COUNT(*) n')->groupBy('data_origin')->pluck('n', 'data_origin');

            $out[$t['db'].'.'.$t['table']] = [
                'LIVE' => (int) ($c['LIVE'] ?? 0),
                'DUMMY' => (int) ($c['DUMMY'] ?? 0),
                'LEGACY' => (int) ($c['LEGACY'] ?? 0),
            ];
        }

        return $out;
    }

    // ===================== KELENGKAPAN DATA (SYARAT WAJIB) =====================
    // Keputusan user 2026-09-18: data LEGACY yang sudah memenuhi syarat data
    // saat ini SAH dipakai sebagai data real; yang belum (mis. master produk
    // tanpa kepemilikan customer) tetap ditandai belum lengkap dan tidak boleh
    // jadi acuan. data_ready = 1 berarti semua syarat wajib tabel itu terisi.

    public function requirements(): array
    {
        return DB::connection('tm2sys')->table('sys_data_requirement')
            ->where('activate', 1)->orderBy('db_name')->orderBy('table_name')->get()->all();
    }

    /** Hitung ulang data_ready. @return array<string, array{ready: int, incomplete: int}> */
    public function computeReady(bool $dryRun = true): array
    {
        $byTable = collect($this->requirements())->groupBy(fn ($r) => $r->db_name.'.'.$r->table_name);
        $flagged = $this->flaggedTables();
        $out = [];

        foreach ($byTable as $key => $reqs) {
            if (! isset($flagged[$key])) {
                continue;
            }

            [$db, $table] = explode('.', $key);
            $conn = DB::connection($db);
            $missing = [];

            foreach ($reqs as $r) {
                if (! preg_match('/^\w+$/', (string) $r->column_name) || ! $this->hasColumn($db, $table, $r->column_name)) {
                    continue;
                }
                $missing[] = "(`{$r->column_name}` IS NULL OR `{$r->column_name}` = '')";
            }

            if (! $missing) {
                continue;
            }

            $sql = implode(' OR ', $missing);
            $incomplete = (clone $conn->table($table))->whereRaw($sql)->count();
            $ready = (clone $conn->table($table))->whereRaw("NOT ($sql)")->count();

            if (! $dryRun) {
                $conn->table($table)->whereRaw($sql)->update(['data_ready' => 0]);
                $conn->table($table)->whereRaw("NOT ($sql)")->update(['data_ready' => 1]);
            }

            $out[$key] = ['ready' => $ready, 'incomplete' => $incomplete];
        }

        return $out;
    }

    /**
     * LEGACY yang sudah lengkap dinaikkan jadi LIVE (data real).
     *
     * @return array<string, int> jumlah baris per "db.table"
     */
    public function promoteReadyLegacy(bool $dryRun = true, ?string $only = null): array
    {
        $out = [];

        foreach ($this->tables() as $t) {
            $key = $t['db'].'.'.$t['table'];

            if (! $t['has_flag'] || ($only && $only !== $key)) {
                continue;
            }

            $q = DB::connection($t['db'])->table($t['table'])->where('data_origin', 'LEGACY')->where('data_ready', 1);
            // data_origin_manual: keputusan admin, tidak boleh ditarik balik aturan.
            $n = $dryRun ? $q->count() : $q->update(['data_origin' => 'LIVE', 'data_origin_manual' => 1]);

            if ($n > 0) {
                $out[$key] = $n;
            }
        }

        return $out;
    }

    /** @return array<string, array{LEGACY_READY: int, LEGACY_INCOMPLETE: int, LIVE_INCOMPLETE: int}> */
    public function readiness(): array
    {
        $byTable = collect($this->requirements())->groupBy(fn ($r) => $r->db_name.'.'.$r->table_name);
        $flagged = $this->flaggedTables();
        $out = [];

        foreach ($byTable->keys() as $key) {
            if (! isset($flagged[$key])) {
                continue;
            }

            [$db, $table] = explode('.', $key);
            $rows = DB::connection($db)->table($table)
                ->selectRaw('data_origin, data_ready, COUNT(*) n')->groupBy('data_origin', 'data_ready')->get();

            $out[$key] = [
                'LEGACY_READY' => (int) $rows->firstWhere(fn ($r) => $r->data_origin === 'LEGACY' && $r->data_ready)?->n,
                'LEGACY_INCOMPLETE' => (int) $rows->firstWhere(fn ($r) => $r->data_origin === 'LEGACY' && ! $r->data_ready)?->n,
                'LIVE_INCOMPLETE' => (int) $rows->firstWhere(fn ($r) => $r->data_origin === 'LIVE' && ! $r->data_ready)?->n,
            ];
        }

        return $out;
    }

    public function hideLegacyIncomplete(): bool
    {
        return (bool) SysConstant::number('DATA_HIDE_LEGACY_INCOMPLETE', 0);
    }

    // ===================== SNAPSHOT & RESET DUMMY =====================

    // Simpan salinan SEMUA baris DUMMY sebagai "keadaan semula" untuk reset.
    public function snapshot(string $login): int
    {
        $sys = DB::connection('tm2sys');
        $sys->table('sys_dummy_snapshot')->delete();
        $total = 0;
        $now = now();

        foreach ($this->tables() as $t) {
            if (! $t['has_flag']) {
                continue;
            }

            DB::connection($t['db'])->table($t['table'])->where('data_origin', 'DUMMY')
                ->orderBy($t['pk'][0])
                ->chunk(500, function ($rows) use ($sys, $t, $now, $login, &$total) {
                    $sys->table('sys_dummy_snapshot')->insert($rows->map(fn ($r) => [
                        'db_name' => $t['db'],
                        'table_name' => $t['table'],
                        'pk_key' => $this->pkKey($t, (array) $r),
                        'row_json' => json_encode($r, JSON_UNESCAPED_UNICODE),
                        'captured_at' => $now,
                        'captured_by' => $login,
                    ])->all());
                    $total += $rows->count();
                });
        }

        return $total;
    }

    public function snapshotInfo(): array
    {
        $sys = DB::connection('tm2sys')->table('sys_dummy_snapshot');

        return [
            'rows' => (clone $sys)->count(),
            'at' => (clone $sys)->max('captured_at'),
            'by' => (clone $sys)->orderByDesc('captured_at')->value('captured_by'),
        ];
    }

    /**
     * Kembalikan data DUMMY ke isi snapshot: baris yang terhapus dikembalikan,
     * yang berubah ditimpa isi semula. $removeNew = baris DUMMY yang lahir
     * SETELAH snapshot ikut dihapus (supaya benar-benar "seperti semula").
     *
     * @return array{restored: int, changed: int, new: int, tables: array}
     */
    public function reset(bool $dryRun, bool $removeNew = false): array
    {
        $sum = ['restored' => 0, 'changed' => 0, 'new' => 0, 'tables' => []];
        $byTable = DB::connection('tm2sys')->table('sys_dummy_snapshot')
            ->select('db_name', 'table_name')->distinct()->get()
            ->map(fn ($r) => $r->db_name.'.'.$r->table_name)->all();
        $meta = collect($this->tables())->keyBy(fn ($t) => $t['db'].'.'.$t['table']);

        foreach (array_unique(array_merge($byTable, $meta->filter(fn ($t) => $t['has_flag'])->keys()->all())) as $key) {
            $t = $meta->get($key);
            if (! $t) {
                continue;
            }

            $conn = DB::connection($t['db']);
            $snap = DB::connection('tm2sys')->table('sys_dummy_snapshot')
                ->where('db_name', $t['db'])->where('table_name', $t['table'])
                ->pluck('row_json', 'pk_key');

            $current = [];
            foreach ($conn->table($t['table'])->where('data_origin', 'DUMMY')->cursor() as $r) {
                $current[$this->pkKey($t, (array) $r)] = (array) $r;
            }

            $restore = [];
            $changed = 0;
            $restored = 0;

            foreach ($snap as $pk => $json) {
                $orig = json_decode($json, true);
                if (! isset($current[$pk])) {
                    // Bisa terhapus, atau flag-nya diubah -- cek baris dengan PK sama.
                    $restored++;
                    $restore[] = $orig;
                } elseif ($this->differs($orig, $current[$pk])) {
                    $changed++;
                    $restore[] = $orig;
                }
            }

            $new = array_diff_key($current, $snap->all());

            if ($restore || ($removeNew && $new)) {
                $sum['tables'][$key] = ['restored' => $restored, 'changed' => $changed, 'new' => count($new)];
            }
            $sum['restored'] += $restored;
            $sum['changed'] += $changed;
            $sum['new'] += count($new);

            if ($dryRun || (! $restore && ! ($removeNew && $new))) {
                continue;
            }

            $conn->statement('SET FOREIGN_KEY_CHECKS=0');
            try {
                $conn->transaction(function () use ($conn, $t, $restore, $removeNew, $new) {
                    foreach (array_chunk($restore, 200) as $chunk) {
                        $conn->table($t['table'])->upsert($chunk, $t['pk'], array_keys($chunk[0]));
                    }

                    if ($removeNew) {
                        foreach ($new as $row) {
                            $q = $conn->table($t['table'])->where('data_origin', 'DUMMY');
                            foreach ($t['pk'] as $col) {
                                $q->where($col, $row[$col]);
                            }
                            $q->delete();
                        }
                    }
                });
            } finally {
                $conn->statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        return $sum;
    }

    private function differs(array $a, array $b): bool
    {
        foreach ($a as $k => $v) {
            // updated_at berubah bukan berarti isinya berubah.
            if (in_array($k, ['updated_at', 'update_log'], true)) {
                continue;
            }
            if ((string) ($b[$k] ?? '') !== (string) ($v ?? '')) {
                return true;
            }
        }

        return false;
    }

    private function pkKey(array $t, array $row): string
    {
        return implode('|', array_map(fn ($c) => (string) ($row[$c] ?? ''), $t['pk']));
    }
}
