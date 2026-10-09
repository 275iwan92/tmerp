<?php

namespace Devlib\Components;

// Papan Kanban (mockup_UIUX_kanban, kanbanboardstandard). Layar cukup deklarasi kolom + kartu.
// columns: [['key', 'label', 'tone', 'limit' => batas WIP]]
// cards:   [['id', 'col', 'title', 'desc', 'tags' => [['label', 'tone']], 'priority' => High|Medium|Low,
//            'due' => 'YYYY-MM-DD HH:MM', 'assignee' => nama, 'checklist' => [['text', 'done']], 'comments' => [['by', 'at', 'text']]]]
// members: nama anggota tim yang bisa dipasang ke kartu (popover Members). Buka kartu = klik 2x (LOCKED).
// transitions: [kolom asal => [kolom tujuan..]] (kosong = bebas). Pindah lewat drag ATAU menu "Pindah ke"
// (cadangan non-drag, wajib ada). :readonly = papan baca-saja (tanpa geser / buka / ubah kartu; kartu boleh membawa 'url' + 'urlLabel' = tombol Buka ke layar asal). :move-url = alamat POST JSON {card, from, to}: server memutuskan; ditolak = kartu kembali + pesan galat. Tiap perubahan memancarkan dl:kanban (detail: {type, card, from, to}) —
// aplikasi WAJIB memvalidasi ulang di server (DB = sumber kebenaran posisi kartu).
class Kanban extends DevlibComponent
{
    public function __construct(
        public array $columns = [],
        public array $cards = [],
        public array $transitions = [],
        public array $members = [],
        public bool $addColumn = true,
        public bool $addCard = true,
        public string $me = 'Me',
        public ?string $source = null,
        public ?string $moveUrl = null,
        public bool $readonly = false,
    ) {}

    public function tag(): string
    {
        return 'x-dl::kanban';
    }

    public function source(): string
    {
        return $this->source ?? 'kartu kanban';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-kanban') }} {!! $dl() !!} data-dl-kanban data-columns="{{ json_encode($columns) }}" data-cards="{{ json_encode($cards) }}" data-transitions="{{ json_encode((object) $transitions) }}" @if ($moveUrl) data-move-url="{{ $moveUrl }}" @endif data-add-column="{{ $addColumn ? 1 : 0 }}" data-add-card="{{ $addCard ? 1 : 0 }}" data-readonly="{{ $readonly ? 1 : 0 }}" data-me="{{ $me === 'Me' ? \Devlib\Lang::text('Me') : $me }}" data-members="{{ json_encode($members) }}">
                <div class="dl-kanban__bar">
                    <div class="dl-grid__search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" placeholder="{{ \Devlib\Lang::text('Search cards...') }}" data-k-search aria-label="{{ \Devlib\Lang::text('Search cards') }}"></div>
                    <div class="dl-segment" data-k-prio><button type="button" class="dl-segment__opt is-active" data-value="">{{ \Devlib\Lang::text('All') }}</button><button type="button" class="dl-segment__opt" data-value="High">High</button><button type="button" class="dl-segment__opt" data-value="Medium">Medium</button><button type="button" class="dl-segment__opt" data-value="Low">Low</button></div>
                </div>
                <div class="dl-kanban__board" data-k-board></div>
            </div>
            BLADE;
    }
}
