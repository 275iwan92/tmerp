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
    $locked = (bool) $inq?->stat_confirm_sales;
    $rp = fn ($v) => 'IDR '.\Devlib\Locale::number((float) $v, 0);
    $num = fn ($v) => \Devlib\Locale::number((float) $v, 0);
    $orders = $doc ? ProductMaster::orders($doc['id']) : [];
    $won = array_values(array_filter($orders, fn ($o) => $o['result'] === 'WIN'));
    $lastQuote = $doc ? \App\Models\Sdi\QuoQuotation::whereIn('id_quotation', \App\Models\Sdi\QuoQuotationItem::whereIn('inquiry_id', IqpInquiry::where('product_id', $doc['id'])->pluck('id_inquiry'))->pluck('quotation_id'))->latest('issued_date')->first() : null;
    $home = route('v2.screen', 'inquiry-recalc');
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif
@if ($errors->any())<x-dl::alert tone="danger" :message="raw($errors->first())" />@endif

@if ($noProduct)<x-dl::alert tone="warn" message="The selected inquiry is not a Master Product yet (Quotation not WON). Choose a product from the list." />@endif

@if (! $doc && ! request('pick') && ! $noProduct)
    {{-- Inquiry > Recalc: meja Sales untuk jenis RECALC (satu tabel iqp_inquiry, saringan jenis + tanda; rulebook A.20, C.28). --}}
    @php $g = (new \App\Support\V2\Grid\Sales\InquiryRecalcGrid)->props(); @endphp
    <x-dl::grid id="recalc-docs" :rows="$g['rows']" :columns="$g['columns']" :stats="$g['stats']" :filters="$g['filters']" :detail="$g['detail']" :search="$g['search']"
        actions="worklist" :active-key="null" :new-url="$g['newUrl']" :open-url="$g['openUrl']" :source="$g['source']" />
@elseif (! $doc)
    <x-dl::card title="+ New Record — choose the product to recalculate" description="Only products from Master Product (born from a WON Quotation). Double-click a row.">
        <x-dl::grid id="recalc-pick" :rows="ProductMaster::rows()" search="Search product, design no., customer..." :new-record="null" actions="worklist" :active-key="null"
            :open-url="$home.'?product={id}'" source="{{ Src::of('tm2mst.prod_product') }}"
            :columns="[
                ['key' => 'photo', 'label' => 'Artwork', 'type' => 'image', 'fallback' => 'name', 'sortable' => false],
                ['key' => 'name', 'label' => 'Product', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
                ['key' => 'customer', 'label' => 'Customer'],
                ['key' => 'join', 'label' => 'Joint'],
                ['key' => 'status', 'label' => 'Status', 'type' => 'badge', 'tones' => ['ACTIVE' => 'success', 'DRAFT' => 'neutral']],
            ]" />
        <x-dl::action-bar><x-dl::button variant="secondary" icon="back" label="Back" :href="$home" /></x-dl::action-bar>
    </x-dl::card>
