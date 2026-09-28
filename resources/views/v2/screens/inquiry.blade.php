{{-- Inquiry Product (worklist). Alur & field: tmerp pages/inquiry/hub.blade.php. Keputusan user 2026-09-28:
     3 tombol (Create Inquiry / Continue Draft / Ready to Quote), 2 kotak statistik (Pro-Dev, Pro-Cost),
     wizard jenis -> sumber -> pilih inquiry, dan Duplicate per baris. Win/Lost ada di Quotation. --}}
@php
    use App\Models\Master\CustCustomer;
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;
    use MockupParts\Components\Sales\InquiryTypeCard;

    $rows = Sales::inquiries();
    $col = fn (string $key, string $label, string $src, array $more = []) => ['key' => $key, 'label' => $label, 'src' => $src] + $more;
    $columns = [
        $col('product_draft_name', 'Produk / Customer', Src::of('tm2sdi.iqp_inquiry.product_draft_name', 'tm2mst.cust_customer.customer_name', 'tm2sdi.iqp_inquiry.prospect_name'), ['type' => 'identity', 'code' => 'customer']),
        $col('inquiry_no', 'No. Inkuiri', Src::of('tm2sdi.iqp_inquiry.inquiry_no')),
        $col('inquiry_date', 'Tgl', Src::of('tm2sdi.iqp_inquiry.inquiry_date'), ['type' => 'date']),
        $col('customer', 'Customer', Src::of('tm2sdi.iqp_inquiry.customer_id', 'tm2mst.cust_customer.customer_name'), ['visible' => false]),
        $col('part_type', 'Tipe Produk', Src::of('tm2sdi.iqp_part.dieline_spec.type_code').' (part pertama)'),
        $col('qty', 'Qty tier utama', Src::of('tm2sdi.iqp_tier.qty').' (is_primary = 1)', ['type' => 'number']),
        $col('sub_state', 'Status', Src::of('tm2sdi.iqp_inquiry.sub_state'), ['type' => 'badge', 'tones' => Sales::STATE_TONES]),
        $col('inquiry_type', 'Jenis', Src::of('tm2sdi.iqp_inquiry.inquiry_type'), ['type' => 'badge', 'tones' => Sales::TYPE_TONES]),
        $col('pic_sales', 'PIC Sales', Src::of('tm2sdi.iqp_inquiry.pic_sales'), ['visible' => false]),
        $col('deadline_quotation', 'Batas Quotation', Src::of('tm2sdi.iqp_inquiry.deadline_quotation'), ['type' => 'date', 'visible' => false]),
    ];
    $picker = fn (string $type) => ['*' => route('v2.screen', 'inquiry-'.strtolower($type)).'?source_id={id}'];
    $saved = array_values(array_filter($rows, fn ($r) => $r['inquiry_no']));
    $draftCount = count(array_filter($rows, fn ($r) => $r['sub_state'] === 'DRAFT'));
    $readyCount = count(array_filter($rows, fn ($r) => $r['ready']));
    $customers = CustCustomer::where('activate', true)->orderBy('customer_name')->get(['id_customer', 'customer_code', 'customer_name'])
        ->mapWithKeys(fn ($c) => [$c->id_customer => $c->customer_name.' ('.$c->customer_code.')'])->all();
    $srcTitle = ['RECALC' => 'Hitung Ulang Harga', 'REVISE' => 'Ubah Spesifikasi'];
@endphp

@if (session('v2_flash'))
    <x-dl::alert tone="success" :message="session('v2_flash')" />
@endif
@if ($errors->any())
    <x-dl::alert tone="danger" :message="$errors->first()" />
@endif

<div class="mp-inq-actions">
    <x-dl::button icon="＋" label="Create Inquiry" data-dl-open="wiz-type" />
    <x-dl::button variant="secondary" icon="📝" :label="'Continue Draft ('.$draftCount.')'" data-inq-preset="draft" title="Saring grid ke inquiry DRAFT" />
    <x-dl::button variant="secondary" icon="💰" :label="'Ready to Quote ('.$readyCount.')'" data-inq-preset="ready" title="Sudah dapat harga, belum dikirim ke customer lewat Quotation" />
    <span class="mp-inq-actions__hint">Klik 2x baris untuk buka. DRAFT bisa diedit; status lain lihat-saja.</span>
</div>

<x-mp::sales.inquiry-worklist class="mp-inq-grid" :rows="$rows" :columns="$columns" search="Cari no. inkuiri / produk / customer..."
    source="{{ Src::of('tm2sdi.iqp_inquiry', 'tm2sdi.iqp_tier', 'tm2sdi.iqp_part', 'tm2sdi.iqp_costing', 'tm2mst.cust_customer') }}"
    :data-open="json_encode(['NEW' => route('v2.screen', 'inquiry-new').'?id={id}', 'RECALC' => route('v2.screen', 'inquiry-recalc').'?id={id}', 'REVISE' => route('v2.screen', 'inquiry-revise').'?id={id}'])"
    :stats="[
        ['label' => 'Processed by Pro-Dev', 'where' => ['prodev' => true], 'tone' => 'info'],
        ['label' => 'Processed by Pro-Cost', 'where' => ['procost' => true], 'tone' => 'success'],
    ]"
    :tabs="[
        ['label' => 'Semua'],
        ['label' => 'Produk Baru', 'where' => ['inquiry_type' => 'NEW']],
        ['label' => 'Hitung Ulang Harga', 'where' => ['inquiry_type' => 'RECALC']],
        ['label' => 'Ubah Spesifikasi', 'where' => ['inquiry_type' => 'REVISE']],
    ]"
    :filters="['sub_state' => 'Status', 'customer' => 'Customer', 'pic_sales' => 'PIC Sales']"
    :groups="['customer' => 'Customer', 'sub_state' => 'Status', 'part_type' => 'Tipe Produk']"
    :expand="['pic_sales', 'deadline_quotation']" />

