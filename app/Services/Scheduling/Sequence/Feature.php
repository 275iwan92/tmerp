<?php

/*
 * REMARK
 * Tujuan       : Kelas dasar satu fitur urutan job (satu angka per operasi, makin besar makin layak didahulukan). Titik sambung untuk fitur baru.
 * Sumber hulu  : baris operasi Ppic::operations() + konteks (sekarang, job sebelumnya di mesin, prioritas WO, aturan tunggu).
 * Input        : array operasi, array konteks FeatureSet::context().
 * Output       : float terbatas (-3..3) agar latihan stabil tanpa normalisasi lain.
 * Dipakai oleh : FeatureSet, TraceRecorder, SequenceSuggester, SequenceTrainer.
 * Status       : Aktif. Kriteria plat / warna / plong dari Sequence Rules BELUM disetujui Jo: tambahkan sebagai turunan kelas ini, lalu daftarkan di FeatureSet::all().
 */

declare(strict_types=1);

namespace App\Services\Scheduling\Sequence;

abstract class Feature
{
    /** Stable name stored with the model (changing it retires old weights). */
    abstract public function name(): string;

    /**
     * @param  array<string, mixed>  $op   one row of Ppic::operations()
     * @param  array<string, mixed>  $ctx  FeatureSet::context()
     */
    abstract public function value(array $op, array $ctx): float;

    protected static function clamp(float $v, float $lo = -3.0, float $hi = 3.0): float
    {
        return max($lo, min($hi, $v));
    }
}
