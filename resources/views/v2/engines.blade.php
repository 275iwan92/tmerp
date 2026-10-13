{{--
 REMARK
 Tujuan       : Menampilkan katalog engine aplikasi: kegunaan, input, output, cara kerja, dan tabel yang dibaca / ditulis.
 Sumber hulu  : App\Support\Engines\EngineRegistry::catalog(); halaman yang sama ada di devlib.test > Engines.
 Input        : engines dari route; khusus Administrator / grup DEVELOPER.
 Output       : x-dl::card berisi x-dl::engine-catalog.
 Dipakai oleh : route v2.engines (routes/v2.php); menu Engines (App\Support\V2\AppMenu).
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => t('Engine Catalog'), 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::card title="Engine Catalog" icon="fa-gears"
            description="Every engine of the application: what it is for, how to call it, its input and output, how it works, and the tables it reads and writes. Method signatures are read from the code.">
            <x-dl::engine-catalog :engines="$engines" source="App\Support\Engines\EngineRegistry" />
        </x-dl::card>
    </div>
@endsection