{{-- Wizard Create Inquiry: 1 jenis -> 2 sumber (RECALC/REVISE) -> 3 pilih inquiry -> form. --}}
<x-dl::modal id="wiz-type" title="Create Inquiry — Langkah 1: pilih jenis" size="lg" icon="plus">
    <div class="mp-wiz">
        @foreach (InquiryTypeCard::TYPES as $type => $t)
            <div class="mp-wiz__opt mp-typecard--{{ strtolower($type) }}">
                <span class="mp-wiz__emoji">{{ $t[0] }}</span>
                <div class="mp-wiz__txt">
                    <b>{{ $t[1] }}</b> <x-dl::badge :label="$type" :tone="Sales::TYPE_TONES[$type]" />
                    <p>{{ $t[2] }}</p>
                    <small>Perlu: {{ $t[3] }} · Alur: {{ $t[4] }}</small>
                </div>
                @if ($type === 'NEW')
                    <x-dl::button size="sm" label="Choose" :href="route('v2.screen', 'inquiry-new')" />
                @else
                    <x-dl::button size="sm" label="Choose" data-dl-open="wiz-src-{{ strtolower($type) }}" data-inq-next />
                @endif
            </div>
        @endforeach
    </div>
    <x-slot:footer><x-dl::button preset="cancel" data-dl-close-modal /></x-slot:footer>
</x-dl::modal>

@foreach ($srcTitle as $type => $title)
    <x-dl::modal :id="'wiz-src-'.strtolower($type)" :title="$title.' — Langkah 2: pilih sumber data'" size="lg" icon="search">
        <div class="mp-wiz">
            <div class="mp-wiz__opt">
                <span class="mp-wiz__emoji">📁</span>
                <div class="mp-wiz__txt">
                    <b>Dari Inquiry Lama</b>
                    <p>Semua inquiry bernomor ikut, termasuk yang Cancelled/Lost — spec-nya dipakai lagi.</p>
                </div>
                <x-dl::button size="sm" label="Choose" data-dl-open="pick-{{ strtolower($type) }}" data-inq-next />
            </div>
            <div class="mp-wiz__opt is-off">
                <span class="mp-wiz__emoji">📦</span>
                <div class="mp-wiz__txt">
                    <b>Dari Master Produk</b> <x-dl::badge label="Segera" tone="warn" />
                    <p>Belum aktif: Master Produk belum menyimpan dieline yang dipakai form Inquiry, perlu dirancang terpisah.</p>
                </div>
                <x-dl::button size="sm" variant="secondary" label="Choose" disabled />
            </div>
        </div>
        <x-slot:footer>
            <x-dl::button variant="secondary" icon="prev" label="Back" data-dl-open="wiz-type" data-inq-next />
            <x-dl::button preset="cancel" data-dl-close-modal />
        </x-slot:footer>
    </x-dl::modal>

    <x-dl::modal :id="'pick-'.strtolower($type)" :title="$title.' — Langkah 3: pilih inquiry sumber'" size="lg" icon="search">
        <x-dl::alert :tone="$type === 'RECALC' ? 'info' : 'warn'" :message="$type === 'RECALC'
            ? 'Semua spesifikasi disalin dari inkuiri terpilih dan DIKUNCI. Anda hanya mengisi qty & deadline.'
            : 'Spesifikasi disalin lalu BISA DIUBAH. Tipe produk tetap terkunci — kalau tipe berubah, gunakan Produk Baru.'" />
        <x-mp::sales.inquiry-worklist :id="'pick-'.strtolower($type).'-grid'" :rows="$saved" :per-page="8" search="Cari no. inkuiri / customer / produk..."
            :columns="array_values(array_filter($columns, fn ($c) => in_array($c['key'], ['product_draft_name', 'inquiry_no', 'inquiry_date', 'part_type', 'qty', 'sub_state'])))"
            :data-open="json_encode($picker($type))" />
        <x-slot:footer>
            <x-dl::button variant="secondary" icon="prev" label="Back" :data-dl-open="'wiz-src-'.strtolower($type)" data-inq-next />
            <x-dl::button preset="cancel" data-dl-close-modal />
        </x-slot:footer>
    </x-dl::modal>
@endforeach

{{-- Duplicate: spec sama, customer lain -> NEW DRAFT baru (routes/v2.php v2.inquiry.duplicate). --}}
<x-dl::modal id="dup-modal" title="Duplicate Inquiry" size="md" icon="copy">
    <form method="POST" action="{{ route('v2.inquiry.duplicate') }}" id="dup-form" class="mp-dup">
        @csrf
        <input type="hidden" name="source_id" data-dup-source>
        <p class="mp-dup__src" data-dup-label></p>
        <x-dl::field.select name="customer_id" label="Customer tujuan" required :options="$customers" placeholder="Pilih customer..."
            :source="Src::of('tm2mst.cust_customer.customer_name')" />
        <x-dl::alert tone="info" message="Spec (part, bahan, proses, finishing, tinta) disalin apa adanya. Qty & harga tidak ikut: inquiry baru lahir sebagai Produk Baru DRAFT dan lewat Pro-Dev → Pro-Cost lagi." />
    </form>
    <x-slot:footer>
        <x-dl::button preset="cancel" data-dl-close-modal />
        <x-dl::button icon="⧉" label="Duplicate" type="submit" form="dup-form" />
    </x-slot:footer>
</x-dl::modal>
