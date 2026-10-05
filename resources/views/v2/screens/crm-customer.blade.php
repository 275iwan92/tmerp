{{-- CRM > Customer 360 (user 2026-10-02) lewat App\Support\V2\Data\Crm. Hanya membaca.
     GRID customer aktif + ringkasan hubungan (order terakhir, quotation / order terbuka, kontak terakhir, follow-up, komplain)
     -> klik 2x = halaman satu customer: angka kunci, grafik order 24 bulan, product yang sering dipesan, dan tab
     Activities / Quotations / Orders / Deliveries / Complaints / Contacts. Order & kiriman = sistem baru + arsip sistem lama. --}}
@php
    use App\Support\V2\Data\Crm;
    use App\Support\V2\Src;

    $home = route('v2.screen', 'crm-customer');
    $c = request('id') ? Crm::find((int) request('id')) : null;
    abort_if(request('id') && ! $c, 404);
    $dueStats = [
        ['label' => 'All'],
        ['label' => 'Overdue', 'where' => ['bucket' => 'OVERDUE'], 'tone' => 'danger'],
        ['label' => 'Today', 'where' => ['bucket' => 'TODAY'], 'tone' => 'tangerine'],
        ['label' => 'Due soon', 'where' => ['bucket' => 'SOON'], 'tone' => 'yellow'],
    ];
@endphp

@if (session('v2_flash'))<x-dl::alert tone="success" :message="raw(session('v2_flash'))" />@endif

@if (! $c)
    <x-dl::grid id="crm-customer-list" mode="report" :rows="Crm::rows()" search="Search customer, code, group, industry..." :open-url="$home.'?id={id}'" row-tone="row_tone"
        source="{{ Src::of('tm2mst.cust_customer', 'tm2sdi.co_order', 'tm2sdi.co_order_legacy', 'tm2sdi.quo_quotation', 'tm2sdi.crm_activity', 'tm2sdi.crm_complaint') }}"
        :columns="[
            ['key' => 'name', 'label' => 'Customer', 'type' => 'identity', 'code' => 'code', 'avatar' => false],
            ['key' => 'segment', 'label' => 'Segment', 'type' => 'badge', 'tones' => collect(Crm::SEGMENT_TONES)->mapWithKeys(fn ($tone, $label) => [t($label) => $tone])->all()],
            ['key' => 'group', 'label' => 'Group', 'visible' => false],
            ['key' => 'industry', 'label' => 'Industry', 'visible' => false],
            ['key' => 'last_order', 'label' => 'Last Order', 'type' => 'date'],
            ['key' => 'orders_12m', 'label' => 'Orders (12 months)', 'type' => 'number'],
            ['key' => 'open_quo', 'label' => 'Open Quotations', 'type' => 'number'],
            ['key' => 'open_co', 'label' => 'Open Orders', 'type' => 'number'],
            ['key' => 'last_contact', 'label' => 'Last Contact', 'type' => 'date'],
            ['key' => 'next_date', 'label' => 'Next Follow-up', 'type' => 'date'],
            ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'toneKey' => 'due_tone'],
            ['key' => 'complaints', 'label' => 'Open Complaints', 'type' => 'number'],
        ]"
        :stats="[...$dueStats, ['label' => 'Open complaint', 'where' => ['has_complaint' => true], 'tone' => 'accent']]"
        :tabs="[['label' => 'All'], ...collect(Crm::SEGMENT_TONES)->map(fn ($tone, $label) => ['label' => $label, 'where' => ['segment' => t($label)], 'tone' => $tone])->values()->all()]"
        :filters="['segment' => 'Segment', 'group' => 'Group', 'industry' => 'Industry']" />
@else
    {{-- Halaman satu customer = objek form khusus x-mp::sales.customer-card (MockupParts); isi = Crm::card(). --}}
    <x-mp::sales.customer-card :card="Crm::card($c, $home)" />
@endif
