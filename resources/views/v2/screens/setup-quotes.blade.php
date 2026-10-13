{{-- REMARK
     Tujuan       : Layar Setup > Dashboard and Widgets > Quotes, dua tab huruf besar: Per Hari (366 baris per tanggal, opsional) dan Semua Kutipan (tokoh + anonim, 1000 baris).
     Sumber hulu  : tm2sys.sys_daily_quote dan tm2sys.sys_quotes lewat QuoteLookup / DailyQuoteLookup.
     Input        : Sel diubah langsung lalu Save per tab; kotak filter Famous / Anonymous / Inactive dan Search.
     Output       : Pool kutipan untuk rotasi per user (pagi, setelah makan siang, sore/malam; tanpa ulang 7 hari) dan kutipan acak layar login.
     Dipakai oleh : Menu SET_QUOTES.
     Status       : Aktif. --}}
@php
    use App\Support\V2\Grid\Grids;

    $all = $screen['grid']->props();
    $day = Grids::find('setup-daily-quotes')->props();
@endphp
<x-dl::alert tone="info" message="All Quotes: every quote, famous and anonymous. Each user receives a different random quote in the morning, after lunch and in the evening, never repeated within 7 days. Per Day: an optional quote for each day of the year (day 1 = 1 January, day 60 = 29 February)." />
<x-dl::tabs class="dl-tabs--caps" :tabs="['all' => ['All Quotes', 'fa-quote-left'], 'day' => ['Per Day', 'fa-calendar-day']]">
    <x-slot:all>
        <x-dl::lookup-grid class="dl-sheet--roomy" :rows="$all['rows']" :columns="$all['columns']" :save-url="$all['saveUrl']" :stats="$all['stats']" :source="$all['source']" />
    </x-slot:all>
    <x-slot:day>
        <x-dl::lookup-grid class="dl-sheet--roomy" :rows="$day['rows']" :columns="$day['columns']" :save-url="$day['saveUrl']" :stats="$day['stats']" :source="$day['source']" />
    </x-slot:day>
</x-dl::tabs>
