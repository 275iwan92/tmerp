{{-- Inquiry Product (worklist). Alur & field: tmerp pages/inquiry/hub.blade.php; tampilan: inquiry_main_hub.html. --}}
@php
    use App\Support\Data\Sales;
    use App\Support\Src;

    $rows = Sales::inquiries();
    $live = array_values(array_diff(Sales::STATES, ['CANCELLED', 'LOST']));
    $col = fn (string $key, string $label, string $src, array $more = []) => ['key' => $key, 'label' => $label, 'src' => $src] + $more;
    $columns = [
        $col('product_draft_name', 'Produk / Customer', Src::of('tm2sdi.iqp_inquiry.product_draft_name', 'tm2mst.cust_customer.customer_name', 'tm2sdi.iqp_inquiry.prospect_name'), ['type' => 'identity', 'code' => 'customer']),
        $col('inquiry_no', 'No. Inkuiri', Src::of('tm2sdi.iqp_inquiry.inquiry_no')),
        $col('inquiry_date', 'Tgl', Src::of('tm2sdi.iqp_inquiry.inquiry_date'), ['type' => 'date']),
        $col('customer', 'Customer', Src::of('tm2sdi.iqp_inquiry.customer_id', 'tm2mst.cust_customer.customer_name'), ['visible' => false]),
        $col('part_type', 'Tipe Produk', Src::of('tm2sdi.iqp_part.part_type').' (part pertama, part_seq terkecil)'),
        $col('qty', 'Qty tier utama', Src::of('tm2sdi.iqp_tier.qty').' (is_primary = 1)', ['type' => 'number']),
        $col('sub_state', 'Status', Src::of('tm2sdi.iqp_inquiry.sub_state'), ['type' => 'badge', 'tones' => Sales::STATE_TONES]),
        $col('inquiry_type', 'Jenis', Src::of('tm2sdi.iqp_inquiry.inquiry_type'), ['type' => 'badge', 'tones' => Sales::TYPE_TONES]),
        $col('pic_sales', 'PIC Sales', Src::of('tm2sdi.iqp_inquiry.pic_sales'), ['visible' => false]),
        $col('deadline_quotation', 'Batas Quotation', Src::of('tm2sdi.iqp_inquiry.deadline_quotation'), ['type' => 'date', 'visible' => false]),
    ];
    $unconfirmed = fn ($t) => ['inquiry_type' => $t, 'stat_confirm' => false, 'sub_state' => $live];
    $priced = fn ($t) => ['inquiry_type' => $t, 'sub_state' => 'PRICE_APPROVED'];
    $picker = fn (string $type) => ['*' => route('screen', 'inquiry-'.['RECALC' => 'recalc', 'REVISE' => 'revise'][$type]).'?source_id={id}'];
    $saved = array_values(array_filter($rows, fn ($r) => $r['inquiry_no']));
@endphp

<div class="mp-pick">
    <x-mp::sales.inquiry-type-card type="NEW" :new-url="route('screen', 'inquiry-new')" />
    <x-mp::sales.inquiry-type-card type="RECALC" picker="pick-recalc" />
    <x-mp::sales.inquiry-type-card type="REVISE" picker="pick-revise" />
</div>

<x-dl::alert tone="info" message="Pilih inkuiri untuk membukanya (klik 2x baris). DRAFT bisa diedit; status lain dibuka lihat-saja." />

<x-mp::sales.inquiry-worklist class="mp-inq-grid" :rows="$rows" :columns="$columns" search="Cari no. inkuiri / produk / customer..."
    source="{{ Src::of('tm2sdi.iqp_inquiry', 'tm2sdi.iqp_tier', 'tm2sdi.iqp_part', 'tm2mst.cust_customer') }}"
    :data-open="json_encode(['NEW' => route('screen', 'inquiry-new').'?id={id}', 'RECALC' => route('screen', 'inquiry-recalc').'?id={id}', 'REVISE' => route('screen', 'inquiry-revise').'?id={id}'])"
    :stats="[
        ['label' => 'NEW belum confirm', 'where' => $unconfirmed('NEW'), 'tone' => 'warn'],
        ['label' => 'REVISE belum confirm', 'where' => $unconfirmed('REVISE'), 'tone' => 'warn'],
        ['label' => 'RECALC belum confirm', 'where' => $unconfirmed('RECALC'), 'tone' => 'warn'],
        ['label' => 'Total inquiry tahun ini', 'where' => [], 'tone' => 'accent'],
        ['label' => 'NEW sudah harga, belum ke customer', 'where' => $priced('NEW'), 'tone' => 'info'],
        ['label' => 'REVISE sudah harga, belum ke customer', 'where' => $priced('REVISE'), 'tone' => 'info'],
        ['label' => 'RECALC sudah harga, belum ke customer', 'where' => $priced('RECALC'), 'tone' => 'info'],
        ['label' => 'Sudah di-approve tahun ini', 'where' => ['is_approved' => true], 'tone' => 'success'],
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

{{-- Pemilih produk existing untuk RECALC / REVISE: sumbernya inkuiri tersimpan yang sudah bernomor (sama seperti tmerp). --}}
@foreach (['RECALC' => ['pick-recalc', 'Semua spesifikasi disalin dari inkuiri terpilih dan DIKUNCI. Anda hanya mengisi qty & deadline.'],
           'REVISE' => ['pick-revise', 'Spesifikasi disalin lalu BISA DIUBAH. Tipe produk tetap terkunci — kalau tipe berubah, gunakan NEW.']] as $type => [$id, $note])
    <x-dl::modal :id="$id" :title="'Pilih produk existing — '.$type" size="lg" icon="search">
        <x-dl::alert :tone="$type === 'RECALC' ? 'info' : 'warn'" :message="$note" />
        <x-mp::sales.inquiry-worklist :id="$id.'-grid'" :rows="$saved" :per-page="8" search="Cari no. inkuiri / customer / produk..."
            :columns="array_values(array_filter($columns, fn ($c) => in_array($c['key'], ['product_draft_name', 'inquiry_no', 'inquiry_date', 'part_type', 'qty', 'sub_state'])))"
            :data-open="json_encode($picker($type))" />
        <x-slot:footer><x-dl::button preset="cancel" data-dl-close-modal /></x-slot:footer>
    </x-dl::modal>
@endforeach
