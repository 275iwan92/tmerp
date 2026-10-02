{{-- Inquiry Product (worklist). Alur & field: tmerp pages/inquiry/hub.blade.php. Keputusan user 2026-09-28:
     wizard jenis -> sumber -> pilih inquiry. Win/Lost ada di Quotation.
     User 2026-10-01/02: Grid Transactional DevLib biasa (x-dl::grid, sama dengan Quotation / Customer Order): + New Record di toolbar
     membuka wizard; Continue Draft / Ready to Quote = filter teks Draft / Ready to Quote di baris filter (klik = saring);
     Duplicate = tombol di dalam form Inquiry (screens/_inquiry-duplicate), bukan ikon di baris grid. --}}
@php
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;
    use MockupParts\Components\Sales\InquiryTypeCard;

    // Tiap baris membawa alamat bukanya sendiri: layar sesuai jenis dokumen (3 layar terpisah); selain DRAFT = lihat-saja.
    $screenOf = ['NEW' => 'inquiry-new', 'RECALC' => 'inquiry-recalc', 'REVISE' => 'inquiry-revise'];
    $rows = array_map(fn ($r) => $r + ['open_url' => route('v2.screen', $screenOf[$r['inquiry_type']] ?? 'inquiry-new').'?id='.$r['id'].($r['sub_state'] === 'DRAFT' ? '' : '&view=1')], Sales::inquiries());
    $col = fn (string $key, string $label, string $src, array $more = []) => ['key' => $key, 'label' => $label, 'src' => $src] + $more;
    // Rulebook C.28: kolom pertama = persegi dokumen (No. inquiry + customer); induk 7 kolom tampil; Expand = tabel part.
    $columns = [
        $col('inquiry_no', 'Inquiry No. / Customer', Src::of('tm2sdi.iqp_inquiry.inquiry_no', 'tm2mst.cust_customer.customer_name', 'tm2sdi.iqp_inquiry.prospect_name'), ['type' => 'doc', 'party' => 'customer']),
        $col('inquiry_date', t('Date'), Src::of('tm2sdi.iqp_inquiry.inquiry_date'), ['type' => 'date']),
        $col('product_draft_name', 'Product', Src::of('tm2sdi.iqp_inquiry.product_draft_name')),
        $col('part_type', t('Product Type'), Src::of('tm2sdi.iqp_part.dieline_spec.type_code').' (first part)'),
        $col('qty', t('Main tier qty'), Src::of('tm2sdi.iqp_tier.qty').' (is_primary = 1)', ['type' => 'number']),
        $col('inquiry_type', t('Type'), Src::of('tm2sdi.iqp_inquiry.inquiry_type'), ['type' => 'badge', 'tones' => Sales::TYPE_TONES]),
        $col('stage', 'Stage', Src::of('tm2sdi.iqp_inquiry.stat_confirm_sales', 'stat_confirm_prodev', 'stat_confirm_procost', 'sub_state'), ['type' => 'badge', 'tones' => Sales::STAGE_TONES]),
        $col('sub_state', 'Status', Src::of('tm2sdi.iqp_inquiry.sub_state'), ['type' => 'badge', 'tones' => Sales::STATE_TONES, 'visible' => false]),
        $col('pic_sales', 'PIC Sales', Src::of('tm2sdi.iqp_inquiry.pic_sales'), ['visible' => false]),
        $col('deadline_quotation', t('Quotation deadline'), Src::of('tm2sdi.iqp_inquiry.deadline_quotation'), ['type' => 'date', 'visible' => false]),
    ];
    $saved = array_values(array_filter($rows, fn ($r) => $r['inquiry_no']));
    $srcTitle = ['RECALC' => 'Recalculate Price', 'REVISE' => 'Revise Specification'];
@endphp

@if (session('v2_flash'))
    <x-dl::alert tone="success" :message="session('v2_flash')" />
@endif
@if ($errors->any())
    <x-dl::alert tone="danger" :message="$errors->first()" />
@endif

<x-dl::grid id="inquiry-list" :rows="$rows" :columns="$columns" :search="t('Search inquiry no. / product / customer...')" actions="worklist" :active-key="null"
    source="{{ Src::of('tm2sdi.iqp_inquiry', 'tm2sdi.iqp_tier', 'tm2sdi.iqp_part', 'tm2sdi.iqp_costing', 'tm2mst.cust_customer') }}"
    new-modal="wiz-type" open-url="{open_url}"
    :stats="[
        ['label' => t('All')],
        ['label' => 'Draft', 'where' => ['stage' => 'Draft'], 'tone' => 'neutral'],
        ['label' => 'Ready to Quote', 'where' => ['ready' => true], 'tone' => 'warn'],
        ['label' => 'Pro-Dev', 'where' => ['prodev' => true], 'tone' => 'violet'],
        ['label' => 'Pro-Cost', 'where' => ['procost' => true], 'tone' => 'success'],
    ]"
    :tabs="[
        ['label' => t('All')],
        ['label' => t('New Product'), 'where' => ['inquiry_type' => 'NEW']],
        ['label' => t('Recalculate Price'), 'where' => ['inquiry_type' => 'RECALC']],
        ['label' => t('Revise Specification'), 'where' => ['inquiry_type' => 'REVISE']],
    ]"
    :filters="['sub_state' => 'Status', 'customer' => 'Customer', 'pic_sales' => 'PIC Sales']"
    :detail="Sales::PART_DETAIL" />

