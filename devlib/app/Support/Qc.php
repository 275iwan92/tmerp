<?php

namespace App\Support;

use Devlib\Components\QcBar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Keputusan QC cepat (Approved / Need improvement) per halaman devlib.test; tabel qc_review di database devlib.
// Tiga lapis berurutan (QC -> UAT_OFFICER -> UAT_SUPERVISOR); sekarang hanya QC yang dipakai bilahnya.
final class Qc
{
    public const STATUS = ['APPROVED', 'NEEDS_IMPROVEMENT'];

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('qc_review');
        } catch (\Throwable) {
            return false;
        }
    }

    // Keputusan berlaku (baris terakhir) tiap lapis untuk satu halaman: [stage => row].
    public static function latest(string $key): array
    {
        if (! self::ready()) {
            return [];
        }

        return DB::table('qc_review as r')->where('r.item_key', $key)
            ->whereRaw('r.id_review = (select max(x.id_review) from qc_review x where x.item_key = r.item_key and x.stage = r.stage)')
            ->get()->keyBy('stage')->map(fn ($r) => (array) $r)->all();
    }

    // Data bilah untuk satu halaman: lapis QC yang bisa diputuskan akun ini + ringkasan lapis lain.
    public static function bar(string $key, string $name, ?array $user): array
    {
        $rows = self::latest($key);
        $mine = $rows['QC'] ?? null;
        $chain = [];
        foreach (QcBar::STAGES as $st => $label) {
            $chain[$st] = [$label, $rows[$st]['status'] ?? null];
        }

        return [
            'key' => $key, 'name' => $name, 'stage' => 'QC', 'status' => $mine['status'] ?? null, 'note' => $mine['note'] ?? null,
            'by' => $mine['reviewed_by'] ?? null, 'at' => $mine['reviewed_at'] ?? null,
            'action' => route('qc.review'), 'csrf' => csrf_token(),
            'canReview' => self::ready() && (bool) ($user['developer'] ?? false),
            'chain' => $chain,
            'release' => collect(QcBar::STAGES)->keys()->every(fn ($st) => ($rows[$st]['status'] ?? null) === 'APPROVED'),
        ];
    }

    public static function record(string $key, string $name, string $stage, string $status, ?string $note, string $by): array
    {
        abort_unless(array_key_exists($stage, QcBar::STAGES) && in_array($status, self::STATUS, true) && $key !== '', 422);
        $note = $note !== null ? trim($note) : null;
        abort_if($status === 'NEEDS_IMPROVEMENT' && ($note === null || $note === ''), 422);
        DB::table('qc_review')->insert(['item_key' => mb_substr($key, 0, 190), 'item_name' => mb_substr($name, 0, 190), 'stage' => $stage, 'status' => $status,
            'note' => $status === 'NEEDS_IMPROVEMENT' ? mb_substr($note, 0, 500) : null, 'reviewed_by' => $by, 'reviewed_at' => now()]);

        return ['ok' => true];
    }

    // Item yang ketiga lapisnya (QC, UAT officer, UAT supervisor) sudah Approved = siap rilis ke live.
    public static function releasable(): array
    {
        if (! self::ready()) {
            return [];
        }
        $rows = DB::table('qc_review as r')->whereRaw('r.id_review = (select max(x.id_review) from qc_review x where x.item_key = r.item_key and x.stage = r.stage)')
            ->where('r.status', 'APPROVED')->get()->groupBy('item_key');

        return $rows->filter(fn ($g) => $g->pluck('stage')->unique()->count() === count(QcBar::STAGES))
            ->map(fn ($g) => $g->first()->item_name)->all();
    }

    // Untuk agen: Need improvement yang belum ditandai selesai (keputusan berlaku saja).
    public static function open(): array
    {
        if (! self::ready()) {
            return [];
        }

        return DB::table('qc_review as r')
            ->whereRaw('r.id_review = (select max(x.id_review) from qc_review x where x.item_key = r.item_key and x.stage = r.stage)')
            ->where('r.status', 'NEEDS_IMPROVEMENT')->whereNull('r.resolved_at')->orderBy('r.reviewed_at')->get()->map(fn ($r) => (array) $r)->all();
    }
}
