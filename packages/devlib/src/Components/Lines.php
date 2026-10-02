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
// 'currencyField' => nama field mata uang di form (default 'currency'): simbol & terbilang ikut (IDR = Indonesia, lainnya English).
// 'landed' => true (Purchasing): biaya bercentang HPP dibagi ke baris (by value/qty/equal) -> kolom 'landed' => 'amount'|'unit'.
//   -> footer: tab Discount (Normal/Qty/Amount) / Tax (PPN, PPh, Bea Cukai) -- kolom % & Amount saling mengisi + Total --
//      / Other Costs (+ Notes bila slot note) di kiri; Summary selalu di kanan.
// 'more' => true = kolom tidak di tabel, diisi lewat panel › per baris (detail berkolom banyak tetap ringkas).
// Kolom pertama + kolom rumus terakhir diam saat tabel digeser ke samping. Slot note = kiri footer (total di kanan).
// Layar server-driven: 'keyField' => 'id_item' -> terkirim name[<id>][kolom] (bukan indeks); 'removeField' => 'remove' -> baris
// ber-id yang dihapus terkirim remove[<id>]=1; :addable=false -> tambah baris lewat tombol layar;
// :duplicable=false = tanpa tombol salin baris; row-lock="editable" = baris dgn nilai false tidak bisa dihapus (mis. snapshot quotation) (mis. pilih Master Product).
// Slot tools = kontrol tambah baris milik layar (mis. pilih Master Item + tombol) di bar judul Details.
// Terkirim: name[i][kolom] per baris, name_discount, name_ppn. Di dalam x-dl::master: baris = data[name].
class Lines extends DevlibComponent
{
    public function __construct(
        public string $name = 'items',
        public array $columns = [],
        public array $rows = [],
        public string $title = 'Item',
        public string $addLabel = '+ Add Row',
        public int $minRows = 1, // 0 = boleh kosong (tanpa baris awal); tombol hapus mati bila jumlah baris <= minRows
        public array $totals = [],
        public ?string $totalKey = null,
        public ?string $countKey = null,
        public bool $readonly = false,
        public ?string $source = null,
        public ?string $keyField = null,
        public ?string $removeField = null,
        public bool $addable = true,
        public bool $duplicable = true,
        public ?string $rowLock = null,
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
        // UU PPN ps. 1 angka 18: semua biaya yang ditagih penjual atas penyerahan BKP masuk Harga Jual (DPP) -> default kena PPN;
        // bea meterai = pajak tersendiri -> default tidak. Alokasi HPP (landed cost) default ya bila 'landed' aktif.
        $rows = [];
        foreach ($this->totals['costs'] ?? [] as $k => $c) {
            $c = is_array($c) ? $c : [$c];
            $stamp = in_array($k, ['admin', 'materai', 'stamp'], true);
            $rows[$k] = [$c[0], $c[1] ?? 0, 1, $c[3] ?? ! $stamp, $c[4] ?? ! $stamp];
        }

        return $rows;
    }

    // Baris pajak: [kode => [label, % awal, +1 ditambah | -1 dipotong]]. 'tax' => array sendiri, atau 'ppn' => 11 = PPN + PPh + Bea Cukai.
    public function taxRows(): array
    {
        if (isset($this->totals['tax'])) {
            return $this->totals['tax'];
        }

        return isset($this->totals['ppn']) ? ['ppn' => ['VAT', $this->totals['ppn'], 1], 'pph' => ['PPh 23', 0, -1], 'customs' => ['Customs Duty', 0, 1]] : [];
    }

    public function config(): array
    {
        $cols = array_map(function ($c) {
            $c += ['type' => 'text', 'required' => false, 'sum' => false];
            if ($c['type'] === 'lookup') {
                $name = $c['lookup'];
                $c['lookup'] = Lookup::get($name) + ['url' => route('devlib.lookup', ['name' => $name])];
            }
            if (isset($c['options']) && array_is_list($c['options'])) {
                $c['options'] = array_combine($c['options'], $c['options']);
            }

            return $c;
        }, $this->columns);

        return ['name' => $this->name, 'columns' => array_values($cols), 'minRows' => $this->minRows, 'totals' => $this->totals, 'readonly' => $this->readonly, 'keyField' => $this->keyField, 'removeField' => $this->removeField, 'addable' => $this->addable, 'duplicable' => $this->duplicable, 'rowLock' => $this->rowLock];
    }

