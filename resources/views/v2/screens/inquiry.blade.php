{{-- REMARK
     Tujuan       : Worklist Inquiry Product (Grid Transactional DevLib) + wizard Create Inquiry: jenis -> sumber (RECALC / REVISE) -> pilih inquiry -> form.
     Sumber hulu  : Inquiry Product (tm2sdi.iqp_inquiry, iqp_part, iqp_tier, iqp_costing, tm2mst.cust_customer) lewat App\Support\V2\Data\Sales::inquiries().
     Input        : -; filter Draft / Ready to Quote / Pro-Dev / Pro-Cost dan tab jenis memakai kolom stage / inquiry_type.
     Output       : Daftar inquiry; baris DRAFT membuka layar jenisnya (inquiry-new / -recalc / -revise), selain DRAFT = lihat-saja (&view=1).
     Dipakai oleh : Menu Sales > Inquiry & Quotation; modal wizard memakai x-dl::wizard-option.
     Status       : Aktif. Duplicate = tombol di form (screens/_inquiry-duplicate); Win / Lost ada di Quotation (keputusan user 2026-09-28, 2026-10-02). --}}
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
        $col('part_type', t('Product Type'), Src::of('tm2sdi.iqp_part.dieline_spec.type_code').' (part pertama)'),
        $col('qty', t('Main tier qty'), Src::of('tm2sdi.iqp_tier.qty').' (is_primary = 1)', ['type' => 'number']),
        $col('inquiry_type', t('Type'), Src::of('tm2sdi.iqp_inquiry.inquiry_type'), ['type' => 'badge', 'tones' => Sales::TYPE_TONES]),
        $col('stage', 'Stage', Src::of('tm2sdi.iqp_inquiry.stat_confirm_sales', 'stat_confirm_prodev', 'stat_confirm_procost', 'sub_state'), ['type' => 'badge', 'tones' => Sales::STAGE_TONES]),
        $col('sub_state', 'Status', Src::of('tm2sdi.iqp_inquiry.sub_state'), ['type' => 'badge', 'tones' => Sales::STATE_TONES, 'visible' => false]),
        $col('pic_sales', 'Sales PIC', Src::of('tm2sdi.iqp_inquiry.pic_sales'), ['visible' => false]),
        $col('deadline_quotation', t('Quotation deadline'), Src::of('tm2sdi.iqp_inquiry.deadline_quotation'), ['type' => 'date', 'visible' => false]),
    ];
    $saved = array_values(array_filter($rows, fn ($r) => $r['inquiry_no']));
    $srcTitle = ['RECALC' => 'Recalculate Price', 'REVISE' => 'Change Specification'];
@endphp

@if (session('v2_flash'))
    <x-dl::alert tone="success" :message="raw(session('v2_flash'))" />
@endif
@if ($errors->any())
    <x-dl::alert tone="danger" :message="raw($errors->first())" />
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
        ['label' => t('Change Specification'), 'where' => ['inquiry_type' => 'REVISE']],
    ]"
    :filters="['sub_state' => 'Status', 'customer' => 'Customer', 'pic_sales' => 'Sales PIC']"
    :detail="Sales::PART_DETAIL" />

{{-- Wizard Create Inquiry: 1 jenis -> 2 sumber (RECALC/REVISE) -> 3 pilih inquiry -> form. --}}
<x-dl::modal id="wiz-type" title="Create Inquiry — Step 1: choose the type" size="lg" icon="plus">
    <x-dl::wizard-options>
        @foreach (InquiryTypeCard::TYPES as $type => $t)
            <x-dl::wizard-option :kind="'mp-typecard--'.strtolower($type)" :emoji="$t[0]" :title="$t[1]" :description="$t[2]" :badge="$type" :badge-tone="Sales::TYPE_TONES[$type]"
                :note="raw(t('Needs').': '.t($t[3]).' · '.t('Flow').': '.t($t[4]))">
                @if ($type === 'NEW')
                    <x-dl::button size="sm" label="Choose" :href="route('v2.screen', 'inquiry-new')" />
                @else
                    <x-dl::button size="sm" label="Choose" data-dl-open="wiz-src-{{ strtolower($type) }}" data-dl-close-modal />
                @endif
            </x-dl::wizard-option>
        @endforeach
    </x-dl::wizard-options>
    <x-slot:footer><x-dl::button preset="cancel" data-dl-close-modal /></x-slot:footer>
</x-dl::modal>

@foreach ($srcTitle as $type => $title)
    <x-dl::modal :id="'wiz-src-'.strtolower($type)" :title="raw(t($title)).' — Step 2: choose the data source'" size="lg" icon="search">
        <x-dl::wizard-options>
            <x-dl::wizard-option emoji="📁" title="From an Existing Inquiry" description="All numbered inquiries are listed, including Cancelled/Lost ones — their spec is used again.">
                <x-dl::button size="sm" label="Choose" data-dl-open="pick-{{ strtolower($type) }}" data-dl-close-modal />
            </x-dl::wizard-option>
            <x-dl::wizard-option emoji="📦" title="From Master Product" badge="Soon" badge-tone="warn" off
                description="Not active yet: Master Product does not store the dieline used by the Inquiry form yet; it has to be designed separately.">
                <x-dl::button size="sm" variant="secondary" label="Choose" disabled />
            </x-dl::wizard-option>
        </x-dl::wizard-options>
        <x-slot:footer>
            <x-dl::button variant="secondary" icon="prev" label="Back" data-dl-open="wiz-type" data-dl-close-modal />
            <x-dl::button preset="cancel" data-dl-close-modal />
        </x-slot:footer>
    </x-dl::modal>

    <x-dl::modal :id="'pick-'.strtolower($type)" :title="raw(t($title)).' — Step 3: choose the source inquiry'" size="lg" icon="search">
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
