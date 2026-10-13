{{--
 REMARK
 Tujuan       : Laporan Quotation terbit yang masih menunggu keputusan customer (belum WIN, LOST, Cancel), dikelompokkan per Sales PIC.
 Sumber hulu  : App\Support\V2\Data\Quotation::outstandingRows(); tm2sdi.quo_quotation (PUBLISHED), quo_activity, iqp_inquiry.sub_state.
 Input        : Tidak ada parameter; pencarian dan saringan statistik ditangani objek grid.
 Output       : x-dl::grid mode report (kolom customer, sales, umur, validitas, grand total, follow-up); baris membuka layar quotation ?id=.
 Dipakai oleh : route v2.screen (routes/v2.php), slug quotation-outstanding (menu Sales, laporan Open Quotations).
 Status       : Aktif (laporan baca-saja).
--}}
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
        ['key' => 'date', 'label' => 'Issued', 'type' => 'date'],
        ['key' => 'age', 'label' => t('Age (days)'), 'type' => 'number'],
        ['key' => 'valid', 'label' => 'Valid Until', 'type' => 'date'],
        ['key' => 'expired', 'label' => 'Validity', 'type' => 'badge', 'tones' => [t('VALID') => 'success', t('EXPIRED') => 'danger']],
        ['key' => 'total', 'label' => 'Grand total', 'type' => 'money', 'sum' => true],
        ['key' => 'followups', 'label' => 'Follow-up', 'type' => 'number'],
        ['key' => 'last_followup', 'label' => 'Last follow-up', 'type' => 'date'],
        ['key' => 'next', 'label' => 'Upcoming follow-up', 'type' => 'date'],
    ]"
    :stats="[['label' => t('All open')], ['label' => 'Expired', 'where' => ['expired' => t('EXPIRED')], 'tone' => 'danger'], ['label' => t('Not followed up'), 'where' => ['followups' => 0], 'tone' => 'warn']]"
    :filters="['sales' => 'Sales PIC', 'customer' => 'Customer', 'expired' => 'Validity']" />
