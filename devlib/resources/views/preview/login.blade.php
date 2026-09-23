@extends('layout', ['title' => 'Pratinjau Login'])
@section('body')
    <x-dl::login action="javascript:void(0)" :model="$model" />
@endsection
