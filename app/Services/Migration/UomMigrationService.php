<?php

namespace App\Services\Migration;

use App\Services\Migration\Concerns\BuildsLegacyArchive;
use Illuminate\Support\Facades\DB;

// tm2old.mst_uom(_conversion) -> tm2mst.uom_unit / uom_conversion.
//
// Beda dari Service arsip lain: ini BUKAN truncate+reload. uom_unit sudah
// berisi 42 satuan hasil kurasi V2 dan uom_conversion 24 baris -- master
// yang sudah ada MENANG, legacy cuma menambal kode yang belum ada. Satuan
// dipakai lintas modul (tiap baris detail arsip menyimpan uom_code), jadi
// menimpa definisi V2 dgn data lama justru merusak yang sudah benar.
class UomMigrationService
{
    use BuildsLegacyArchive;

    public function run(string $actor): array
    {
        $report = [
            'uom_unit' => ['created' => 0, 'skipped_existing' => 0],
            'uom_conversion' => ['created' => 0, 'skipped_existing' => 0],
            'errors' => [],
        ];

        $existingUom = DB::connection('tm2mst')->table('uom_unit')
            ->pluck('uom_code')
            ->map(fn ($c) => mb_strtoupper(trim((string) $c)))
            ->flip();

        $now = now();
        $newUnits = [];

        foreach (DB::connection('tm2old')->table('mst_uom')->get() as $u) {
            $code = trim((string) $u->uom_code);
            if ($code === '') {
                continue;
            }
            if ($existingUom->has(mb_strtoupper($code))) {
                $report['uom_unit']['skipped_existing']++;

                continue;
            }
            $newUnits[mb_strtoupper($code)] = [
                'uom_code' => $code,
                'uom_name' => $this->blank($u->description) ?? $code,
                'is_base' => 0,
                'activate' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $actor,
                'updated_by' => $actor,
            ];
        }
        $report['uom_unit']['created'] = $this->insertChunked('tm2mst', 'uom_unit', array_values($newUnits));

        $existingConv = DB::connection('tm2mst')->table('uom_conversion')
            ->get(['uom_src', 'uom_dst'])
            ->map(fn ($r) => mb_strtoupper(trim($r->uom_src).'>'.trim($r->uom_dst)))
            ->flip();

        $newConv = [];
        foreach (DB::connection('tm2old')->table('mst_uom_conversion')->get() as $c) {
            $src = trim((string) $c->uom_orig);
            $dst = trim((string) $c->uom_dest);
            if ($src === '' || $dst === '') {
                continue;
            }
            $key = mb_strtoupper($src.'>'.$dst);
            if ($existingConv->has($key) || isset($newConv[$key])) {
                $report['uom_conversion']['skipped_existing']++;

                continue;
            }
            $newConv[$key] = [
                'uom_src' => $src,
                'uom_dst' => $dst,
                'conversion_value' => $c->value_conv,
                'activate' => 1,
                'created_at' => $now,
                'updated_at' => $now,
                'created_by' => $actor,
                'updated_by' => $actor,
            ];
        }
        $report['uom_conversion']['created'] = $this->insertChunked('tm2mst', 'uom_conversion', array_values($newConv));

        return $report;
    }
}
