{{-- ACC Digital / Proof Digital = DOKUMEN CETAK (user 2026-09-29); pencatatan ACC ada di ProDev (partial v2.partials.product-acc-forms).
     Format = Proof Digital yang dipakai selama ini (docs/attachments/Proof Digital ... DS003637): judul + REVISION NOTE, dieline per part,
     blok CUSTOMER/ITEM/Inner SIZE/PRINT COLOUR/DRAWING BY/APPROVED BY. Tanpa ?id = grid product + status ACC; ?id = dokumen. --}}
@php
    use App\Models\Master\ProdProductAcc;
    use App\Support\V2\Data\ProductMaster;
    use App\Support\V2\Src;

    $doc = request('id') ? ProductMaster::doc((int) request('id')) : null;
    abort_if(request('id') && ! $doc, 404);
    $mm = fn ($v) => $v === null ? '—' : \Devlib\Locale::decimal($v * 10, 1).' mm';
    $inks = $doc ? collect($doc['components'])->flatMap(fn ($c) => array_column($c['inks'], 'ink'))->unique()->values()->all() : [];
    $swatch = ['CYAN' => '#00a0e3', 'MAGENTA' => '#e5007e', 'YELLOW' => '#ffed00', 'BLACK' => '#1d1d1b'];
    $digital = $doc ? collect($doc['accs'])->firstWhere('type', 'DIGITAL') : null;
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if (! $doc)
    <x-dl::grid id="product-acc" :rows="ProductMaster::rows()" search="Search product, design no., customer..." :new-record="null" actions="worklist" :active-key="null"
        :open-url="route('v2.screen', 'product-acc').'?id={id}'" source="{{ Src::of('tm2mst.prod_product', 'tm2mst.prod_product_acc') }}"
        :columns="[
            ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
            ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'acc_digital', 'label' => 'Digital ACC', 'type' => 'badge', 'tones' => [t('AVAILABLE') => 'success', t('NOT YET') => 'warn']],
            ['key' => 'status', 'label' => 'Product status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
        ]"
        :stats="[['label' => t('All')], ['label' => t('No Digital ACC yet'), 'where' => ['acc_digital' => t('NOT YET')], 'tone' => 'warn'], ['label' => t('Digital ACC done'), 'where' => ['acc_digital' => t('AVAILABLE')], 'tone' => 'success']]" />
@else
    <x-dl::action-bar>
        <x-dl::button variant="secondary" icon="back" label="Back" href="javascript:history.back()" />
        <x-dl::button variant="secondary" icon="view" label="Master Product" :href="route('v2.screen', 'master-product').'?id='.$doc['id']" />
        <x-dl::doc-share target=".mp-proof" type="product-acc" :doc-id="$doc['id']" :number="$doc['code']" title="ACC / Proof Digital" orientation="landscape" />
    </x-dl::action-bar>

    <div class="mp-proof" data-dl-src="{{ Src::of('tm2mst.prod_product', 'tm2mst.prod_part_artwork', 'tm2mst.prod_product_acc') }}">
        <div class="mp-proof__top">
            <div class="mp-proof__title">{{ $doc['code'] }}<br>{{ collect($doc['dims'])->map(fn ($v) => $v === null ? '—' : \Devlib\Locale::decimal($v, 2))->implode(' x ') }} cm</div>
            <table class="mp-proof__rev">
                <tr><th>@t('NO')</th><th>@t('REVISION NOTE')</th><th>@t('DESIGN NUMBER')</th><th>@t('DATE')</th></tr>
                @foreach (ProductMaster::lineage($doc['id']) as $i => $l)
                    <tr><td>{{ str_pad((string) $i, 2, '0', STR_PAD_LEFT) }}</td><td>{{ $l['note'] }}</td><td>{{ $l['design'] }}</td><td>{{ $l['date'] }}</td></tr>
                @endforeach
            </table>
        </div>
        @foreach ($doc['components'] as $c)
            <div class="mp-proof__art"><span>{{ preg_replace('/^Part\s+/i', '', $c['name']) }}</span>
                @if ($c['artwork'])<img src="{{ $c['artwork'] }}" alt="Proof {{ $c['name'] }}">@else<div class="mp-partvis__empty">{{ tf('No artwork for :part yet.', ['part' => $c['name']]) }}</div>@endif
            </div>
        @endforeach
        <table class="mp-proof__block">
            <tr>
                <td rowspan="4" class="mp-proof__logo"><b>{{ \App\Support\Branding\Brand::companyName() }}</b><br><small>{{ \App\Support\Branding\Brand::tagline() }}</small>
                    <div class="mp-proof__warn"><b>@t('Attention !!!')</b><br>@t('* The colours on this print out are not the standard for the production process; please check this print out and the attached print colours again.') @t('Mistakes after the ACC are not our responsibility.')<br>@t('* The unit of measure in this layout is millimetres (mm) unless stated otherwise.')</div></td>
                <th>@t('CUSTOMER')</th><th>@t('ITEM')</th><th>@t('Inner SIZE')</th><th>@t('APPROVED BY')</th><th>@t('APPROVED BY')</th><th>@t('INQUIRE & APPROVED BY')</th><th>@t('NOTE')</th>
            </tr>
            <tr><td rowspan="2" class="mp-proof__big">{{ $doc['customer'] }}</td><td rowspan="2" class="mp-proof__big">{{ $doc['name'] }}</td>
                <td class="mp-proof__mono">L : {{ $mm($doc['dims']['p']) }}<br>W : {{ $mm($doc['dims']['l']) }}<br>H : {{ $mm($doc['dims']['t']) }}</td>
                <td rowspan="3" class="mp-proof__sign"></td><td rowspan="3" class="mp-proof__sign"></td>
                <td rowspan="3" class="mp-proof__sign">@if ($digital){{ $digital['by'] }}<br><small>{{ $digital['at'] }} · {{ $digital['channel'] }}</small>@endif</td><td rowspan="3"></td></tr>
            <tr><th>@t('DRAWING BY')</th></tr>
            <tr><td class="mp-proof__mono"><small>@t('DATE')</small> {{ optional($doc['design_date'])->format('d/m/Y') ?? '—' }}<br><small>@t('DESIGN NUMBER')</small> {{ $doc['design_no'] ?? '—' }}</td>
                <td><small>@t('PRINT COLOUR')</small><br>@foreach ($inks as $k)<i class="mp-proof__sw" style="background:{{ $swatch[$k] ?? '#999' }}" title="{{ $k }}"></i>@endforeach {{ implode(' · ', $inks) ?: '—' }}</td>
                <td class="mp-proof__big">{{ $doc['drawing_by'] ?? '—' }}</td></tr>
            <tr><td colspan="4"></td><td class="mp-proof__cap">@t('QA')</td><td class="mp-proof__cap">@t('PREPRESS')</td><td class="mp-proof__cap">@t('CUSTOMER')</td><td></td></tr>
        </table>
        <p class="mp-proof__red">@t('ACC PROOF PRINT (DIGITAL + PRINTED) MUST BE ATTACHED TO THE MASTER CARD')</p>
    </div>

    <x-dl::alert tone="info" message="Printed document. Customer ACC (date, approver, evidence) is recorded on the ProDev screen." />
@endif
