{{--
 REMARK
 Tujuan       : Menampilkan diagram alur dokumen satu modul; blok bisa diklik membuka layarnya.
 Sumber hulu  : App\Support\Flows::get(modul) (app/Support/Flows.php).
 Input        : flow (title, steps) dari route /flow/{module}.
 Output       : x-dl::flow dan satu catatan teks kelas dl-note.
 Dipakai oleh : route v2.flow (routes/v2.php); menu F-SALES, F-COSTING dst (App\Support\Menu\MenuRoutes, App\Support\V2\Screens).
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => $flow['title'], 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        <x-dl::flow :title="$flow['title']" :steps="$flow['steps']" source="app/Support/Flows.php" />
        <x-dl::box as="p" class="dl-note">@t('Point the mouse at a block to see its short rule; click a block to open its screen. Block status = the mockup status of that screen.')</x-dl::box>
    </x-dl::box>
@endsection
