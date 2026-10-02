<?php

namespace Devlib\Components;

// Grid Pivot: data datar -> tabel silang yang bisa diatur user di layar.
// fields: [kolom => label] dimensi yang boleh jadi baris/kolom; measures: [kolom => label] nilai; format: number|money.
// rowsBy: [kolom, kolom?] (maks 2 tingkat, tingkat 1 dapat subtotal), colBy: kolom|null, value, agg: sum|count|avg|min|max.
// Klik sel -> daftar baris sumber (event dl:pivot-cell). Export CSV = tabel yang tampil.
class Pivot extends DevlibComponent
{
    public function __construct(
        public array $rows = [],
        public array $fields = [],
        public array $measures = [],
        public array $rowsBy = [],
        public ?string $colBy = null,
        public ?string $value = null,
        public string $agg = 'sum',
        public array $formats = [],
        public string $title = '',
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::pivot';
    }

    public function source(): string
    {
        return $this->source ?? 'data transaksi (datar)';
    }

    public function config(): array
    {
        return ['fields' => (object) $this->fields, 'measures' => (object) $this->measures, 'rowsBy' => $this->rowsBy ?: [array_key_first($this->fields)],
            'colBy' => $this->colBy, 'value' => $this->value ?? array_key_first($this->measures), 'agg' => $this->agg, 'formats' => (object) $this->formats];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class('dl-pivot') }} {!! $dl() !!} data-dl-pivot data-config="{{ json_encode($config()) }}" data-rows="{{ json_encode($rows) }}">
                <div class="dl-pivot__bar">
                    @if ($title)<b class="dl-pivot__title">{{ $title }}</b>@endif
                    <label><span>{{ \Devlib\Lang::text('Row') }}</span><select data-p-row="0"></select></label>
                    <label><span>{{ \Devlib\Lang::text('Sub-row') }}</span><select data-p-row="1"><option value="">—</option></select></label>
                    <label><span>{{ \Devlib\Lang::text('Column') }}</span><select data-p-col><option value="">—</option></select></label>
                    <label><span>{{ \Devlib\Lang::text('Value') }}</span><select data-p-val></select></label>
                    <div class="dl-segment" data-p-agg><button type="button" class="dl-segment__opt" data-value="sum">{{ \Devlib\Lang::text('Sum') }}</button><button type="button" class="dl-segment__opt" data-value="count">{{ \Devlib\Lang::text('Row count') }}</button><button type="button" class="dl-segment__opt" data-value="avg">{{ \Devlib\Lang::text('Avg') }}</button><button type="button" class="dl-segment__opt" data-value="min">Min</button><button type="button" class="dl-segment__opt" data-value="max">{{ \Devlib\Lang::text('Max') }}</button></div>
                    <label class="dl-choice"><input type="checkbox" data-p-pct> % total</label>
                    <label class="dl-choice"><input type="checkbox" data-p-heat checked> Heatmap</label>
                    <x-dl::button variant="secondary" size="sm" icon="export" label="Export" data-p-export />
                </div>
                <div class="dl-pivot__wrap"><table class="dl-pivot__table" data-p-table></table></div>
            </div>
            BLADE;
    }
}
