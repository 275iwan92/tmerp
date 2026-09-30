<?php

namespace Devlib\Components;

// Matriks pilih pemenang: baris = kebutuhan (mis. baris PR), kolom = penawar (mis. supplier), tiap sel = satu radio.
// Satu pilihan per baris (atau "Lewati"); default = sel 'default' baris itu (mis. termurah). Ringkasan di bawah menghitung
// pilihan per kolom ("2 PO: A (2 baris), B (1 baris)") dan ikut berubah saat officer mengganti pilihan.
//   columns: [colKey => ['label' => ..., 'sub' => ...]]
//   rows   : [['key', 'label', 'sub', 'locked' => teks bila baris tidak bisa dipilih lagi, 'default' => colKey,
//              'cells' => [colKey => ['value' => nilai terkirim, 'text', 'sub', 'disabled' => alasan|null]]]]
// Terkirim: {name}[rowKey] = value sel terpilih (Lewati = kosong).
class PickMatrix extends DevlibComponent
{
    public function __construct(
        public string $name = 'pick',
        public array $columns = [],
        public array $rows = [],
        public string $empty = 'Belum ada penawaran untuk dibandingkan.',
        public string $unit = 'PO',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::pick-matrix';
    }

    public function source(): string
    {
        return $this->source ?? 'baris x penawar';
    }

    // Sel diurutkan ikut kolom (kolom tanpa penawaran = null) -- tanpa @php di dalam loop Blade.
    public function matrix(): array
    {
        return array_map(fn ($r) => $r + ['grid' => array_map(fn ($ck) => $r['cells'][$ck] ?? null, array_combine(array_keys($this->columns), array_keys($this->columns)))], $this->rows);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-pick') }} {!! $dl() !!} data-dl-pick data-unit="{{ $unit }}" data-cols="{{ json_encode(array_map(fn ($c) => $c['label'] ?? '', $columns)) }}">
                @if (! $rows || ! $columns)
                    <div class="dl-pick__empty">{{ $empty }}</div>
                @else
                    <div class="dl-table-wrap"><table class="dl-table dl-pick__table">
                        <thead><tr><th>Kebutuhan</th>@foreach ($columns as $c)<th class="num">{{ $c['label'] ?? '' }}@if (! empty($c['sub']))<small>{{ $c['sub'] }}</small>@endif</th>@endforeach<th class="num">Lewati</th></tr></thead>
                        <tbody>
                            @foreach ($matrix() as $r)
                                <tr @class(['is-locked' => ! empty($r['locked'])])>
                                    <td><b>{{ $r['label'] }}</b>@if (! empty($r['sub']))<small>{{ $r['sub'] }}</small>@endif @if (! empty($r['locked']))<small class="dl-pick__lock">{{ $r['locked'] }}</small>@endif</td>
                                    @foreach ($r['grid'] as $ck => $cell)
                                        <td class="num @if ($cell && ($r['default'] ?? null) === $ck) is-best @endif">
                                            @if ($cell)
                                                <label class="dl-pick__cell" @if (! empty($cell['disabled'])) title="{{ $cell['disabled'] }}" @endif>
                                                    <input type="radio" name="{{ $name }}[{{ $r['key'] }}]" value="{{ $cell['value'] }}" data-col="{{ $ck }}" @checked(empty($r['locked']) && empty($cell['disabled']) && ($r['default'] ?? null) === $ck) @disabled(! empty($r['locked']) || ! empty($cell['disabled']))>
                                                    <span>{{ $cell['text'] }}</span>
                                                </label>
                                                @if (! empty($cell['sub']))<small>{{ $cell['sub'] }}</small>@endif
                                                @if (! empty($cell['disabled']))<small class="dl-pick__warn">{{ $cell['disabled'] }}</small>@endif
                                            @else
                                                <span class="dl-pick__none">—</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="num"><input type="radio" name="{{ $name }}[{{ $r['key'] }}]" value="" data-col="" @checked(! empty($r['locked']) || ! isset($r['cells'][$r['default'] ?? ''])) @disabled(! empty($r['locked']))></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                    <div class="dl-pick__sum" data-dl-pick-sum></div>
                @endif
            </div>
            BLADE;
    }
}
