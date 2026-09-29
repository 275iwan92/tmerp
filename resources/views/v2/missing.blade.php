@extends('v2.layout', ['title' => $item->label, 'bodyClass' => 'dl-page-body', 'framed' => true])
@section('body')
    <div class="dl-cat">
        <x-dl::empty-state icon="fa-person-digging" :title="$item->label" :message="'Layar untuk menu ini belum ada, baik v2 maupun v1. Kode menu: '.$item->code.($item->status_note ? ' · '.$item->status_note : '')" />
    </div>
@endsection
