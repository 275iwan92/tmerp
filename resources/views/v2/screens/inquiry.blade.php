{{-- REMARK
     Tujuan       : Laporan All Inquiries (Grid for Reporting DevLib, SEMUA lihat-saja): cari bebas, pintasan Stage / Type, dan Filter per kolom dalam satu pop-up.
     Sumber hulu  : Inquiry Product (tm2sdi.iqp_inquiry, iqp_part, iqp_tier, iqp_costing, tm2mst.cust_customer) lewat App\Support\V2\Data\Sales::inquiries().
     Input        : -; kotak cari, pintasan Stage / Type, tombol Filter (customer, status, Sales PIC).
     Output       : Daftar inquiry; buka baris = layar jenisnya dalam mode lihat-saja (&view=1). Tidak ada New / Edit / aksi massal.
     Dipakai oleh : Menu Sales > Inquiry Product > All Inquiries (target v2:inquiry). Membuat / mengubah inquiry lewat Inquiry New / Revise / Recalc.
     Status       : Aktif; laporan sejak 2026-10-16 (wizard Create Inquiry dihapus dari layar ini, keputusan Jo). --}}
@php
    use App\Support\V2\Data\Sales;
    use App\Support\V2\Src;

    // Tiap baris membawa alamat bukanya sendiri: layar sesuai jenis dokumen (3 layar terpisah), selalu lihat-saja.
    $screenOf = ['NEW' => 'inquiry-new', 'RECALC' => 'inquiry-recalc', 'REVISE' => 'inquiry-revise'];
    $rows = array_map(fn ($r) => $r + ['open_url' => route('v2.screen', $screenOf[$r['inquiry_type']] ?? 'inquiry-new').'?id='.$r['id'].'&view=1'], Sales::inquiries());
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
@endphp

@if (session('v2_flash'))
    <x-dl::alert tone="success" :message="raw(session('v2_flash'))" />
@endif
@if ($errors->any())
    <x-dl::alert tone="danger" :message="raw($errors->first())" />
@endif

<x-dl::grid id="inquiry-list" :rows="$rows" :columns="$columns" :search="t('Search inquiry no. / product / customer...')" mode="report" :active-key="null"
    source="{{ Src::of('tm2sdi.iqp_inquiry', 'tm2sdi.iqp_tier', 'tm2sdi.iqp_part', 'tm2sdi.iqp_costing', 'tm2mst.cust_customer') }}"
    open-url="{open_url}"
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
    :filters="['customer' => 'Customer', 'sub_state' => 'Status', 'pic_sales' => 'Sales PIC']"
    :detail="Sales::PART_DETAIL" />
