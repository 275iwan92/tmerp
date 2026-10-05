@extends('layout', ['title' => 'DevLib'])
@section('body')
    <x-dynamic-component :component="\Devlib\Components\Shell::preferred()" :modules="$modules" :user="$user" :logout-action="route('logout')" :chat="\App\Support\DemoData::chat()" :developer="$user['developer'] ?? false" menu-source="Catalog DevLib — tanpa database">
        <x-slot:dashboard>
            {{-- Developer (user 2026-10-05): sambutan sama, bagian widget = papan Build Progress (pemantauan user + dua agen).
                 Akun lain tetap melihat dashboard contoh per jabatan. --}}
            @php($build = ($user['developer'] ?? false) ? \App\Support\Catalog::build() : [])
            @if ($build)
                <x-dl::dashboard :board="\App\Support\DashboardData::developer($user, $build)" source="resources/data/v2/build.json">
                    <x-dl::build-board :build="$build" :library="\App\Support\Catalog::stats()" :engines-url="route('lib', ['slug' => 'p9-engines'])" source="resources/data/v2/build.json" />
                </x-dl::dashboard>
            @else
                <x-dl::dashboard :board="\App\Support\DashboardData::for($user)" source="App\Support\DashboardData (contoh, tanpa database)" />
            @endif
        </x-slot:dashboard>
        <p>Ini <b>DevLib</b>: library induk semua komponen. Tiap menu membuka satu komponen hidup. Arahkan mouse ke objek untuk melihat
            namanya; klik ikon <b>&lt;/&gt;</b> di pojok kanan bawah untuk melihat kode pemakaiannya.</p>
    </x-dynamic-component>
@endsection
