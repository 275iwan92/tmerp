@extends('layout', ['title' => '403'])
@section('body')
    <x-dl::error-page code="403" :home="url('/')" />
@endsection
