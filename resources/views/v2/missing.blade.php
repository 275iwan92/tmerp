@extends('v2.layout', ['title' => $item->label, 'bodyClass' => 'dl-page-body'])
@section('body')
    <div class="dl-cat">
        <x-dl::empty-state icon="fa-person-digging" :title="$item->label" :message="raw(str_replace(':code', $item->code.($item->status_note ? ' · '.$item->status_note : ''), t('There is no screen for this menu yet, neither v2 nor v1. Menu code: :code')))" />
    </div>
@endsection
