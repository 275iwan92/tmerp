<?php

namespace MockupParts\Components\Sales;

use Devlib\Components\DevlibComponent;

// Ringkasan spec yang dipakai ulang apa adanya (RECALC): kotak label + nilai + "✓ reuse", terkunci.
// items: [['label', 'value', 'wide' => bool, 'flat' => ['spec' => dieline_spec, 'dims' => [...]]]]
// 'flat' = nilai dihitung TMDieline.flat() di browser (bentangan W × H mm), sama dengan hasil Dieline.
class ReuseSummary extends DevlibComponent
{
    public function __construct(
        public array $items = [],
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-mp::sales.reuse-summary';
    }

    public function source(): string
    {
        return $this->source ?? 'spec reuse (baca-saja)';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('mp-reuse') }} {!! $dl() !!}>
                @foreach ($items as $it)
                    <div @class(['is-wide' => $it['wide'] ?? false])>
                        <small>{{ $it['label'] }}</small>
                        @if (isset($it['flat']))
                            <b data-mp-flat data-spec="{{ json_encode($it['flat']['spec']) }}" data-dims="{{ json_encode((object) $it['flat']['dims']) }}">—</b>
                        @else
                            <b>{{ $it['value'] ?? '—' }}</b>
                        @endif
                        <em>✓ reuse</em>
                    </div>
                @endforeach
            </div>
            BLADE;
    }
}
