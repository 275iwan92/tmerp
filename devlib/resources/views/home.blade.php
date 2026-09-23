@extends('layout', ['title' => 'DevLib'])
@section('body')
    <x-dl::shell :modules="$modules" :user="$user" :logout-action="route('logout')" :developer="$user['developer'] ?? false"
        menu-source="Catalog (docs/mockups) — tanpa database">
        <p class="dl-home__lib">Ini <b>DevLib</b>: library induk semua komponen. Setiap menu membuka satu mockup Anda
            berdampingan dengan komponen library yang menirunya. Nyalakan <b>Mode Developer</b> di menu avatar untuk
            melihat asal-usul setiap objek.</p>
    </x-dl::shell>
@endsection
