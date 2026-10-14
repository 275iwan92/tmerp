<?php

/*
 * REMARK
 * Tujuan       : Fitur kesamaan dengan job sebelumnya di mesin (jangkar = job terkunci / live terakhir sebelum blok Draft): produk sama = setup lebih kecil.
 * Sumber hulu  : operasi pada mesin yang sama (customer, product).
 * Input        : operasi, konteks anchor[mesin].
 * Output       : 0..1 (0.5 customer sama + 0.5 produk sama).
 * Dipakai oleh : FeatureSet.
 * Status       : Aktif. Bahan / ukuran / jumlah warna belum ada di baris operasi; tambahkan sebagai fitur turunan setelah Sequence Rules disetujui.
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

final class SimilarityFeature extends Feature
{
    public function name(): string
    {
        return 'similarity';
    }

    public function value(array $op, array $ctx): float
    {
        $prev = $ctx['anchor'][$op['machine_id']] ?? null;
        if (! $prev) {
            return 0.0;
        }

        return ((string) $op['customer'] !== '' && $op['customer'] === $prev['customer'] ? 0.5 : 0.0)
            + ((string) $op['product'] !== '' && $op['product'] === $prev['product'] ? 0.5 : 0.0);
    }
}
