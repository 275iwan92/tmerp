@extends('layout', ['title' => '503'])
@section('body')
    <x-dl::error-page code="503" :home="url('/')" />
@endsection
