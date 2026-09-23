@extends('layout', ['title' => $mockup['title'], 'bodyClass' => $mockup['type'] === 'html' ? '' : 'dl-page-body'])
@section('body')
    @if ($mockup['type'] === 'html')
        <iframe class="dl-mockup-full" src="{{ route('mockup.file', ['file' => $mockup['file']]) }}"></iframe>
    @else
        <img class="dl-mockup-img" src="{{ route('mockup.file', ['file' => $mockup['file']]) }}" alt="{{ $mockup['title'] }}">
    @endif
@endsection
