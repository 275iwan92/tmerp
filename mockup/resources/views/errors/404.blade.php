@extends('layout', ['title' => '404'])
@section('body')
    <x-dl::error-page code="404" :home="url('/')" />
@endsection