{{-- Wizard Create Inquiry: 1 jenis -> 2 sumber (RECALC/REVISE) -> 3 pilih inquiry -> form. --}}
<x-dl::modal id="wiz-type" title="Create Inquiry — Step 1: choose the type" size="lg" icon="plus">
    <div class="mp-wiz">
        @foreach (InquiryTypeCard::TYPES as $type => $t)
            <div class="mp-wiz__opt mp-typecard--{{ strtolower($type) }}">
                <span class="mp-wiz__emoji">{{ $t[0] }}</span>
                <div class="mp-wiz__txt">
                    <b>{{ $t[1] }}</b> <x-dl::badge :label="$type" :tone="Sales::TYPE_TONES[$type]" />
                    <p>{{ $t[2] }}</p>
                    <small>{{ t('Needs') }}: {{ $t[3] }} · {{ t('Flow') }}: {{ $t[4] }}</small>
                </div>
                @if ($type === 'NEW')
                    <x-dl::button size="sm" label="Choose" :href="route('v2.screen', 'inquiry-new')" />
                @else
                    <x-dl::button size="sm" label="Choose" data-dl-open="wiz-src-{{ strtolower($type) }}" data-dl-close-modal />
                @endif
            </div>
        @endforeach
    </div>
    <x-slot:footer><x-dl::button preset="cancel" data-dl-close-modal /></x-slot:footer>
</x-dl::modal>

@foreach ($srcTitle as $type => $title)
    <x-dl::modal :id="'wiz-src-'.strtolower($type)" :title="raw(t($title)).' — Step 2: choose data source'" size="lg" icon="search">
        <div class="mp-wiz">
            <div class="mp-wiz__opt">
                <span class="mp-wiz__emoji">📁</span>
                <div class="mp-wiz__txt">
                    <b>{{ t('From an Earlier Inquiry') }}</b>
                    <p>{{ t('All numbered inquiries are included, including Cancelled/Lost ones — their spec is reused.') }}</p>
                </div>
                <x-dl::button size="sm" label="Choose" data-dl-open="pick-{{ strtolower($type) }}" data-dl-close-modal />
            </div>
            <div class="mp-wiz__opt is-off">
                <span class="mp-wiz__emoji">📦</span>
                <div class="mp-wiz__txt">
                    <b>{{ t('From Master Product') }}</b> <x-dl::badge :label="t('Soon')" tone="warn" />
                    <p>{{ t('Not active yet: Master Product does not store the dieline used by the Inquiry form yet; it needs a separate design.') }}</p>
                </div>
                <x-dl::button size="sm" variant="secondary" label="Choose" disabled />
            </div>
        </div>
        <x-slot:footer>
            <x-dl::button variant="secondary" icon="prev" label="Back" data-dl-open="wiz-type" data-dl-close-modal />
            <x-dl::button preset="cancel" data-dl-close-modal />
        </x-slot:footer>
    </x-dl::modal>

    <x-dl::modal :id="'pick-'.strtolower($type)" :title="raw(t($title)).' — Step 3: choose source inquiry'" size="lg" icon="search">
        <x-dl::alert :tone="$type === 'RECALC' ? 'info' : 'warn'" :message="$type === 'RECALC'
            ? 'All specifications are copied from the selected inquiry and LOCKED. You only fill in qty & deadline.'
            : 'The specification is copied and CAN BE CHANGED. The product type stays locked — if the type changes, use New Product.'" />
        <x-dl::grid :id="'pick-'.strtolower($type).'-grid'" :rows="$saved" :per-page="8" :new-record="null" :search="t('Search inquiry no. / customer / product...')" actions="worklist" :active-key="null"
            :columns="array_values(array_filter($columns, fn ($c) => in_array($c['key'], ['inquiry_no', 'inquiry_date', 'product_draft_name', 'part_type', 'qty', 'stage'])))"
            :open-url="route('v2.screen', 'inquiry-'.strtolower($type)).'?source_id={id}'" />
        <x-slot:footer>
            <x-dl::button variant="secondary" icon="prev" label="Back" :data-dl-open="'wiz-src-'.strtolower($type)" data-dl-close-modal />
            <x-dl::button preset="cancel" data-dl-close-modal />
        </x-slot:footer>
    </x-dl::modal>
@endforeach
