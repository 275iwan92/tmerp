{{-- Inquiry Rekalkulasi (RECALC) dari Master Product (docs/flow_product_master.md §4, mockup disetujui user 2026-09-29).
     Alur layar: GRID dokumen RECALC -> + New Record = pilih product (grid) -> form -> Simpan (DRAFT, bisa diedit/dihapus) -> Confirm
     (terkunci, lanjut ProDev -> ProCost); sesudah Confirm hanya Cancel. Urutan isi (fokus Sales): nama product, riwayat order &
     harga JUAL, customer & order baru, dieline + 3D, bahan baku + proses khusus. Harga beli bahan & ukuran plano hanya di ProCost. --}}
@php
    use App\Models\Master\ProdProduct;
    use App\Models\Sdi\IqpInquiry;
    use App\Support\V2\Data\ProductMaster;
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;

    $inq = request('id') ? IqpInquiry::where('inquiry_type', 'RECALC')->with('tiers')->find((int) request('id')) : null;
    abort_if(request('id') && ! $inq, 404);
    // Dari wizard worklist Inquiry (?source_id = inquiry asal) -> product-nya.
    $productId = $inq?->product_id ?? ((int) request('product') ?: (request('source_id') ? IqpInquiry::whereKey((int) request('source_id'))->value('product_id') : null));
    $noProduct = request('source_id') && ! $productId;
    $doc = $productId ? ProductMaster::doc($productId) : null;
    abort_if($productId && ! $doc, 404);
    $locked = (bool) $inq?->stat_confirm;
    $rp = fn ($v) => 'Rp '.\Devlib\Locale::number((float) $v, 0);
    $num = fn ($v) => \Devlib\Locale::number((float) $v, 0);
    $orders = $doc ? ProductMaster::orders($doc['id']) : [];
    $won = array_values(array_filter($orders, fn ($o) => $o['result'] === 'WIN'));
    $lastQuote = $doc ? \App\Models\Sdi\QuoQuotation::whereIn('id_quotation', \App\Models\Sdi\QuoQuotationItem::whereIn('inquiry_id', IqpInquiry::where('product_id', $doc['id'])->pluck('id_inquiry'))->pluck('quotation_id'))->latest('issued_date')->first() : null;
    $home = route('v2.screen', 'inquiry-recalc');
@endphp

<x-mp::sales.flow-steps current="inquiry" note="Recalculation = same product, new price. After Confirm it goes to Product Development and Product Costing, then the new price returns here." />

@if (session('v2_flash'))<x-dl::alert tone="success" :message="session('v2_flash')" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="$errors->first()" />@endif

@if ($noProduct)<x-dl::alert tone="warn" message="The selected inquiry is not a Master Product yet (Quotation not WON). Choose a product from the list." />@endif

@if (! $doc && ! request('pick') && ! $noProduct)
    <x-dl::grid id="recalc-docs" :rows="ProductMaster::derivedRows('RECALC')" search="Search inquiry no., product, customer..." actions="worklist" :active-key="null"
        :new-url="$home.'?pick=1'" :open-url="$home.'?id={id}'"
        source="{{ Src::of('tm2sdi.iqp_inquiry (inquiry_type = RECALC)', 'tm2sdi.iqp_tier') }}"
        :columns="[
            ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'inquiry_no'],
            ['key' => 'customer', 'label' => 'Customer'],
            ['key' => 'date', 'label' => t('Date'), 'type' => 'date'],
            ['key' => 'qty', 'label' => 'Qty order', 'type' => 'number'],
            ['key' => 'deadline', 'label' => 'Deadline quotation', 'type' => 'date'],
            ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => Sales::STATE_TONES],
        ]"
        :stats="[['label' => t('All')], ['label' => 'Draft', 'where' => ['status' => 'DRAFT'], 'tone' => 'neutral'], ['label' => t('Confirm (to ProDev/ProCost)'), 'where' => ['status' => 'SUBMITTED'], 'tone' => 'warn']]" />
