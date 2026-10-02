{{-- Papan widget: Customize -> pindah / lebar / lepas, Add Widget dari katalog, Reset to Template. Susunan contoh disimpan di sesi. --}}
<x-dl::widget-board :widgets="\App\Support\BoardDemo::props()['widgets']" :catalog="\App\Support\BoardDemo::props()['catalog']"
    :action-url="route('board.demo')" title="Company KPI" source="App\Support\BoardDemo (contoh, susunan di sesi)" />
