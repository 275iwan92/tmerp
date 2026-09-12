<?php

namespace App\Services\Migration\Concerns;

use Illuminate\Support\Facades\DB;

// Helper bersama utk engine migrasi arsip tm2old.* -> tabel *_legacy read-only
// (dashboard/laporan). Dipakai CustomerOrderMigrationService dkk -- menghindari
// menulis ulang pola preload-crosswalk + bulk-insert-chunked di tiap Service.
trait BuildsLegacyArchive
{
    protected function preloadMap(string $connection, string $table, string $keyColumn, string $valueColumn): array
    {
        return DB::connection($connection)->table($table)
            ->whereNotNull($keyColumn)
            ->pluck($valueColumn, $keyColumn)
            ->all();
    }

    protected function insertChunked(string $connection, string $table, array $rows, int $chunkSize = 1000): int
    {
        $inserted = 0;
        foreach (array_chunk($rows, $chunkSize) as $batch) {
            DB::connection($connection)->table($table)->insert($batch);
            $inserted += count($batch);
        }

        return $inserted;
    }

    protected function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    // Kunci rantai dokumen di sumber lama memakai 0 (bukan NULL) untuk
    // "tidak ada referensi" -- dinormalkan ke NULL supaya join tidak
    // salah menempel ke baris ber-id 0 dan COUNT sisa relasi jujur.
    protected function nullableId(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}
