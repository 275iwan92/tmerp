<?php

namespace Devlib\Components;

// Daftar ENGINE (mesin hitung / aturan bisnis) sebuah aplikasi: untuk tiap engine tampil kegunaan, cara memanggil
// (tanda tangan asli dari kode lewat Devlib\Engine\EngineInspector), parameter input, output, cara kerja langkah demi
// langkah, tabel yang dibaca / ditulis, setelan, layar pemakai, test dan berkas sumbernya. Kotak cari + saringan grup.
// engines: [[
//   'key', 'name', 'group', 'mode' => suggest|auto|calc|flow, 'purpose',
//   'class' => ?string, 'file', 'lines', 'entries' => [['method','static','params' => [['name','type','default']],'returns','note'?]],
//   'inputs' => [[nama, arti]], 'outputs' => [[nama, arti]], 'steps' => [teks], 'reads' => [..], 'writes' => [..],
//   'settings' => [..], 'used' => [..], 'tests' => [..], 'docs' => [..], 'restricted' => ?teks,
// ]]
// Isi (nama engine, penjelasan) adalah DATA milik aplikasi: tidak diterjemahkan objek ini, hanya judul bagiannya.
class EngineCatalog extends DevlibComponent
{
    public const MODES = [
        'suggest' => ['Suggests, officer confirms', 'info'],
        'auto' => ['Runs automatically', 'success'],
        'calc' => ['Calculation', 'accent'],
        'flow' => ['Document flow', 'violet'],
    ];

    public function __construct(
        public array $engines = [],
        public bool $open = false,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::engine-catalog';
    }

    public function source(): string
    {
        return $this->source ?? 'daftar engine';
    }

    /** @return array<string, list<array>> grup => engine, urutan kemunculan */
    public function groups(): array
    {
        $out = [];
        foreach ($this->engines as $e) {
            $out[$e['group'] ?? 'Other'][] = $e;
        }

        return $out;
    }

    public function mode(array $e): array
    {
        return self::MODES[$e['mode'] ?? ''] ?? [$e['mode'] ?? '', 'neutral'];
    }

    // run(float $planoW, float $gap = 3): array
    public function signature(array $entry): string
    {
        // literal = teks pemanggilan ditulis apa adanya (engine yang bukan kelas PHP, mis. JavaScript).
        if ($entry['literal'] ?? false) {
            return $entry['method'];
        }
        $params = array_map(fn ($p) => trim(($p['type'] !== 'mixed' ? $p['type'].' ' : '').'$'.$p['name'].($p['default'] !== null ? ' = '.$p['default'] : '')), $entry['params'] ?? []);

        return ($entry['static'] ?? false ? 'static ' : '').$entry['method'].'('.implode(', ', $params).')'.(($entry['returns'] ?? 'mixed') !== 'mixed' ? ': '.$entry['returns'] : '');
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-eng') }} {!! $dl() !!} data-dl-engine-catalog>
                <div class="dl-eng__bar">
                    <input type="search" class="dl-input dl-eng__q" data-eng-q placeholder="{{ \Devlib\Lang::text('Search engine, parameter, table...') }}" aria-label="{{ \Devlib\Lang::text('Search engine, parameter, table...') }}">
                    <span class="dl-eng__count" data-eng-count>{{ count($engines) }}</span>
                    <span class="dl-eng__chips" role="group">
                        <button type="button" class="dl-eng__chip is-active" data-eng-group="">{{ \Devlib\Lang::text('All') }}</button>
                        @foreach ($groups() as $g => $list)
                            <button type="button" class="dl-eng__chip" data-eng-group="{{ $g }}">{{ $g }} <em>{{ count($list) }}</em></button>
                        @endforeach
                    </span>
                    <x-dl::button variant="secondary" size="sm" icon="fa-up-right-and-down-left-from-center" label="Expand all" data-eng-all />
                </div>
                @foreach ($groups() as $g => $list)
                    <section class="dl-eng__group" data-eng-section="{{ $g }}">
                        <h3 class="dl-eng__gt">{{ $g }}</h3>
                        @foreach ($list as $e)
                            <details class="dl-eng__item" id="engine-{{ $e['key'] }}" data-eng-item data-group="{{ $g }}" @if ($open) open @endif>
                                <summary class="dl-eng__head">
                                    <span class="dl-eng__name"><b>{{ $e['name'] }}</b><small>{{ $e['purpose'] ?? '' }}</small></span>
                                    <x-dl::badge :label="$mode($e)[0]" :tone="$mode($e)[1]" />
                                    <i class="fa-solid fa-chevron-down dl-eng__chev"></i>
                                </summary>
                                <div class="dl-eng__body">
                                    @if (! empty($e['restricted']))<x-dl::alert tone="warn" :message="\Devlib\Lang::raw($e['restricted'])" />@endif
                                    @if (! empty($e['entries']))
                                        <div class="dl-eng__sec">
                                            <h4>{{ \Devlib\Lang::text('How to call') }}</h4>
                                            @foreach ($e['entries'] as $en)
                                                <div class="dl-eng__call"><code>{{ $signature($en) }}</code>@if (! empty($en['note']))<small>{{ $en['note'] }}</small>@endif</div>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="dl-eng__io">
                                        <div class="dl-eng__sec">
                                            <h4>{{ \Devlib\Lang::text('Input') }}</h4>
                                            <x-dl::table :columns="['name' => 'Parameter', 'note' => 'Meaning']" :striped="false" empty="No input."
                                                :rows="array_map(fn ($r) => ['name' => $r[0], 'note' => $r[1]], $e['inputs'] ?? [])" />
                                        </div>
                                        <div class="dl-eng__sec">
                                            <h4>{{ \Devlib\Lang::text('Output') }}</h4>
                                            <x-dl::table :columns="['name' => 'Result', 'note' => 'Meaning']" :striped="false" empty="No output."
                                                :rows="array_map(fn ($r) => ['name' => $r[0], 'note' => $r[1]], $e['outputs'] ?? [])" />
                                        </div>
                                    </div>
                                    @if (! empty($e['steps']))
                                        <div class="dl-eng__sec">
                                            <h4>{{ \Devlib\Lang::text('How it works') }}</h4>
                                            <ol class="dl-eng__steps">@foreach ($e['steps'] as $s)<li>{{ $s }}</li>@endforeach</ol>
                                        </div>
                                    @endif
                                    <dl class="dl-eng__meta">
                                        @foreach (['reads' => 'Reads', 'writes' => 'Writes', 'settings' => 'Settings', 'used' => 'Used by', 'tests' => 'Tests', 'docs' => 'Documents'] as $k => $label)
                                            @if (! empty($e[$k]))
                                                <dt>{{ \Devlib\Lang::text($label) }}</dt>
                                                <dd>@foreach ($e[$k] as $v)<span>{{ $v }}</span>@endforeach</dd>
                                            @endif
                                        @endforeach
                                        <dt>{{ \Devlib\Lang::text('Source') }}</dt>
                                        <dd><span><code>{{ $e['class'] ?? $e['file'] ?? '' }}</code></span>@if (! empty($e['class']) && ! empty($e['file']))<span>{{ $e['file'] }}</span>@endif @if (! empty($e['lines']))<span>{{ \Devlib\Locale::number($e['lines'], 0) }} {{ \Devlib\Lang::text('lines') }}</span>@endif</dd>
                                    </dl>
                                </div>
                            </details>
                        @endforeach
                    </section>
                @endforeach
                <p class="dl-eng__none" data-eng-none hidden>{{ \Devlib\Lang::text('No engine matches.') }}</p>
            </div>
            BLADE;
    }
}
