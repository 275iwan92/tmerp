{{--
 REMARK
 Tujuan       : Pemberitahuan bahwa layar untuk sebuah menu belum ada (bukan v2, bukan v1).
 Sumber hulu  : Baris menu sys_app_menu ($item: label, code, status_note).
 Input        : item dari route fallback menu.
 Output       : x-dl::empty-state dengan nama menu dan kode menu.
 Dipakai oleh : routes/v2.php (fallback bila menu tidak punya layar v2 maupun v1).
 Status       : Aktif.
--}}
@extends('v2.layout', ['title' => $item->label, 'bodyClass' => 'dl-page-body'])
@section('body')
    <x-dl::box class="dl-cat">
        <x-dl::empty-state icon="fa-person-digging" :title="$item->label" :message="'The screen for this menu does not exist yet, neither v2 nor v1. Menu code: '.raw($item->code.($item->status_note ? ' · '.$item->status_note : ''))" />
    </x-dl::box>
@endsection