    public function render()
    {
        return <<<'BLADE'
            <div {{ $attributes->class(['dl-lines', 'is-readonly' => $readonly]) }} {!! $dl() !!} data-dl-lines data-name="{{ $name }}" data-total-key="{{ $totalKey }}" data-count-key="{{ $countKey }}" data-config="{{ json_encode($config()) }}" data-rows="{{ json_encode($rows) }}">
                <div class="dl-lines__head"><b>{{ $title }}</b><span class="dl-lines__count" data-l-count></span><span class="dl-lines__spacer"></span>@isset($tools)<div class="dl-lines__tools">{{ $tools }}</div>@endisset
                    @if (! $readonly && $addable)<x-dl::button variant="secondary" size="sm" :label="$addLabel" data-l-add :title="\Devlib\Lang::text('Add row (Enter on the last row)')" />@endif
                </div>
                <div class="dl-lines__wrap"><table class="dl-lines__table"><thead data-l-head></thead><tbody data-l-body></tbody><tfoot data-l-foot></tfoot></table></div>
                <div data-l-inputs hidden></div>
                @if ($totals || isset($note))
                    @php
                        $costs = $costRows();
                        $discs = $discRows();
                        $taxes = $taxRows();
                        $landed = (bool) ($totals['landed'] ?? false);
                        $adj = array_filter(['disc' => $discs ? 'Discount' : null, 'tax' => $taxes ? 'Tax' : null, 'cost' => $costs ? 'Other Costs' : null, 'note' => isset($note) ? 'Notes' : null]);
                    @endphp
                    {{-- Footer dokumen (user 2026-09-30): kiri = tab Discount / Tax / Other Costs; tiap baris diskon & pajak punya
                         kolom % dan Amount yang saling mengisi + baris Total. Kanan = Summary yang SELALU terlihat. --}}
                    <div class="dl-lines__footer">
                        <div class="dl-lines__left">
                        @if ($adj)
                            <div class="dl-lines__adj dl-tabs" data-dl-tabs>
                                <div class="dl-tabs__bar" role="tablist">@foreach ($adj as $k => $t)<button type="button" class="dl-tabs__tab {{ $loop->first ? 'is-active' : '' }}" data-tab="{{ $k }}">{{ \Devlib\Lang::text($t) }}</button>@endforeach</div>
                                @foreach ($adj as $k => $t)
                                    <div class="dl-tabs__panel {{ $loop->first ? 'is-active' : '' }}" data-panel="{{ $k }}">
                                        @if (in_array($k, ['disc', 'tax', 'cost'], true))
                                            <table class="dl-lines__adjt" data-l-grp="{{ $k }}">
                                                <thead><tr><th>{{ \Devlib\Lang::text(['disc' => 'Discount', 'tax' => 'Tax', 'cost' => 'Cost'][$k]) }}</th><th class="num">%</th><th class="num">{{ \Devlib\Lang::text('Amount') }}</th>@if ($k === 'cost')<th class="dl-lines__chk" title="{{ \Devlib\Lang::text('In tax base (subject to VAT)') }}">{{ \Devlib\Lang::text('VAT') }}</th>@if ($landed)<th class="dl-lines__chk" title="{{ \Devlib\Lang::text('Allocated to item COGS (landed cost)') }}">{{ \Devlib\Lang::text('COGS') }}</th>@endif @endif</tr></thead>
                                                <tbody>
                                                    @foreach (['disc' => $discs, 'tax' => $taxes, 'cost' => $costs][$k] as $rk => $row)
                                                        @php [$rl, $rp, $sign] = $row; @endphp
                                                        <tr data-l-adj="{{ $rk }}" data-sign="{{ $sign }}" data-src="pct">
                                                            <td>{{ \Devlib\Lang::text($rl) }}@if ($k === 'tax')<small class="dl-lines__sign">{{ \Devlib\Lang::text($sign < 0 ? '− deducted' : '+ added') }}</small>@endif</td>
                                                            <td class="num"><input type="text" inputmode="decimal" name="{{ $name }}_{{ $k }}_{{ $rk }}_pct" class="dl-lines__adjin" data-l-pct value="{{ \Devlib\Locale::raw($rp) }}" @readonly($readonly)></td>
                                                            <td class="num"><input type="text" inputmode="numeric" name="{{ $name }}_{{ $k }}_{{ $rk }}_amt" class="dl-lines__adjin" data-l-amt value="0" @readonly($readonly)></td>
                                                            @if ($k === 'cost')
                                                                <td class="dl-lines__chk"><input type="checkbox" name="{{ $name }}_cost_{{ $rk }}_tax" value="1" data-l-tx @checked($row[3]) @disabled($readonly)></td>
                                                                @if ($landed)<td class="dl-lines__chk"><input type="checkbox" name="{{ $name }}_cost_{{ $rk }}_hpp" value="1" data-l-hpp @checked($row[4]) @disabled($readonly)></td>@endif
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot><tr><td>Total {{ \Devlib\Lang::text(['disc' => 'Discount', 'tax' => 'Tax', 'cost' => 'Other Costs'][$k]) }}</td><td class="num" data-l-t="{{ $k }}_pct"></td><td class="num" data-l-t="{{ $k }}_amt"></td>@if ($k === 'cost')<td></td>@if ($landed)<td></td>@endif @endif</tr></tfoot>
                                            </table>
                                            <p class="dl-lines__hint">{{ \Devlib\Lang::text(['disc' => 'The percentage is taken from the Details total; fill in % or Amount and the other one is calculated.', 'tax' => 'The percentage is taken from the tax base (Details minus discount). Income tax (PPh) is deducted, VAT & Customs Duty are added.', 'cost' => 'The percentage is taken from the Details total. Tick VAT = the cost goes into the tax base (VAT law: every cost billed by the seller is subject to VAT).'][$k]) }}{{ $k === 'cost' && $landed ? ' '.\Devlib\Lang::text('Tick COGS = allocated to the item cost.') : '' }}</p>
                                            @if ($k === 'cost' && $landed)
                                                <div class="dl-lines__adjrow" style="margin-top:6px"><label>{{ \Devlib\Lang::text('Allocation to item COGS') }}</label>
                                                    <select name="{{ $name }}_alloc" class="dl-lines__adjin" data-l-alloc @disabled($readonly)><option value="value">{{ \Devlib\Lang::text('By Value (row value)') }}</option><option value="qty">{{ \Devlib\Lang::text('By Quantity') }}</option><option value="equal">{{ \Devlib\Lang::text('Equal (same for every row)') }}</option></select>
                                                </div>
                                            @endif
                                        @else
                                            {{ $note }}
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        {{-- Terbilang Grand Total: IDR = Bahasa Indonesia, mata uang asing = English (user 2026-09-30). --}}
                        @if ($totals)<div class="dl-lines__words" data-l-words data-currencies="{{ json_encode(\Devlib\Terbilang::CURRENCIES) }}"><span>{{ \Devlib\Lang::text('Amount in words') }}</span><i data-l-words-text></i></div>@endif
                        </div>
                        @if ($totals)<div class="dl-lines__totals">
                            <div class="dl-lines__sumh">{{ \Devlib\Lang::text('Summary') }}</div>
                            <div><span>{{ \Devlib\Lang::text('Details') }}</span><b data-l-t="sub"></b></div>
                            @if ($discs)<div><span>{{ \Devlib\Lang::text('Discount') }}</span><b data-l-t="disc"></b></div>@endif
                            @if ($costs)<div><span>{{ \Devlib\Lang::text('Other Costs (subject to VAT)') }}</span><b data-l-t="cost_tx"></b></div>@endif
                            @if ($discs || $costs)<div class="dl-lines__dpp"><span>{{ \Devlib\Lang::text('Tax Base') }}</span><b data-l-t="dpp"></b></div>@endif
                            @if ($taxes)<div><span>{{ \Devlib\Lang::text('Tax') }}</span><b data-l-t="tax"></b></div>@endif
                            @if ($costs)<div><span>{{ \Devlib\Lang::text('Other Costs (non-VAT)') }}</span><b data-l-t="cost_nt"></b></div>@endif
                            <div class="dl-lines__grand"><span>{{ \Devlib\Lang::text('Grand Total') }}</span><b data-l-t="grand"></b></div>
                        </div>@endif
                    </div>
                @endif
            </div>
            BLADE;
    }
}
