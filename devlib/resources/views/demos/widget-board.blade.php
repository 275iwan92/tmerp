{{-- Papan widget: pilihan periode (Daily .. Yearly), Customize -> pindah / lebar / lepas, Add Widget dari katalog, Reset to Template.
     Susunan dan periode contoh disimpan di sesi. --}}
@php($demo = \App\Support\BoardDemo::props())
<x-dl::widget-board :widgets="$demo['widgets']" :catalog="$demo['catalog']" :periods="$demo['periods']" :period="$demo['period']"
    :action-url="route('board.demo')" title="Company KPI" source="App\Support\BoardDemo (contoh, susunan di sesi)" />
