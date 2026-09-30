<?php

namespace Devlib\Components;

use Devlib\Lookup;

// Tabel baris item dokumen (detail dari Master-Detail: PO, PR, CO, Stock In, ...).
// columns: [['key', 'label', 'type' => text|number|money|select|lookup|date|display,
//            'required', 'width', 'options' (select), 'lookup' => nama Devlib\Lookup (fill -> kolom lain di baris),
//            'placeholder', 'readonlyUnless' => kolom bool (sel terkunci sampai kolom itu true),
//            type primary = tepat 1 baris true (tier/bahan Utama; 'labels' => [on, off]), type bool = tombol Ya/Tidak,
//            'formula' => 'qty * price' (hanya nama kolom + angka + - * / ( )), 'sum' => true, 'default']]
// totals: ['of' => kolom, 'discount' => true|[...], 'ppn' => 11 | 'tax' => [...], 'costs' => ['freight' => 'Ongkos Kirim', ...]]
//   -> footer: tab Discount (Normal/Qty/Amount) / Tax (PPN, PPh, Bea Cukai) -- kolom % & Amount saling mengisi + Total --
//      / Other Costs (+ Notes bila slot note) di kiri; Summary selalu di kanan.
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

    // Baris diskon: [kode => [label, % awal, 1]]. 'discount' => true = 3 jenis baku; boleh array sendiri.
    public function discRows(): array
    {
        $d = $this->totals['discount'] ?? false;
        if (! $d) {
            return [];
        }

        return is_array($d) ? $d : ['normal' => ['Normal Discount', 0, 1], 'qty' => ['Qty Discount', 0, 1], 'amount' => ['Amount Discount', 0, 1]];
    }

    // Biaya lain (acuan SAP delivery costs, D365 charge codes, Oracle/Odoo landed costs, Zahir/Accurate "Biaya Lain-lain"):
    // 'costs' => [kode => label] atau [kode => [label, % awal, 1]].
    public function costRows(): array
    {
        return array_map(fn ($c) => is_array($c) ? $c : [$c, 0, 1], $this->totals['costs'] ?? []);
    }

    // Baris pajak: [kode => [label, % awal, +1 ditambah | -1 dipotong]]. 'tax' => array sendiri, atau 'ppn' => 11 = PPN + PPh + Bea Cukai.
    public function taxRows(): array
    {
        if (isset($this->totals['tax'])) {
            return $this->totals['tax'];
        }

        return isset($this->totals['ppn']) ? ['ppn' => ['PPN', $this->totals['ppn'], 1], 'pph' => ['PPh 23', 0, -1], 'customs' => ['Bea Cukai', 0, 1]] : [];
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
                        $costs = $costRows();
                        $discs = $discRows();
                        $taxes = $taxRows();
                        $adj = array_filter(['disc' => $discs ? 'Discount' : null, 'tax' => $taxes ? 'Tax' : null, 'cost' => $costs ? 'Other Costs' : null, 'note' => isset($note) ? 'Notes' : null]);
                    @endphp
                    {{-- Footer dokumen (user 2026-09-30): kiri = tab Discount / Tax / Other Costs; tiap baris diskon & pajak punya
                         kolom % dan Amount yang saling mengisi + baris Total. Kanan = Summary yang SELALU terlihat. --}}
                    <div class="dl-lines__footer">
                        @if ($adj)
                            <div class="dl-lines__adj dl-tabs" data-dl-tabs>
                                <div class="dl-tabs__bar" role="tablist">@foreach ($adj as $k => $t)<button type="button" class="dl-tabs__tab {{ $loop->first ? 'is-active' : '' }}" data-tab="{{ $k }}">{{ $t }}</button>@endforeach</div>
                                @foreach ($adj as $k => $t)
                                    <div class="dl-tabs__panel {{ $loop->first ? 'is-active' : '' }}" data-panel="{{ $k }}">
                                        @if (in_array($k, ['disc', 'tax', 'cost'], true))
                                            <table class="dl-lines__adjt" data-l-grp="{{ $k }}">
                                                <thead><tr><th>{{ ['disc' => 'Discount', 'tax' => 'Tax', 'cost' => 'Cost'][$k] }}</th><th class="num">%</th><th class="num">Amount</th></tr></thead>
                                                <tbody>
                                                    @foreach (['disc' => $discs, 'tax' => $taxes, 'cost' => $costs][$k] as $rk => [$rl, $rp, $sign])
                                                        <tr data-l-adj="{{ $rk }}" data-sign="{{ $sign }}" data-src="pct">
                                                            <td>{{ $rl }}@if ($k === 'tax')<small class="dl-lines__sign">{{ $sign < 0 ? '− dipotong' : '+ ditambah' }}</small>@endif</td>
                                                            <td class="num"><input type="text" inputmode="decimal" name="{{ $name }}_{{ $k }}_{{ $rk }}_pct" class="dl-lines__adjin" data-l-pct value="{{ $rp + 0 }}" @readonly($readonly)></td>
                                                            <td class="num"><input type="text" inputmode="numeric" name="{{ $name }}_{{ $k }}_{{ $rk }}_amt" class="dl-lines__adjin" data-l-amt value="0" @readonly($readonly)></td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot><tr><td>Total {{ ['disc' => 'Discount', 'tax' => 'Tax', 'cost' => 'Other Costs'][$k] }}</td><td class="num" data-l-t="{{ $k }}_pct"></td><td class="num" data-l-t="{{ $k }}_amt"></td></tr></tfoot>
                                            </table>
                                            <p class="dl-lines__hint">{{ ['disc' => 'Persen dihitung dari jumlah Details; isi % atau Amount, yang lain terhitung sendiri.', 'tax' => 'Persen dihitung dari DPP (Details dikurangi diskon). PPh dipotong, PPN & Bea Cukai ditambahkan.', 'cost' => 'Persen dihitung dari jumlah Details; biaya ditambahkan setelah pajak.'][$k] }}</p>
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
                            @if ($discs)<div><span>Discount</span><b data-l-t="disc"></b></div><div><span>DPP</span><b data-l-t="dpp"></b></div>@endif
                            @if ($taxes)<div><span>Tax</span><b data-l-t="tax"></b></div>@endif
                            @if ($costs)<div><span>Other Costs</span><b data-l-t="cost"></b></div>@endif
                            <div class="dl-lines__grand"><span>Grand Total</span><b data-l-t="grand"></b></div>
                        </div>@endif
                    </div>
                @endif
            </div>
            BLADE;
    }
}
