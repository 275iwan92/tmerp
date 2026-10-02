@extends('v2.layout', ['title' => $item->label, 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::empty-state icon="fa-person-digging" :title="$item->label" :message="'The screen for this menu does not exist yet, neither v2 nor v1. Menu code: '.raw($item->code.($item->status_note ? ' · '.$item->status_note : ''))" />
    </div>
@endsection
