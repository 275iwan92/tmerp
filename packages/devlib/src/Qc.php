<?php

namespace Devlib;

use Devlib\Components\QcBar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Penyimpanan keputusan QC cepat (x-dl::qc-bar) di tabel qc_review milik aplikasi pemakai: Approved / Need improvement + catatan
// per item dan per tahap (QC -> UAT_OFFICER -> UAT_SUPERVISOR). Riwayat tidak menimpa; keputusan berlaku = baris terakhir per
// (item_key, stage). Ketiga tahap Approved = Ready to release. Koneksi database ditentukan aplikasi (null = bawaan).
class Qc
{
    public const STATUS = ['APPROVED', 'NEEDS_IMPROVEMENT'];

    public function __construct(public ?string $connection = null, public string $table = 'qc_review') {}

    private function db()
    {
        return DB::connection($this->connection);
    }

    public function ready(): bool
    {
        try {
            return Schema::connection($this->connection)->hasTable($this->table);
        } catch (\Throwable) {
            return false;
        }
    }

    private function lastPerStage(string $alias = 'r')
    {
        $t = $this->table;

        return $this->db()->table("{$t} as {$alias}")
            ->whereRaw("{$alias}.id_review = (select max(x.id_review) from {$t} x where x.item_key = {$alias}.item_key and x.stage = {$alias}.stage)");
    }

    // Keputusan berlaku tiap tahap untuk satu item: [stage => row].
    public function latest(string $key): array
    {
        if (! $this->ready()) {
            return [];
        }

        return $this->lastPerStage()->where('r.item_key', $key)->get()->keyBy('stage')->map(fn ($r) => (array) $r)->all();
    }

    // Keputusan berlaku satu tahap untuk semua item: [item_key => status]. Dipakai ikon status di menu.
    public function statusMap(string $stage = 'QC'): array
    {
        if (! $this->ready()) {
            return [];
        }

        return $this->lastPerStage()->where('r.stage', $stage)->pluck('r.status', 'r.item_key')->all();
    }

    // Prop bilah untuk satu item; $stage = tahap yang diputuskan akun ini (sekarang selalu QC).
    public function bar(string $key, string $name, string $action, string $csrf, bool $canReview, string $stage = 'QC'): array
    {
        $rows = $this->latest($key);
        $mine = $rows[$stage] ?? null;
        $chain = [];
        foreach (QcBar::STAGES as $st => $label) {
            $chain[$st] = [$label, $rows[$st]['status'] ?? null];
        }

        return [
            'key' => $key, 'name' => $name, 'stage' => $stage, 'status' => $mine['status'] ?? null, 'note' => $mine['note'] ?? null,
            'by' => $mine['reviewed_by'] ?? null, 'at' => $mine['reviewed_at'] ?? null, 'action' => $action, 'csrf' => $csrf,
            'canReview' => $canReview && $this->ready(), 'chain' => $chain,
            'release' => collect(QcBar::STAGES)->keys()->every(fn ($st) => ($rows[$st]['status'] ?? null) === 'APPROVED'),
        ];
    }

    public function record(string $key, string $name, string $stage, string $status, ?string $note, string $by): array
    {
        abort_unless(array_key_exists($stage, QcBar::STAGES) && in_array($status, self::STATUS, true) && $key !== '', 422);
        $note = $note !== null ? trim($note) : null;
        abort_if($status === 'NEEDS_IMPROVEMENT' && ($note === null || $note === ''), 422);
        $this->db()->table($this->table)->insert(['item_key' => mb_substr($key, 0, 190), 'item_name' => mb_substr($name, 0, 190), 'stage' => $stage,
            'status' => $status, 'note' => $status === 'NEEDS_IMPROVEMENT' ? mb_substr($note, 0, 500) : null, 'reviewed_by' => $by, 'reviewed_at' => now()]);

        return ['ok' => true];
    }

    // Item yang ketiga tahapnya Approved = siap rilis ke live: [item_key => item_name].
    public function releasable(): array
    {
        if (! $this->ready()) {
            return [];
        }
        $rows = $this->lastPerStage()->where('r.status', 'APPROVED')->get()->groupBy('item_key');

        return $rows->filter(fn ($g) => $g->pluck('stage')->unique()->count() === count(QcBar::STAGES))->map(fn ($g) => $g->first()->item_name)->all();
    }

    // Untuk agen: Need improvement yang belum ditandai selesai (keputusan berlaku saja).
    public function open(): array
    {
        if (! $this->ready()) {
            return [];
        }

        return $this->lastPerStage()->where('r.status', 'NEEDS_IMPROVEMENT')->whereNull('r.resolved_at')->orderBy('r.reviewed_at')->get()->map(fn ($r) => (array) $r)->all();
    }

    // Approved terbaru (keputusan berlaku).
    public function approved(int $limit = 30)
    {
        return $this->ready() ? $this->lastPerStage()->where('r.status', 'APPROVED')->orderByDesc('r.reviewed_at')->limit($limit)->get() : collect();
    }

    public function resolve(int $id, string $by): int
    {
        return $this->db()->table($this->table)->where('id_review', $id)->where('status', 'NEEDS_IMPROVEMENT')->whereNull('resolved_at')
            ->update(['resolved_by' => $by, 'resolved_at' => now()]);
    }
}
