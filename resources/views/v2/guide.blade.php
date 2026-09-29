@extends('v2.layout', ['title' => $title, 'bodyClass' => 'dl-page-body'])
@section('body')
    <article class="dl-prose">{!! $html !!}</article>
@endsection
