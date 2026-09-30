{{-- Laporan: hasil quotation (WIN / LOST / Cancelled) per bulan tutup & Sales PIC — nilai, alasan kalah/batal. Grid for Reporting. --}}
@php
    use App\Support\V2\Data\Quotation;
    use App\Support\V2\Src;
@endphp
<x-dl::grid id="quotation-result" mode="report" :rows="Quotation::resultRows()" search="Search quotation no., customer, reason..."
    :open-url="route('v2.screen', 'quotation').'?id={id}'" group-by="month"
    source="{{ Src::of('tm2sdi.quo_quotation', 'tm2sdi.iqp_inquiry.sub_state + cancel_reason', 'tm2sdi.co_order.co_date') }}"
    :columns="[
        ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no', 'avatar' => false],
        ['key' => 'month', 'label' => t('Closing month')],
        ['key' => 'sales', 'label' => 'Sales PIC'],
        ['key' => 'date', 'label' => 'Terbit', 'type' => 'date'],
        ['key' => 'closed', 'label' => 'Ditutup', 'type' => 'date'],
        ['key' => 'status', 'label' => 'Hasil', 'type' => 'badge', 'tones' => Quotation::STATUS_TONES],
        ['key' => 'total', 'label' => 'Nilai', 'type' => 'money', 'sum' => true],
        ['key' => 'co', 'label' => 'Customer Order'],
        ['key' => 'reason', 'label' => 'Alasan (LOST / Cancel)'],
        ['key' => 'followups', 'label' => 'Follow-up', 'type' => 'number'],
    ]"
    :stats="[['label' => t('All closed')], ['label' => 'WIN', 'where' => ['status' => 'WON'], 'tone' => 'success'], ['label' => 'LOST', 'where' => ['status' => 'LOST'], 'tone' => 'danger'], ['label' => 'Cancelled', 'where' => ['status' => 'CANCELLED'], 'tone' => 'neutral']]"
    :filters="['status' => 'Hasil', 'sales' => 'Sales PIC', 'month' => 'Bulan tutup', 'customer' => 'Customer']" />
