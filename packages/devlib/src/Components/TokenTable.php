<?php

namespace Devlib\Components;

// Tabel dokumentasi token Basic (DevLib): nama token, nilai terkini, contoh tampilan.
// kind: color | font | space | radius | shadow | text
class TokenTable extends DevlibComponent
{
    public function __construct(public array $tokens = [], public string $kind = 'color') {}

    public function tag(): string
    {
        return 'x-dl::token-table kind='.$this->kind;
    }

    public function source(): string
    {
        return 'packages/devlib/resources/assets/devlib.css (:root) + active theme';
    }

    public function render()
    {
        return <<<'BLADE'
            <table {{ $attributes->class(['dl-tokens', 'dl-tokens--'.$kind]) }} {!! $dl() !!}>
                <thead><tr><th>Token</th><th>{{ \Devlib\Lang::text('Current value') }}</th><th>{{ \Devlib\Lang::text('Example') }}</th><th>{{ \Devlib\Lang::text('Used for') }}</th></tr></thead>
                <tbody>
                    @foreach ($tokens as $name => $use)
                        <tr data-token="{{ $name }}">
                            <td><code>{{ $name }}</code></td>
                            <td class="dl-tokens__val" data-dl-token-value="{{ $name }}">…</td>
                            <td>
                                @switch($kind)
                                    @case('color') <span class="dl-tokens__swatch" style="background:var({{ $name }})"></span> @break
                                    @case('font') <span style="font-size:calc(var({{ $name }}) * var(--dl-font-scale))">Telasih Makmur 123</span> @break
                                    @case('radius') <span class="dl-tokens__box" style="border-radius:var({{ $name }})"></span> @break
                                    @case('shadow') <span class="dl-tokens__box dl-tokens__box--shadow" style="box-shadow:var({{ $name }})"></span> @break
                                    @case('space') <span class="dl-tokens__bar" style="width:var({{ $name }})"></span> @break
                                    @default <span>—</span>
                                @endswitch
                            </td>
                            <td class="dl-tokens__use">{{ $use }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            BLADE;
    }
}
