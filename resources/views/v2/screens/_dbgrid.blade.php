{{-- Grid DB generik: semua isi dari objek App\Support\V2\Grid\DbGrid (data dari database, keluarga grid DevLib). --}}
@php $p = $screen['grid']->props(); @endphp
<x-dl::grid :id="$p['id']" :rows="$p['rows']" :columns="$p['columns']" :stats="$p['stats']" :filters="$p['filters']" :search="$p['search']"
    :mode="$p['mode']" :actions="$p['actions']" :active-key="$p['activeKey']" :new-record="$p['newRecord']" :new-url="$p['newUrl']"
    :open-url="$p['openUrl']" :toggle-url="$p['toggleUrl']" :source="$p['source']" />
