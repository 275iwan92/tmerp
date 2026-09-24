@extends('v2.layout', ['title' => 'Home'])
@section('body')
    <x-dynamic-component :component="\Devlib\Components\Shell::preferred()" :modules="$modules" :user="$user" :logout-action="route('v2.logout')" :chat="\App\Support\V2\DemoData::chat()" :developer="$user['developer']" :open="$open" menu-source="sys_app_menu ({{ config('database.connections.tm2sys.database') }})">
        <p>Ini <b>Build &amp; UAT</b>: layar ERP disusun dari objek <b>DevLib</b> dengan data <b>dummy</b> ({{ config('database.connections.tm2mst.database') }} dst). Lencana di menu:
            <b>Review</b> = layar v2 menunggu penilaian, <b>v1</b> = sementara membuka layar lama, <b>Belum</b> = belum ada layar.
            Grup <b>Alur</b> menunjukkan perjalanan dokumen antar layar.</p>
    </x-dynamic-component>
@endsection
