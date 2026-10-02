<?php

namespace App\Assistant\Metrics;

use App\Assistant\ErpAsker;
use App\Support\V2\Board\Board;
use App\Support\V2\Board\WidgetCatalog;
use Assistant\Assistant;
use Assistant\Contracts\Asker;
use Assistant\Contracts\Command;
use Assistant\Reply;

// Perintah papan widget lewat asisten (user 2026-10-02: "dengan bantuan asisten AI seseorang bisa meminta dipasangkan widget-nya").
// Asisten tidak menghitung apa pun: ia hanya mencocokkan nama widget dari katalog (Setup > Opening Board Widgets) lalu
// memanggil aturan papan yang sama dengan tombol Customize (Board::apply) -- hak pasang tetap dari security user.
//   "pasang widget top customers" / "add widget receivables aging"     -> pasang
//   "lepas widget kata harian" / "remove widget quote of the day"      -> lepas
//   "widget apa saja" / "list widgets"                                 -> daftar yang boleh dipasang
//   "reset widget" / "kembalikan papan widget ke template"             -> Reset to Template
final class BoardWidgets implements Command
{
    private const REMOVE = ['lepas', 'lepaskan', 'hapus', 'cabut', 'buang', 'copot', 'remove', 'delete', 'uninstall', 'hide', 'sembunyikan'];

    private const RESET = ['reset', 'template', 'bawaan', 'default'];

    private const LIST = ['apa', 'saja', 'daftar', 'list', 'available', 'tersedia', 'pilihan', 'which'];

    public function key(): string
    {
        return 'board_widgets';
    }

    public function title(): string
    {
        return 'Opening board widgets';
    }

    public function phrases(): array
    {
        return ['widget', 'widgets', 'papan widget', 'opening board'];
    }

    public function module(): ?string
    {
        return null;
    }

    public function minLevel(): int
    {
        return 0;
    }

    public function suggest(): bool
    {
        return false;
    }

    public function answer(Asker $asker, string $question): Reply
    {
        $title = Assistant::text('Opening board');
        if (! $asker instanceof ErpAsker || ! Board::profile($asker->user)) {
            return new Reply(Reply::DENIED, $title, Assistant::text('The widget board is open for top management first. Other positions still use the opening screen template.'));
        }
        $user = $asker->user;
        $catalog = app(WidgetCatalog::class);
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question)) ?: [];
        $has = fn (array $list) => (bool) array_intersect($words, $list);
        $name = fn (string $key) => Assistant::text($catalog->all()[$key]['row']->title);
        $on = Board::layout($user)->pluck('widget_key')->all();

        if ($has(self::RESET)) {
            Board::apply($user, 'reset', null);

            return $this->done($title, Assistant::text('Your board is back to the template of your position.'));
        }

        $key = $catalog->match($user, $question);
        if (! $key) {
            $rows = collect($catalog->for($user))->map(fn ($entry, $k) => [Assistant::text($entry['row']->title), Assistant::text(in_array($k, $on, true) ? 'On your board' : 'Not installed')])->values()->all();

            return new Reply(Reply::HELP, $title, $has(self::LIST) ? Assistant::text('Widgets you may install:')
                : Assistant::text('Which widget? Say for example: add widget Top Customers. Widgets you may install:'), rows: $rows);
        }

        $action = $has(self::REMOVE) ? 'remove' : 'add';
        if ($error = Board::apply($user, $action, $key)) {
            return new Reply(Reply::HELP, $name($key), Assistant::text($error));
        }

        return $this->done($name($key), Assistant::text($action === 'add' ? 'Installed on your board. Use Customize on the board to move or resize it.' : 'Removed from your board.'));
    }

    // refresh = 'board': layar memuat ulang papan tanpa memuat ulang halaman.
    private function done(string $title, string $text): Reply
    {
        $reply = new Reply(Reply::HELP, $title, $text);
        $reply->refresh = 'board';

        return $reply;
    }
}
