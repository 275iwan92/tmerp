@extends('layout', ['title' => $page['label'], 'dev' => true, 'bodyClass' => $page['full'] ? '' : 'dl-page-body'])
@section('body')
    @include('demos.'.$demo, $data)
    <x-dl::code-button :code="$code" :file="'resources/views/demos/'.$demo.'.blade.php'" :classes="$page['classes']" :mockup-url="route('mockup', ['file' => $page['mockup']])" />
@endsection
