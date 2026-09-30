<?php

namespace Devlib\Components;

use Devlib\Lookup;

// Tabel baris item dokumen (detail dari Master-Detail: PO, PR, CO, Stock In, ...).
// columns: [['key', 'label', 'type' => text|number|money|select|lookup|date|display,
//            'required', 'width', 'options' (select), 'lookup' => nama Devlib\Lookup (fill -> kolom lain di baris),
//            'placeholder', 'readonlyUnless' => kolom bool (sel terkunci sampai kolom itu true),
//            type primary = tepat 1 baris true (tier/bahan Utama; 'labels' => [on, off]), type bool = tombol Ya/Tidak,
//            'formula' => 'qty * price' (hanya nama kolom + angka + - * / ( )), 'sum' => true, 'default']]
// totals: ['of' => kolom, 'discount' => true, 'ppn' => 11, 'costs' => ['freight' => 'Ongkos Kirim', ...]]
//   -> footer: tab Discount (Rp/%) / Tax (PPN + tarif) / Other Costs (+ Notes bila slot note) di kiri, Summary selalu di kanan.
// 'more' => true = kolom tidak di tabel, diisi lewat panel › per baris (detail berkolom banyak tetap ringkas).
// Kolom pertama + kolom rumus terakhir diam saat tabel digeser ke samping. Slot note = kiri footer (total di kanan).
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
                @if ($totals || isset($note))
                    @php
                        $costs = $totals['costs'] ?? [];
                        $adj = array_filter(['disc' => ($totals['discount'] ?? false) ? 'Discount' : null, 'tax' => isset($totals['ppn']) ? 'Tax' : null, 'cost' => $costs ? 'Other Costs' : null, 'note' => isset($note) ? 'Notes' : null]);
                    @endphp
                    {{-- Footer dokumen (user 2026-09-30): kiri = tab penyesuaian Discount / Tax / Other Costs (+ Notes);
                         kanan = Summary yang SELALU terlihat (Details, Diskon, DPP, PPN, biaya lain, Grand Total). --}}
                    <div class="dl-lines__footer">
                        @if ($adj)
                            <div class="dl-lines__adj dl-tabs" data-dl-tabs>
                                <div class="dl-tabs__bar" role="tablist">@foreach ($adj as $k => $t)<button type="button" class="dl-tabs__tab {{ $loop->first ? 'is-active' : '' }}" data-tab="{{ $k }}">{{ $t }}</button>@endforeach</div>
                                @foreach ($adj as $k => $t)
                                    <div class="dl-tabs__panel {{ $loop->first ? 'is-active' : '' }}" data-panel="{{ $k }}">
                                        @if ($k === 'disc')
                                            <div class="dl-lines__adjrow"><label>Diskon dokumen</label>
                                                <select name="{{ $name }}_disc_mode" class="dl-lines__adjin" data-l-dmode @disabled($readonly)><option value="RP">Rp</option><option value="PCT">%</option></select>
                                                <input type="text" inputmode="decimal" name="{{ $name }}_discount" class="dl-lines__disc" data-l-disc value="0" @readonly($readonly)></div>
                                            <p class="dl-lines__hint">Dipotong dari jumlah Details sebelum PPN.</p>
                                        @elseif ($k === 'tax')
                                            <div class="dl-lines__adjrow"><label class="dl-choice"><input type="checkbox" name="{{ $name }}_ppn" value="1" data-l-ppn checked @disabled($readonly)> PPN</label>
                                                <select name="{{ $name }}_ppn_rate" class="dl-lines__adjin" data-l-rate @disabled($readonly)>@foreach (array_unique([(float) $totals['ppn'], 11.0, 12.0]) as $r)<option value="{{ $r }}">{{ $r + 0 }}%</option>@endforeach</select></div>
                                            <p class="dl-lines__hint">Dihitung dari DPP (Details dikurangi diskon).</p>
                                        @elseif ($k === 'cost')
                                            <div class="dl-lines__costs">@foreach ($costs as $ck => $cl)<label><span>{{ $cl }}</span><input type="text" inputmode="numeric" name="{{ $name }}_cost_{{ $ck }}" class="dl-lines__disc" data-l-cost value="0" @readonly($readonly)></label>@endforeach</div>
                                            <p class="dl-lines__hint">Ditambahkan setelah PPN (tidak kena PPN).</p>
                                        @else
                                            {{ $note }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        @if ($totals)<div class="dl-lines__totals">
                            <div class="dl-lines__sumh">Summary</div>
                            <div><span>Details</span><b data-l-t="sub"></b></div>
                            @if ($totals['discount'] ?? false)<div><span>Diskon</span><b data-l-t="disc"></b></div><div><span>DPP</span><b data-l-t="dpp"></b></div>@endif
                            @if (isset($totals['ppn']))<div><span data-l-ppnlbl>PPN {{ $totals['ppn'] }}%</span><b data-l-t="ppn"></b></div>@endif
                            @if ($costs)<div><span>Other Costs</span><b data-l-t="cost"></b></div>@endif
                            <div class="dl-lines__grand"><span>Grand Total</span><b data-l-t="grand"></b></div>
                        </div>@endif
                    </div>
                @endif
            </div>
            BLADE;
    }
}
