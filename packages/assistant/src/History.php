<?php

namespace Assistant;

use Assistant\Contracts\Asker;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

// Riwayat percakapan: satu baris = satu pertanyaan + jawabannya, milik satu user (percakapan pribadi dengan asisten).
// Juga log untuk admin: pertanyaan yang tidak terjawab atau dijempol turun = bahan menambah Help / frasa.
class History
{
    private function table(): Builder
    {
        return DB::connection(config('assistant.connection'))->table(config('assistant.tables.message'));
    }

    public function store(Asker $asker, string $question, Reply $reply, int $ms, bool $modelUsed): int
    {
        return (int) $this->table()->insertGetId(['user_id' => $asker->id(), 'user_name' => $asker->name(), 'user_level' => $asker->level(),
            'question' => mb_substr($question, 0, 500), 'reply_kind' => $reply->kind, 'reply_key' => $reply->key,
            'reply' => json_encode($reply->toArray(), JSON_UNESCAPED_UNICODE), 'answered' => $reply->answered(), 'model_used' => $modelUsed,
            'duration_ms' => $ms, 'created_at' => now()]);
    }

    /** @return array<int, array{id: int, q: string, reply: array<string, mixed>, feedback: ?int, ts: int}> terlama dulu */
    public function recent(Asker $asker, int $limit): array
    {
        return $this->table()->where('user_id', $asker->id())->orderByDesc('id_assistant_message')->limit($limit)->get()->reverse()->values()
            ->map(fn ($m) => ['id' => (int) $m->id_assistant_message, 'q' => $m->question, 'reply' => json_decode($m->reply, true) ?: [],
                'feedback' => $m->feedback === null ? null : (int) $m->feedback, 'ts' => strtotime((string) $m->created_at)])->all();
    }

    // Jempol naik (1) / turun (-1) / dicabut (0) pada jawaban milik si penanya sendiri.
    public function feedback(Asker $asker, int $id, int $value): bool
    {
        return (bool) $this->table()->where('id_assistant_message', $id)->where('user_id', $asker->id())->update(['feedback' => $value ?: null]);
    }

    // Buang riwayat yang lewat masa simpan menurut level penanya saat bertanya. Kembalian = jumlah baris terhapus.
    public function prune(): int
    {
        $top = (int) Assistant::setting('level_top_min');
        $middle = (int) Assistant::setting('level_middle_min');
        $n = 0;
        foreach ([
            [(int) Assistant::setting('history_days_top'), fn (Builder $q) => $q->where('user_level', '>=', $top)],
            [(int) Assistant::setting('history_days_middle'), fn (Builder $q) => $q->where('user_level', '>=', $middle)->where('user_level', '<', $top)],
            [(int) Assistant::setting('history_days_low'), fn (Builder $q) => $q->where('user_level', '<', $middle)],
        ] as [$days, $scope]) {
            if ($days > 0) {
                $n += $scope($this->table())->where('created_at', '<', now()->subDays($days))->delete();
            }
        }

        return $n;
    }
}
