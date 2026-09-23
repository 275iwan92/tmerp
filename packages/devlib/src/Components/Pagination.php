<?php

namespace Devlib\Components;

// Kaki halaman ala ScriptCase: Go to + baris/halaman | navigasi | info total.
class Pagination extends DevlibComponent
{
    public function __construct(
        public int $total = 0,
        public int $perPage = 25,
        public int $page = 1,
        public array $sizes = [10, 25, 50, 100],
    ) {}

    public function tag(): string
    {
        return 'x-dl::pagination';
    }

    public function render()
    {
        return <<<'BLADE'
            @php($last = max(1, (int) ceil($total / max(1, $perPage))))
            @php($from = min($total, ($page - 1) * $perPage + 1))
            @php($to = min($total, $page * $perPage))
            @php($start = max(1, min($page - 2, $last - 4)))
            <div {{ $attributes->class('dl-pager') }} {!! $dl() !!} data-dl-pager>
                <div class="dl-pager__left">Go to <input type="number" min="1" max="{{ $last }}" value="{{ $page }}" class="dl-pager__goto"> View
                    @foreach ($sizes as $n)<button type="button" class="dl-pager__size {{ $n === $perPage ? 'is-active' : '' }}">{{ $n }}</button>@endforeach
                </div>
                <div class="dl-pager__nav">
                    <button type="button" @disabled($page <= 1) title="Halaman pertama">⏮</button><button type="button" @disabled($page <= 1) title="Sebelumnya">◀</button>
                    @for ($p = $start; $p <= min($last, $start + 4); $p++)<button type="button" class="{{ $p === $page ? 'is-active' : '' }}">{{ $p }}</button>@endfor
                    <button type="button" @disabled($page >= $last) title="Berikutnya">▶</button><button type="button" @disabled($page >= $last) title="Halaman terakhir">⏭</button>
                </div>
                <div class="dl-pager__info">[{{ \Devlib\Locale::number($from) }} – {{ \Devlib\Locale::number($to) }} dari {{ \Devlib\Locale::number($total) }}]</div>
            </div>
            BLADE;
    }
}
