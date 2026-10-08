<?php

declare(strict_types=1);

namespace App\Services\Maintenance;

use Illuminate\Database\Eloquent\Model;

// Nomor dokumen Maintenance: PREFIX-yymm-nnnn (PMO, BMO, IMO, MR, DC). Tiap awalan punya urutan sendiri yang mulai lagi tiap bulan.
final class DocNumber
{
    /** @param class-string<Model> $model */
    public static function next(string $prefix, string $model, string $column = 'doc_no'): string
    {
        $head = $prefix.'-'.now()->format('ym').'-';
        $last = $model::query()->where($column, 'like', $head.'%')->orderByDesc($column)->value($column);

        return $head.str_pad((string) ((int) substr((string) $last, -4) + 1), 4, '0', STR_PAD_LEFT);
    }
}
