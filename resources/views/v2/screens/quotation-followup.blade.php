{{-- Laporan: log follow-up Sales atas quotation (telepon/kunjungan/WA/email) + tindak lanjut yang jatuh tempo. Grid for Reporting. --}}
@php
    use App\Support\V2\Data\Quotation;
    use App\Support\V2\Src;
@endphp
<x-dl::grid id="quotation-followup" mode="report" :rows="Quotation::followupRows()" search="Search quotation, customer, notes..."
    :open-url="route('v2.screen', 'quotation').'?id={id}'" source="{{ Src::of('tm2sdi.quo_activity', 'tm2sdi.quo_quotation') }}"
    :columns="[
        ['key' => 'date', 'label' => t('Date'), 'type' => 'date'],
        ['key' => 'customer', 'label' => 'Customer', 'type' => 'identity', 'code' => 'no', 'avatar' => false],
        ['key' => 'type', 'label' => t('Type')],
        ['key' => 'contact', 'label' => 'Contact'],
        ['key' => 'result', 'label' => 'Result'],
        ['key' => 'notes', 'label' => t('Notes')],
        ['key' => 'next_date', 'label' => 'Tindak lanjut', 'type' => 'date'],
        ['key' => 'next', 'label' => 'Apa'],
        ['key' => 'due', 'label' => 'Jatuh tempo', 'type' => 'badge', 'tones' => ['LEWAT' => 'danger', 'HARI INI' => 'warn', 'NANTI' => 'info', '—' => 'neutral']],
        ['key' => 'by', 'label' => t('By')],
    ]"
    :stats="[['label' => t('All')], ['label' => 'Tindak lanjut lewat', 'where' => ['due' => 'LEWAT'], 'tone' => 'danger'], ['label' => t('Today'), 'where' => ['due' => 'HARI INI'], 'tone' => 'warn']]"
    :filters="['type' => 'Jenis', 'result' => 'Hasil', 'by' => 'Oleh', 'due' => 'Jatuh tempo']" />
