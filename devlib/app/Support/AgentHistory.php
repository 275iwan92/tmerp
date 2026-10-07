<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

// Agent History devlib.test (user 2026-10-07): catatan pengingat user (tabel dev_note, database devlib).
// Agen menyimpan ringkasan laporan di akhir kerja (`php artisan note:add`); user membaca, menyematkan, dan menambah catatan.
final class AgentHistory
{
    public const KINDS = ['REPORT' => 'Report', 'NOTE' => 'Note', 'DECISION' => 'Decision', 'ARTIFACT' => 'Artifact'];

    public const AUTHORS = ['iwan' => 'Agent Iwan', 'jo' => 'Agent Jo', 'user' => 'User'];

    public const STATE_TONES = ['Unread' => 'warn', 'Pinned' => 'accent', 'Read' => 'neutral'];

    // Tabelnya dibuat lewat migrasi; sebelum itu menu tetap tampil tanpa angka.
    public static function ready(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('dev_note');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function unread(): int
    {
        return self::ready() ? DB::table('dev_note')->whereNull('read_at')->count() : 0;
    }

    public static function find(int $id): ?object
    {
        return DB::table('dev_note')->where('id_note', $id)->first();
    }

    public static function state(object $n): string
    {
        return $n->pinned ? 'Pinned' : ($n->read_at ? 'Read' : 'Unread');
    }

    // Disematkan di atas, lalu terbaru.
    public static function rows(): array
    {
        return DB::table('dev_note')->orderByDesc('pinned')->orderByDesc('id_note')->get()->map(fn ($n) => [
            'id' => $n->id_note, 'title' => $n->title, 'author' => self::AUTHORS[$n->author] ?? $n->author,
            'kind' => self::KINDS[$n->kind] ?? $n->kind, 'created' => substr((string) $n->created_at, 0, 10), 'state' => self::state($n),
        ])->all();
    }

    // Umpan gaya blog: catatan lengkap (judul + isi) terbaru di atas, sematan di paling atas; $author menyaring per penulis.
    public static function feed(?string $author = null): array
    {
        return DB::table('dev_note')->when($author && isset(self::AUTHORS[$author]), fn ($q) => $q->where('author', $author))
            ->orderByDesc('pinned')->orderByDesc('created_at')->orderByDesc('id_note')->get()->all();
    }

    public static function add(string $author, string $kind, string $title, ?string $body, ?string $url, string $by): int
    {
        $title = trim($title);
        if (! isset(self::AUTHORS[$author]) || ! isset(self::KINDS[$kind]) || $title === '') {
            throw new \InvalidArgumentException('A note needs a known author, a known kind and a title.');
        }
        $now = now();

        return (int) DB::table('dev_note')->insertGetId([
            'author' => $author, 'kind' => $kind, 'title' => mb_substr($title, 0, 190), 'body' => $body !== null && trim($body) !== '' ? trim($body) : null,
            'url' => $url !== null && trim($url) !== '' ? mb_substr(trim($url), 0, 500) : null,
            'read_at' => $author === 'user' ? $now : null, 'created_by' => $by, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    // 'pin' membalik tanda sematan, 'read' membalik tanda sudah dibaca.
    public static function toggle(int $id, string $what): void
    {
        $n = self::find($id) ?? abort(404);
        $change = match ($what) {
            'pin' => ['pinned' => ! $n->pinned],
            'read' => ['read_at' => $n->read_at ? null : now()],
            default => abort(422),
        };
        DB::table('dev_note')->where('id_note', $id)->update($change + ['updated_at' => now()]);
    }
}
