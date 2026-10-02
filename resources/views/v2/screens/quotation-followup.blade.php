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
        ['key' => 'next_date', 'label' => 'Next action', 'type' => 'date'],
        ['key' => 'next', 'label' => 'What'],
        ['key' => 'due', 'label' => 'Due', 'type' => 'badge', 'tones' => [t('OVERDUE') => 'danger', t('TODAY') => 'warn', t('LATER') => 'info', '—' => 'neutral']],
        ['key' => 'by', 'label' => t('By')],
    ]"
    :stats="[['label' => t('All')], ['label' => 'Overdue follow-up', 'where' => ['due' => t('OVERDUE')], 'tone' => 'danger'], ['label' => t('Today'), 'where' => ['due' => t('TODAY')], 'tone' => 'warn']]"
    :filters="['type' => 'Type', 'result' => 'Result', 'by' => 'By', 'due' => 'Due']" />
