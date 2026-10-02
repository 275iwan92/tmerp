{{-- Engine Catalog (user 2026-10-02): semua engine tmerp dengan input, output dan cara kerjanya. Isi = App\Support\Engines\EngineRegistry,
     tanda tangan method dibaca dari kode. Objek DevLib x-dl::engine-catalog; halaman yang sama ada di devlib.test > Engines. --}}
@extends('v2.layout', ['title' => 'Engine Catalog', 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::card title="Engine Catalog" icon="fa-gears"
            description="Every engine of the application: what it is for, how to call it, its input and output, how it works, and the tables it reads and writes. Method signatures are read from the code.">
            <x-dl::engine-catalog :engines="$engines" source="App\Support\Engines\EngineRegistry" />
        </x-dl::card>
    </div>
@endsection
