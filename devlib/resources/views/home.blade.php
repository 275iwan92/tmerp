@extends('layout', ['title' => 'DevLib'])
@section('body')
    <x-dynamic-component :component="\Devlib\Components\Shell::preferred()" :modules="$modules" :user="$user" :logout-action="route('logout')" :chat="\App\Support\DemoData::chat()" :developer="$user['developer'] ?? false" menu-source="Catalog DevLib — tanpa database">
        <x-slot:dashboard>
            {{-- Papan Build Progress (user 2026-10-05): pemantauan bersama user + agen; di atas dashboard contoh. --}}
            @if ($build = \App\Support\Catalog::build())
                <x-dl::build-board :build="$build" :library="\App\Support\Catalog::stats()" :engines-url="route('lib', ['slug' => 'p9-engines'])" source="resources/data/v2/build.json" />
            @endif
            <x-dl::dashboard :board="\App\Support\DashboardData::for($user)" source="App\Support\DashboardData (contoh, tanpa database)" /></x-slot:dashboard>
        <p>Ini <b>DevLib</b>: library induk semua komponen. Tiap menu membuka satu komponen hidup. Arahkan mouse ke objek untuk melihat
            namanya; klik ikon <b>&lt;/&gt;</b> di pojok kanan bawah untuk melihat kode pemakaiannya.</p>
    </x-dynamic-component>
@endsection
