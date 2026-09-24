@extends('layout', ['title' => '500'])
@section('body')
    <x-dl::error-page code="500" :home="url('/')" />
@endsection
