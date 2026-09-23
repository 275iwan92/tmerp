@extends('layout', ['title' => '419'])
@section('body')
    <x-dl::error-page code="419" :home="url('/')" />
@endsection