@elseif (! $doc)
    <x-dl::card title="+ New Record — choose the product to recalculate" description="Only products from Master Product (born from a WON Quotation). Double-click a row.">
        <x-dl::grid id="recalc-pick" :rows="ProductMaster::rows()" search="Search product, design no., customer..." :new-record="null" actions="worklist" :active-key="null"
            :open-url="$home.'?product={id}'" source="{{ Src::of('tm2mst.prod_product') }}"
            :columns="[
                ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
                ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'join', 'label' => 'Sambungan'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
            ]" />
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    </x-dl::card>
@else
    <form method="post" action="{{ route('v2.derived.save', 'recalc') }}" id="recalc-form" data-dl-guard>
        @csrf
        <input type="hidden" name="product_id" value="{{ $doc['id'] }}">
        @if ($inq)<input type="hidden" name="id" value="{{ $inq->id_inquiry }}">@endif

        <x-dl::card>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <span class="mp-chips">
                    <x-dl::badge :label="$inq ? $inq->inquiry_no : 'Belum tersimpan'" />
                    <x-dl::badge :label="$inq?->sub_state ?? 'DRAFT'" :tone="Sales::STATE_TONES[$inq?->sub_state ?? 'DRAFT'] ?? 'neutral'" />
                </span>
                <x-dl::doc-share target="#recalc-form" type="inquiry-recalc" :doc-id="$inq?->id_inquiry" :number="$inq?->inquiry_no" title="Inquiry Rekalkulasi" />
            </x-dl::action-bar>
            <h2 class="mp-bigname">{{ $doc['name'] }}</h2>
            <p class="mp-hint">{{ $doc['code'] }} · No. Desain {{ $doc['design_no'] ?? '—' }} · {{ collect($doc['dims'])->map(fn ($v) => $v === null ? '—' : \Devlib\Locale::decimal($v, 2))->implode(' × ') }} cm{{ $doc['join'] ? ' · '.$doc['join'] : '' }}</p>
            <x-dl::alert tone="info" message="Recalculation = same product, new price. Spec, dieline, artwork, material and process are locked — use Revision to change them." />
        </x-dl::card>

        <x-dl::card title="Order & selling price history" description="Newest on top. COGS, margin and material purchase price stay in ProCost.">
            <div class="mp-chips">
                <x-dl::badge tone="success" :label="'Terjual '.count($won).'× · '.$num(array_sum(array_column($won, 'qty'))).' pcs'" />
                @if ($won)<x-dl::badge :label="'Harga terakhir terjual: '.$rp($won[0]['price']).' /pcs ('.$won[0]['date'].')'" />@endif
            </div>
            <x-dl::table :columns="['date' => 'Tanggal', 'quotation' => 'Quotation', 'co' => 'Customer Order', 'qty' => 'Qty (pcs)', 'price' => 'Harga jual /pcs', 'total' => 'Total', 'result' => 'Hasil', 'tiers' => 'Jenjang qty ditawarkan', 'ships' => 'Pengiriman']"
                :rows="array_map(fn ($o) => ['date' => $o['date'], 'quotation' => $o['quotation'] ?? '—', 'co' => $o['co'] ?? '—', 'qty' => $num($o['qty']), 'price' => $rp($o['price']), 'total' => $rp($o['qty'] * $o['price']),
                    'result' => ['WIN' => 'WIN · terjual', 'LOST' => 'LOST'.($o['reason'] ? ' · '.$o['reason'] : ''), 'OPEN' => 'Terbuka'][$o['result']],
                    'tiers' => collect($o['tiers'])->map(fn ($t) => $num($t['qty']).' @ '.$rp($t['price']))->implode('; '),
                    'ships' => collect($o['ships'])->map(fn ($s) => $s['date'].' · '.$num($s['qty']).' pcs')->implode('; ') ?: '—'], $orders)"
                empty="Belum ada order untuk product ini." source="{{ Src::of('tm2sdi.quo_quotation_item', 'tm2sdi.co_order_item', 'tm2sdi.co_order_delivery') }}" />
        </x-dl::card>

        <x-dl::card title="Customer & new order">
            <x-dl::form.row cols="3">
                <x-dl::field.display label="Customer" :value="$doc['customer']" :source="Src::of('tm2mst.prod_product.customer_id')" />
                <x-dl::field.display label="Kontak (attn)" :value="$lastQuote?->attn_contact ?? '—'" :source="Src::of('tm2sdi.quo_quotation.attn_contact').' (quotation terakhir)'" />
                <x-dl::field.display label="Sales PIC · Termin" :value="($lastQuote?->sales_pic ?? '—').' · '.($lastQuote?->payment_term ?? '—')" :source="Src::of('tm2sdi.quo_quotation.sales_pic', 'payment_term')" />
            </x-dl::form.row>
            <x-dl::form.row cols="3">
                <x-dl::field.decimal name="qty" label="New order qty (pcs)" required :value="old('qty', $inq ? rtrim(rtrim((string) $inq->tiers->firstWhere('is_primary', true)?->qty, '0'), '.') : ($orders[0]['qty'] ?? ''))" :disabled="$locked" :source="Src::of('tm2sdi.iqp_tier.qty').' (tier utama)'" />
                <x-dl::field.date name="deadline_quotation" label="Deadline quotation" :value="old('deadline_quotation', $inq?->deadline_quotation?->toDateString())" :disabled="$locked" :source="Src::of('tm2sdi.iqp_inquiry.deadline_quotation')" />
                <x-dl::field.display label="Order terakhir" :value="$won ? ($won[0]['co'] ?? '').' · '.$num($won[0]['qty']).' pcs · '.$won[0]['date'] : '—'" :source="Src::of('tm2sdi.co_order_item.product_id')" />
            </x-dl::form.row>
        </x-dl::card>

        <x-dl::card title="Dieline & preview 3D" description="Locked — same as the Master Product.">
            <div class="mp-grid2">
                @foreach ($doc['components'] as $c)
                    <div><b>{{ $c['name'] }}</b><x-mp::sales.part-visual :part="$c" :types="Sales::dielineTypes()" height="300px" /></div>
                @endforeach
            </div>
            @if ($doc['components'])
                <x-mp::box3d :dims="$doc['dims']" :front="$doc['components'][0]['artwork']" :back="$doc['components'][1]['artwork'] ?? null" class="mp-mt" />
            @endif
        </x-dl::card>

        <x-dl::card title="Raw materials used" description="Brand is only a reference; purchase price & sheet size are in ProCost.">
            <x-dl::table :columns="['part' => 'Part', 'seq' => 'Bahan', 'jenis' => 'Jenis / Gramatur', 'brand' => 'Merek (acuan)']"
                :rows="collect($doc['components'])->flatMap(fn ($c) => array_map(fn ($m) => ['part' => $c['name'], 'seq' => '#'.$m['seq'], 'jenis' => $m['jenis'] ?? '—', 'brand' => $m['brand'] ?? '—'], $c['materials']))->all()"
                empty="Belum ada bahan." source="{{ Src::of('tm2mst.prod_part_material', 'tm2mst.mat_category') }}" />
            <x-dl::divider label="Special processes (high cost)" />
            <div class="mp-chips">@forelse (ProductMaster::specialProcesses($doc) as $s)<x-dl::badge :label="$s" tone="warn" />@empty<span class="mp-hint">Tidak ada.</span>@endforelse</div>
        </x-dl::card>

        <x-dl::action-bar>
            @if ($inq)<x-dl::button variant="secondary" icon="copy" label="Duplicate" type="button" data-dl-open="dup-modal" />@endif
            @if (! $locked)
                @if ($inq)<x-dl::button preset="delete" type="submit" :formaction="route('v2.derived.delete', $inq->id_inquiry)" onclick="return confirm('Hapus draft {{ $inq->inquiry_no }}?')" formnovalidate />@endif
                <x-dl::button preset="save" name="action" value="save" />
                <x-dl::button preset="confirm" type="submit" name="action" value="confirm" onclick="return confirm('Confirm rekalkulasi ini? Form akan terkunci dan dikirim ke ProDev → ProCost.')" />
            @elseif (! $inq->stat_cancel)
                <x-dl::button preset="cancel" data-dl-open="recalc-cancel" />
            @endif
        </x-dl::action-bar>
    </form>

    @if ($inq)
        @include('v2.screens._inquiry-duplicate', ['source' => $inq->id_inquiry, 'label' => $inq->inquiry_no.' — '.$doc['name'].' · '.$doc['customer']])
    @endif

    @if ($locked && ! $inq->stat_cancel)
        <x-dl::card title="Continue to Product Development" description="After Confirm, send each part to ProDev; from there continue to ProCost.">
            <div class="mp-chips">
                @foreach (ProductMaster::inquiryParts($inq) as $ip)
                    @if ($ip['kind'] === 'COMPONENT')
                        <form method="post" action="{{ route('v2.derived.prodev', $inq->id_inquiry) }}">@csrf<input type="hidden" name="part_id" value="{{ $ip['id'] }}"><x-dl::button type="submit" size="sm" icon="send" :label="'Kirim '.$ip['name'].' ke ProDev'" /></form>
                    @endif
                @endforeach
            </div>
        </x-dl::card>
        <x-dl::modal id="recalc-cancel" title="Cancel rekalkulasi" icon="cancel">
            <form method="post" action="{{ route('v2.derived.cancel', $inq->id_inquiry) }}">
                @csrf
                <x-dl::field.text name="cancel_reason" label="Cancellation reason" required />
                <x-dl::action-bar><x-dl::button preset="save" label="Cancel dokumen" variant="danger" /></x-dl::action-bar>
            </form>
        </x-dl::modal>
    @endif
@endif
