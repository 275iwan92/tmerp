<?php

namespace Devlib\Components;

use Devlib\Lookup;

// Tabel baris item dokumen (detail dari Master-Detail: PO, PR, CO, Stock In, ...).
// columns: [['key', 'label', 'type' => text|number|money|select|lookup|date|display,
//            'required', 'width', 'options' (select), 'lookup' => nama Devlib\Lookup (fill -> kolom lain di baris),
//            'placeholder', 'readonlyUnless' => kolom bool (sel terkunci sampai kolom itu true),
//            type primary = tepat 1 baris true (tier/bahan Utama; 'labels' => [on, off]), type bool = tombol Ya/Tidak,
//            'formula' => 'qty * price' (hanya nama kolom + angka + - * / ( )), 'sum' => true, 'default']]
// totals: ['of' => kolom, 'discount' => true, 'ppn' => 11]  -> Subtotal, Diskon, DPP, PPN, Total.
// Terkirim: name[i][kolom] per baris, name_discount, name_ppn. Di dalam x-dl::master: baris = data[name].
class Lines extends DevlibComponent
{
    public function __construct(
        public string $name = 'items',
        public array $columns = [],
        public array $rows = [],
        public string $title = 'Item',
        public string $addLabel = '+ Tambah Baris',
        public int $minRows = 1, // 0 = boleh kosong (tanpa baris awal); tombol hapus mati bila jumlah baris <= minRows
        public array $totals = [],
        public ?string $totalKey = null,
        public ?string $countKey = null,
        public bool $readonly = false,
        public ?string $source = null,
    ) {}

    public function tag(): string
    {
        return 'x-dl::lines name='.$this->name;
    }

    public function source(): string
    {
        return $this->source ?? 'baris detail dokumen';
    }

    public function config(): array
    {
        $cols = array_map(function ($c) {
            $c += ['type' => 'text', 'required' => false, 'sum' => false];
            if ($c['type'] === 'lookup') {
                $c['lookup'] = Lookup::get($c['lookup']);
            }
            if (isset($c['options']) && array_is_list($c['options'])) {
                $c['options'] = array_combine($c['options'], $c['options']);
            }

            return $c;
        }, $this->columns);

        return ['name' => $this->name, 'columns' => array_values($cols), 'minRows' => $this->minRows, 'totals' => $this->totals, 'readonly' => $this->readonly];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-lines', 'is-readonly' => $readonly]) }} {!! $dl() !!} data-dl-lines data-name="{{ $name }}" data-total-key="{{ $totalKey }}" data-count-key="{{ $countKey }}" data-config="{{ json_encode($config()) }}" data-rows="{{ json_encode($rows) }}">
                <div class="dl-lines__head"><b>{{ $title }}</b><span class="dl-lines__count" data-l-count></span><span class="dl-lines__spacer"></span>
                    @unless ($readonly)<x-dl::button variant="secondary" size="sm" :label="$addLabel" data-l-add title="Tambah baris (Enter di baris terakhir)" />@endunless
                </div>
                <div class="dl-lines__wrap"><table class="dl-lines__table"><thead data-l-head></thead><tbody data-l-body></tbody><tfoot data-l-foot></tfoot></table></div>
                <div data-l-inputs hidden></div>
                @if ($totals)
                    <div class="dl-lines__totals">
                        <div><span>Subtotal</span><b data-l-t="sub"></b></div>
                        @if ($totals['discount'] ?? false)<div><span>Diskon (Rp)</span><input type="text" inputmode="numeric" name="{{ $name }}_discount" class="dl-lines__disc" data-l-disc value="0" @readonly($readonly)></div><div><span>DPP</span><b data-l-t="dpp"></b></div>@endif
                        @if (isset($totals['ppn']))<div><label class="dl-choice"><input type="checkbox" name="{{ $name }}_ppn" value="1" data-l-ppn checked @disabled($readonly)> PPN {{ $totals['ppn'] }}%</label><b data-l-t="ppn"></b></div>@endif
                        <div class="dl-lines__grand"><span>Total</span><b data-l-t="grand"></b></div>
                    </div>
                @endif
            </div>
            BLADE;
    }
}
