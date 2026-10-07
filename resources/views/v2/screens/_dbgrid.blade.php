{{-- Grid DB generik: semua isi dari objek App\Support\V2\Grid\DbGrid (data dari database, keluarga grid DevLib). --}}
@php $p = $screen['grid']->props(); @endphp
@if ($guide = $screen['grid']->guide())
    <x-dl::alert tone="info" :message="$guide" />
@endif
@php
    $period = $screen['grid']->period();
    $params = $screen['grid']->params();
    $printUrl = $screen['grid']->printUrlOf();
    $excelUrl = $screen['grid']->excelUrl();
@endphp
@if ($period || $params || $printUrl || $excelUrl)
{{-- Laporan berperiode: pilih tanggal (dan saringan teks) lalu Show memuat ulang grid; Print = cetakan x-dl::report yang sama isinya. --}}
<x-dl::action-form method="get" action="{{ $screen['grid']->url() }}">
    <x-dl::action-bar align="start">
        @if ($period)
            <x-dl::field.date-range name="from" name-end="to" label="Period" :value="$period[0]->toDateString()" :value-end="$period[1]->toDateString()" :duration="false" />
        @endif
        @foreach ($params as $key => $label)
            <x-dl::field.text :name="$key" :label="$label" :value="$screen['grid']->param($key)" />
        @endforeach
        @if ($period || $params)
            <x-dl::button variant="secondary" icon="fa-rotate" label="Show" type="submit" />
        @endif
        @if ($printUrl)
            <x-dl::button variant="secondary" icon="print" label="Print" :href="$printUrl" />
        @endif
        @if ($excelUrl)
            <x-dl::button icon="fa-file-excel" label="Download Excel" :href="$excelUrl" />
        @endif
    </x-dl::action-bar>
</x-dl::action-form>
@endif
@if ($actionUrls = $screen['grid']->actionUrls())
{{-- Tombol di baris (mis. Print): buka alamat dokumennya di tab baru. --}}
<script>
    document.addEventListener('dl:grid-action', function (e) {
        var url = @json($actionUrls)[e.detail.action];
        if (url) { e.preventDefault(); window.open(url.replace('{id}', encodeURIComponent(e.detail.id)), '_blank'); }
    });
</script>
@endif
@if ($screen['grid']->family() === \App\Support\V2\Grid\DbLookup::LOOKUP)
<x-dl::lookup-grid :rows="$p['rows']" :columns="$p['columns']" :save-url="$p['saveUrl']" :source="$p['source']" />
@else
<x-dl::grid :id="$p['id']" :rows="$p['rows']" :columns="$p['columns']" :stats="$p['stats']" :tabs="$p['tabs']" :filters="$p['filters']" :groups="$p['groups']" :group-by="$p['groupBy']" :search="$p['search']"
    :mode="$p['mode']" :actions="$p['actions']" :active-key="$p['activeKey']" :new-record="$p['newRecord']" :new-url="$p['newUrl']"
    :open-url="$p['openUrl']" :toggle-url="$p['toggleUrl']" :source="$p['source']" :detail="$p['detail']" :row-tone="$p['rowTone']" />
@endif
