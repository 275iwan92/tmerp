<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// Sisa master pendukung tm2old.mst_* -> tm2mst.ref_legacy_lookup.
//
// Dibaca DINAMIS (kolom pertama = code, kedua = description bila ada, sisanya
// masuk JSON extra) supaya satu Service cukup untuk puluhan tabel lookup tanpa
// menulis pemetaan satu per satu -- dan tidak ada kolom sumber yang hilang.
//
// AMBANG 300 BARIS: tabel lebih besar dari itu bukan lookup, melainkan data
// master/transaksional yang butuh desain tabelnya sendiri (mis.
// mst_product_colour 3.647, mst_product_compo 6.922, mst_product_promac
// 6.832, mst_place_area 23.149). Itu dilaporkan sbg "perlu desain terpisah",
// BUKAN dijejalkan ke tabel lookup supaya skema tetap jujur.
class SupportingLookupMigrationService
{
    use BuildsLegacyArchive;

    private const MAX_LOOKUP_ROWS = 300;

    // Sudah punya rumah sendiri lewat Service lain -- jangan dobel.
    private const ALREADY_MIGRATED = [
        'mst_item', 'mst_vendor', 'mst_vendor_bank',
        'mst_vendor_customer_category', 'mst_vendor_supplier_category',
        'mst_uom', 'mst_uom_conversion',
        'mst_card', 'mst_card_process', 'mst_card_rawmat', 'mst_card_diecut',
        'mst_diecut', 'mst_product_mc',
        'mst_employee', 'mst_employee_address', 'mst_employee_position', 'mst_employee_type',
    ];

    // Salinan/cadangan manual, bukan data independen.
    private const SKIP_COPIES = ['mst_card_rawmat_copy'];

    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $report = [
            'groups_migrated' => 0,
            'rows_inserted' => 0,
            'duplicate_codes_skipped' => 0,
            'skipped_empty' => [],
            'skipped_already_migrated' => [],
            'needs_own_design' => [],
            'errors' => [],
        ];

        DB::connection('tm2mst')->table('ref_legacy_lookup')->delete();

        $tables = collect(DB::connection('tm2old')->select(
            "SELECT TABLE_NAME AS t FROM information_schema.tables
             WHERE table_schema='tm2old' AND table_type='BASE TABLE'
               AND TABLE_NAME LIKE 'mst\\_%' ORDER BY TABLE_NAME"
        ))->map(fn ($r) => $r->t);

        $now = now();
        $rows = [];

        foreach ($tables as $table) {
            try {
                if (in_array($table, self::SKIP_COPIES, true)) {
                    continue;
                }
                if (in_array($table, self::ALREADY_MIGRATED, true)) {
                    $report['skipped_already_migrated'][] = $table;

                    continue;
                }

                $count = DB::connection('tm2old')->table($table)->count();
                if ($count === 0) {
                    $report['skipped_empty'][] = $table;

                    continue;
                }
                if ($count > self::MAX_LOOKUP_ROWS) {
                    $report['needs_own_design'][$table] = $count;

                    continue;
                }

                $columns = collect(DB::connection('tm2old')->select("SHOW COLUMNS FROM `$table`"))
                    ->map(fn ($c) => $c->Field)->values();
                if ($columns->isEmpty()) {
                    continue;
                }

                $codeCol = $columns[0];
                $descCol = $columns[1] ?? null;
                $seenCodes = [];

                foreach (DB::connection('tm2old')->table($table)->get() as $row) {
                    $arr = (array) $row;
                    $code = trim((string) ($arr[$codeCol] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    // Dedup case-insensitive, menyamai unique index MySQL
                    // (utf8mb4_unicode_ci) -- bukan perbandingan PHP biasa.
                    $codeKey = mb_strtoupper($code);
                    if (isset($seenCodes[$codeKey])) {
                        $report['duplicate_codes_skipped']++;

                        continue;
                    }
                    $seenCodes[$codeKey] = true;

                    $extra = $arr;
                    unset($extra[$codeCol]);
                    if ($descCol !== null) {
                        unset($extra[$descCol]);
                    }
                    $extra = array_filter($extra, fn ($v) => $v !== null && $v !== '');

                    $rows[] = [
                        'lookup_group' => $table,
                        'code' => mb_substr($code, 0, 100),
                        'description' => $descCol !== null ? $this->blank($arr[$descCol] ?? null) : null,
                        'extra' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $report['groups_migrated']++;
            } catch (\Throwable $e) {
                $report['errors'][] = "$table: {$e->getMessage()}";
            }
        }

        $report['rows_inserted'] = $this->insertChunked('tm2mst', 'ref_legacy_lookup', $rows, 1000);

        arsort($report['needs_own_design']);

        return $report;
    }
}
