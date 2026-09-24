@extends('layout', ['title' => 'Mockup ERP'])
@section('body')
    <x-dynamic-component :component="\Devlib\Components\Shell::preferred()" :modules="$modules" :user="$user" :logout-action="route('logout')" :chat="\App\Support\DemoData::chat()" :developer="$user['developer'] ?? false" menu-source="Menu mockup.test (app/Support/Menu.php)">
        <p>Ini <b>Mockup ERP</b>: semua layar &amp; alur ERP disusun dari objek <b>DevLib</b>, belum terhubung database.
            Nyalakan Mode Developer lalu arahkan mouse ke isian/kolom untuk melihat <b>tabel &amp; database</b> tujuannya.
            Tiap modul lengkap dengan master penyertanya; grup <b>Alur</b> menunjukkan perjalanan dokumen antar layar.</p>
    </x-dynamic-component>
@endsection
