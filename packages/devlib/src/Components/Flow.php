<?php

namespace Devlib\Components;

// Diagram alur proses berupa blok bersambung (gaya pipeline approval), untuk menjelaskan alur antar modul/dokumen.
// steps: [['key','label','icon','module','url','status' => done|review|todo|locked,'note','branch' => [langkah cabang]]]
// Blok diklik -> buka url (atau event dl:flow {key} bila tanpa url). Legenda status tampil di bawah.
class Flow extends DevlibComponent
{
    public const STATUS = ['done' => ['Done', 'success'], 'review' => ['Review', 'info'], 'todo' => ['To Do', 'neutral'], 'locked' => ['Locked', 'warn']];

    public function __construct(
        public array $steps = [],
        public string $title = '',
        public bool $vertical = false,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::flow';
    }

    public function source(): string
    {
        return $this->source ?? '—';
    }

    public function statuses(): array
    {
        return self::STATUS;
    }

    public function status(?string $s): array
    {
        return self::STATUS[$s ?? 'todo'] ?? self::STATUS['todo'];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-flow', 'is-vertical' => $vertical]) }} {!! $dl() !!} data-dl-flow>
                @if ($title !== '')<div class="dl-flow__title">{{ $title }}</div>@endif
                <ol class="dl-flow__track">
                    @foreach ($steps as $i => $s)
                        <li class="dl-flow__item">
                            <a class="dl-flow__step is-{{ $s['status'] ?? 'todo' }}" @if (! empty($s['url'])) href="{{ $s['url'] }}" @else href="#" data-flow-key="{{ $s['key'] ?? $i }}" @endif @if (! empty($s['note'])) title="{{ $s['note'] }}" @endif>
                                <span class="dl-flow__no">{{ $i + 1 }}</span>
                                <span class="dl-flow__ico"><i class="fa-solid {{ \Devlib\Icon::fa($s['icon'] ?? 'file') }}"></i></span>
                                <span class="dl-flow__txt"><b>{{ $s['label'] }}</b>@if (! empty($s['module']))<small>{{ $s['module'] }}</small>@endif</span>
                                <x-dl::badge :label="$status($s['status'] ?? null)[0]" :tone="$status($s['status'] ?? null)[1]" />
                            </a>
                            @if (! empty($s['branch']))
                                <ul class="dl-flow__branch">
                                    @foreach ($s['branch'] as $b)
                                        <li><a class="dl-flow__sub is-{{ $b['status'] ?? 'todo' }}" @if (! empty($b['url'])) href="{{ $b['url'] }}" @else href="#" @endif><i class="fa-solid {{ \Devlib\Icon::fa($b['icon'] ?? 'file') }}"></i> {{ $b['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                </ol>
                <div class="dl-flow__legend">@foreach ($statuses() as $k => [$l, $t])<x-dl::badge :label="$l" :tone="$t" />@endforeach</div>
            </div>
            BLADE;
    }
}
