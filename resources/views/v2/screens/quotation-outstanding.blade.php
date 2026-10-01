{{-- Laporan: Quotation terbit yang masih menunggu keputusan customer (belum WIN/LOST/Cancel). Grid for Reporting. --}}
@php
    use App\Support\V2\Data\Quotation;
    use App\Support\V2\Src;
@endphp
<x-dl::grid id="quotation-outstanding" mode="report" :rows="Quotation::outstandingRows()" search="Search quotation no., customer, sales..."
    :open-url="route('v2.screen', 'quotation').'?id={id}'" group-by="sales"
    source="{{ Src::of('tm2sdi.quo_quotation (PUBLISHED)', 'tm2sdi.quo_activity', 'tm2sdi.iqp_inquiry.sub_state') }}"
    :columns="[
        ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no', 'avatar' => false],
        ['key' => 'sales', 'label' => 'Sales PIC'],
        ['key' => 'date', 'label' => 'Terbit', 'type' => 'date'],
        ['key' => 'age', 'label' => t('Age (days)'), 'type' => 'number'],
        ['key' => 'valid', 'label' => 'Valid Until', 'type' => 'date'],
        ['key' => 'expired', 'label' => 'Validity', 'type' => 'badge', 'tones' => ['BERLAKU' => 'success', 'KEDALUWARSA' => 'danger']],
        ['key' => 'total', 'label' => 'Grand total', 'type' => 'money', 'sum' => true],
        ['key' => 'followups', 'label' => 'Follow-up', 'type' => 'number'],
        ['key' => 'last_followup', 'label' => 'Follow-up terakhir', 'type' => 'date'],
        ['key' => 'next', 'label' => 'Tindak lanjut berikut', 'type' => 'date'],
    ]"
    :stats="[['label' => t('All open')], ['label' => 'Kedaluwarsa', 'where' => ['expired' => 'KEDALUWARSA'], 'tone' => 'danger'], ['label' => t('Not followed up'), 'where' => ['followups' => 0], 'tone' => 'warn']]"
    :filters="['sales' => 'Sales PIC', 'customer' => 'Customer', 'expired' => 'Masa berlaku']" />