@else
    <x-dl::action-form action="{{ route('v2.derived.save', 'recalc') }}" id="recalc-form" data-dl-guard>
        <input type="hidden" name="product_id" value="{{ $doc['id'] }}">
        @if ($inq)<input type="hidden" name="id" value="{{ $inq->id_inquiry }}">@endif

        <x-dl::card>
            <x-dl::action-bar>
                <x-dl::button variant="secondary" icon="back" label="Back" :href="$home" />
                <span class="mp-chips">
                    <x-dl::badge :label="$inq ? $inq->inquiry_no : 'Not saved yet'" />
                    <x-dl::badge :label="$inq?->sub_state ?? 'DRAFT'" :tone="Sales::STATE_TONES[$inq?->sub_state ?? 'DRAFT'] ?? 'neutral'" />
                </span>
                @if ($inq)<x-dl::button preset="print" :href="\App\Support\V2\Print\PrintDocs::url('inquiry', $inq->id_inquiry, ['from' => 'inquiry-recalc'])" target="_blank" />@endif
            </x-dl::action-bar>
            <h2 class="mp-bigname">{{ $doc['name'] }}</h2>
            <p class="mp-hint">{{ $doc['code'] }} · @t('Design No.') {{ $doc['design_no'] ?? '—' }} · {{ collect($doc['dims'])->map(fn ($v) => $v === null ? '—' : \Devlib\Locale::decimal($v, 2))->implode(' × ') }} cm{{ $doc['join'] ? ' · '.$doc['join'] : '' }}</p>
            <x-dl::alert tone="info" message="Recalculation = same product, new price. Spec, dieline, artwork, material and process are locked — use Revision to change them." />
        </x-dl::card>

        <x-dl::card title="Order & selling price history" description="Newest on top. COGS, margin and material purchase price stay in ProCost.">
            <div class="mp-chips">
                <x-dl::badge tone="success" :label="'Sold '.count($won).'× · '.$num(array_sum(array_column($won, 'qty'))).' pcs'" />
                @if ($won)<x-dl::badge :label="'Last sold price: '.$rp($won[0]['price']).' /pcs ('.$won[0]['date'].')'" />@endif
            </div>
            <x-dl::table :columns="['date' => 'Date', 'quotation' => 'Quotation', 'co' => 'Customer Order', 'qty' => 'Qty (pcs)', 'price' => 'Selling price /pcs', 'total' => 'Total', 'result' => 'Result', 'tiers' => 'Qty tiers offered', 'ships' => 'Delivery']"
                :rows="array_map(fn ($o) => ['date' => $o['date'], 'quotation' => $o['quotation'] ?? '—', 'co' => $o['co'] ?? '—', 'qty' => $num($o['qty']), 'price' => $rp($o['price']), 'total' => $rp($o['qty'] * $o['price']),
                    'result' => ['WIN' => 'WIN · '.t('sold'), 'LOST' => 'LOST'.($o['reason'] ? ' · '.$o['reason'] : ''), 'OPEN' => t('Still open')][$o['result']],
                    'tiers' => collect($o['tiers'])->map(fn ($t) => $num($t['qty']).' @ '.$rp($t['price']))->implode('; '),
                    'ships' => collect($o['ships'])->map(fn ($s) => $s['date'].' · '.$num($s['qty']).' pcs')->implode('; ') ?: '—'], $orders)"
                empty="No order for this product yet." source="{{ Src::of('tm2sdi.quo_quotation_item', 'tm2sdi.co_order_item', 'tm2sdi.co_order_delivery') }}" />
        </x-dl::card>

        <x-dl::card title="Customer & new order">
            <x-dl::form.row cols="3">
                <x-dl::field.display label="Customer" :value="$doc['customer']" :source="Src::of('tm2mst.prod_product.customer_id')" />
                <x-dl::field.display label="Contact (attn)" :value="$lastQuote?->attn_contact ?? '—'" :source="Src::of('tm2sdi.quo_quotation.attn_contact').' (quotation terakhir)'" />
                <x-dl::field.display label="Sales PIC · Payment Term" :value="($lastQuote?->sales_pic ?? '—').' · '.($lastQuote?->payment_term ?? '—')" :source="Src::of('tm2sdi.quo_quotation.sales_pic', 'payment_term')" />
            </x-dl::form.row>
            <x-dl::form.row cols="3">
                <x-dl::field.decimal name="qty" label="New order qty (pcs)" required :value="old('qty', $inq ? rtrim(rtrim((string) $inq->tiers->firstWhere('is_primary', true)?->qty, '0'), '.') : ($orders[0]['qty'] ?? ''))" :disabled="$locked" :source="Src::of('tm2sdi.iqp_tier.qty').' (tier utama)'" />
                <x-dl::field.date name="deadline_quotation" label="Quotation deadline" :value="old('deadline_quotation', $inq?->deadline_quotation?->toDateString())" :disabled="$locked" :source="Src::of('tm2sdi.iqp_inquiry.deadline_quotation')" />
                <x-dl::field.display label="Last order" :value="$won ? ($won[0]['co'] ?? '').' · '.$num($won[0]['qty']).' pcs · '.$won[0]['date'] : '—'" :source="Src::of('tm2sdi.co_order_item.product_id')" />
            </x-dl::form.row>
        </x-dl::card>

        <x-dl::card title="Dieline & 3D preview" description="Locked — same as the Master Product.">
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
            <x-dl::table :columns="['part' => 'Part', 'seq' => 'Material', 'jenis' => 'Type / Grammage', 'brand' => 'Brand (reference)']"
                :rows="collect($doc['components'])->flatMap(fn ($c) => array_map(fn ($m) => ['part' => $c['name'], 'seq' => '#'.$m['seq'], 'jenis' => $m['jenis'] ?? '—', 'brand' => $m['brand'] ?? '—'], $c['materials']))->all()"
                empty="No material yet." source="{{ Src::of('tm2mst.prod_part_material', 'tm2mst.mat_category') }}" />
            <x-dl::divider label="Special processes (high cost)" />
            <div class="mp-chips">@forelse (ProductMaster::specialProcesses($doc) as $s)<x-dl::badge :label="$s" tone="warn" />@empty<span class="mp-hint">@t('None.')</span>@endforelse</div>
        </x-dl::card>

        <x-dl::action-bar>
            @if ($inq)<x-dl::button variant="secondary" icon="copy" label="Duplicate" type="button" data-dl-open="dup-modal" />@endif
            @if (! $locked)
                @if ($inq)<x-dl::button preset="delete" type="submit" :formaction="route('v2.derived.delete', $inq->id_inquiry)" onclick="return confirm('{{ tf('Delete draft :no?', ['no' => $inq->inquiry_no]) }}')" formnovalidate />@endif
                <x-dl::button preset="save" name="action" value="save" />
                <x-dl::button preset="confirm" type="submit" name="action" value="confirm" onclick="return confirm('{{ t('Confirm this recalculation? The form will be locked and sent straight to Pro-Cost.') }}')" />
            @elseif (! $inq->stat_cancel)
                <x-dl::button preset="cancel" data-dl-open="recalc-cancel" />
            @endif
        </x-dl::action-bar>
    </x-dl::action-form>

    @if ($inq)
        @include('v2.screens._inquiry-duplicate', ['source' => $inq->id_inquiry, 'label' => $inq->inquiry_no.' — '.$doc['name'].' · '.$doc['customer']])
    @endif

    @if ($locked && ! $inq->stat_cancel)
        <x-dl::alert tone="info" message="Confirmed by Sales: this recalculation goes straight to Pro-Cost › Recalc (no Pro-Dev, the specification is unchanged). After Pro-Cost publishes the price it is ready for Quotation." />
        <x-dl::modal id="recalc-cancel" title="Cancel recalculation" icon="cancel">
            <x-dl::action-form action="{{ route('v2.derived.cancel', $inq->id_inquiry) }}">
                <x-dl::field.text name="cancel_reason" label="Cancellation reason" required />
                <x-dl::action-bar><x-dl::button preset="save" label="Cancel document" variant="danger" /></x-dl::action-bar>
            </x-dl::action-form>
        </x-dl::modal>
    @endif
@endif
