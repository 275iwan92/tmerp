@extends('layout', ['title' => 'Pratinjau Shell'])
@section('body')
    <x-dl::shell :modules="$modules" :user="$user" :logout-action="route('logout')" :developer="true" menu-source="Catalog (docs/mockups)" />
@endsection
