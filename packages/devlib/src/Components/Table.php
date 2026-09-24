<?php

namespace Devlib\Components;

// Tabel sederhana (di dalam form/kartu). columns: key => label, atau key => [label, num=>true]. Angka rata kanan.
class Table extends DevlibComponent
{
    public function __construct(
        public array $columns = [],
        public array $rows = [],
        public string $empty = 'Belum ada data.',
        public bool $striped = true,
        public array $totals = [],
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::table';
    }

    public function source(): string
    {
        return $this->source ?? 'baris tabel';
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-table-wrap') }} {!! $dl() !!}>
                <table class="dl-table {{ $striped ? 'is-striped' : '' }}">
                    <thead><tr>@foreach ($columns as $k => $c)<th class="{{ is_array($c) && ($c['num'] ?? false) ? 'num' : '' }}">{{ is_array($c) ? $c[0] : $c }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>@foreach ($columns as $k => $c)<td class="{{ is_array($c) && ($c['num'] ?? false) ? 'num' : '' }}">{{ is_array($c) && ($c['money'] ?? false) ? \Devlib\Locale::money($r[$k] ?? 0) : (is_array($c) && ($c['num'] ?? false) ? \Devlib\Locale::number($r[$k] ?? 0) : ($r[$k] ?? '')) }}</td>@endforeach</tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}" class="dl-table__empty">{{ $empty }}</td></tr>
                        @endforelse
                    </tbody>
                    @if ($totals)
                        <tfoot><tr>@foreach ($columns as $k => $c)<td class="{{ is_array($c) && ($c['num'] ?? false) ? 'num' : '' }}">{{ array_key_exists($k, $totals) ? (is_array($c) && ($c['money'] ?? false) ? \Devlib\Locale::money($totals[$k]) : $totals[$k]) : '' }}</td>@endforeach</tr></tfoot>
                    @endif
                </table>
            </div>
            BLADE;
    }
}
