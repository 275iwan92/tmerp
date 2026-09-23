@extends('layout', ['title' => $mockup['title'], 'bodyClass' => $mockup['type'] === 'html' ? '' : 'dl-page-body'])
@section('body')
    @if ($mockup['type'] === 'html')
        <iframe class="dl-mockup-full" src="{{ route('mockup.file', ['file' => $mockup['file']]) }}"></iframe>
        @foreach (['jpg', 'jpeg', 'png'] as $ext)
            @php($img = preg_replace('/\.html$/', '.'.$ext, $mockup['file']))
            @if (\App\Support\Catalog::path($img))
                <x-dl::button variant="secondary" size="sm" icon="view" label="Gambar lampiran" :href="route('mockup.file', ['file' => $img])" target="_blank" class="dl-mockup-attach" />
            @endif
        @endforeach
    @else
        <img class="dl-mockup-img" src="{{ route('mockup.file', ['file' => $mockup['file']]) }}" alt="{{ $mockup['title'] }}">
    @endif
@endsection
