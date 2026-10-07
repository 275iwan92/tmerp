<?php

namespace Devlib\Components;

use Devlib\Locale;

// Laporan keuangan baris demi baris (Neraca, Laba Rugi, Arus Kas): judul kelompok, baris biasa, subtotal, total akhir. Objek ini tidak tahu database:
// pemakai menyerahkan baris jadi. rows: [['kind' => group|line|total|net|note, 'label' => teks, 'values' => [angka, ...]]]. 'values' satu angka per kolom di `columns`
// (bawaan satu kolom "Amount"; dua kolom atau lebih = perbandingan periode); group / note tanpa angka. Angka mengikuti bahasa tampilan (Devlib\Locale::money, IDR),
// negatif berwarna merah. Label baris = teks English (diterjemahkan objek) atau nilai data dibungkus raw().
class Statement extends DevlibComponent
{
    public const KINDS = ['group', 'line', 'total', 'net', 'note'];

    /** @param list<array{kind?: string, label: string, values?: list<float|int|null>}> $rows @param list<string> $columns */
    public function __construct(
        public array $rows = [],
        public array $columns = ['Amount'],
        public string $title = '',
        public string $subtitle = '',
        public string $currency = 'IDR',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::statement';
    }

    public function source(): string
    {
        return $this->source ?? 'baris laporan keuangan';
    }

    public function kind(array $row): string
    {
        return in_array($row['kind'] ?? 'line', self::KINDS, true) ? $row['kind'] ?? 'line' : 'line';
    }

    /** @return string angka siap tampil; null = kosong */
    public function money(float|int|null $v): string
    {
        return $v === null ? '' : Locale::money($v, $this->currency);
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-stmt') }} {!! $dl() !!}>
                @if ($title || $subtitle)<div class="dl-stmt__head">@if ($title)<b>{{ $title }}</b>@endif @if ($subtitle)<span>{{ $subtitle }}</span>@endif</div>@endif
                <table class="dl-stmt__table">
                    @if (count($columns) > 1)<thead><tr><th></th>@foreach ($columns as $c)<th class="num">{{ $c }}</th>@endforeach</tr></thead>@endif
                    <tbody>
                        @foreach ($rows as $r)
                            @php $k = $kind($r); @endphp
                            <tr class="dl-stmt__row is-{{ $k }}">
                                @if (in_array($k, ['group', 'note'], true))
                                    <td colspan="{{ count($columns) + 1 }}">{{ $r['label'] }}</td>
                                @else
                                    <td>{{ $r['label'] }}</td>
                                    @foreach ($columns as $i => $c)
                                        @php $v = $r['values'][$i] ?? null; @endphp
                                        <td class="num {{ is_numeric($v) && $v < 0 ? 'is-neg' : '' }}">{{ $money(is_numeric($v) ? (float) $v : null) }}</td>
                                    @endforeach
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            BLADE;
    }
}
