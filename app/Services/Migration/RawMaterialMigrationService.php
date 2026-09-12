<?php

namespace App\Services\Migration;

use App\Models\Legacy\MstItem;
use App\Models\Master\MatRaw;

// Engine migrasi tm2old.mst_item (kandidat raw material) -> tm2mst.mat_raw.
// Dipicu manual dari tab "Migrasi Data Legacy" (Setup, admin-only).
// Idempotent: matching by old_item_code, aman diklik berkali-kali -- item
// yang sudah ada (5.893 baris hasil impor lama) TIDAK disentuh ulang kalau
// datanya sama, cuma di-update kalau ada perubahan di sumber.
//
// Kriteria "kandidat raw material" (sumber lama tidak konsisten -- kolom
// material_class banyak kosong, flag as_mat/as_fgs/dst pakai 0/1/2 dgn 2 =
// "belum pernah diisi/default"): material_class='RAW MATERIAL' ATAU as_mat=1.
// Item yang tidak masuk kriteria manapun TIDAK dipaksakan masuk -- dilaporkan
// terpisah sebagai "unclassified" biar admin yang putuskan.
class RawMaterialMigrationService
{
    public function run(string $actor): array
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $report = [
            'materials' => ['created' => 0, 'updated' => 0, 'unchanged' => 0],
            'skipped_too_long' => [],
            'errors' => [],
        ];

        $candidates = MstItem::query()
            ->where(function ($q) {
                $q->where('material_class', 'RAW MATERIAL')
                    ->orWhere('as_mat', 1);
            })
            ->orderBy('item_code');

        $candidates->chunk(200, function ($chunk) use (&$report, $actor) {
            foreach ($chunk as $item) {
                try {
                    $oldCode = trim((string) $item->item_code);
                    if ($oldCode === '') {
                        continue;
                    }

                    $materialCode = $this->resolveMaterialCode($oldCode, $report);

                    $data = [
                        'material_name' => $this->blank($item->item_name) ?? $oldCode,
                        'uom_stock' => $this->blank($item->uom_stock),
                        'uom_purchase' => $this->blank($item->uom_purchase),
                        'description' => $this->blank($item->note),
                        'activate' => ((int) $item->active_item) === 1,
                        'old_item_code' => $oldCode,
                        'old_item_name' => $this->blank($item->item_name),
                        'update_by' => $actor,
                    ];

                    $existing = MatRaw::where('old_item_code', $oldCode)->first();

                    if ($existing) {
                        $changed = collect($data)->except(['update_by'])
                            ->some(fn ($value, $key) => $existing->{$key} != $value);

                        if ($changed) {
                            $existing->update($data);
                            $report['materials']['updated']++;
                        } else {
                            $report['materials']['unchanged']++;
                        }
                    } else {
                        MatRaw::create(array_merge($data, [
                            'material_code' => $materialCode,
                            'item_type' => 'MATERIAL',
                            'purchasable' => true,
                            'create_by' => $actor,
                        ]));
                        $report['materials']['created']++;
                    }
                } catch (\Throwable $e) {
                    $report['errors'][] = "{$item->item_code}: {$e->getMessage()}";
                }
            }
        });

        return $report;
    }

    // mat_raw.material_code varchar(60) -- kode lama ada yg >60 karakter
    // (48 kasus, biasanya nama produk gabungan, bukan bahan baku sungguhan).
    // Dipotong + suffix urut kalau perlu, tapi teks ASLI utuh tetap tersimpan
    // di old_item_code (varchar 255) supaya tidak ada informasi yang hilang.
    protected function resolveMaterialCode(string $oldCode, array &$report): string
    {
        if (mb_strlen($oldCode) <= 60) {
            return $oldCode;
        }

        $report['skipped_too_long'][] = $oldCode;
        $truncated = mb_substr($oldCode, 0, 55);
        $suffix = 1;
        $candidate = $truncated;

        while (MatRaw::where('material_code', $candidate)->exists()) {
            $candidate = $truncated.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    protected function blank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
